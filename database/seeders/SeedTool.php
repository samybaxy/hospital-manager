<?php

namespace HospitalManager\Database\Seeders;

/**
 * Tools > Hospital Manager Seed: fills an empty install with demo data from wp-admin.
 *
 * Lives in database/seeders/, so only staging zips (bin/build-release.sh --staging) and
 * development checkouts have it; production zips leave the folder out.
 *
 * Demo accounts have published passwords, so this refuses to run when:
 *   - WordPress reports the "production" environment (unset WP_ENVIRONMENT_TYPE counts as
 *     production), unless wp-config.php defines HOSPITAL_MANAGER_ALLOW_SEED as true
 *   - the hospital tables already hold patients or doctors, as seeding only adds rows
 *
 * The browser runs one seeder per request, so a proxy timeout can't cut off the whole
 * run, and a failed run resumes from the seeder that failed.
 */
class SeedTool
{
    const PAGE = 'hospital-manager-seed';
    const AJAX_ACTION = 'hospital_manager_seed_step';
    const STATE_OPTION = 'hospital_manager_seed_state';
    const LOCK_OPTION = 'hospital_manager_seed_lock';
    /** Seconds before a lock left behind by a killed request is ignored. */
    const LOCK_TTL = 900;

    public static function register()
    {
        add_action('admin_menu', [self::class, 'addPage']);
        add_action('admin_notices', [self::class, 'pluginsScreenNotice']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'ajaxStep']);
    }

    public static function isAllowed($environment = null)
    {
        $environment = $environment ?? wp_get_environment_type();
        return $environment !== 'production'
            || (defined('HOSPITAL_MANAGER_ALLOW_SEED') && HOSPITAL_MANAGER_ALLOW_SEED);
    }

    public static function hasHospitalData()
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_patients") > 0
            || (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_doctors") > 0;
    }

    /** Seeder class names in run order, with their files loaded (PSR-4 can't map database/seeders). */
    public static function steps()
    {
        require_once __DIR__ . '/Seeder.php';
        require_once __DIR__ . '/DatabaseSeeder.php';
        foreach (DatabaseSeeder::SEEDERS as $class) {
            require_once __DIR__ . '/' . basename(str_replace('\\', '/', $class)) . '.php';
        }
        return DatabaseSeeder::SEEDERS;
    }

    /**
     * Run the next seeder.
     *
     * @return array{ok: bool, done: bool, step: int, total: int, label: string, log: string}
     */
    public static function advance()
    {
        $steps = self::steps();
        $total = count($steps);
        $refuse = function ($message) use ($total) {
            return ['ok' => false, 'done' => false, 'step' => 0, 'total' => $total, 'label' => '', 'log' => $message];
        };

        if (!self::isAllowed()) {
            return $refuse(self::blockedMessage());
        }
        if (!self::acquireLock()) {
            return $refuse('A seeder is still running. Wait a minute, then reload this page and resume.');
        }
        register_shutdown_function([self::class, 'releaseLock']);

        // Read under the lock: a request that waited must see the step the last one finished.
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete(self::STATE_OPTION, 'options');
        $state = get_option(self::STATE_OPTION);
        if (!is_array($state) || !isset($state['next'])) {
            if (self::hasHospitalData()) {
                self::releaseLock();
                return $refuse('The hospital tables already hold patients or doctors, so nothing was seeded.');
            }
            $state = ['next' => 0];
        }

        $index = (int) $state['next'];
        $class = $steps[$index];
        $label = basename(str_replace('\\', '/', $class));
        update_option(self::STATE_OPTION, $state, false);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        ignore_user_abort(true);
        // Seeded addresses can belong to real people: nothing goes out during a run.
        add_filter('pre_wp_mail', '__return_false');

        $ok = true;
        ob_start();
        try {
            $ok = (new $class())->run() !== false;
        } catch (\Throwable $e) {
            $ok = false;
            echo 'Error: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
        }
        $log = preg_replace('/\033\[[0-9;]*m/', '', (string) ob_get_clean());
        remove_filter('pre_wp_mail', '__return_false');

        $done = false;
        if ($ok) {
            $done = $index + 1 >= $total;
            if ($done) {
                delete_option(self::STATE_OPTION);
                update_option('hospital_manager_seeded_at', current_time('mysql'), false);
                self::clearCachedQueries();
            } else {
                update_option(self::STATE_OPTION, ['next' => $index + 1], false);
            }
        }
        self::releaseLock();

        return ['ok' => $ok, 'done' => $done, 'step' => $index + 1, 'total' => $total, 'label' => $label, 'log' => trim($log)];
    }

    public static function ajaxStep()
    {
        check_ajax_referer(self::AJAX_ACTION);
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['log' => 'You are not allowed to do this.'], 403);
        }
        $result = self::advance();
        $result['ok'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public static function addPage()
    {
        add_management_page('Hospital Manager Seed', 'Hospital Manager Seed', 'manage_options', self::PAGE, [self::class, 'renderPage']);
    }

    /** Point at the tool right after install, since nothing else leads to it. */
    public static function pluginsScreenNotice()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'plugins' || !current_user_can('manage_options')
            || !self::isAllowed() || self::hasHospitalData()) {
            return;
        }
        printf(
            '<div class="notice notice-info"><p>Hospital Manager has no data yet. <a href="%s">Seed demo data</a> to test the app.</p></div>',
            esc_url(admin_url('tools.php?page=' . self::PAGE))
        );
    }

    public static function renderPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $state = get_option(self::STATE_OPTION);
        $resuming = is_array($state) && !empty($state['next']);

        echo '<div class="wrap"><h1>Hospital Manager Seed</h1>';
        echo '<p>Fills this site with fake patients, doctors, appointments, labs and inventory for testing.</p>';

        if (!self::isAllowed()) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html(self::blockedMessage()) . '</p></div></div>';
            return;
        }
        if (!$resuming && self::hasHospitalData()) {
            $seeded_at = get_option('hospital_manager_seeded_at');
            echo '<p><strong>' . ($seeded_at
                ? 'Demo data was seeded on ' . esc_html($seeded_at) . '.'
                : 'This site already has patients or doctors, so seeding is turned off.') . '</strong></p>';
            self::renderLogins();
            echo '</div>';
            return;
        }

        echo '<div class="notice notice-warning inline"><p>This creates over 150 WordPress users, including an administrator '
            . 'whose password is public. Use it on test sites only.</p></div>';
        echo '<p><label><input type="checkbox" id="hm-seed-toggle"> Seed demo data on this site</label></p>';
        echo '<p><button type="button" class="button button-primary" id="hm-seed-run" disabled>'
            . ($resuming ? 'Resume seeding' : 'Run seed') . '</button></p>';
        echo '<p id="hm-seed-status" role="status"></p>';
        echo '<pre id="hm-seed-log" style="display:none;max-height:360px;overflow:auto;background:#fff;padding:12px;border:1px solid #c3c4c7"></pre>';
        echo '<div id="hm-seed-logins" style="display:none">';
        self::renderLogins();
        echo '</div></div>';
        self::renderScript();
    }

    public static function acquireLock()
    {
        if (add_option(self::LOCK_OPTION, time(), '', false)) {
            return true;
        }
        if (time() - (int) get_option(self::LOCK_OPTION) < self::LOCK_TTL) {
            return false;
        }
        update_option(self::LOCK_OPTION, time(), false);
        return true;
    }

    public static function releaseLock()
    {
        delete_option(self::LOCK_OPTION);
    }

    private static function blockedMessage()
    {
        return "Seeding is off because WordPress reports this site as production. On a test site, add define('WP_ENVIRONMENT_TYPE', 'staging'); "
            . "to wp-config.php, or define('HOSPITAL_MANAGER_ALLOW_SEED', true); if the site must stay on the production type.";
    }

    /** Model query results are cached in transients, which must not outlive a seed. */
    private static function clearCachedQueries()
    {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '\\_transient\\_hospital\\_manager\\_%'
                OR option_name LIKE '\\_transient\\_timeout\\_hospital\\_manager\\_%'"
        );
        wp_cache_flush();
    }

    private static function renderLogins()
    {
        echo '<h2>Demo logins</h2><p>Every account below uses the password <code>demo1234</code>.</p>';
        echo '<table class="widefat striped" style="max-width:420px"><tbody>';
        self::steps();
        foreach (UserSeeder::DEMO_ROLES as $role) {
            echo '<tr><td><code>demo_' . esc_html($role) . '</code></td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function renderScript()
    {
        $config = wp_json_encode([
            'url' => admin_url('admin-ajax.php'),
            'action' => self::AJAX_ACTION,
            'nonce' => wp_create_nonce(self::AJAX_ACTION),
        ]);
        ?>
        <script>
        (function () {
            var config = <?php echo $config; ?>;
            var toggle = document.getElementById('hm-seed-toggle');
            var button = document.getElementById('hm-seed-run');
            var status = document.getElementById('hm-seed-status');
            var log = document.getElementById('hm-seed-log');

            toggle.addEventListener('change', function () { button.disabled = !toggle.checked; });

            function write(text) {
                log.style.display = 'block';
                log.textContent += text + '\n';
                log.scrollTop = log.scrollHeight;
            }
            function stop(message) {
                status.textContent = message + ' Reload this page to resume.';
                button.disabled = false;
            }
            function next() {
                var body = new URLSearchParams({ action: config.action, _ajax_nonce: config.nonce });
                fetch(config.url, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function (response) {
                        return response.text().then(function (text) {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error('The server answered ' + response.status + ' instead of a seed result.');
                            }
                        });
                    })
                    .then(function (result) {
                        var data = result.data || {};
                        if (data.log) { write((data.label ? '== ' + data.label + ' ==\n' : '') + data.log); }
                        if (!result.success) { stop('Seeding stopped.'); return; }
                        if (data.done) {
                            status.textContent = 'Seeding finished.';
                            document.getElementById('hm-seed-logins').style.display = 'block';
                            button.style.display = 'none';
                            toggle.closest('p').style.display = 'none';
                            return;
                        }
                        status.textContent = 'Finished ' + data.step + ' of ' + data.total + ' (' + data.label + ')...';
                        next();
                    })
                    .catch(function (error) { stop(error.message); });
            }
            button.addEventListener('click', function () {
                button.disabled = true;
                status.textContent = 'Seeding...';
                next();
            });
        })();
        </script>
        <?php
    }
}

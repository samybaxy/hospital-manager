<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\AuthController;

/**
 * Password reset through the real AuthController:
 *   POST /auth/reset-password          { email, nonce }            -> emails a link with a token
 *   POST /auth/reset-password/confirm  { token, password, nonce }  -> sets the new password
 */
class PasswordResetFlowTest extends TestCase
{
    const OLD_PASSWORD = 'old-password-1';
    const NEW_PASSWORD = 'brand-new-pass-2';
    const META = 'hospital_manager_password_reset_token';

    private $user_id;
    private $other_user_id;
    private $original_remote_addr;

    public function setUp(): void
    {
        parent::setUp();

        $this->user_id = $this->factory->user->create([
            'user_login' => 'nurse_ifeoma',
            'user_email' => 'ifeoma.eze@example.com',
            'user_pass'  => self::OLD_PASSWORD,
            'role'       => 'hospital_nurse',
        ]);
        $this->other_user_id = $this->factory->user->create([
            'user_login' => 'dr_emeka',
            'user_email' => 'emeka.nnaji@example.com',
            'user_pass'  => self::OLD_PASSWORD,
            'role'       => 'doctor',
        ]);

        wp_set_current_user(0);
        $this->original_remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '198.51.100.' . wp_rand(1, 250);
        reset_phpmailer_instance();

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        (new AuthController())->register_routes();
    }

    public function tearDown(): void
    {
        delete_transient('login_attempts_' . $_SERVER['REMOTE_ADDR']);
        if ($this->original_remote_addr === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
        }
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    // ------------------------------------------------------------ helpers

    private function post($route, array $params, $with_nonce = true)
    {
        if ($with_nonce) {
            $params['nonce'] = wp_create_nonce('wp_rest');
        }
        $request = new \WP_REST_Request('POST', '/hospital-manager/v1' . $route);
        $request->set_body_params($params);
        return @rest_get_server()->dispatch($request);
    }

    private function requestReset($email)
    {
        return $this->post('/auth/reset-password', ['email' => $email]);
    }

    private function confirm($token, $password = self::NEW_PASSWORD)
    {
        wp_set_current_user(0);
        return $this->post('/auth/reset-password/confirm', ['token' => $token, 'password' => $password]);
    }

    private function sentMail()
    {
        return tests_retrieve_phpmailer_instance()->mock_sent;
    }

    /** Pull the token out of the reset link in the last email sent. */
    private function tokenFromLastEmail()
    {
        $mail = $this->sentMail();
        $this->assertNotEmpty($mail, 'A reset email should have been sent');
        $body = end($mail)['body'];
        $this->assertMatchesRegularExpression('/[?&]token=([A-Za-z0-9]+)/', $body, 'The email should contain a reset link with a token');
        preg_match('/[?&]token=([A-Za-z0-9]+)/', $body, $m);
        return $m[1];
    }

    private function canLogIn($password)
    {
        return !is_wp_error(wp_authenticate('nurse_ifeoma', $password));
    }

    // ------------------------------------------------------------ request

    public function test_request_emails_a_reset_link_to_a_registered_user()
    {
        $response = $this->requestReset('ifeoma.eze@example.com');

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertTrue($response->get_data()['success']);

        $mail = $this->sentMail();
        $this->assertCount(1, $mail);
        $this->assertSame('ifeoma.eze@example.com', $mail[0]['to'][0][0]);
        $this->assertStringContainsString('Password Reset', $mail[0]['subject']);

        $stored = get_user_meta($this->user_id, self::META, true);
        $this->assertNotEmpty($this->tokenFromLastEmail());
        $this->assertGreaterThan(time(), $stored['expiration']);
    }

    public function test_request_for_an_unknown_email_looks_identical_and_sends_nothing()
    {
        $known = $this->requestReset('ifeoma.eze@example.com');
        reset_phpmailer_instance();
        $unknown = $this->requestReset('nobody@example.com');

        // Same status and message, so the endpoint can't be used to find accounts.
        $this->assertSame($known->get_status(), $unknown->get_status());
        $this->assertSame($known->get_data(), $unknown->get_data());
        $this->assertEmpty($this->sentMail());
    }

    public function test_request_without_email_is_rejected()
    {
        $this->assertSame(400, $this->post('/auth/reset-password', [])->get_status());
    }

    public function test_request_without_nonce_is_rejected()
    {
        $response = $this->post('/auth/reset-password', ['email' => 'ifeoma.eze@example.com'], false);

        $this->assertSame(403, $response->get_status());
        $this->assertEmpty($this->sentMail());
        $this->assertSame('', get_user_meta($this->user_id, self::META, true));
    }

    public function test_logged_in_users_cannot_use_the_reset_endpoints()
    {
        wp_set_current_user($this->user_id);
        $request = new \WP_REST_Request('POST', '/hospital-manager/v1/auth/reset-password');
        $request->set_body_params(['email' => 'ifeoma.eze@example.com', 'nonce' => wp_create_nonce('wp_rest')]);

        $this->assertContains(rest_get_server()->dispatch($request)->get_status(), [401, 403]);
    }

    // ------------------------------------------------------------ full flow

    public function test_full_flow_resets_the_password_and_the_new_one_logs_in()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();

        $response = $this->confirm($token);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertTrue($response->get_data()['success']);
        $this->assertNotEmpty($response->get_data()['fresh_nonce']);
        $this->assertFalse($this->canLogIn(self::OLD_PASSWORD), 'Old password must stop working');
        $this->assertTrue($this->canLogIn(self::NEW_PASSWORD), 'New password must work');
        $this->assertSame('', get_user_meta($this->user_id, self::META, true), 'Token is removed after use');

        // And through the app's own login endpoint, by username and by email.
        foreach (['nurse_ifeoma', 'ifeoma.eze@example.com'] as $identifier) {
            wp_set_current_user(0);
            $login = $this->post('/auth/login', ['username' => $identifier, 'password' => self::NEW_PASSWORD]);
            $this->assertSame(200, $login->get_status(), $identifier . ': ' . wp_json_encode($login->get_data()));
            $this->assertTrue($login->get_data()['authenticated']);
        }
    }

    public function test_token_can_only_be_used_once()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();

        $this->assertSame(200, $this->confirm($token)->get_status());
        $second = $this->confirm($token, 'another-pass-3');

        $this->assertSame(400, $second->get_status());
        $this->assertSame('invalid_token', $second->get_data()['code']);
        $this->assertTrue($this->canLogIn(self::NEW_PASSWORD));
    }

    public function test_a_new_request_replaces_the_previous_token()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $first = $this->tokenFromLastEmail();
        $this->requestReset('ifeoma.eze@example.com');
        $second = $this->tokenFromLastEmail();

        $this->assertNotSame($first, $second);
        $this->assertSame(400, $this->confirm($first)->get_status());
        $this->assertSame(200, $this->confirm($second)->get_status());
    }

    public function test_expired_token_is_rejected()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();
        $stored = get_user_meta($this->user_id, self::META, true);
        $stored['expiration'] = time() - 1;
        update_user_meta($this->user_id, self::META, $stored);

        $response = $this->confirm($token);

        $this->assertSame(400, $response->get_status());
        $this->assertTrue($this->canLogIn(self::OLD_PASSWORD), 'Password unchanged');
        $this->assertSame('', get_user_meta($this->user_id, self::META, true), 'Expired token is cleaned up');
    }

    public function test_wrong_token_is_rejected_and_changes_nothing()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();

        $response = $this->confirm(strrev($token));

        $this->assertSame(400, $response->get_status());
        $this->assertTrue($this->canLogIn(self::OLD_PASSWORD));
    }

    /**
     * Tokens were found with LIKE '%<token>%' and the matched user's token was
     * deleted when it didn't match exactly, so anyone could cancel another
     * user's pending reset by sending a fragment such as "token" or one character.
     */
    public function test_guessed_fragments_cannot_cancel_someone_elses_pending_reset()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();

        foreach (['token', 'expiration', substr($token, 0, 1), substr($token, 0, 8), 's:32', '%', '_'] as $fragment) {
            $this->assertSame(400, $this->confirm($fragment)->get_status(), "fragment '{$fragment}'");
        }

        $this->assertSame(200, $this->confirm($token)->get_status(), 'The real token must still work');
    }

    public function test_one_users_token_cannot_reset_another_users_password()
    {
        $this->requestReset('emeka.nnaji@example.com');
        $emeka_token = $this->tokenFromLastEmail();

        $this->assertSame(200, $this->confirm($emeka_token)->get_status());
        $this->assertTrue($this->canLogIn(self::OLD_PASSWORD), "Ifeoma's password is untouched");
        $this->assertFalse(is_wp_error(wp_authenticate('dr_emeka', self::NEW_PASSWORD)));
    }

    public function test_confirm_requires_token_password_and_nonce()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();

        $this->assertSame(400, $this->post('/auth/reset-password/confirm', ['password' => self::NEW_PASSWORD])->get_status());
        $this->assertSame(400, $this->post('/auth/reset-password/confirm', ['token' => $token])->get_status());
        $no_nonce = $this->post('/auth/reset-password/confirm', ['token' => $token, 'password' => self::NEW_PASSWORD], false);
        $this->assertSame(403, $no_nonce->get_status());

        $this->assertTrue($this->canLogIn(self::OLD_PASSWORD));
        $this->assertSame(200, $this->confirm($token)->get_status(), 'Rejected attempts leave the token usable');
    }

    /**
     * POST /auth/login refuses passwords shorter than 8 characters before even
     * checking them, so a short reset password would lock the user out.
     */
    public function test_reset_password_must_meet_the_login_minimum_length()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();

        $short = $this->confirm($token, 'short');

        $this->assertSame(400, $short->get_status());
        $this->assertTrue($this->canLogIn(self::OLD_PASSWORD));
        $this->assertSame(200, $this->confirm($token)->get_status(), 'Token still usable after a rejected password');
    }

    // ------------------------------------------------------------ token hashing

    public function test_only_a_hash_of_the_token_is_stored()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();

        $stored = get_user_meta($this->user_id, self::META, true);
        $this->assertNotSame($token, $stored['token']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $stored['token'], 'Stored as a SHA-256 hex digest');

        global $wpdb;
        $raw = $wpdb->get_col("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = '" . self::META . "'");
        $this->assertStringNotContainsString($token, implode('|', $raw), 'The plain token must not be in the database');
    }

    public function test_the_stored_hash_itself_cannot_be_used_as_a_token()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();
        // Someone who can read the database sees only the hash.
        $leaked = get_user_meta($this->user_id, self::META, true)['token'];

        $this->assertSame(400, $this->confirm($leaked)->get_status());
        $this->assertTrue($this->canLogIn(self::OLD_PASSWORD));
        $this->assertSame(200, $this->confirm($token)->get_status());
    }

    // ------------------------------------------------------------ signing out

    /** Log in through the app and return the JWT it hands out. */
    private function appLoginToken($identifier, $password)
    {
        wp_set_current_user(0);
        $login = $this->post('/auth/login', ['username' => $identifier, 'password' => $password]);
        $this->assertSame(200, $login->get_status(), wp_json_encode($login->get_data()));
        wp_set_current_user(0);
        return $login->get_data()['token'];
    }

    private function refresh($jwt)
    {
        return $this->post('/auth/refresh', ['token' => $jwt]);
    }

    public function test_reset_signs_out_every_existing_session()
    {
        // An app login (custom cookie token + JWT) and two other WordPress sessions.
        $jwt = $this->appLoginToken('nurse_ifeoma', self::OLD_PASSWORD);
        $sessions = \WP_Session_Tokens::get_instance($this->user_id);
        $sessions->create(time() + DAY_IN_SECONDS);
        $sessions->create(time() + DAY_IN_SECONDS);
        $this->assertNotEmpty($sessions->get_all());
        $this->assertNotEmpty(get_user_meta($this->user_id, 'hospital_manager_auth_token', true));
        $this->assertSame(200, $this->refresh($jwt)->get_status(), 'JWT works before the reset');

        $this->requestReset('ifeoma.eze@example.com');
        $this->assertSame(200, $this->confirm($this->tokenFromLastEmail())->get_status());

        $this->assertSame([], \WP_Session_Tokens::get_instance($this->user_id)->get_all(), 'WordPress sessions destroyed');
        $this->assertSame('', get_user_meta($this->user_id, 'hospital_manager_auth_token', true), 'App cookie token removed');
        $refresh = $this->refresh($jwt);
        $this->assertSame(401, $refresh->get_status(), 'Old JWT can no longer be refreshed');
        $this->assertSame('invalid_token', $refresh->get_data()['code']);
    }

    public function test_tokens_issued_after_the_reset_work()
    {
        $this->requestReset('ifeoma.eze@example.com');
        $this->confirm($this->tokenFromLastEmail());

        $jwt = $this->appLoginToken('nurse_ifeoma', self::NEW_PASSWORD);
        $refresh = $this->refresh($jwt);
        $this->assertSame(200, $refresh->get_status(), wp_json_encode($refresh->get_data()));

        // ...and the refreshed token is itself valid.
        $this->assertSame(200, $this->refresh($refresh->get_data()['token'])->get_status());
    }

    public function test_other_users_stay_signed_in()
    {
        $emeka_jwt = $this->appLoginToken('dr_emeka', self::OLD_PASSWORD);
        $emeka_sessions = \WP_Session_Tokens::get_instance($this->other_user_id);
        $emeka_sessions->create(time() + DAY_IN_SECONDS);

        $this->requestReset('ifeoma.eze@example.com');
        $this->confirm($this->tokenFromLastEmail());

        $this->assertNotEmpty(\WP_Session_Tokens::get_instance($this->other_user_id)->get_all());
        $this->assertNotEmpty(get_user_meta($this->other_user_id, 'hospital_manager_auth_token', true));
        $this->assertSame(200, $this->refresh($emeka_jwt)->get_status());
    }

    public function test_requesting_or_failing_a_reset_does_not_sign_anyone_out()
    {
        $jwt = $this->appLoginToken('nurse_ifeoma', self::OLD_PASSWORD);
        \WP_Session_Tokens::get_instance($this->user_id)->create(time() + DAY_IN_SECONDS);

        // Anyone can request a reset for any email, and send bad tokens.
        $this->requestReset('ifeoma.eze@example.com');
        $this->confirm('WrongTokenWrongTokenWrongToken12');
        $this->confirm($this->tokenFromLastEmail(), 'short');

        $this->assertNotEmpty(\WP_Session_Tokens::get_instance($this->user_id)->get_all());
        $this->assertSame(200, $this->refresh($jwt)->get_status());
    }

    public function test_reset_link_opens_the_apps_reset_page()
    {
        // The app is whichever published page holds the [hospital_manager] shortcode.
        $page = $this->factory->post->create([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Clinic Portal',
            'post_content' => '<!-- wp:shortcode -->[hospital_manager]<!-- /wp:shortcode -->',
        ]);

        $this->requestReset('ifeoma.eze@example.com');
        $token = $this->tokenFromLastEmail();
        $mail = $this->sentMail();
        $body = end($mail)['body'];

        // The React app uses a HashRouter, so the route goes after the "#".
        $this->assertStringContainsString(get_permalink($page) . '#/reset-password?token=' . $token, $body);

        // ...and the app must actually route it to the reset page, outside the login-guarded Layout.
        $app = file_get_contents(dirname(__DIR__, 2) . '/assets/js/src/App.jsx');
        $this->assertMatchesRegularExpression('#<Route path="/reset-password" element=\{<PasswordResetConfirm />\}#', $app);
        $this->assertLessThan(
            strrpos($app, '<Route path="*"'), // outer catch-all that wraps everything in the login-guarded Layout
            strpos($app, '<Route path="/reset-password"'),
            '/reset-password must be declared before the catch-all Layout route'
        );
    }
}

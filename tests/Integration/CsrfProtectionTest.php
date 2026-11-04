<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\AuthController;
use HospitalManager\Services\AuthService;

/**
 * Cookie-authenticated REST requests must carry a valid nonce.
 *
 * AuthService::custom_authenticate() used to return true for any signed-in
 * user, which told WordPress authentication had passed and skipped core's nonce
 * check (rest_cookie_check_errors). Another site could then make requests that
 * carried the user's cookies: CSRF.
 */
class CsrfProtectionTest extends TestCase
{
    private $user_id;
    private $server_backup;
    private $cookie_backup;

    public function setUp(): void
    {
        parent::setUp();
        $this->server_backup = $_SERVER;
        $this->cookie_backup = $_COOKIE;

        $this->user_id = $this->factory->user->create([
            'user_login' => 'dr_chioma', 'user_email' => 'chioma@example.com',
            'user_pass' => 'csrf-test-pass-1', 'role' => 'doctor',
        ]);
        $_SERVER['REQUEST_URI'] = trailingslashit(wp_parse_url(rest_url(), PHP_URL_PATH)) . 'hospital-manager/v1/patients';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.' . wp_rand(1, 250);
        wp_set_current_user(0);
    }

    public function tearDown(): void
    {
        delete_transient('login_attempts_' . $_SERVER['REMOTE_ADDR']);
        $_SERVER = $this->server_backup;
        $_COOKIE = $this->cookie_backup;
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    private function nonceFor($user_id)
    {
        $previous = get_current_user_id();
        wp_set_current_user($user_id);
        $nonce = wp_create_nonce('wp_rest');
        wp_set_current_user($previous);
        return $nonce;
    }

    // ------------------------------------------------------------ WordPress login cookie

    public function test_signed_in_user_without_a_nonce_is_left_to_core_nonce_check()
    {
        wp_set_current_user($this->user_id);

        // null = "no opinion": core's rest_cookie_check_errors then requires the nonce.
        $this->assertNull(AuthService::custom_authenticate(null));
    }

    public function test_signed_in_user_with_a_wrong_nonce_is_left_to_core_nonce_check()
    {
        wp_set_current_user($this->user_id);
        $_SERVER['HTTP_X_WP_NONCE'] = 'deadbeef00';

        $this->assertNull(AuthService::custom_authenticate(null));
    }

    public function test_signed_in_user_with_their_nonce_is_authenticated()
    {
        wp_set_current_user($this->user_id);
        $_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce('wp_rest');

        $this->assertTrue(AuthService::custom_authenticate(null));
        $this->assertSame($this->user_id, get_current_user_id());
    }

    // ------------------------------------------------------------ the app's own cookie

    private function giveAppCookie()
    {
        $token = wp_generate_password(32, false);
        update_user_meta($this->user_id, 'hospital_manager_auth_token', [
            'token' => hash('sha256', $token), 'expires' => time() + HOUR_IN_SECONDS,
        ]);
        $_COOKIE['hospital_manager_auth'] = $token;
    }

    public function test_app_cookie_without_a_nonce_does_not_sign_in()
    {
        $this->giveAppCookie();

        $this->assertNull(AuthService::custom_authenticate(null));
        $this->assertSame(0, get_current_user_id(), 'A cookie alone (e.g. sent by another site) must not sign in');
    }

    public function test_app_cookie_with_that_users_nonce_signs_in()
    {
        $this->giveAppCookie();
        $_SERVER['HTTP_X_WP_NONCE'] = $this->nonceFor($this->user_id);

        $this->assertTrue(AuthService::custom_authenticate(null));
        $this->assertSame($this->user_id, get_current_user_id());
    }

    public function test_app_cookie_with_a_logged_out_nonce_does_not_sign_in()
    {
        $this->giveAppCookie();
        $_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce('wp_rest'); // public, user 0

        // A valid logged-out nonce is "no error" for an anonymous request; what
        // matters is that the cookie doesn't turn it into the cookie's owner.
        AuthService::custom_authenticate(null);
        $this->assertSame(0, get_current_user_id());
    }

    // ------------------------------------------------------------ nonce handed out at login

    /**
     * The app keeps using the nonce from the login response (no page reload), so
     * it must be valid for the session that login created. It used to be created
     * before the new login cookie was visible, i.e. for "no session", and the
     * next request rejected it.
     */
    public function test_nonce_from_login_is_valid_for_the_new_session()
    {
        $issued_cookie = null;
        add_action('set_logged_in_cookie', function ($cookie) use (&$issued_cookie) {
            $issued_cookie = $cookie;
        }, 99);

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        (new AuthController())->register_routes();

        $_COOKIE = [];
        $request = new \WP_REST_Request('POST', '/hospital-manager/v1/auth/login');
        $request->set_body_params(['username' => 'dr_chioma', 'password' => 'csrf-test-pass-1', 'nonce' => wp_create_nonce('wp_rest')]);
        $response = @rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $nonce = $response->get_data()['fresh_nonce'];
        $this->assertNotEmpty($issued_cookie);

        // The next request from the browser: only the issued cookie, as that user.
        $_COOKIE = [LOGGED_IN_COOKIE => $issued_cookie];
        wp_set_current_user($this->user_id);
        $this->assertNotFalse(wp_verify_nonce($nonce, 'wp_rest'), 'Login nonce must verify against the new session');
    }

    // ------------------------------------------------------------ frontend

    public function test_frontend_reads_the_nonce_from_the_real_global()
    {
        $src = dirname(__DIR__, 2) . '/assets/js/src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));
        foreach ($files as $file) {
            if (preg_match('/\.(jsx?|tsx?)$/', $file->getFilename())) {
                // window.hospitalManager (without "Data") doesn't exist, so no nonce was sent.
                $this->assertDoesNotMatchRegularExpression(
                    '/window\.hospitalManager\??\.nonce/',
                    file_get_contents($file->getPathname()),
                    $file->getPathname()
                );
            }
        }
    }
}

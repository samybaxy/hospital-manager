<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\AuthController;
use HospitalManager\Controllers\Api\DoctorController;
use HospitalManager\Controllers\Api\PatientController;
use HospitalManager\Services\AuthService;
use HospitalManager\Services\WebSocketService;

/**
 * Debug-mode and "marker" authentication shortcuts must not sign anyone in.
 *
 * On a WP_DEBUG local site (like Local by Flywheel's default) these used to work
 * for an anonymous client, using only things any script can send:
 *   - a site Origin/Referer header  -> signed in as the first administrator
 *   - X-WP-Bypass-Auth header       -> authentication skipped
 *   - a valid nonce (logged-out nonces are printed on the public app page)
 *   - a Bearer header with any value
 * and in every environment:
 *   - cookie hospital_manager_auth=authenticated, any login cookie, a Basic-auth
 *     header (password never checked) -> create/edit/delete doctors
 */
class DebugAuthBypassTest extends TestCase
{
    private $server_backup;
    private $cookie_backup;
    private $admin_id;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Recreate the conditions the shortcuts were gated on (WP_DEBUG is already
        // on in the test config). The fixed code no longer reads this constant for
        // authentication, so defining it doesn't change other tests.
        if (!defined('WP_ENVIRONMENT_TYPE')) {
            define('WP_ENVIRONMENT_TYPE', 'local');
        }
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->server_backup = $_SERVER;
        $this->cookie_backup = $_COOKIE;

        $this->admin_id = $this->createUserWithRole('administrator');
        get_role('administrator')->add_cap('view_patients');
        $this->createTestPatient(['first_name' => 'Private', 'last_name' => 'Record']);

        // The plugin registers this in AuthService::init(), which the test bootstrap doesn't run.
        add_filter('rest_authentication_errors', [AuthService::class, 'custom_authenticate'], 15);

        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        foreach ([AuthController::class, DoctorController::class, PatientController::class] as $controller) {
            (new $controller())->register_routes();
        }
    }

    public function tearDown(): void
    {
        $_SERVER = $this->server_backup;
        $_COOKIE = $this->cookie_backup;
        get_role('administrator')->remove_cap('view_patients');
        remove_all_filters('wp_doing_ajax');
        remove_filter('rest_authentication_errors', [AuthService::class, 'custom_authenticate'], 15);
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    // ------------------------------------------------------------ helpers

    /** Headers a script can send to look like the app running on this site. */
    private function forgedSiteHeaders()
    {
        return [
            'Origin'   => site_url(),
            'Referer'  => site_url('/hospital-manager/'),
            'X-WP-Nonce' => wp_create_nonce('wp_rest'), // logged-out nonce, as on the public page
        ];
    }

    private function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
    }

    /** Run the REST authentication filters the way WP_REST_Server::serve_request() does. */
    private function authenticate(array $headers)
    {
        $this->setHeaders($headers);
        // AuthService only acts on this plugin's REST paths (/<rest prefix>/hospital-manager/v1/...)
        $_SERVER['REQUEST_URI'] = trailingslashit(wp_parse_url(rest_url(), PHP_URL_PATH)) . 'hospital-manager/v1/patients';
        wp_set_current_user(0);
        $result = apply_filters('rest_authentication_errors', null);
        return [$result, get_current_user_id()];
    }

    private function dispatch($method, $route, array $headers = [], array $body = [])
    {
        $this->setHeaders($headers);
        $request = new \WP_REST_Request($method, '/hospital-manager/v1' . $route);
        foreach ($headers as $name => $value) {
            $request->set_header($name, $value);
        }
        if ($body) {
            $request->set_body_params($body);
        }
        return rest_get_server()->dispatch($request);
    }

    private function doctorBody()
    {
        return [
            'first_name' => 'Forged', 'last_name' => 'Doctor', 'phone' => '08031234567',
            'specialty' => 'General Practice', 'license_number' => 'MDCN/FORGED/1',
        ];
    }

    private function doctorCount()
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_doctors");
    }

    // ------------------------------------------------------------ AuthService (rest_authentication_errors)

    public function test_forged_site_headers_do_not_sign_in_as_an_administrator()
    {
        foreach ([['Referer' => site_url('/hospital-manager/')], ['Origin' => site_url()], $this->forgedSiteHeaders()] as $headers) {
            [, $user_id] = $this->authenticate($headers);
            $this->assertSame(0, $user_id, 'Signed in via ' . implode(', ', array_keys($headers)));
        }
    }

    public function test_bypass_header_is_ignored()
    {
        // Checked on AuthService's own filter: later WordPress core filters also
        // return "no error" for anonymous requests, which would hide the difference.
        $this->setHeaders(['X-WP-Bypass-Auth' => '1']);
        $_SERVER['REQUEST_URI'] = trailingslashit(wp_parse_url(rest_url(), PHP_URL_PATH)) . 'hospital-manager/v1/patients';
        wp_set_current_user(0);

        $this->assertNull(AuthService::custom_authenticate(null), 'AuthService must not vouch for a request because of a header');
        $this->assertSame(0, get_current_user_id());
    }

    // ------------------------------------------------------------ /auth/me

    public function test_auth_me_does_not_report_forged_requests_as_signed_in()
    {
        $response = $this->dispatch('GET', '/auth/me', $this->forgedSiteHeaders());

        $this->assertFalse($response->get_data()['authenticated'], wp_json_encode($response->get_data()));
        $this->assertSame(0, get_current_user_id());
    }

    public function test_auth_me_ignores_the_literal_authenticated_cookie()
    {
        $_COOKIE['hospital_manager_auth'] = 'authenticated';

        $this->assertFalse($this->dispatch('GET', '/auth/me')->get_data()['authenticated']);
    }

    public function test_auth_me_still_reports_a_real_session()
    {
        wp_set_current_user($this->admin_id);
        $data = $this->dispatch('GET', '/auth/me')->get_data();

        $this->assertTrue($data['authenticated']);
        $this->assertEquals($this->admin_id, $data['user']['ID']);
    }

    // ------------------------------------------------------------ BaseController::check_permission (GET /patients)

    public function test_patient_list_rejects_forged_nonce_and_bearer_requests()
    {
        $site = ['Origin' => site_url(), 'Referer' => site_url('/hospital-manager/')];
        $cases = [
            'nonce + site headers'  => $this->forgedSiteHeaders(),
            'bearer + site headers' => $site + ['Authorization' => 'Bearer anything'],
        ];
        foreach ($cases as $label => $headers) {
            $response = $this->dispatch('GET', '/patients', $headers);
            $this->assertSame(401, $response->get_status(), $label);
            $this->assertStringNotContainsString('Private', wp_json_encode($response->get_data()), $label);
        }
    }

    public function test_patient_list_still_works_for_a_signed_in_user_with_the_capability()
    {
        wp_set_current_user($this->admin_id);
        $this->assertSame(200, $this->dispatch('GET', '/patients')->get_status());
    }

    // ------------------------------------------------------------ BaseController::check_auth (doctor writes)

    public function test_anonymous_requests_cannot_create_doctors()
    {
        $cases = [
            'public nonce'           => ['X-WP-Nonce' => wp_create_nonce('wp_rest')],
            'site headers + nonce'   => $this->forgedSiteHeaders(),
            'basic auth, any pw'     => ['Authorization' => 'Basic ' . base64_encode('admin:wrong')],
        ];
        $before = $this->doctorCount();

        foreach ($cases as $label => $headers) {
            if (strpos($label, 'basic') === 0) {
                $_SERVER['PHP_AUTH_USER'] = 'admin';
                $_SERVER['PHP_AUTH_PW'] = 'wrong';
            }
            $this->assertSame(401, $this->dispatch('POST', '/doctors', $headers, $this->doctorBody())->get_status(), $label);
            $_SERVER = $this->server_backup;
        }

        $this->assertSame($before, $this->doctorCount());
    }

    public function test_marker_cookies_cannot_create_doctors()
    {
        $before = $this->doctorCount();

        $_COOKIE['hospital_manager_auth'] = 'authenticated';
        $this->assertSame(401, $this->dispatch('POST', '/doctors', [], $this->doctorBody())->get_status(), 'literal cookie');
        $_COOKIE = $this->cookie_backup;

        add_filter('wp_doing_ajax', '__return_true');
        $_COOKIE[LOGGED_IN_COOKIE] = 'not-a-real-session';
        $this->assertSame(401, $this->dispatch('POST', '/doctors', [], $this->doctorBody())->get_status(), 'fake login cookie');

        $this->assertSame($before, $this->doctorCount());
    }

    public function test_only_doctors_and_administrators_can_write_doctors_in_every_environment()
    {
        $before = $this->doctorCount();
        foreach (['patient', 'hospital_nurse', 'hospital_staff', 'lab_tech'] as $role) {
            wp_set_current_user($this->createUserWithRole($role));
            $this->assertSame(403, $this->dispatch('POST', '/doctors', [], $this->doctorBody())->get_status(), $role);
        }
        $this->assertSame($before, $this->doctorCount());

        wp_set_current_user($this->admin_id);
        $this->assertSame(201, $this->dispatch('POST', '/doctors', [], $this->doctorBody())->get_status(), 'administrator');
    }

    // ------------------------------------------------------------ WebSocketService

    public function test_websocket_auth_check_requires_a_signed_in_user()
    {
        $this->setHeaders($this->forgedSiteHeaders() + ['Host' => 'localhost']);
        $_COOKIE['hospital_manager_auth'] = 'authenticated';
        $_COOKIE[LOGGED_IN_COOKIE] = 'not-a-real-session';
        $this->assertFalse(WebSocketService::checkAuthentication());

        wp_set_current_user($this->admin_id);
        $this->assertTrue(WebSocketService::checkAuthentication());
    }
}

<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\NotificationController;
use HospitalManager\Services\NotificationService;

/**
 * The notifications endpoints must only answer signed-in users.
 *
 * check_auth() used to accept "Authorization: Bearer <anything>" without
 * validating it, and AJAX-looking requests that merely had a login cookie
 * present (any value).
 */
class NotificationAuthTest extends TestCase
{
    private $user_id;
    private $other_user_id;
    private $notification_id;
    private $other_notification_id;
    private $server_backup;

    public function setUp(): void
    {
        parent::setUp();
        $this->server_backup = $_SERVER;

        $this->user_id = $this->createUserWithRole('doctor');
        $this->other_user_id = $this->createUserWithRole('patient');
        $this->notification_id = NotificationService::create($this->user_id, 'appointment', 'Mine', 'For the doctor')->ID;
        $this->other_notification_id = NotificationService::create($this->other_user_id, 'appointment', 'Theirs', 'For the patient')->ID;

        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        (new NotificationController())->register_routes();
    }

    public function tearDown(): void
    {
        $_SERVER = $this->server_backup;
        unset($_COOKIE[LOGGED_IN_COOKIE]);
        remove_all_filters('wp_doing_ajax');
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    private function routes()
    {
        return [
            ['GET', '/notifications'],
            ['GET', '/notifications/unread'],
            ['PUT', '/notifications/' . $this->other_notification_id . '/read'],
        ];
    }

    private function dispatch($method, $route, array $headers = [])
    {
        $request = new \WP_REST_Request($method, '/hospital-manager/v1' . $route);
        foreach ($headers as $name => $value) {
            $request->set_header($name, $value);
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        return rest_get_server()->dispatch($request);
    }

    private function isRead($id)
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT `read` FROM {$wpdb->prefix}hm_notifications WHERE ID = %d", $id));
    }

    public function test_anonymous_requests_are_rejected()
    {
        foreach ($this->routes() as [$method, $route]) {
            $this->assertSame(401, $this->dispatch($method, $route)->get_status(), "{$method} {$route}");
        }
    }

    public function test_an_unvalidated_bearer_header_does_not_authenticate()
    {
        foreach (['Bearer anything', 'Bearer ', 'Bearer eyJhbGciOiJub25lIn0.e30.', 'NotBearer'] as $header) {
            foreach ($this->routes() as [$method, $route]) {
                $response = $this->dispatch($method, $route, ['Authorization' => $header]);
                $this->assertSame(401, $response->get_status(), "{$method} {$route} with '{$header}'");
            }
        }
        $this->assertFalse($this->isRead($this->other_notification_id));
    }

    public function test_a_fake_login_cookie_on_an_ajax_looking_request_does_not_authenticate()
    {
        add_filter('wp_doing_ajax', '__return_true');
        $_COOKIE[LOGGED_IN_COOKIE] = 'not-a-real-session';
        $headers = ['X-Requested-With' => 'XMLHttpRequest', 'Referer' => site_url('/hospital-manager/')];

        foreach ($this->routes() as [$method, $route]) {
            $this->assertSame(401, $this->dispatch($method, $route, $headers)->get_status(), "{$method} {$route}");
        }
        $this->assertFalse($this->isRead($this->other_notification_id));
    }

    public function test_signed_in_user_gets_only_their_own_notifications()
    {
        wp_set_current_user($this->user_id);

        // The app sends a Bearer header too; it must not get in the way.
        $response = $this->dispatch('GET', '/notifications', ['Authorization' => 'Bearer whatever']);

        $this->assertSame(200, $response->get_status());
        $ids = array_map('intval', array_column($response->get_data()['data'], 'ID'));
        $this->assertSame([(int) $this->notification_id], $ids);
        $this->assertSame(200, $this->dispatch('GET', '/notifications/unread')->get_status());
    }

    public function test_signed_in_user_can_mark_only_their_own_notification_read()
    {
        wp_set_current_user($this->user_id);

        $this->assertSame(404, $this->dispatch('PUT', '/notifications/' . $this->other_notification_id . '/read')->get_status());
        $this->assertFalse($this->isRead($this->other_notification_id));

        $this->assertSame(200, $this->dispatch('PUT', '/notifications/' . $this->notification_id . '/read')->get_status());
        $this->assertTrue($this->isRead($this->notification_id));
    }
}

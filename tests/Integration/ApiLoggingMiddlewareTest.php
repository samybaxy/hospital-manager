<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Middleware\ApiLoggingMiddleware;

/**
 * Response bodies and request params contain patient/medical data, so they may only
 * be logged in debug mode; method/route/status lines are always logged.
 */
class ApiLoggingMiddlewareTest extends TestCase
{
    private $log_file;
    private $previous_error_log;

    public function setUp(): void
    {
        parent::setUp();
        $this->log_file = tempnam(sys_get_temp_dir(), 'hm-log-');
        $this->previous_error_log = ini_set('error_log', $this->log_file);
    }

    public function tearDown(): void
    {
        ini_set('error_log', $this->previous_error_log);
        @unlink($this->log_file);
        remove_all_filters('hospital_manager_log_api_payloads');
        parent::tearDown();
    }

    public function test_response_body_is_not_logged_when_payload_logging_is_off()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_false');

        $this->logResponse(['patient' => ['first_name' => 'Ngozi', 'diagnosis' => 'Malaria']], 200);

        $log = $this->readLog();
        $this->assertStringContainsString('GET /hospital-manager/v1/dashboard - Status: 200', $log);
        $this->assertStringNotContainsString('Ngozi', $log);
        $this->assertStringNotContainsString('Malaria', $log);
        $this->assertStringNotContainsString('Data:', $log);
    }

    public function test_request_params_are_not_logged_when_payload_logging_is_off()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_false');

        $request = new \WP_REST_Request('POST', '/hospital-manager/v1/patients');
        $request->set_body_params(['first_name' => 'Ngozi', 'chronic_conditions' => 'Hypertension']);
        ApiLoggingMiddleware::logRequest(null, rest_get_server(), $request);

        $log = $this->readLog();
        $this->assertStringContainsString('POST /hospital-manager/v1/patients', $log);
        $this->assertStringNotContainsString('Hypertension', $log);
        $this->assertStringNotContainsString('Params:', $log);
    }

    public function test_payloads_are_logged_with_secrets_redacted_when_enabled()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_true');

        $this->logResponse([
            'token' => 'secret-jwt',
            'user' => ['ID' => 5, 'email' => 'ngozi@example.com'],
            'data' => ['nested' => ['password' => 'hunter22', 'first_name' => 'Ngozi']],
        ], 200);

        $log = $this->readLog();
        $this->assertStringContainsString('Data:', $log);
        $this->assertStringContainsString('Ngozi', $log);
        $this->assertStringNotContainsString('secret-jwt', $log);
        $this->assertStringNotContainsString('ngozi@example.com', $log);
        $this->assertStringNotContainsString('hunter22', $log);
    }

    /** Off unless HOSPITAL_MANAGER_LOG_PAYLOADS is defined: WP_DEBUG alone must not log patient data. */
    public function test_payload_logging_defaults_to_off_even_with_wp_debug()
    {
        $this->assertSame(defined('HOSPITAL_MANAGER_LOG_PAYLOADS') && HOSPITAL_MANAGER_LOG_PAYLOADS, ApiLoggingMiddleware::shouldLogPayloads());
        if (!defined('HOSPITAL_MANAGER_LOG_PAYLOADS')) {
            $this->assertFalse(ApiLoggingMiddleware::shouldLogPayloads());
        }
    }

    public function test_object_payloads_do_not_fatal()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_true');

        $response = new \WP_REST_Response((object) ['data' => [], 'meta' => ['total' => 0]]);
        $result = ApiLoggingMiddleware::logResponse(
            $response,
            rest_get_server(),
            new \WP_REST_Request('GET', '/hospital-manager/v1/chats/1/messages')
        );

        $this->assertSame($response, $result);
    }

    public function test_routes_outside_the_plugin_are_ignored()
    {
        $this->logResponse(['x' => 1], 200, '/wp/v2/posts');

        $this->assertSame('', $this->readLog());
    }

    private function logResponse($data, $status, $route = '/hospital-manager/v1/dashboard')
    {
        ApiLoggingMiddleware::logResponse(
            new \WP_REST_Response($data, $status),
            rest_get_server(),
            new \WP_REST_Request('GET', $route)
        );
    }

    private function readLog()
    {
        return (string) file_get_contents($this->log_file);
    }
}

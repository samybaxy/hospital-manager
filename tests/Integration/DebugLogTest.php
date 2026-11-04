<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Helpers\DebugLog;
use HospitalManager\Models\Patient;
use HospitalManager\Services\PatientService;

/**
 * DebugLog::payload() must write nothing unless debug payload logging is enabled,
 * and must redact secrets when it is. Also checks a real call site end-to-end.
 */
class DebugLogTest extends TestCase
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

    public function test_payload_writes_nothing_when_disabled()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_false');

        $logged = DebugLog::payload('Lab object', ['patient' => 'Ngozi Eze', 'result' => 'Positive']);

        $this->assertFalse($logged);
        $this->assertSame('', $this->readLog());
    }

    public function test_payload_logs_redacted_json_when_enabled()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_true');

        $logged = DebugLog::payload('Params', [
            'search' => 'Ngozi',
            'auth' => ['token' => 'secret-jwt', 'email' => 'ngozi@example.com'],
        ]);

        $log = $this->readLog();
        $this->assertTrue($logged);
        $this->assertStringContainsString('Params: {"search":"Ngozi"', $log);
        $this->assertStringNotContainsString('secret-jwt', $log);
        $this->assertStringNotContainsString('ngozi@example.com', $log);
    }

    public function test_payload_serialises_models_instead_of_empty_objects()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_true');

        DebugLog::payload('Patient', new Patient(['ID' => 7, 'first_name' => 'Ngozi']));

        $this->assertStringContainsString('Patient: {"ID":7,"first_name":"Ngozi"}', $this->readLog());
    }

    /** Off unless HOSPITAL_MANAGER_LOG_PAYLOADS is defined: WP_DEBUG alone must not log patient data. */
    public function test_enabled_defaults_to_off_even_with_wp_debug()
    {
        $this->assertSame(defined('HOSPITAL_MANAGER_LOG_PAYLOADS') && HOSPITAL_MANAGER_LOG_PAYLOADS, DebugLog::enabled());
        if (!defined('HOSPITAL_MANAGER_LOG_PAYLOADS')) {
            $this->assertFalse(DebugLog::enabled());
        }
    }

    public function test_patient_search_terms_are_not_logged_when_disabled()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_false');

        PatientService::getPatients(['params' => ['search' => 'Zzyzxsearchterm']]);

        $this->assertStringNotContainsString('Zzyzxsearchterm', $this->readLog());
    }

    public function test_patient_search_terms_are_logged_when_enabled()
    {
        add_filter('hospital_manager_log_api_payloads', '__return_true');

        PatientService::getPatients(['params' => ['search' => 'Zzyzxsearchterm']]);

        $this->assertStringContainsString('Zzyzxsearchterm', $this->readLog());
    }

    private function readLog()
    {
        return (string) file_get_contents($this->log_file);
    }
}

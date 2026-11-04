<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Patient;
use HospitalManager\Services\AuditLogger;
use HospitalManager\Controllers\Api\ChatController;
use HospitalManager\Controllers\Api\LabInvestigationController;
use HospitalManager\Controllers\Api\NotificationController;

/**
 * Regression tests for what patient-facing screens receive:
 *   - Medical History tab / appointment list: doctor names resolved via hm_doctors
 *     (queries joined hm_*.doctor_id straight to wp_users.ID and showed a random user)
 *   - AuditLogger::log(): writes succeed (inserting non-existent ip_address/user_agent
 *     columns made every write fail silently)
 *   - /notifications, /chats return real data (not "{}") and /lab-investigations/pending
 *     only shows a patient their own tests
 */
class PatientRecordViewsTest extends TestCase
{
    private $doctor_user_id;
    private $doctor;
    private $patient_user_id;
    private $patient;

    public function setUp(): void
    {
        parent::setUp();

        $this->doctor_user_id = $this->createUserWithRole('doctor');
        wp_update_user(['ID' => $this->doctor_user_id, 'display_name' => 'Dr. Ada Okafor']);
        $this->doctor = $this->createTestDoctor(['user_id' => $this->doctor_user_id, 'first_name' => 'Ada', 'last_name' => 'Okafor']);

        $this->patient_user_id = $this->createUserWithRole('patient');
        $this->patient = $this->createTestPatient(['user_id' => $this->patient_user_id, 'first_name' => 'Ngozi', 'last_name' => 'Eze']);

        // Decoy: a WP user whose ID equals the doctor *record* ID. The old query joined
        // hm_*.doctor_id straight to wp_users.ID and would return "Wrong Person".
        global $wpdb;
        $decoy = $this->createUserWithRole('subscriber');
        wp_update_user(['ID' => $decoy, 'display_name' => 'Wrong Person']);
        $taken = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}hm_doctors WHERE ID = %d", $decoy));
        if ($taken) {
            $this->markTestSkipped("hm_doctors.ID {$decoy} already in use");
        }
        $wpdb->update($wpdb->prefix . 'hm_doctors', ['ID' => $decoy], ['ID' => $this->doctor->ID]);
        $this->doctor = \HospitalManager\Models\Doctor::find($decoy);
        $this->assertNotEquals($this->doctor_user_id, $this->doctor->ID);

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        foreach ([ChatController::class, LabInvestigationController::class, NotificationController::class] as $controller) {
            (new $controller())->register_routes();
        }
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function test_visitation_history_shows_the_treating_doctor()
    {
        $this->createTestVisitation(['patient_id' => $this->patient->ID, 'doctor_id' => $this->doctor->ID]);
        Patient::invalidateCache('getVisitationHistory', [$this->patient->ID]);

        $history = Patient::find($this->patient->ID)->getVisitationHistory();

        $this->assertCount(1, $history);
        $this->assertSame('Dr. Ada Okafor', $history[0]->doctor);
    }

    public function test_patient_appointments_show_the_treating_doctor()
    {
        $this->createTestAppointment($this->patient->ID, $this->doctor->ID);
        Patient::invalidateCache('getAppointments', [$this->patient->ID]);

        $appointments = Patient::find($this->patient->ID)->getAppointments();

        $this->assertCount(1, $appointments);
        $this->assertSame('Dr. Ada Okafor', $appointments[0]->doctor_name);
    }

    public function test_audit_logger_writes_and_stores_request_meta_in_details()
    {
        global $wpdb;
        $ip_filter = function () { return '203.0.113.7'; };
        add_filter('hospital_manager_user_ip', $ip_filter);

        $entry = AuditLogger::log('view_patient', 'patient', $this->patient->ID, ['section' => 'labs'], $this->doctor_user_id);

        remove_filter('hospital_manager_user_ip', $ip_filter);

        $this->assertNotFalse($entry, 'AuditLogger::log() returned false (write failed)');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_audit_logs WHERE ID = %d", $entry->ID
        ), ARRAY_A);
        $this->assertNotNull($row);
        $this->assertSame('view_patient', $row['action']);
        $details = json_decode($row['details'], true);
        $this->assertSame('203.0.113.7', $details['ip_address']);
        $this->assertSame('labs', $details['section']);
    }

    public function test_notifications_endpoint_returns_real_data()
    {
        $this->createTestNotification(['user_id' => $this->patient_user_id, 'title' => 'Lab Results Available']);

        $data = $this->getAs($this->patient_user_id, '/hospital-manager/v1/notifications');

        $this->assertSame('Lab Results Available', $data['data'][0]['title']);
    }

    public function test_chats_endpoint_returns_real_data()
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_chats', [
            'doctor_id' => $this->doctor_user_id,
            'patient_id' => $this->patient_user_id,
            'status' => 'active',
            'last_message_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ]);

        $data = $this->getAs($this->patient_user_id, '/hospital-manager/v1/chats');

        $this->assertCount(1, $data);
        $this->assertEquals($this->doctor_user_id, $data[0]['doctor_id']);
    }

    public function test_chat_messages_return_text_without_exposing_user_records()
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_chats', [
            'doctor_id' => $this->doctor_user_id,
            'patient_id' => $this->patient_user_id,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ]);
        $chat_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . 'hm_chat_messages', [
            'chat_id' => $chat_id,
            'sender_id' => $this->doctor_user_id,
            'receiver_id' => $this->patient_user_id,
            'message' => 'Your results are ready.',
            'read' => 0,
            'created_at' => current_time('mysql'),
        ]);

        $data = $this->getAs($this->patient_user_id, "/hospital-manager/v1/chats/{$chat_id}/messages");

        $this->assertSame('Your results are ready.', $data['data'][0]['message']);
        $this->assertSame(['ID' => $this->doctor_user_id, 'display_name' => 'Dr. Ada Okafor'], $data['data'][0]['sender']);
        $this->assertStringNotContainsString('user_pass', wp_json_encode($data), 'Message payload must not include WP_User records');
    }

    public function test_starting_an_existing_chat_returns_it_instead_of_an_empty_object()
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_chats', [
            'doctor_id' => $this->doctor_user_id,
            'patient_id' => $this->patient_user_id,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ]);
        $chat_id = $wpdb->insert_id;

        $response = $this->postAs($this->patient_user_id, '/hospital-manager/v1/chats/start', ['doctor_id' => $this->doctor_user_id]);
        $data = json_decode(wp_json_encode($response->get_data()), true);

        $this->assertSame(200, $response->get_status(), wp_json_encode($data));
        $this->assertEquals($chat_id, $data['ID'] ?? null, 'Existing chat must be returned with its ID (was "{}")');
        $this->assertEquals($this->doctor_user_id, $data['doctor_id']);
    }

    public function test_new_message_push_carries_the_message_not_an_empty_object()
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_chats', [
            'doctor_id' => $this->doctor_user_id,
            'patient_id' => $this->patient_user_id,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ]);
        $chat_id = $wpdb->insert_id;
        delete_transient('hm_ws_' . $this->doctor_user_id);

        $response = $this->postAs($this->patient_user_id, "/hospital-manager/v1/chats/{$chat_id}/messages", ['message' => 'Is my result back?']);
        $this->assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));

        $queued = get_transient('hm_ws_' . $this->doctor_user_id) ?: [];
        $push = json_decode(wp_json_encode(end($queued)), true);
        $this->assertSame('new_message', $push['data']['type'] ?? null);
        $this->assertSame('Is my result back?', $push['data']['message']['message'] ?? null, 'Real-time push must contain the message');
        $this->assertStringNotContainsString('user_pass', wp_json_encode($push));
    }

    public function test_pending_labs_only_shows_a_patient_their_own_tests()
    {
        $other = $this->createTestPatient(['user_id' => $this->createUserWithRole('patient')]);
        $lab_tech = $this->createUserWithRole('lab_tech');
        foreach ([$this->patient, $other] as $p) {
            $visit = $this->createTestVisitation(['patient_id' => $p->ID, 'doctor_id' => $this->doctor->ID]);
            $this->createTestLabInvestigation([
                'visitation_id' => $visit->ID,
                'patient_id' => $p->ID,
                'doctor_id' => $this->doctor->ID,
                'lab_tech_id' => $lab_tech,
            ]);
        }
        // The route's permission check wants this capability
        get_role('patient')->add_cap('view_patient_records');

        $data = $this->getAs($this->patient_user_id, '/hospital-manager/v1/lab-investigations/pending');

        $this->assertNotEmpty($data);
        $this->assertSame([(string) $this->patient->ID], array_values(array_unique(array_map('strval', array_column($data, 'patient_id')))));
    }

    private function postAs($user_id, $route, array $body)
    {
        wp_set_current_user($user_id);
        $request = new \WP_REST_Request('POST', $route);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode($body));
        return rest_get_server()->dispatch($request);
    }

    private function getAs($user_id, $route)
    {
        wp_set_current_user($user_id);
        $response = rest_get_server()->dispatch(new \WP_REST_Request('GET', $route));
        $data = json_decode(wp_json_encode($response->get_data()), true);
        $this->assertSame(200, $response->get_status(), "{$route}: " . wp_json_encode($data));
        return $data;
    }
}

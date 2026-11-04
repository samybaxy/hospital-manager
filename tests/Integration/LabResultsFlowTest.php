<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\LabInvestigationController;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Services\LabResultService;
use HospitalManager\Services\NotificationService;

/**
 * End-to-end lab result flow through the REAL controller. Regression tests for:
 *   - LabInvestigation::save() writing to wp_posts (edits discarded)
 *   - PUT /results: saving, then notifyResultsReady() re-saving NULL results (wiped)
 *     and a 500 from reading the protected ->attributes property
 *   - abnormal/critical flags never set for the stored {"parameters": [...]} shape
 *   - NotificationService::create() writing posts instead of hm_notifications, and
 *     WebSocketService duplicating lab result notifications
 *   - notifications falling back to patient/doctor *record* IDs as user IDs
 *   - PUT /status bypassing the audit log; old_status logged after the change
 *   - notifyLabRequest() always returning early (isset() on a protected property)
 *   - getUnreadCount() SQL error (unquoted reserved word `read`)
 */
class LabResultsFlowTest extends TestCase
{
    private $lab_tech;
    private $doctor_user;
    private $doctor;
    private $patient_user;
    private $patient;
    private $lab;

    public function setUp(): void
    {
        parent::setUp();

        $this->lab_tech = $this->createUserWithRole('lab_tech');
        get_role('lab_tech')->add_cap('update_lab_results');

        $this->doctor_user = $this->createUserWithRole('doctor');
        $this->doctor = $this->createTestDoctor(['user_id' => $this->doctor_user]);
        $this->patient_user = $this->createUserWithRole('patient');
        $this->patient = $this->createTestPatient(['user_id' => $this->patient_user]);

        $visit = $this->createTestVisitation(['patient_id' => $this->patient->ID, 'doctor_id' => $this->doctor->ID]);
        $this->lab = $this->createTestLabInvestigation([
            'visitation_id' => $visit->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
            'lab_tech_id' => $this->lab_tech,
            'test_type' => 'Complete Blood Count',
        ]);

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        (new LabInvestigationController())->register_routes();
    }

    public function tearDown(): void
    {
        get_role('lab_tech')->remove_cap('update_lab_results');
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    // ------------------------------------------------------------------ results

    public function test_results_are_saved_flagged_and_kept()
    {
        $response = $this->putAs($this->lab_tech, '/results', [
            'test_results' => ['parameters' => [
                ['name' => 'Hemoglobin', 'value' => 9.1, 'unit' => 'g/dL', 'is_abnormal' => true, 'is_critical' => false],
            ]],
            'lab_notes' => 'Low hemoglobin',
        ]);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $row = $this->labRow();
        $this->assertSame('completed', $row['status']);
        $this->assertSame('Low hemoglobin', $row['lab_notes']);
        $this->assertSame('1', (string) $row['is_abnormal']);
        $this->assertSame('0', (string) $row['is_critical']);
        $results = json_decode($row['test_results'], true);
        $this->assertSame(9.1, $results['parameters'][0]['value'], 'Results must not be wiped after saving');
    }

    public function test_critical_results_flag_and_alert_the_doctor()
    {
        $this->putAs($this->lab_tech, '/results', [
            'test_results' => ['parameters' => [
                ['name' => 'Potassium', 'value' => 6.9, 'is_abnormal' => true, 'is_critical' => true],
            ]],
        ]);

        $this->assertSame('1', (string) $this->labRow()['is_critical']);
        $doctor_notes = $this->notificationsFor($this->doctor_user);
        $this->assertCount(1, $doctor_notes);
        $this->assertSame('CRITICAL Lab Results', $doctor_notes[0]['title']);
    }

    public function test_flags_are_computed_when_results_arrive_as_a_json_string()
    {
        $lab = LabInvestigation::find($this->lab->ID);
        $lab->updateResults(wp_json_encode(['parameters' => [['name' => 'WBC', 'value' => 19, 'is_abnormal' => true]]]));

        $this->assertSame('1', (string) $this->labRow()['is_abnormal']);
    }

    public function test_patient_and_doctor_each_get_exactly_one_visible_notification()
    {
        global $wpdb;
        $posts_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");

        $this->putAs($this->lab_tech, '/results', [
            'test_results' => ['parameters' => [['name' => 'Hemoglobin', 'value' => 13.5, 'is_abnormal' => false]]],
        ]);

        foreach ([$this->patient_user, $this->doctor_user] as $user) {
            $notes = $this->notificationsFor($user);
            $this->assertCount(1, $notes, "User {$user} should get exactly one notification");
            $this->assertSame('lab_results', $notes[0]['type']);
            $this->assertEquals($this->lab->ID, $notes[0]['reference_id']);
        }
        $this->assertSame($posts_before, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"), 'Notifications must not be stored as posts');

        // ...and they reach the patient's notification list and unread count
        $listed = NotificationService::getUserNotifications($this->patient_user, 1, 20);
        $this->assertSame('Lab Results Available', json_decode(wp_json_encode($listed['items'][0]->getAttributes()), true)['title']);
        $this->assertSame(1, NotificationService::getUnreadCount($this->patient_user));
    }

    public function test_patient_without_portal_account_is_not_confused_with_another_user()
    {
        global $wpdb;
        // Decoy: a WP user whose ID equals the patient *record* ID
        $decoy = $this->createUserWithRole('subscriber');
        $wpdb->update($wpdb->prefix . 'hm_patients', ['ID' => $decoy, 'user_id' => 0], ['ID' => $this->patient->ID]);
        $wpdb->update($wpdb->prefix . 'hm_lab_investigations', ['patient_id' => $decoy], ['ID' => $this->lab->ID]);

        $this->putAs($this->lab_tech, '/results', [
            'test_results' => ['parameters' => [['name' => 'Hemoglobin', 'value' => 13.5]]],
        ]);

        $this->assertCount(0, $this->notificationsFor($decoy), 'Must not notify the user whose ID equals the patient record ID');
        $this->assertCount(1, $this->notificationsFor($this->doctor_user), 'Doctor is still notified');
    }

    public function test_notify_results_ready_does_not_modify_the_results()
    {
        $lab = LabInvestigation::find($this->lab->ID);
        $lab->updateResults(['parameters' => [['name' => 'Hemoglobin', 'value' => 9.1, 'is_abnormal' => true]]]);

        $this->assertTrue(LabResultService::notifyResultsReady($this->lab->ID));

        $row = $this->labRow();
        $this->assertNotNull($row['test_results'], 'notifyResultsReady() must not wipe the results');
        $this->assertSame('1', (string) $row['is_abnormal']);
        $this->assertCount(1, $this->notificationsFor($this->patient_user));
    }

    public function test_results_update_is_audited()
    {
        $this->putAs($this->lab_tech, '/results', [
            'test_results' => ['parameters' => [['name' => 'Hemoglobin', 'value' => 9.1, 'is_abnormal' => true]]],
        ]);

        $entry = $this->auditEntry('update_lab_results');
        $this->assertNotNull($entry);
        $this->assertEquals($this->patient->ID, $entry['details']['patient_id']);
        $this->assertEquals(1, $entry['details']['is_abnormal']);
    }

    // ------------------------------------------------------------------ status / edits

    public function test_status_change_is_saved_and_audited_with_the_real_old_status()
    {
        $response = $this->putAs($this->lab_tech, '/status', ['status' => 'sample_collected']);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertSame('sample_collected', $this->labRow()['status']);
        $entry = $this->auditEntry('update_lab_status');
        $this->assertNotNull($entry, 'Status changes must be audited');
        $this->assertSame('requested', $entry['details']['old_status']);
        $this->assertSame('sample_collected', $entry['details']['new_status']);
    }

    public function test_invalid_status_is_rejected()
    {
        $response = $this->putAs($this->lab_tech, '/status', ['status' => 'done']);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('requested', $this->labRow()['status']);
    }

    public function test_general_update_persists()
    {
        $response = $this->putAs($this->lab_tech, '', ['lab_notes' => 'Sample haemolysed, recollect', 'sample_type' => 'Whole Blood']);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $row = $this->labRow();
        $this->assertSame('Sample haemolysed, recollect', $row['lab_notes']);
        $this->assertSame('Whole Blood', $row['sample_type']);
    }

    // ------------------------------------------------------------------ lab request

    public function test_lab_tech_is_notified_of_a_new_request()
    {
        $this->assertTrue(LabResultService::notifyLabRequest($this->lab->ID));

        $notes = $this->notificationsFor($this->lab_tech);
        $this->assertCount(1, $notes);
        $this->assertSame('lab_request', $notes[0]['type']);
        $this->assertEquals($this->lab->ID, $notes[0]['reference_id']);
    }

    // ------------------------------------------------------------------ helpers

    private function putAs($user_id, $suffix, array $body)
    {
        wp_set_current_user($user_id);
        $request = new \WP_REST_Request('PUT', "/hospital-manager/v1/lab-investigations/{$this->lab->ID}{$suffix}");
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode($body));
        return rest_get_server()->dispatch($request);
    }

    private function labRow()
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_lab_investigations WHERE ID = %d", $this->lab->ID
        ), ARRAY_A);
    }

    private function notificationsFor($user_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_notifications WHERE user_id = %d ORDER BY ID", $user_id
        ), ARRAY_A);
    }

    private function auditEntry($action)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_audit_logs
             WHERE action = %s AND entity_type = 'lab_investigation' AND entity_id = %d
             ORDER BY ID DESC LIMIT 1",
            $action,
            $this->lab->ID
        ), ARRAY_A);
        if ($row) {
            $row['details'] = json_decode($row['details'], true);
        }
        return $row;
    }
}

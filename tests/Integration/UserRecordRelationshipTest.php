<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\MedicalReport;
use HospitalManager\Models\Patient;
use HospitalManager\Middleware\ApiLoggingMiddleware;
use HospitalManager\Controllers\Api\DashboardController;
use HospitalManager\Controllers\Api\ProfileController;
use HospitalManager\Controllers\Api\StatsController;

/**
 * Guards the WordPress-user <-> hospital-record relationship.
 *
 * Two different IDs exist for every doctor and patient:
 *   - wp_users.ID            (login, chats, notifications, medical_reports.doctor_id)
 *   - hm_doctors.ID / hm_patients.ID  (appointments, visitations, lab investigations)
 * linked through hm_doctors.user_id / hm_patients.user_id.
 *
 * The doctor dashboard previously queried appointments with the WP user ID, so it
 * always came back empty (and the endpoint fatalled on MedicalReport). These tests
 * deliberately make the two IDs differ so that kind of mix-up cannot pass silently.
 */
class UserRecordRelationshipTest extends TestCase
{
    private $doctor_user_id;
    private $doctor;
    private $patient_user_id;
    private $patient;

    public function setUp(): void
    {
        parent::setUp();
        $this->useRealControllers();

        $this->doctor_user_id = $this->createUserWithRole('doctor');
        $this->doctor = $this->createRecordWithDivergentId(function () {
            return $this->createTestDoctor([
                'user_id' => $this->doctor_user_id,
                'first_name' => 'Ada',
                'last_name' => 'Okafor',
            ]);
        }, $this->doctor_user_id);

        $this->patient_user_id = $this->createUserWithRole('patient');
        $this->patient = $this->createRecordWithDivergentId(function () {
            return $this->createTestPatient([
                'user_id' => $this->patient_user_id,
                'first_name' => 'Ngozi',
                'last_name' => 'Eze',
            ]);
        }, $this->patient_user_id);
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        // Let the next test rebuild the (mock-backed) default server
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    private function useRealControllers()
    {
        // Build a fresh default server so rest_api_init fires inside this test
        // (register_rest_route() emits _doing_it_wrong otherwise), then replace it.
        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();

        foreach ([DashboardController::class, ProfileController::class, StatsController::class] as $controller) {
            (new $controller())->register_routes();
        }
    }

    // ------------------------------------------------------------------
    // Model-level relationship
    // ------------------------------------------------------------------

    public function test_doctor_find_by_user_id_returns_linked_record()
    {
        $found = Doctor::findByUserId($this->doctor_user_id);

        $this->assertInstanceOf(Doctor::class, $found);
        $this->assertEquals($this->doctor->ID, $found->ID);
        $this->assertEquals($this->doctor_user_id, $found->user_id);
        $this->assertNotEquals(
            $this->doctor_user_id,
            $found->ID,
            'Fixture must keep doctor record ID and WP user ID different'
        );
    }

    public function test_patient_find_by_user_id_returns_linked_record()
    {
        $found = Patient::findByUserId($this->patient_user_id);

        $this->assertInstanceOf(Patient::class, $found);
        $this->assertEquals($this->patient->ID, $found->ID);
        $this->assertEquals($this->patient_user_id, $found->user_id);
    }

    public function test_find_by_user_id_returns_null_without_a_linked_record()
    {
        $unlinked_user = $this->createUserWithRole('doctor');

        $this->assertNull(Doctor::findByUserId($unlinked_user));
        $this->assertNull(Doctor::findByUserId(0));
        $this->assertNull(Patient::findByUserId(0));
    }

    public function test_todays_appointments_are_keyed_by_doctor_record_id()
    {
        $this->insertAppointment($this->patient->ID, $this->doctor->ID, current_time('Y-m-d'), '09:00:00');

        $by_record = Appointment::getTodaysForDoctor($this->doctor->ID);
        $by_user = Appointment::getTodaysForDoctor($this->doctor_user_id);

        $this->assertCount(1, $by_record);
        $this->assertCount(
            0,
            $by_user,
            'Appointments must not be found via the WP user ID (regression: doctor dashboard bug)'
        );
    }

    public function test_upcoming_patient_appointments_join_the_right_doctor()
    {
        $this->insertAppointment($this->patient->ID, $this->doctor->ID, date('Y-m-d', strtotime('+2 days')), '10:00:00');

        $upcoming = Appointment::getUpcomingForPatient($this->patient->ID);

        $this->assertCount(1, $upcoming);
        $row = $this->toArray($upcoming[0]);
        $this->assertEquals($this->doctor->ID, $row['doctor_id']);
        $this->assertStringContainsString('Okafor', $row['doctor_name']);
    }

    public function test_medical_report_model_loads_and_scopes_by_doctor_user_id()
    {
        // Class used to fatal on load (non-static count() overriding static BaseModel::count()).
        $this->assertTrue(class_exists(MedicalReport::class));

        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
        ]);
        MedicalReport::create([
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor_user_id, // medical reports store the doctor's WP user ID
            'visitation_id' => $visitation->ID,
            'report_content' => 'Pending report',
            'status' => 'pending',
        ]);

        $pending = MedicalReport::getPendingForDoctor($this->doctor_user_id);

        $this->assertCount(1, $pending);
        $this->assertEquals($this->patient->ID, $pending[0]->patient_id);
    }

    // ------------------------------------------------------------------
    // REST endpoints that depend on the relationship
    // ------------------------------------------------------------------

    public function test_doctor_dashboard_lists_todays_appointments_for_logged_in_doctor()
    {
        $other_doctor = $this->createTestDoctor(['user_id' => $this->createUserWithRole('doctor')]);
        $today = current_time('Y-m-d');

        $this->insertAppointment($this->patient->ID, $this->doctor->ID, $today, '09:00:00');
        $this->insertAppointment($this->patient->ID, $this->doctor->ID, $today, '14:30:00');
        $this->insertAppointment($this->patient->ID, $this->doctor->ID, date('Y-m-d', strtotime('+1 day')), '09:00:00');
        $this->insertAppointment($this->patient->ID, $other_doctor->ID, $today, '11:00:00');

        $data = $this->getAs($this->doctor_user_id, '/hospital-manager/v1/dashboard', 200);

        $this->assertEquals($this->doctor->ID, $data['doctor_id']);
        $this->assertSame(2, $data['total_patients_today']);
        $this->assertCount(2, $data['todays_appointments']);

        foreach ($data['todays_appointments'] as $appointment) {
            // Serialised as real data, not "{}"
            $this->assertEquals($this->doctor->ID, $appointment['doctor_id']);
            $this->assertEquals($this->patient->ID, $appointment['patient_id']);
            $this->assertEquals('Ngozi Eze', $appointment['patient_name']);
            $this->assertEquals($today, $appointment['appointment_date']);
        }
    }

    public function test_doctor_dashboard_includes_pending_reports()
    {
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
        ]);
        MedicalReport::create([
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor_user_id,
            'visitation_id' => $visitation->ID,
            'report_content' => 'Awaiting sign-off',
            'status' => 'pending',
        ]);

        $data = $this->getAs($this->doctor_user_id, '/hospital-manager/v1/dashboard', 200);

        $this->assertCount(1, $data['pending_reports']);
        $this->assertEquals('Awaiting sign-off', $data['pending_reports'][0]['report_content']);
    }

    public function test_doctor_dashboard_returns_404_when_user_has_no_doctor_record()
    {
        $unlinked_doctor = $this->createUserWithRole('doctor');

        $this->getAs($unlinked_doctor, '/hospital-manager/v1/dashboard', 404);
    }

    public function test_patient_dashboard_returns_only_own_records()
    {
        $other_patient = $this->createTestPatient(['user_id' => $this->createUserWithRole('patient')]);
        $future = date('Y-m-d', strtotime('+3 days'));

        $this->insertAppointment($this->patient->ID, $this->doctor->ID, $future, '10:00:00');
        $this->insertAppointment($other_patient->ID, $this->doctor->ID, $future, '11:00:00');
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
        ]);
        $this->createTestLabInvestigation([
            'visitation_id' => $visitation->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
            'lab_tech_id' => $this->createUserWithRole('lab_tech'),
        ]);

        $data = $this->getAs($this->patient_user_id, '/hospital-manager/v1/dashboard', 200);

        $this->assertEquals($this->patient->ID, $data['patient']['ID']);
        $this->assertEquals($this->patient_user_id, $data['patient']['user_id']);

        $this->assertCount(1, $data['upcoming_appointments']);
        $this->assertEquals($this->patient->ID, $data['upcoming_appointments'][0]['patient_id']);

        $this->assertCount(1, $data['recent_visitations']);
        $this->assertEquals($visitation->ID, $data['recent_visitations'][0]['ID']);

        $this->assertCount(1, $data['pending_lab_tests']);
        $this->assertEquals($this->patient->ID, $data['pending_lab_tests'][0]['patient_id']);
    }

    public function test_profile_endpoint_resolves_doctor_and_patient_records()
    {
        $doctor_profile = $this->getAs($this->doctor_user_id, '/hospital-manager/v1/profile', 200);
        $this->assertEquals('doctor', $doctor_profile['data']['role']);
        $this->assertEquals($this->doctor->ID, $doctor_profile['data']['ID']);

        $patient_profile = $this->getAs($this->patient_user_id, '/hospital-manager/v1/profile', 200);
        $this->assertEquals('patient', $patient_profile['data']['role']);
        $this->assertEquals($this->patient->ID, $patient_profile['data']['ID']);
    }

    public function test_admin_stats_endpoint_counts_todays_visits_and_pending_labs()
    {
        $admin = $this->createUserWithRole('administrator');
        $before = $this->getAs($admin, '/hospital-manager/v1/stats', 200);

        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
            'date' => current_time('Y-m-d'),
        ]);
        $this->createTestLabInvestigation([
            'visitation_id' => $visitation->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
            'lab_tech_id' => $this->createUserWithRole('lab_tech'),
            'status' => 'in_progress',
        ]);

        // A completed lab must not count as pending
        $this->createTestLabInvestigation([
            'visitation_id' => $visitation->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
            'lab_tech_id' => $this->createUserWithRole('lab_tech'),
            'status' => 'completed',
        ]);

        $after = $this->getAs($admin, '/hospital-manager/v1/stats', 200);

        $this->assertSame(1, $after['todayVisitations'] - $before['todayVisitations']);
        $this->assertSame(1, $after['pendingLabTests'] - $before['pendingLabTests']);
    }

    public function test_logging_middleware_accepts_object_payloads()
    {
        $request = new \WP_REST_Request('GET', '/hospital-manager/v1/chats/1/messages');
        $response = new \WP_REST_Response((object) ['data' => [], 'meta' => ['total' => 0]]);

        // Used to fatal: "Cannot use object of type stdClass as array"
        $result = ApiLoggingMiddleware::logResponse($response, rest_get_server(), $request);

        $this->assertSame($response, $result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Create a record whose primary key differs from the given WP user ID, so a test
     * that confuses the two cannot pass by coincidence.
     */
    private function createRecordWithDivergentId(callable $factory, $user_id)
    {
        global $wpdb;
        $record = $factory();

        // Auto-increment values are never reused, so deleting the clashing row and
        // inserting again always yields a different ID. (No ALTER TABLE: DDL would
        // implicitly commit and break WP_UnitTestCase's per-test rollback.)
        for ($i = 0; $i < 3 && (int) $record->ID === (int) $user_id; $i++) {
            $wpdb->delete($record->getTable(), ['ID' => $record->ID]);
            $record = $factory();
        }

        $this->assertNotEquals((int) $user_id, (int) $record->ID, 'Could not create a record with a divergent ID');
        return $record;
    }

    private function insertAppointment($patient_id, $doctor_id, $date, $time, $status = 'confirmed')
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_appointments', [
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'status' => $status,
            'reason' => 'Relationship test',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        $this->assertNotEmpty($wpdb->insert_id, 'Failed to insert appointment: ' . $wpdb->last_error);
        return $wpdb->insert_id;
    }

    /**
     * Dispatch a GET as the given user and return the JSON-decoded body, so the
     * assertions see exactly what a browser would (models that serialise to "{}" fail).
     */
    private function getAs($user_id, $route, $expected_status)
    {
        wp_set_current_user($user_id);
        $response = rest_get_server()->dispatch(new \WP_REST_Request('GET', $route));
        $data = json_decode(wp_json_encode($response->get_data()), true);

        $this->assertSame(
            $expected_status,
            $response->get_status(),
            "Unexpected status for {$route}: " . wp_json_encode($data)
        );

        return $data;
    }

    private function toArray($model)
    {
        return method_exists($model, 'getAttributes') ? $model->getAttributes() : (array) $model;
    }
}

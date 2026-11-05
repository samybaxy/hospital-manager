<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\LabInvestigationController;
use HospitalManager\Controllers\Api\PatientController;
use HospitalManager\Controllers\Api\VisitationController;
use HospitalManager\Http\Routes\AccessRoutes;

/**
 * GET /access drives the frontend menus and buttons. Its patient, visit and lab
 * flags must agree with what the API actually allows (AccessPolicy), so nobody
 * sees a menu item or button that ends in a 403, or misses one they can use.
 */
class AccessFlagsTest extends TestCase
{
    const ROLES = ['administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff',
                   'lab_tech', 'pharmacy_staff', 'inventory_manager', 'patient'];

    private $users = [];
    private $own_patient;
    private $other_patient;
    private $other_visit;
    private $doctor;

    public function setUp(): void
    {
        parent::setUp();
        foreach (self::ROLES as $role) {
            $this->users[$role] = $this->createUserWithRole($role);
        }
        $this->doctor = $this->createTestDoctor(['user_id' => $this->users['doctor']])->ID;
        $this->own_patient = $this->createTestPatient(['user_id' => $this->users['patient']])->ID;
        $this->other_patient = $this->createTestPatient(['first_name' => 'Other'])->ID;
        $this->other_visit = $this->createTestVisitation(['patient_id' => $this->other_patient, 'doctor_id' => $this->doctor])->ID;

        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        (new AccessRoutes())->register();
        foreach ([LabInvestigationController::class, PatientController::class, VisitationController::class] as $controller) {
            (new $controller())->register_routes();
        }
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    private function dispatch($role, $method, $route, array $body = [])
    {
        wp_set_current_user($this->users[$role]);
        $request = new \WP_REST_Request($method, '/hospital-manager/v1' . $route);
        if ($body) {
            $request->set_body_params($body);
        }
        return rest_get_server()->dispatch($request);
    }

    private function flags($role)
    {
        $response = $this->dispatch($role, 'GET', '/access');
        $this->assertSame(200, $response->get_status(), "{$role}: " . wp_json_encode($response->get_data()));
        return $response->get_data()['data']['access'];
    }

    /** flag => a request the flag promises will (or won't) be allowed */
    private function probes()
    {
        // Fresh records per call: some probes delete what they touch.
        $this->other_patient = $this->createTestPatient(['first_name' => 'Other'])->ID;
        $this->other_visit = $this->createTestVisitation(['patient_id' => $this->other_patient, 'doctor_id' => $this->doctor])->ID;
        $lab = $this->createTestLabInvestigation(['patient_id' => $this->other_patient, 'doctor_id' => $this->doctor, 'visitation_id' => $this->other_visit])->ID;
        return [
            'patients'                   => ['GET', '/patients', []],
            'create_patients'            => ['POST', '/patients', ['first_name' => 'N', 'last_name' => 'P', 'phone' => '08011111111', 'gender' => 'Male', 'age' => 30]],
            'edit_patients'              => ['PUT', "/patients/{$this->other_patient}", ['first_name' => 'Other']],
            'delete_patients'            => ['DELETE', "/patients/{$this->other_patient}", []],
            'visitations'                => ['GET', '/visitations', []],
            'lab_dashboard'              => ['GET', '/lab-investigations', []],
            'edit_visitation'            => ['PUT', "/visitations/{$this->other_visit}", ['diagnosis' => 'Same']],
            'add_visitation'             => ['POST', '/visitations', ['patient_id' => $this->other_patient, 'doctor_id' => $this->doctor, 'date' => date('Y-m-d'), 'time' => '09:00:00']],
            'delete_visitation'          => ['DELETE', "/visitations/{$this->other_visit}", []],
            'request_lab_investigations' => ['POST', '/lab-investigations', [
                'visitation_id' => $this->other_visit, 'patient_id' => $this->other_patient, 'doctor_id' => $this->doctor,
                'lab_tech_id' => $this->users['lab_tech'], 'sample_type' => 'Blood']],
            'edit_lab_results'           => ['PUT', "/lab-investigations/{$lab}/status", ['status' => 'in_progress']],
        ];
    }

    public function test_access_loads_for_every_role()
    {
        // pharmacy_staff and inventory_manager used to get 403 (no "read" capability),
        // so their menus never loaded.
        foreach (self::ROLES as $role) {
            $this->assertIsArray($this->flags($role), $role);
        }
    }

    public function test_every_flag_matches_what_the_api_allows_for_every_role()
    {
        foreach (self::ROLES as $role) {
            $flags = $this->flags($role);
            foreach ($this->probes() as $flag => [$method, $route, $body]) {
                $this->assertArrayHasKey($flag, $flags, "{$role}: /access is missing '{$flag}'");
                $status = $this->dispatch($role, $method, $route, $body)->get_status();
                $allowed = !in_array($status, [401, 403], true);
                $this->assertSame(
                    $allowed,
                    (bool) $flags[$flag],
                    "{$role}: flag '{$flag}' is " . var_export((bool) $flags[$flag], true) . " but {$method} {$route} returned {$status}"
                );
            }
        }
    }

    public function test_patients_see_their_own_visits_and_labs_but_no_patient_list_or_actions()
    {
        $flags = $this->flags('patient');

        $this->assertTrue($flags['visitations']);
        $this->assertTrue($flags['lab_dashboard']);
        foreach (['patients', 'create_patients', 'edit_patients', 'delete_patients', 'add_visitation',
                  'edit_visitation', 'delete_visitation', 'request_lab_investigations', 'add_lab_results',
                  'edit_lab_results', 'delete_lab_results'] as $flag) {
            $this->assertFalse($flags[$flag], "patient should not get '{$flag}'");
        }
    }

    public function test_doctor_flags_say_own_visits_only_and_include_the_doctor_record_id()
    {
        $response = $this->dispatch('doctor', 'GET', '/access')->get_data()['data'];
        $this->assertTrue($response['access']['edit_visitation']);
        $this->assertTrue($response['access']['edit_own_visits_only']);
        $this->assertSame((int) $this->doctor, $response['doctor_id'], 'doctor record ID, not the WordPress user ID');

        $this->assertTrue($response['access']['add_visitation']);
        $this->assertTrue($response['access']['add_own_visits_only']);

        foreach (['administrator', 'hospital_nurse'] as $role) {
            $data = $this->dispatch($role, 'GET', '/access')->get_data()['data'];
            $this->assertTrue($data['access']['edit_visitation'], $role);
            $this->assertFalse($data['access']['edit_own_visits_only'], $role);
            $this->assertFalse($data['access']['add_own_visits_only'], $role);
        }
        $this->assertNull($this->dispatch('patient', 'GET', '/access')->get_data()['data']['doctor_id']);
    }

    public function test_frontend_checks_visit_ownership_by_doctor_record_id()
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/assets/js/src/services/UserAccessService.js');
        $this->assertStringContainsString("hasAccess('edit_own_visits_only')", $service);
        $this->assertStringContainsString('Number(visit.doctor_id) === Number(doctorId)', $service);
        $this->assertStringContainsString('doctorId: response.data.data.doctor_id', $service);

        $this->assertStringContainsString("hasAccess('add_own_visits_only') ? this.getDoctorId() : null", $service);

        $pages = dirname(__DIR__, 2) . '/assets/js/src/pages/visitations';
        $form = file_get_contents("{$pages}/VisitForm.jsx");
        $this->assertStringContainsString('getLockedVisitDoctorId()', $form, 'add-visit form locks the doctor');
        $this->assertStringContainsString('disabled={Boolean(lockedDoctorId)}', $form);

        foreach (['Visitations.jsx', 'EditVisit.jsx', 'ViewVisitDetails.jsx'] as $file) {
            $this->assertMatchesRegularExpression('/canEditVisit(Record)?\(visit(Data)?\)/', file_get_contents("{$pages}/{$file}"), $file);
        }
    }

    public function test_frontend_uses_the_flags_instead_of_hardcoded_roles()
    {
        $pages = dirname(__DIR__, 2) . '/assets/js/src/pages';
        $expect = [
            'visitations/Visitations.jsx'      => ["hasAccess('delete_visitation')", "hasAccess('request_lab_investigations')", "hasAccess('add_visitation')"],
            'visitations/EditVisit.jsx'        => [],
            'visitations/AddVisit.jsx'         => ["hasAccess('add_visitation')"],
            'visitations/ViewVisitDetails.jsx' => [],
            'patients/PatientDetails.jsx'      => ["hasAccess('edit_patients')", "hasAccess('delete_patients')"],
            'patients/Patients.jsx'            => ["hasAccess('edit_patients')", "hasAccess('add_visitation')"],
            'LabInvestigations.jsx'            => ["hasAccess('edit_lab_results')"],
        ];
        foreach ($expect as $file => $needles) {
            $src = file_get_contents("{$pages}/{$file}");
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $src, $file);
            }
            // The old checks compared visit.doctor_id (doctor record ID) with user.ID (WordPress user ID)
            $this->assertStringNotContainsString('doctor_id === user?.ID', $src, $file);
        }
        $this->assertStringNotContainsString("hasAccess('visitations', 'create')", file_get_contents("{$pages}/visitations/Visitations.jsx"));
    }
}

<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\HMOController;
use HospitalManager\Controllers\Api\LabInvestigationController;
use HospitalManager\Controllers\Api\PatientController;
use HospitalManager\Controllers\Api\VisitationController;
use HospitalManager\Services\AccessPolicy;

/**
 * Role-based access for endpoints that use BaseController::check_permission().
 *
 * Before AccessPolicy, the capabilities these endpoints check were granted to
 * no role, and a fallback let any hospital role through, so a patient could
 * list, read, edit and delete every patient record.
 */
class AccessPolicyTest extends TestCase
{
    /** @var array<string,int> role => user ID */
    private $users = [];
    private $own_patient;
    private $other_patient;
    private $own_visit;
    private $other_visit;
    private $doctor;

    public function setUp(): void
    {
        parent::setUp();

        foreach (['administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff',
                  'lab_tech', 'pharmacy_staff', 'inventory_manager', 'patient'] as $role) {
            $this->users[$role] = $this->createUserWithRole($role);
        }

        $this->doctor = $this->createTestDoctor(['user_id' => $this->users['doctor']])->ID;
        $this->own_patient = $this->createTestPatient(['user_id' => $this->users['patient'], 'first_name' => 'Own', 'last_name' => 'Patient'])->ID;
        $this->other_patient = $this->createTestPatient(['first_name' => 'Someone', 'last_name' => 'Else'])->ID;
        $this->own_visit = $this->createTestVisitation(['patient_id' => $this->own_patient, 'doctor_id' => $this->doctor, 'diagnosis' => 'Own diagnosis'])->ID;
        $this->other_visit = $this->createTestVisitation(['patient_id' => $this->other_patient, 'doctor_id' => $this->doctor, 'diagnosis' => 'Private diagnosis'])->ID;

        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        foreach ([HMOController::class, LabInvestigationController::class, PatientController::class, VisitationController::class] as $controller) {
            (new $controller())->register_routes();
        }
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    // ------------------------------------------------------------ helpers

    private function as($role, $method, $route, array $body = [])
    {
        wp_set_current_user($this->users[$role]);
        $request = new \WP_REST_Request($method, '/hospital-manager/v1' . $route);
        if ($body) {
            $request->set_body_params($body);
        }
        return rest_get_server()->dispatch($request);
    }

    private function assertAllowed($role, $method, $route, array $body = [])
    {
        $status = $this->as($role, $method, $route, $body)->get_status();
        $this->assertNotContains($status, [401, 403], "{$role} should be allowed {$method} {$route} (got {$status})");
    }

    private function assertDenied($role, $method, $route, array $body = [])
    {
        $status = $this->as($role, $method, $route, $body)->get_status();
        $this->assertSame(403, $status, "{$role} should be denied {$method} {$route}");
    }

    /** A complete lab request body (route args are validated before permissions). */
    private function labRequest($patient_id, $visit_id)
    {
        return [
            'visitation_id' => $visit_id, 'patient_id' => $patient_id, 'doctor_id' => $this->doctor,
            'lab_tech_id' => $this->users['lab_tech'], 'sample_type' => 'Blood', 'request_notes' => 'FBC',
        ];
    }

    private function json($response)
    {
        return wp_json_encode($response->get_data());
    }

    // ------------------------------------------------------------ patients

    public function test_patients_cannot_list_or_search_patients()
    {
        $this->assertDenied('patient', 'GET', '/patients');
        $this->assertDenied('patient', 'GET', '/patients/search', ['search' => 'Someone']);
        $this->assertDenied('patient', 'GET', '/hmos');
    }

    public function test_patients_can_read_only_their_own_record_and_visits()
    {
        $this->assertAllowed('patient', 'GET', "/patients/{$this->own_patient}");
        $this->assertAllowed('patient', 'GET', "/patients/{$this->own_patient}/visitations");
        $this->assertAllowed('patient', 'GET', "/visitations/{$this->own_visit}");

        $this->assertDenied('patient', 'GET', "/patients/{$this->other_patient}");
        $this->assertDenied('patient', 'GET', "/patients/{$this->other_patient}/visitations");
        $this->assertDenied('patient', 'GET', "/visitations/{$this->other_visit}");
    }

    public function test_patient_visit_list_contains_only_their_visits()
    {
        $response = $this->as('patient', 'GET', '/visitations');

        $this->assertSame(200, $response->get_status());
        $this->assertStringContainsString('Own diagnosis', $this->json($response));
        $this->assertStringNotContainsString('Private diagnosis', $this->json($response));
    }

    public function test_patient_login_without_a_patient_record_sees_nothing()
    {
        $this->users['patient'] = $this->createUserWithRole('patient'); // no hm_patients row

        // Refused outright: there is no record of theirs to show. (Without the
        // policy, the visit list crashed on a null patient record.)
        $response = $this->as('patient', 'GET', '/visitations');
        $this->assertSame(403, $response->get_status());
        $this->assertStringNotContainsString('diagnosis', strtolower($this->json($response)));
        $this->assertDenied('patient', 'GET', "/patients/{$this->own_patient}");
    }

    public function test_patients_cannot_write_patient_or_visit_records()
    {
        $this->assertDenied('patient', 'POST', '/patients', ['first_name' => 'X', 'last_name' => 'Y']);
        $this->assertDenied('patient', 'PUT', "/patients/{$this->own_patient}", ['first_name' => 'Changed']);
        $this->assertDenied('patient', 'DELETE', "/patients/{$this->other_patient}");
        $this->assertDenied('patient', 'POST', '/visitations', ['patient_id' => $this->own_patient]);
        $this->assertDenied('patient', 'PUT', "/visitations/{$this->own_visit}", ['diagnosis' => 'Changed']);
        $this->assertDenied('patient', 'DELETE', "/visitations/{$this->other_visit}");
        $this->assertDenied('patient', 'POST', '/lab-investigations', $this->labRequest($this->own_patient, $this->own_visit));

        global $wpdb;
        $this->assertSame('2', (string) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_patients"));
        $this->assertSame('Own diagnosis', $wpdb->get_var($wpdb->prepare("SELECT diagnosis FROM {$wpdb->prefix}hm_visitations WHERE ID = %d", $this->own_visit)));
    }

    // ------------------------------------------------------------ staff roles

    public function test_who_can_look_patients_up()
    {
        foreach (['administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff', 'lab_tech', 'pharmacy_staff'] as $role) {
            $this->assertAllowed($role, 'GET', '/patients');
            $this->assertAllowed($role, 'GET', "/patients/{$this->other_patient}");
        }
        $this->assertDenied('inventory_manager', 'GET', '/patients');
        $this->assertDenied('inventory_manager', 'GET', "/patients/{$this->other_patient}");
    }

    public function test_who_can_create_edit_and_delete_patients()
    {
        $new = ['first_name' => 'New', 'last_name' => 'Patient', 'phone' => '08055555555', 'gender' => 'Female', 'age' => 40];

        foreach (['administrator', 'developer', 'doctor', 'hospital_staff'] as $role) {
            $this->assertAllowed($role, 'POST', '/patients', $new);
        }
        foreach (['hospital_nurse', 'lab_tech', 'pharmacy_staff', 'inventory_manager'] as $role) {
            $this->assertDenied($role, 'POST', '/patients', $new);
        }

        foreach (['administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff'] as $role) {
            $this->assertAllowed($role, 'PUT', "/patients/{$this->other_patient}", ['first_name' => 'Someone']);
        }
        foreach (['lab_tech', 'pharmacy_staff', 'inventory_manager'] as $role) {
            $this->assertDenied($role, 'PUT', "/patients/{$this->other_patient}", ['first_name' => 'Changed']);
        }

        foreach (['developer', 'doctor', 'hospital_nurse', 'hospital_staff', 'lab_tech', 'pharmacy_staff', 'inventory_manager'] as $role) {
            $this->assertDenied($role, 'DELETE', "/patients/{$this->other_patient}");
        }
        $this->assertAllowed('administrator', 'DELETE', "/patients/{$this->other_patient}");
    }

    public function test_who_can_read_and_write_visits()
    {
        foreach (['administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff', 'lab_tech'] as $role) {
            $this->assertAllowed($role, 'GET', '/visitations');
            $this->assertAllowed($role, 'GET', "/visitations/{$this->other_visit}");
        }
        foreach (['pharmacy_staff', 'inventory_manager'] as $role) {
            $this->assertDenied($role, 'GET', '/visitations');
            $this->assertDenied($role, 'GET', "/visitations/{$this->other_visit}");
        }

        foreach (['administrator', 'doctor', 'hospital_nurse'] as $role) {
            $this->assertAllowed($role, 'PUT', "/visitations/{$this->other_visit}", ['diagnosis' => 'Private diagnosis']);
        }
        foreach (['developer', 'hospital_staff', 'lab_tech', 'pharmacy_staff', 'inventory_manager'] as $role) {
            $this->assertDenied($role, 'PUT', "/visitations/{$this->other_visit}", ['diagnosis' => 'Changed']);
        }

        foreach (['developer', 'doctor', 'hospital_nurse', 'hospital_staff', 'lab_tech'] as $role) {
            $this->assertDenied($role, 'DELETE', "/visitations/{$this->other_visit}");
        }
        $this->assertAllowed('administrator', 'DELETE', "/visitations/{$this->other_visit}");
    }

    public function test_who_can_request_labs_and_enter_results()
    {
        $lab = $this->createTestLabInvestigation(['patient_id' => $this->other_patient, 'doctor_id' => $this->doctor, 'visitation_id' => $this->other_visit])->ID;

        foreach (['administrator', 'doctor'] as $role) {
            $this->assertAllowed($role, 'POST', '/lab-investigations', $this->labRequest($this->other_patient, $this->other_visit));
        }
        foreach (['lab_tech', 'hospital_nurse', 'hospital_staff', 'developer', 'pharmacy_staff', 'inventory_manager'] as $role) {
            $this->assertDenied($role, 'POST', '/lab-investigations', $this->labRequest($this->other_patient, $this->other_visit));
            $this->assertDenied($role, 'DELETE', "/lab-investigations/{$lab}");
        }

        foreach (['administrator', 'doctor', 'lab_tech'] as $role) {
            $this->assertAllowed($role, 'PUT', "/lab-investigations/{$lab}/status", ['status' => 'in_progress']);
        }
        foreach (['hospital_nurse', 'hospital_staff', 'developer', 'pharmacy_staff', 'inventory_manager'] as $role) {
            $this->assertDenied($role, 'PUT', "/lab-investigations/{$lab}/status", ['status' => 'completed']);
        }
    }

    public function test_doctors_edit_only_their_own_visits()
    {
        global $wpdb;
        $visits = $wpdb->prefix . 'hm_visitations';

        // Another doctor's visit
        $colleague = $this->createTestDoctor(['first_name' => 'Colleague'])->ID;
        $theirs = $this->createTestVisitation(['patient_id' => $this->other_patient, 'doctor_id' => $colleague, 'diagnosis' => 'Colleague diagnosis'])->ID;

        // Trap: a doctor record whose ID equals our doctor's WordPress *user* ID.
        // Ownership must use the doctor record ID, never the user ID.
        $trap_id = $this->users['doctor'];
        if (!$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}hm_doctors WHERE ID = %d", $trap_id))) {
            $this->createTestDoctor(['ID' => $trap_id, 'first_name' => 'Trap']);
        }
        $trap_visit = $this->createTestVisitation(['patient_id' => $this->other_patient, 'doctor_id' => $trap_id, 'diagnosis' => 'Trap diagnosis'])->ID;
        $this->assertNotEquals($trap_id, $this->doctor, 'fixture: user ID and record ID must differ');

        // The update endpoint needs the whole visit
        $full = function ($diagnosis, $doctor_id = null) {
            return ['patient_id' => $this->other_patient, 'doctor_id' => $doctor_id ?? $this->doctor,
                    'date' => date('Y-m-d'), 'time' => '10:00:00', 'diagnosis' => $diagnosis];
        };

        // Their own visit: allowed, and the change is saved
        $response = $this->as('doctor', 'PUT', "/visitations/{$this->other_visit}", $full('Updated by own doctor'));
        $this->assertSame(200, $response->get_status(), $this->json($response));
        $this->assertSame('Updated by own doctor', $wpdb->get_var($wpdb->prepare("SELECT diagnosis FROM {$visits} WHERE ID = %d", $this->other_visit)));

        // Someone else's visits: refused and unchanged
        foreach ([$theirs => 'Colleague diagnosis', $trap_visit => 'Trap diagnosis'] as $visit_id => $diagnosis) {
            $this->assertDenied('doctor', 'PUT', "/visitations/{$visit_id}", $full('Changed'));
            $this->assertSame($diagnosis, $wpdb->get_var($wpdb->prepare("SELECT diagnosis FROM {$visits} WHERE ID = %d", $visit_id)));
        }

        // Can't hand their own visit to another doctor; keeping themselves is fine
        $this->assertDenied('doctor', 'PUT', "/visitations/{$this->other_visit}", $full('Handed over', $colleague));
        $this->assertEquals($this->doctor, $wpdb->get_var($wpdb->prepare("SELECT doctor_id FROM {$visits} WHERE ID = %d", $this->other_visit)));
        $this->assertSame(200, $this->as('doctor', 'PUT', "/visitations/{$this->other_visit}", $full('Kept'))->get_status());

        // A doctor login without a doctor record edits nothing
        $this->users['doctor'] = $this->createUserWithRole('doctor');
        $this->assertDenied('doctor', 'PUT', "/visitations/{$this->other_visit}", ['diagnosis' => 'Changed']);
    }

    /**
     * The live site's roles store capabilities too (RoleService gives the doctor
     * role edit_visitation). Those must not override the map, or doctors could
     * edit, and reassign, any visit.
     */
    public function test_capabilities_stored_on_a_role_do_not_override_the_map()
    {
        $colleague = $this->createTestDoctor(['first_name' => 'Colleague'])->ID;
        $theirs = $this->createTestVisitation(['patient_id' => $this->other_patient, 'doctor_id' => $colleague])->ID;

        get_role('doctor')->add_cap('edit_visitation');
        get_role('patient')->add_cap('view_patients');
        try {
            $this->users['doctor'] = $this->users['doctor']; // same user, role now has the cap
            wp_set_current_user(0);
            $body = ['patient_id' => $this->other_patient, 'doctor_id' => $colleague, 'date' => date('Y-m-d'), 'time' => '10:00:00', 'diagnosis' => 'Changed'];
            $this->assertDenied('doctor', 'PUT', "/visitations/{$theirs}", $body);
            $this->assertDenied('patient', 'GET', '/patients');
        } finally {
            get_role('doctor')->remove_cap('edit_visitation');
            get_role('patient')->remove_cap('view_patients');
        }
    }

    public function test_doctors_add_visits_only_under_themselves()
    {
        global $wpdb;
        $visits = $wpdb->prefix . 'hm_visitations';
        $colleague = $this->createTestDoctor(['first_name' => 'Colleague'])->ID;
        $new = function ($patient_id, $doctor_id = null) {
            $body = ['patient_id' => $patient_id, 'date' => date('Y-m-d'), 'time' => '11:00:00', 'diagnosis' => 'New visit'];
            if ($doctor_id !== null) {
                $body['doctor_id'] = $doctor_id;
            }
            return $body;
        };
        $count = function () use ($wpdb, $visits) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$visits}");
        };

        // Under themselves: explicit, or filled in when omitted (record ID, not user ID)
        foreach ([$this->doctor, null] as $doctor_id) {
            $response = $this->as('doctor', 'POST', '/visitations', $new($this->other_patient, $doctor_id));
            $this->assertSame(201, $response->get_status(), $this->json($response));
            $saved = $wpdb->get_var("SELECT doctor_id FROM {$visits} ORDER BY ID DESC LIMIT 1");
            $this->assertEquals($this->doctor, $saved);
        }

        // Under a colleague: refused, nothing created
        $before = $count();
        $this->assertDenied('doctor', 'POST', '/visitations', $new($this->other_patient, $colleague));
        $this->assertSame($before, $count());

        // A doctor login without a doctor record can't add visits
        $doctor_user = $this->users['doctor'];
        $this->users['doctor'] = $this->createUserWithRole('doctor');
        $this->assertDenied('doctor', 'POST', '/visitations', $new($this->other_patient));
        $this->users['doctor'] = $doctor_user;

        // A role-stored capability doesn't lift the lock (the live doctor role has one)
        get_role('doctor')->add_cap('add_visitation');
        try {
            $this->assertDenied('doctor', 'POST', '/visitations', $new($this->other_patient, $colleague));
        } finally {
            get_role('doctor')->remove_cap('add_visitation');
        }

        // Nurses and administrators still choose the doctor
        foreach (['hospital_nurse', 'administrator'] as $role) {
            $response = $this->as($role, 'POST', '/visitations', $new($this->other_patient, $colleague));
            $this->assertSame(201, $response->get_status(), $role . ': ' . $this->json($response));
            $this->assertEquals($colleague, $wpdb->get_var("SELECT doctor_id FROM {$visits} ORDER BY ID DESC LIMIT 1"));
        }
    }

    public function test_doctors_still_read_and_add_to_every_patients_history()
    {
        // A colleague's patient and visit
        $colleague = $this->createTestDoctor(['first_name' => 'Colleague'])->ID;
        $patient = $this->createTestPatient(['first_name' => 'Shared'])->ID;
        $visit = $this->createTestVisitation(['patient_id' => $patient, 'doctor_id' => $colleague, 'diagnosis' => 'Colleague note'])->ID;

        $this->assertAllowed('doctor', 'GET', "/patients/{$patient}");
        $this->assertStringContainsString('Colleague note', $this->json($this->as('doctor', 'GET', "/visitations/{$visit}")));
        $this->assertAllowed('doctor', 'GET', "/patients/{$patient}/visitations");

        $body = ['patient_id' => $patient, 'date' => date('Y-m-d'), 'time' => '12:00:00', 'diagnosis' => 'Second opinion'];
        $this->assertSame(201, $this->as('doctor', 'POST', '/visitations', $body)->get_status());

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT doctor_id, diagnosis FROM {$wpdb->prefix}hm_visitations WHERE patient_id = %d ORDER BY ID", $patient), ARRAY_A);
        $this->assertSame([[(string) $colleague, 'Colleague note'], [(string) $this->doctor, 'Second opinion']], array_map('array_values', $rows));
    }

    public function test_nurses_and_administrators_still_edit_any_visit()
    {
        $colleague = $this->createTestDoctor(['first_name' => 'Colleague'])->ID;
        $theirs = $this->createTestVisitation(['patient_id' => $this->other_patient, 'doctor_id' => $colleague])->ID;

        $this->assertAllowed('hospital_nurse', 'PUT', "/visitations/{$theirs}", ['diagnosis' => 'Nurse note']);
        $this->assertAllowed('administrator', 'PUT', "/visitations/{$theirs}", ['diagnosis' => 'Admin note']);

        // A doctor who is also a nurse edits as a nurse
        $user = get_user_by('id', $this->users['doctor']);
        $user->add_role('hospital_nurse');
        $this->assertAllowed('doctor', 'PUT', "/visitations/{$theirs}", ['diagnosis' => 'Doctor-nurse note']);
    }

    // ------------------------------------------------------------ policy rules

    public function test_an_explicit_capability_grant_still_works()
    {
        // A site admin can grant one user extra access without code changes.
        $user = get_user_by('id', $this->users['inventory_manager']);
        $user->add_cap('view_patients');

        $this->assertAllowed('inventory_manager', 'GET', '/patients');
        $this->assertDenied('inventory_manager', 'GET', '/visitations');
    }

    public function test_every_capability_used_by_an_endpoint_is_in_the_map()
    {
        // An unmapped capability would silently lock everyone but explicit grants out.
        $used = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app'));
        foreach ($files as $file) {
            if (substr($file->getFilename(), -4) !== '.php') {
                continue;
            }
            preg_match_all("/check_permission\(\\\$request,\s*'([a-z_]+)'\)/", file_get_contents($file->getPathname()), $m);
            $used = array_merge($used, $m[1]);
        }
        $this->assertNotEmpty($used);

        foreach (array_unique($used) as $capability) {
            $this->assertArrayHasKey($capability, AccessPolicy::MAP, "'{$capability}' is checked by an endpoint but missing from AccessPolicy::MAP");
            $this->assertContains('administrator', AccessPolicy::rolesFor($capability), "administrators should be allowed '{$capability}'");
        }
    }

    public function test_patients_get_own_record_access_only_for_read_capabilities()
    {
        foreach (array_keys(AccessPolicy::MAP) as $capability) {
            $expected = in_array($capability, ['view_patients', 'view_patient_records'], true);
            $this->assertSame($expected, AccessPolicy::patientsOwnRecords($capability), $capability);
            $this->assertNotContains('patient', AccessPolicy::rolesFor($capability), "patients must never get blanket '{$capability}'");
        }
    }
}

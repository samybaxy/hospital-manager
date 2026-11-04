<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\AppointmentController;
use HospitalManager\Controllers\Api\DoctorController;
use HospitalManager\Controllers\Api\InventoryReorderController;
use HospitalManager\Controllers\Api\VisitationController;
use HospitalManager\Models\InventoryReorder;

/**
 * Regression tests (real controllers) for:
 *   1. InventoryReorder declared twice (InventoryReorder.php + InventorySupplier.php)
 *      with different methods — load-order dependent crashes / "Cannot declare class".
 *   2. POST /inventory/reorders inserting non-existent columns (item_id, quantity,
 *      requested_by).
 *   3. PUT/DELETE /visitations/{id} calling Visitation::updateById()/deleteById(),
 *      which didn't exist.
 *   4. POST /doctors always failing: Doctor::save() only handled updates; doctors
 *      were never linked to a login.
 *   5. Appointment booking notifications written with non-existent columns, so
 *      doctors never received them.
 */
class EndpointBugFixesTest extends TestCase
{
    private $admin;
    private $doctor_user;
    private $doctor;
    private $patient;

    const CAPS = ['manage_inventory', 'view_inventory', 'add_visitation', 'edit_visitation', 'delete_visitation', 'manage_patient_records', 'view_patients'];

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUserWithRole('administrator');
        foreach (self::CAPS as $cap) {
            get_role('administrator')->add_cap($cap);
        }
        $this->doctor_user = $this->createUserWithRole('doctor');
        $this->doctor = $this->createTestDoctor(['user_id' => $this->doctor_user, 'first_name' => 'Ada', 'last_name' => 'Okafor']);
        $this->patient = $this->createTestPatient(['user_id' => $this->createUserWithRole('patient'), 'first_name' => 'Ngozi', 'last_name' => 'Eze']);

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        foreach ([AppointmentController::class, DoctorController::class, InventoryReorderController::class, VisitationController::class] as $controller) {
            (new $controller())->register_routes();
        }
    }

    public function tearDown(): void
    {
        foreach (self::CAPS as $cap) {
            get_role('administrator')->remove_cap($cap);
        }
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    // ------------------------------------------------------------ 1. InventoryReorder

    public function test_inventory_reorder_is_declared_once_with_all_methods()
    {
        $models = dirname(__DIR__, 2) . '/app/Models/';
        // Loading both files in one request used to be a fatal "Cannot declare class"
        require_once $models . 'InventoryReorder.php';
        require_once $models . 'InventorySupplier.php';

        $declared = 0;
        foreach (glob($models . '*.php') as $file) {
            $declared += preg_match_all('/^\s*class\s+InventoryReorder\b/m', file_get_contents($file));
        }
        $this->assertSame(1, $declared, 'InventoryReorder must be declared exactly once');

        foreach (['getFiltered', 'getFilteredCount', 'create', 'approve', 'getPending', 'generateSuggestions'] as $method) {
            $this->assertTrue(method_exists(InventoryReorder::class, $method), "InventoryReorder::{$method}()");
        }
        global $wpdb;
        $this->assertSame($wpdb->prefix . 'hm_inventory_reorders', (new InventoryReorder())->getTable());
    }

    // ------------------------------------------------------------ 2. reorder create

    public function test_reorder_is_created_with_the_real_columns()
    {
        $item = $this->insertItem(['quantity' => 3, 'reorder_level' => 10, 'cost' => '12.50']);

        $response = $this->request('POST', '/inventory/reorders', ['item_id' => $item, 'supplier_id' => $this->insertSupplier(), 'quantity' => 20, 'priority' => 'high', 'notes' => 'Urgent']);

        $this->assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));
        $row = $this->latest('hm_inventory_reorders');
        $this->assertEquals($item, $row['inventory_id']);
        $this->assertEquals(20, $row['suggested_quantity']);
        $this->assertEquals(3, $row['current_quantity']);
        $this->assertEquals(10, $row['reorder_level']);
        $this->assertEquals('250.00', $row['estimated_cost']);
        $this->assertSame('high', $row['priority']);
        $this->assertSame('pending', $row['status']);
        $this->assertEquals($this->admin, $row['created_by']);
    }

    public function test_reorder_for_unknown_item_is_a_404()
    {
        $response = $this->request('POST', '/inventory/reorders', ['item_id' => 999999, 'supplier_id' => $this->insertSupplier(), 'quantity' => 5, 'priority' => 'low']);

        $this->assertSame(404, $response->get_status());
    }

    public function test_reorder_list_works()
    {
        $item = $this->insertItem();
        $created = $this->request('POST', '/inventory/reorders', ['item_id' => $item, 'supplier_id' => $this->insertSupplier(), 'quantity' => 5, 'priority' => 'low']);
        $this->assertSame(201, $created->get_status(), wp_json_encode($created->get_data()));

        $response = $this->request('GET', '/inventory/reorders');

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $data = json_decode(wp_json_encode($response->get_data()), true);
        $this->assertNotEmpty($data['data']['data']);
    }

    // ------------------------------------------------------------ 3. visitations

    public function test_visitation_update_persists()
    {
        $visit = $this->createTestVisitation(['patient_id' => $this->patient->ID, 'doctor_id' => $this->doctor->ID, 'diagnosis' => 'Initial']);

        $response = $this->request('PUT', "/visitations/{$visit->ID}", [
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
            'date' => current_time('Y-m-d'),
            'time' => '11:15:00',
            'diagnosis' => 'Malaria (uncomplicated)',
            'treatment' => 'Artemether-Lumefantrine',
        ]);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $row = $this->row('hm_visitations', $visit->ID);
        $this->assertSame('Malaria (uncomplicated)', $row['diagnosis']);
        $this->assertSame('Artemether-Lumefantrine', $row['treatment']);
        $this->assertSame('11:15:00', $row['time']);
        $data = json_decode(wp_json_encode($response->get_data()), true);
        $this->assertSame('Malaria (uncomplicated)', $data['data']['diagnosis']);
    }

    public function test_visitation_delete_removes_the_record()
    {
        $visit = $this->createTestVisitation(['patient_id' => $this->patient->ID, 'doctor_id' => $this->doctor->ID]);

        $response = $this->request('DELETE', "/visitations/{$visit->ID}");

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertNull($this->row('hm_visitations', $visit->ID));
    }

    public function test_missing_visitation_update_and_delete_are_404()
    {
        $body = ['patient_id' => $this->patient->ID, 'doctor_id' => $this->doctor->ID, 'date' => current_time('Y-m-d'), 'time' => '09:00:00'];

        $this->assertSame(404, $this->request('PUT', '/visitations/999999', $body)->get_status());
        $this->assertSame(404, $this->request('DELETE', '/visitations/999999')->get_status());
    }

    // ------------------------------------------------------------ 4. doctors

    public function test_doctor_without_email_is_created_without_a_login()
    {
        $users_before = count_users()['total_users'];

        $response = $this->request('POST', '/doctors', $this->doctorBody());

        $this->assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));
        $row = $this->latest('hm_doctors');
        $this->assertSame('Chidi', $row['first_name']);
        $this->assertSame('08055550000', $row['phone']);
        $this->assertSame('0', (string) $row['user_id']);
        $this->assertSame($users_before, count_users()['total_users']);
    }

    public function test_doctor_with_email_gets_a_linked_doctor_login()
    {
        $response = $this->request('POST', '/doctors', $this->doctorBody(['email' => 'chidi.okeke@example.com']));

        $this->assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));
        $user = get_user_by('email', 'chidi.okeke@example.com');
        $this->assertNotFalse($user);
        $this->assertContains('doctor', $user->roles);
        $this->assertSame('Dr. Chidi Okeke', $user->display_name);
        $this->assertEquals($user->ID, $this->latest('hm_doctors')['user_id']);
    }

    public function test_new_doctor_login_is_emailed_a_set_password_link()
    {
        reset_phpmailer_instance();

        $response = $this->request('POST', '/doctors', $this->doctorBody(['email' => 'ada.nwosu@example.com']));

        $this->assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));
        $mailer = tests_retrieve_phpmailer_instance();
        $recipients = array_map(function ($sent) { return $sent['to'][0][0] ?? ''; }, $mailer->mock_sent);
        $this->assertContains('ada.nwosu@example.com', $recipients, 'The new doctor should be emailed a set-password link');
    }

    public function test_doctor_with_an_existing_email_is_rejected()
    {
        $this->factory->user->create(['user_email' => 'taken.doctor@example.com']);
        $before = $this->countRows('hm_doctors');

        $response = $this->request('POST', '/doctors', $this->doctorBody(['email' => 'taken.doctor@example.com']));

        $this->assertSame(400, $response->get_status());
        $this->assertSame($before, $this->countRows('hm_doctors'));
    }

    // ------------------------------------------------------------ 5. appointment notification

    public function test_booking_notifies_the_doctor()
    {
        // Decoy: a different patient whose *record* ID equals the booking patient's
        // WP user ID. Passing the user ID as the patient ID named this person instead.
        global $wpdb;
        $decoy_id = (int) $this->patient->user_id;
        if (!$this->row('hm_patients', $decoy_id)) {
            $decoy = $this->createTestPatient(['user_id' => $this->createUserWithRole('patient'), 'first_name' => 'Wrong', 'last_name' => 'Person']);
            $wpdb->update($wpdb->prefix . 'hm_patients', ['ID' => $decoy_id], ['ID' => $decoy->ID]);
        }

        $response = $this->request('POST', '/appointments', [
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
            'date' => date('Y-m-d', strtotime('+4 days')),
            'time' => '10:30:00',
            'reason' => 'Review',
        ]);

        $this->assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));
        $appointment = $this->latest('hm_appointments');
        $note = $this->latest('hm_notifications');
        $this->assertNotNull($note, 'Doctor should receive a notification');
        $this->assertEquals($this->doctor_user, $note['user_id']);
        $this->assertSame('appointment', $note['type']);
        $this->assertEquals($appointment['ID'], $note['reference_id']);
        $this->assertStringContainsString('Ngozi Eze', $note['message']);
        $this->assertStringNotContainsString('Wrong Person', $note['message']);
    }

    // ------------------------------------------------------------ helpers

    private function doctorBody(array $overrides = [])
    {
        return array_merge([
            'first_name' => 'Chidi',
            'last_name' => 'Okeke',
            'phone' => '08055550000',
            'specialty' => 'Cardiology',
            'license_number' => 'MD654321',
        ], $overrides);
    }

    private function request($method, $route, ?array $body = null)
    {
        wp_set_current_user($this->admin);
        $request = new \WP_REST_Request($method, '/hospital-manager/v1' . $route);
        if ($body !== null) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(wp_json_encode($body));
        }
        return rest_get_server()->dispatch($request);
    }

    private function insertItem(array $overrides = [])
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_inventory', array_merge([
            'item_name' => 'Paracetamol', 'category' => 'Medication', 'quantity' => 5,
            'unit' => 'packs', 'reorder_level' => 10, 'status' => 'In Stock',
        ], $overrides));
        return (int) $wpdb->insert_id;
    }

    private function insertSupplier()
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_inventory_suppliers', ['name' => 'MediPharma ' . wp_generate_password(4, false), 'is_active' => 1]);
        return (int) $wpdb->insert_id;
    }

    private function row($table, $id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$table} WHERE ID = %d", $id), ARRAY_A);
    }

    private function latest($table)
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}{$table} ORDER BY ID DESC LIMIT 1", ARRAY_A);
    }

    private function countRows($table)
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table}");
    }
}

<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\AppointmentController;
use HospitalManager\Controllers\Api\AuthController;
use HospitalManager\Controllers\Api\DashboardController;
use HospitalManager\Controllers\Api\DoctorController;
use HospitalManager\Controllers\Api\InventoryAlertController;
use HospitalManager\Controllers\Api\InventoryController;
use HospitalManager\Controllers\Api\InventoryReorderController;
use HospitalManager\Controllers\Api\InventoryTransactionController;
use HospitalManager\Controllers\Api\NotificationController;
use HospitalManager\Http\Routes\AccessRoutes;
use HospitalManager\Services\ApiService;
use HospitalManager\Services\AppointmentService;
use HospitalManager\Services\BaseService;

/**
 * Regression tests for the stability audit fixes:
 *  - appointments: role and ownership checks, input validation
 *  - service caches: per-user keys, invalidated by any write
 *  - doctors: directory needs login, patient lists for staff, admin-only create/delete
 *  - dashboard: stats for staff only, /dashboard for every role
 *  - inventory: role capabilities, reorder/alert state checks, stock checks, bulk
 *  - notifications: mark all read
 *  - debug output, CORS, plugin lifecycle (deactivate/uninstall/schema), CLI-only scripts
 */
class StabilityFixesTest extends TestCase
{
    private $admin;
    private $nurse;
    private $lab_tech;
    private $inventory_manager;
    private $doctor_user;
    private $doctor;
    private $other_doctor_user;
    private $other_doctor;
    private $patient_user;
    private $patient;
    private $other_patient_user;
    private $other_patient;

    /** Inventory caps the live roles hold (RoleService::getRolePermissions) */
    const INVENTORY_CAPS = ['inventory_view', 'inventory_create', 'inventory_edit', 'inventory_delete', 'inventory_bulk_operations'];

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUserWithRole('administrator');
        $this->nurse = $this->createUserWithRole('hospital_nurse');
        $this->lab_tech = $this->createUserWithRole('lab_tech');
        if (!get_role('inventory_manager')) {
            add_role('inventory_manager', 'Inventory Manager', ['read' => true]);
        }
        $this->inventory_manager = $this->createUserWithRole('inventory_manager');
        foreach (self::INVENTORY_CAPS as $cap) {
            get_role('inventory_manager')->add_cap($cap);
        }

        $this->doctor_user = $this->createUserWithRole('doctor');
        $this->doctor = $this->createTestDoctor(['user_id' => $this->doctor_user, 'first_name' => 'Ada', 'last_name' => 'Okafor', 'status' => 'active']);
        $this->other_doctor_user = $this->createUserWithRole('doctor');
        $this->other_doctor = $this->createTestDoctor(['user_id' => $this->other_doctor_user, 'first_name' => 'Emeka', 'last_name' => 'Obi', 'status' => 'active']);

        $this->patient_user = $this->createUserWithRole('patient');
        $this->patient = $this->createTestPatient(['user_id' => $this->patient_user, 'first_name' => 'Ngozi', 'last_name' => 'Eze']);
        $this->other_patient_user = $this->createUserWithRole('patient');
        $this->other_patient = $this->createTestPatient(['user_id' => $this->other_patient_user, 'first_name' => 'Chidi', 'last_name' => 'Nwosu', 'phone' => '08099999999']);

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        foreach ([AppointmentController::class, AuthController::class, DashboardController::class, DoctorController::class,
                  InventoryController::class, InventoryAlertController::class, InventoryReorderController::class,
                  InventoryTransactionController::class, NotificationController::class] as $controller) {
            (new $controller())->register_routes();
        }
        (new AccessRoutes())->register();
    }

    public function tearDown(): void
    {
        foreach (self::INVENTORY_CAPS as $cap) {
            if (get_role('inventory_manager')) {
                get_role('inventory_manager')->remove_cap($cap);
            }
        }
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    // ================================================================ appointments

    public function test_patient_cannot_read_another_patients_appointment()
    {
        $theirs = $this->createTestAppointment($this->other_patient->ID, $this->doctor->ID);
        $mine = $this->createTestAppointment($this->patient->ID, $this->doctor->ID, ['appointment_time' => '11:00:00']);

        $this->assertSame(403, $this->as($this->patient_user, 'GET', "/appointments/{$theirs->ID}")->get_status());
        $this->assertSame(200, $this->as($this->patient_user, 'GET', "/appointments/{$mine->ID}")->get_status());
    }

    public function test_patient_booking_is_always_for_themselves()
    {
        $response = $this->as($this->patient_user, 'POST', '/appointments', [
            'patient_id' => $this->other_patient->ID, // ignored for patients
            'doctor_id' => $this->doctor->ID,
            'appointment_date' => date('Y-m-d', strtotime('+3 days')),
            'appointment_time' => '09:00',
            'reason' => 'Check-up',
        ]);

        $this->assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertEquals($this->patient->ID, $response->get_data()['data']['patient_id']);
    }

    public function test_patient_may_only_cancel_their_own_appointment()
    {
        $mine = $this->createTestAppointment($this->patient->ID, $this->doctor->ID);
        $theirs = $this->createTestAppointment($this->other_patient->ID, $this->doctor->ID, ['appointment_time' => '12:00:00']);

        $this->assertSame(403, $this->as($this->patient_user, 'PUT', "/appointments/{$mine->ID}", ['status' => 'confirmed'])->get_status());
        $this->assertSame(403, $this->as($this->patient_user, 'PUT', "/appointments/{$theirs->ID}", ['status' => 'cancelled'])->get_status());
        $this->assertSame(200, $this->as($this->patient_user, 'PUT', "/appointments/{$mine->ID}", ['status' => 'cancelled'])->get_status());
        $this->assertSame('cancelled', $this->row('hm_appointments', $mine->ID)['status']);
    }

    public function test_doctor_cannot_update_another_doctors_appointment()
    {
        $theirs = $this->createTestAppointment($this->patient->ID, $this->other_doctor->ID);
        $mine = $this->createTestAppointment($this->patient->ID, $this->doctor->ID, ['appointment_time' => '13:00:00']);

        $this->assertSame(403, $this->as($this->doctor_user, 'PUT', "/appointments/{$theirs->ID}", ['status' => 'confirmed'])->get_status());
        $this->assertSame(200, $this->as($this->doctor_user, 'PUT', "/appointments/{$mine->ID}", ['status' => 'confirmed'])->get_status());
    }

    public function test_doctor_lists_only_their_own_appointments_whatever_doctor_id_says()
    {
        $this->createTestAppointment($this->patient->ID, $this->doctor->ID);
        $this->createTestAppointment($this->patient->ID, $this->other_doctor->ID, ['appointment_time' => '14:00:00']);

        $response = $this->as($this->doctor_user, 'GET', '/appointments', null, ['doctor_id' => $this->other_doctor->ID]);

        $this->assertSame(200, $response->get_status());
        $doctor_ids = array_unique(array_map(fn($a) => (int) ((array) $a)['doctor_id'], $this->appointmentItems($response)));
        $this->assertSame([(int) $this->doctor->ID], array_values($doctor_ids));
    }

    public function test_roles_without_appointment_access_are_refused()
    {
        $this->assertSame(403, $this->as($this->lab_tech, 'GET', '/appointments')->get_status());
        $this->assertSame(403, $this->as($this->patient_user, 'GET', '/appointments/stats', null, ['doctor_id' => $this->doctor->ID])->get_status());
        $this->assertSame(401, $this->as(0, 'GET', '/appointments')->get_status());
    }

    public function test_appointment_status_is_validated()
    {
        $appointment = $this->createTestAppointment($this->patient->ID, $this->doctor->ID);

        $response = $this->as($this->nurse, 'PUT', "/appointments/{$appointment->ID}", ['status' => 'whatever']);

        $this->assertSame(400, $response->get_status());
    }

    public function test_booking_validates_doctor_date_and_time()
    {
        $base = ['patient_id' => $this->patient->ID, 'doctor_id' => $this->doctor->ID,
                 'appointment_date' => date('Y-m-d', strtotime('+4 days')), 'appointment_time' => '10:30'];

        $this->assertSame(400, $this->as($this->nurse, 'POST', '/appointments', array_merge($base, ['doctor_id' => 999999]))->get_status());
        $this->assertSame(400, $this->as($this->nurse, 'POST', '/appointments', array_merge($base, ['appointment_date' => '04/11/2030']))->get_status());
        $this->assertSame(400, $this->as($this->nurse, 'POST', '/appointments', array_merge($base, ['appointment_time' => '25:00']))->get_status());
        $this->assertSame(201, $this->as($this->nurse, 'POST', '/appointments', $base)->get_status());
    }

    public function test_a_just_booked_slot_is_not_offered_again()
    {
        $date = date('Y-m-d', strtotime('+5 days'));
        $this->assertTrue(AppointmentService::isSlotAvailable($this->doctor->ID, $date, '10:00'));

        $this->createTestAppointment($this->patient->ID, $this->doctor->ID, ['appointment_date' => $date, 'appointment_time' => '10:00:00', 'status' => 'pending']);

        $this->assertFalse(AppointmentService::isSlotAvailable($this->doctor->ID, $date, '10:00'));
    }

    // ================================================================ service caches

    public function test_cached_lists_are_not_shared_between_users()
    {
        $this->createTestAppointment($this->other_patient->ID, $this->doctor->ID);

        // The admin's request fills the cache with everyone's appointments...
        wp_set_current_user($this->admin);
        $all = AppointmentService::getAppointments(['page' => 1]);
        $this->assertGreaterThanOrEqual(1, $all['meta']['total']);

        // ...and a patient sending the same parameters must not be served it.
        wp_set_current_user($this->patient_user);
        $own = AppointmentService::getAppointments(['page' => 1]);
        $this->assertSame(0, $own['meta']['total']);
    }

    public function test_any_write_to_a_hospital_table_invalidates_cached_results()
    {
        BaseService::watchWrites();
        $before = BaseService::currentDataGeneration();

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_hmos', ['name' => 'Test HMO ' . wp_generate_password(4, false)]);

        $this->assertSame($before + 1, BaseService::currentDataGeneration());

        // Writes to other tables don't count
        update_option('hm_unrelated_test_option', wp_generate_password(6, false));
        $this->assertSame($before + 1, BaseService::currentDataGeneration());
    }

    // ================================================================ doctors

    public function test_doctor_directory_needs_login()
    {
        $this->assertSame(401, $this->as(0, 'GET', '/doctors')->get_status());
        $this->assertSame(401, $this->as(0, 'GET', "/doctors/{$this->doctor->ID}")->get_status());
        $this->assertSame(200, $this->as($this->patient_user, 'GET', '/doctors')->get_status());
    }

    public function test_doctor_patient_list_is_for_staff_only()
    {
        $route = "/doctors/{$this->doctor->ID}/patients";
        $this->assertSame(401, $this->as(0, 'GET', $route)->get_status());
        $this->assertSame(403, $this->as($this->patient_user, 'GET', $route)->get_status());
        $this->assertSame(200, $this->as($this->nurse, 'GET', $route)->get_status());
        $this->assertSame(200, $this->as($this->admin, 'GET', $route, null, ['per_page' => 0])->get_status(), 'per_page=0 used to divide by zero');
    }

    public function test_doctors_cannot_edit_or_delete_other_doctors()
    {
        $this->assertSame(403, $this->as($this->doctor_user, 'PUT', "/doctors/{$this->other_doctor->ID}", ['status' => 'inactive'])->get_status());
        $this->assertSame(403, $this->as($this->doctor_user, 'DELETE', "/doctors/{$this->other_doctor->ID}")->get_status());
        $this->assertSame(403, $this->as($this->doctor_user, 'POST', '/doctors', ['first_name' => 'X'])->get_status());
        $this->assertSame('active', $this->row('hm_doctors', $this->other_doctor->ID)['status']);
    }

    public function test_doctor_may_edit_own_profile_but_not_their_status()
    {
        $response = $this->as($this->doctor_user, 'PUT', "/doctors/{$this->doctor->ID}", [
            'office' => 'Room 12', 'status' => 'inactive', 'certification' => 'FWACP',
        ]);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $row = $this->row('hm_doctors', $this->doctor->ID);
        $this->assertSame('Room 12', $row['office']);
        $this->assertSame('active', $row['status']);
        $this->assertSame('FWACP', $row['board_certification']);
    }

    // ================================================================ dashboard

    public function test_hospital_stats_are_for_staff_only()
    {
        $this->assertSame(403, $this->as($this->patient_user, 'GET', '/dashboard/stats')->get_status());
        $this->assertSame(200, $this->as($this->nurse, 'GET', '/dashboard/stats')->get_status());
    }

    public function test_dashboard_answers_every_role()
    {
        foreach ([$this->admin, $this->nurse, $this->inventory_manager] as $user) {
            $response = $this->as($user, 'GET', '/dashboard');
            $this->assertSame(200, $response->get_status(), 'user ' . $user . ': ' . wp_json_encode($response->get_data()));
        }
    }

    // ================================================================ inventory

    public function test_inventory_roles_can_use_inventory()
    {
        $this->assertSame(200, $this->as($this->inventory_manager, 'GET', '/inventory')->get_status());
        $this->assertSame(403, $this->as($this->patient_user, 'GET', '/inventory')->get_status());
    }

    public function test_reorder_is_received_once_and_only_after_approval()
    {
        $item = $this->insertItem(['quantity' => 2]);
        $reorder = $this->insertReorder($item, 'pending');

        $this->assertSame(409, $this->as($this->admin, 'POST', "/inventory/reorders/{$reorder}/complete", ['received_quantity' => 10])->get_status());
        $this->assertSame(200, $this->as($this->admin, 'POST', "/inventory/reorders/{$reorder}/approve")->get_status());
        $this->assertSame(409, $this->as($this->admin, 'POST', "/inventory/reorders/{$reorder}/approve")->get_status());

        $first = $this->as($this->admin, 'POST', "/inventory/reorders/{$reorder}/complete", ['received_quantity' => 10]);
        $this->assertSame(200, $first->get_status(), wp_json_encode($first->get_data()));
        $this->assertSame(409, $this->as($this->admin, 'POST', "/inventory/reorders/{$reorder}/complete", ['received_quantity' => 10])->get_status());

        $this->assertEquals(12, $this->row('hm_inventory', $item)['quantity'], 'Stock added exactly once');
        $this->assertSame(404, $this->as($this->admin, 'POST', '/inventory/reorders/999999/approve')->get_status());
    }

    public function test_stock_out_beyond_what_is_in_stock_is_refused()
    {
        $item = $this->insertItem(['quantity' => 3]);

        $refused = $this->as($this->admin, 'POST', '/inventory/transactions', ['item_id' => $item, 'type' => 'stock_out', 'quantity' => 5]);
        $this->assertSame(400, $refused->get_status());
        $this->assertEquals(3, $this->row('hm_inventory', $item)['quantity']);

        $ok = $this->as($this->admin, 'POST', '/inventory/transactions', ['item_id' => $item, 'type' => 'stock_out', 'quantity' => 2]);
        $this->assertSame(201, $ok->get_status(), wp_json_encode($ok->get_data()));
        $this->assertEquals(1, $this->row('hm_inventory', $item)['quantity']);
    }

    public function test_generating_alerts_twice_does_not_duplicate_them()
    {
        $item = $this->insertItem(['quantity' => 1, 'reorder_level' => 10]);

        $this->as($this->admin, 'POST', '/inventory/alerts/generate');
        $this->as($this->admin, 'POST', '/inventory/alerts/generate');

        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hm_inventory_alerts WHERE inventory_id = %d AND alert_type = 'low_stock'",
            $item
        ));
        $this->assertSame(1, $count);
        $this->assertSame(404, $this->as($this->admin, 'POST', '/inventory/alerts/999999/acknowledge')->get_status());
    }

    public function test_bulk_update_changes_the_items()
    {
        $a = $this->insertItem();
        $b = $this->insertItem();

        $response = $this->as($this->admin, 'POST', '/inventory/bulk', [
            'action' => 'update_location',
            'items' => [['id' => $a, 'location' => 'Store B'], ['id' => $b, 'location' => 'Store B'], ['id' => 999999]],
        ]);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertSame('Store B', $this->row('hm_inventory', $a)['location']);
        $this->assertSame('Store B', $this->row('hm_inventory', $b)['location']);
        $results = $response->get_data()['data'];
        $this->assertFalse(end($results)['success']);
    }

    // ================================================================ notifications

    public function test_mark_all_notifications_read()
    {
        $this->createTestNotification(['user_id' => $this->patient_user, 'read' => 0]);
        $this->createTestNotification(['user_id' => $this->patient_user, 'read' => 0]);
        $this->createTestNotification(['user_id' => $this->other_patient_user, 'read' => 0]);

        $response = $this->as($this->patient_user, 'POST', '/notifications/mark-all-read');

        $this->assertSame(200, $response->get_status());
        global $wpdb;
        $unread = fn($u) => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}hm_notifications WHERE user_id = %d AND `read` = 0", $u));
        $this->assertSame(0, $unread($this->patient_user));
        $this->assertSame(1, $unread($this->other_patient_user), "Other users' notifications are untouched");
        $this->assertSame(200, $this->as($this->patient_user, 'GET', '/notifications', null, ['per_page' => 0])->get_status());
    }

    // ================================================================ debug output, CORS

    public function test_debug_details_are_not_public()
    {
        $this->assertContains($this->as(0, 'GET', '/auth/debug')->get_status(), [401, 403]);
        $this->assertSame(403, $this->as($this->doctor_user, 'GET', '/auth/debug')->get_status());

        $me = $this->as(0, 'GET', '/auth/me')->get_data();
        $this->assertArrayNotHasKey('debug_info', $me);

        $access = $this->as($this->doctor_user, 'GET', '/access')->get_data();
        $this->assertArrayNotHasKey('debug', $access['data'] ?? $access);
    }

    public function test_only_the_sites_own_origin_is_same_site()
    {
        $this->assertTrue(ApiService::isSameSiteOrigin(home_url()));
        $this->assertTrue(ApiService::isSameSiteOrigin(rtrim(home_url(), '/') . '/'));
        $this->assertFalse(ApiService::isSameSiteOrigin('https://evil.example'));
        $this->assertFalse(ApiService::isSameSiteOrigin('null'));
    }

    // ================================================================ plugin lifecycle

    public function test_deactivation_keeps_tables_and_roles()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/hospital-manager.php');
        preg_match('/public function deactivate_plugin\(\)\s*\{(.*?)\n    \}/s', $source, $m);

        $this->assertNotEmpty($m, 'deactivate_plugin() found');
        $this->assertStringNotContainsString('::down(', $m[1]);
        $this->assertStringNotContainsString('remove_role', $m[1]);
    }

    public function test_uninstall_keeps_data_unless_opted_in()
    {
        delete_option('hospital_manager_delete_data_on_uninstall');
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', 'hospital-manager/hospital-manager.php');
        }

        include dirname(__DIR__, 2) . '/uninstall.php';

        require_once dirname(__DIR__, 2) . '/database/migrations/create_hospital_tables.php';
        $this->assertSame([], \CreateHospitalTables::missingTables());
        $this->assertNotNull(get_role('doctor'));
    }

    public function test_schema_is_dbdelta_compatible()
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/create_hospital_tables.php');

        // dbDelta reads "CREATE TABLE IF NOT EXISTS x" as a table named "IF", so it
        // never upgraded existing tables; an inline CONSTRAINT became a bogus column.
        $this->assertDoesNotMatchRegularExpression('/CREATE TABLE IF NOT EXISTS/i', $sql);
        $this->assertDoesNotMatchRegularExpression('/^\s*CONSTRAINT /mi', $sql);
        $this->assertDoesNotMatchRegularExpression("/enum\\([^)]*',\\s+'/i", $sql, 'no spaces inside ENUM lists');
        $this->assertNotEmpty(\CreateHospitalTables::SCHEMA_VERSION);
    }

    public function test_data_changing_scripts_refuse_to_run_from_the_web()
    {
        $root = dirname(__DIR__, 2);
        foreach (['database/reset-data.php', 'database/seeders.php', 'database/run-seeders.php', 'database/bootstrap-wp.php'] as $script) {
            $code = file_get_contents("$root/$script");
            $this->assertMatchesRegularExpression(
                "/\\A<\\?php\\s*(\\/\\/[^\\n]*\\n\\s*)*if \\(PHP_SAPI !== 'cli'\\) \\{/",
                $code,
                "$script must exit before doing anything when not run from the command line"
            );
        }
    }

    // ================================================================ helpers

    private function as($user_id, $method, $route, ?array $body = null, array $query = [])
    {
        wp_set_current_user($user_id);
        $request = new \WP_REST_Request($method, '/hospital-manager/v1' . $route);
        if ($query) {
            $request->set_query_params($query);
        }
        if ($body !== null) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(wp_json_encode($body));
        }
        return @rest_get_server()->dispatch($request);
    }

    private function appointmentItems($response)
    {
        $data = $response->get_data();
        $data = $data['data'] ?? $data;
        $items = $data['appointments']->items ?? $data['appointments']['items'] ?? [];
        return (array) $items;
    }

    private function insertItem(array $overrides = [])
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_inventory', array_merge([
            'item_name' => 'Paracetamol ' . wp_generate_password(4, false), 'category' => 'Medication', 'quantity' => 5,
            'unit' => 'packs', 'reorder_level' => 1, 'status' => 'In Stock',
        ], $overrides));
        return (int) $wpdb->insert_id;
    }

    private function insertReorder($item, $status)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_inventory_suppliers', ['name' => 'MediPharma ' . wp_generate_password(4, false), 'is_active' => 1]);
        $supplier = (int) $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . 'hm_inventory_reorders', [
            'inventory_id' => $item, 'supplier_id' => $supplier, 'current_quantity' => 2, 'reorder_level' => 5,
            'suggested_quantity' => 10, 'priority' => 'medium', 'status' => $status, 'created_by' => $this->admin,
        ]);
        return (int) $wpdb->insert_id;
    }

    private function row($table, $id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$table} WHERE ID = %d", $id), ARRAY_A);
    }
}

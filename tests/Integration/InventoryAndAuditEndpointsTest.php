<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Inventory;
use HospitalManager\Models\InventorySupplier;
use HospitalManager\Controllers\Api\AuditController;
use HospitalManager\Controllers\Api\InventoryController;
use HospitalManager\Controllers\Api\InventoryReorderController;
use HospitalManager\Controllers\Api\InventorySupplierController;

/**
 * Regression tests for endpoints that used to fatal:
 *   - GET /inventory/{id}                 "Cannot instantiate abstract class BaseModel"
 *                                          (FindTrait::find() does `new self`)
 *   - GET /inventory/reorders/suggestions  read non-existent hm_inventory.max_stock_level
 *   - GET /audit-logs/patient/{id}         called a closure *property* as a method
 *   - GET /audit-logs                      checked a capability no role is granted
 *
 * Runs against the REAL controllers (PHPUnit normally loads tests/Mocks/Api/*).
 */
class InventoryAndAuditEndpointsTest extends TestCase
{
    private $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server(); // fire rest_api_init so register_rest_route() is allowed
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        foreach ([
            InventoryController::class,
            InventorySupplierController::class,
            InventoryReorderController::class,
            AuditController::class,
        ] as $controller) {
            (new $controller())->register_routes();
        }

        $this->admin_id = $this->createUserWithRole('administrator');
        // Production grants this via RoleService::initializeRoles(). Strip the legacy
        // 'view_audit_log' (other tests add it and it can persist in the test DB) so
        // only the real capability can grant access.
        get_role('administrator')->remove_cap('view_audit_log');
        get_role('administrator')->add_cap('access_audit_log');
    }

    public function tearDown(): void
    {
        get_role('administrator')->remove_cap('access_audit_log');
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // BaseModel::find() for models that inherit FindTrait from BaseModel
    // ------------------------------------------------------------------

    public function test_find_works_for_inventory_models()
    {
        $item_id = $this->insertInventoryItem(['item_name' => 'Gauze Roll']);
        $supplier_id = $this->insertSupplier('Delta Medical Supplies');

        $item = Inventory::find($item_id);
        $this->assertInstanceOf(Inventory::class, $item);
        $this->assertEquals('Gauze Roll', $item->item_name);

        $this->assertInstanceOf(InventorySupplier::class, InventorySupplier::find($supplier_id));
        $this->assertEquals($item_id, Inventory::findOne($item_id)->ID);
    }

    public function test_find_returns_null_for_missing_or_empty_id()
    {
        $this->assertNull(Inventory::find(999999999));
        $this->assertNull(Inventory::find(0));
    }

    // ------------------------------------------------------------------
    // Inventory endpoints
    // ------------------------------------------------------------------

    public function test_get_inventory_item_returns_serialised_item()
    {
        $item_id = $this->insertInventoryItem(['item_name' => 'Paracetamol 500mg', 'quantity' => 40]);

        $body = $this->getAs($this->admin_id, "/hospital-manager/v1/inventory/{$item_id}", 200);

        $this->assertTrue($body['success']);
        $this->assertEquals($item_id, $body['data']['ID']);
        $this->assertEquals('Paracetamol 500mg', $body['data']['item_name']);
        $this->assertEquals(40, $body['data']['quantity']);
    }

    public function test_get_missing_inventory_item_returns_404()
    {
        $this->getAs($this->admin_id, '/hospital-manager/v1/inventory/999999999', 404);
    }

    public function test_get_supplier_returns_serialised_supplier()
    {
        $supplier_id = $this->insertSupplier('Delta Medical Supplies');

        $body = $this->getAs($this->admin_id, "/hospital-manager/v1/inventory/suppliers/{$supplier_id}", 200);

        $this->assertEquals($supplier_id, $body['data']['ID']);
        $this->assertEquals('Delta Medical Supplies', $body['data']['name']);
    }

    public function test_reorder_suggestions_list_low_stock_items()
    {
        $low_id = $this->insertInventoryItem(['item_name' => 'Oxygen Mask', 'quantity' => 0, 'reorder_level' => 5]);
        $this->insertInventoryItem(['item_name' => 'Well Stocked', 'quantity' => 500, 'reorder_level' => 5]);

        $body = $this->getAs($this->admin_id, '/hospital-manager/v1/inventory/reorders/suggestions?per_page=100', 200);

        $suggestions = $body['data']['data'];
        $ids = array_column($suggestions, 'item_id');
        $this->assertContains((string) $low_id, array_map('strval', $ids));
        $this->assertNotContains('Well Stocked', array_column($suggestions, 'item_name'));

        $low = $suggestions[array_search((string) $low_id, array_map('strval', $ids), true)];
        $this->assertSame('urgent', $low['priority']);
        $this->assertArrayHasKey('max_stock_level', $low);
        $this->assertNull($low['max_stock_level']);
    }

    // ------------------------------------------------------------------
    // Audit log endpoints
    // ------------------------------------------------------------------

    public function test_admin_can_list_audit_logs_with_user_details()
    {
        $this->insertAuditLog($this->admin_id, 'user', $this->admin_id, 'User logged in');

        $body = $this->getAs($this->admin_id, '/hospital-manager/v1/audit-logs?per_page=50', 200);

        $this->assertGreaterThanOrEqual(1, $body['meta']['total']);
        $log = $this->findLog($body['data'], 'User logged in');
        $this->assertNotNull($log, 'Inserted audit log not returned');
        $this->assertEquals($this->admin_id, $log['user_id']);
        $this->assertNotEquals('Unknown User', $log['user_name']);
        $this->assertStringContainsString('administrator', $log['user_role']);
        $this->assertSame(['ip_address' => '127.0.0.1'], $log['details'], 'JSON columns should be decoded');
    }

    public function test_patient_cannot_list_audit_logs()
    {
        $this->getAs($this->createUserWithRole('patient'), '/hospital-manager/v1/audit-logs', 403);
    }

    public function test_doctor_can_view_logs_for_a_patient()
    {
        $patient = $this->createTestPatient(['user_id' => $this->createUserWithRole('patient')]);
        $other = $this->createTestPatient(['user_id' => $this->createUserWithRole('patient')]);
        $this->insertAuditLog($this->admin_id, 'patient', $patient->ID, 'Patient record updated');
        $this->insertAuditLog($this->admin_id, 'patient', $other->ID, 'Other patient updated');

        $logs = $this->getAs($this->createUserWithRole('doctor'), "/hospital-manager/v1/audit-logs/patient/{$patient->ID}", 200);

        $this->assertCount(1, $logs);
        $this->assertEquals('Patient record updated', $logs[0]['action']);
        $this->assertEquals($patient->ID, $logs[0]['entity_id']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function insertInventoryItem(array $overrides = [])
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_inventory', array_merge([
            'item_name' => 'Test Item',
            'category' => 'Supplies',
            'quantity' => 100,
            'unit' => 'units',
            'reorder_level' => 10,
            'status' => 'In Stock',
        ], $overrides));
        $this->assertNotEmpty($wpdb->insert_id, 'Failed to insert inventory item: ' . $wpdb->last_error);
        return (int) $wpdb->insert_id;
    }

    private function insertSupplier($name)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_inventory_suppliers', [
            'name' => $name,
            'email' => 'orders@example.com',
            'is_active' => 1,
        ]);
        $this->assertNotEmpty($wpdb->insert_id, 'Failed to insert supplier: ' . $wpdb->last_error);
        return (int) $wpdb->insert_id;
    }

    private function insertAuditLog($user_id, $entity_type, $entity_id, $action)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hm_audit_logs', [
            'user_id' => $user_id,
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'details' => wp_json_encode(['ip_address' => '127.0.0.1']),
            'created_at' => current_time('mysql'),
        ]);
        $this->assertNotEmpty($wpdb->insert_id, 'Failed to insert audit log: ' . $wpdb->last_error);
    }

    private function findLog(array $logs, $action)
    {
        foreach ($logs as $log) {
            if ($log['action'] === $action) {
                return $log;
            }
        }
        return null;
    }

    private function getAs($user_id, $route, $expected_status)
    {
        wp_set_current_user($user_id);

        $parts = wp_parse_url($route);
        $request = new \WP_REST_Request('GET', $parts['path']);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $request->set_query_params($query);
        }

        $response = rest_get_server()->dispatch($request);
        $data = json_decode(wp_json_encode($response->get_data()), true);

        $this->assertSame(
            $expected_status,
            $response->get_status(),
            "Unexpected status for {$route}: " . wp_json_encode($data)
        );

        return $data;
    }
}

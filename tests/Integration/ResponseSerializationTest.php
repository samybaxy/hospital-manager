<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\BaseModel;

/**
 * Calls every hospital-manager REST endpoint (reads and writes, success and error
 * paths) through the REAL controllers and fails if any response still contains a
 * model or WP_User object.
 *
 * Models keep their columns in protected properties, so json_encode() turns them
 * into "{}"; WP_User objects would expose user records (including password hashes).
 * Inspecting get_data() before encoding also catches objects nested inside arrays
 * or stdClass payloads, which a "{}" text search can miss.
 */
class ResponseSerializationTest extends TestCase
{
    private $ids = [];

    public function setUp(): void
    {
        parent::setUp();

        // Real controllers only (PHPUnit normally registers tests/Mocks/Api/*)
        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        foreach (glob(dirname(__DIR__, 2) . '/app/Controllers/Api/*Controller.php') as $file) {
            $name = basename($file, '.php');
            if (preg_match('/^(Base|InventoryBase)Controller$|_backup|_new/', $name)) {
                continue;
            }
            $class = 'HospitalManager\\Controllers\\Api\\' . $name;
            $controller = new $class();
            if (method_exists($controller, 'register_routes')) {
                $controller->register_routes();
            }
        }

        $this->seedFixtures();

        if (getenv('HM_RESPONSE_REPORT')) {
            add_action('doing_it_wrong_run', function ($function) {
                fwrite(STDERR, "\n[doing_it_wrong] {$function}: " . wp_debug_backtrace_summary(null, 0, false)[4] . ' <- ' . implode(' <- ', array_slice(wp_debug_backtrace_summary(null, 0, false), 5, 4)) . "\n");
            });
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

    /**
     * Endpoints that currently crash for reasons unrelated to serialization (methods
     * that don't exist). Their success paths were checked by reading the code. When one
     * is fixed this test fails until it is removed here, so the list can't go stale.
     */
    const KNOWN_CRASHES = [];

    const CAPS = [
        'access_audit_log', 'add_visitation', 'delete_visitation', 'edit_visitation',
        'manage_inventory', 'manage_lab_investigations', 'manage_patient_records',
        'rest_api_access', 'update_lab_results', 'view_audit_log', 'view_inventory',
        'view_patient_records', 'view_patients',
    ];

    public function test_no_endpoint_returns_model_or_user_objects()
    {
        $offenders = [];
        $crashes = [];
        $report = [];

        foreach ($this->cases() as [$as, $method, $route, $body, $label]) {
            $user = $this->ids[$as];
            wp_set_current_user($user);

            $request = new \WP_REST_Request($method, '/hospital-manager/v1' . $route);
            if ($body !== null) {
                if ($method === 'GET') {
                    $request->set_query_params($body);
                } else {
                    $request->set_header('Content-Type', 'application/json');
                    $request->set_body(wp_json_encode($body));
                }
            }

            try {
                $response = rest_get_server()->dispatch($request);
                $status = $response->get_status();
                $found = $this->findObjects($response->get_data());
            } catch (\Throwable $e) {
                $crashes["{$method} {$label}"] = $e->getMessage();
                $report[] = sprintf('%-6s %-62s %s', $method, $route, 'exception: ' . $e->getMessage());
                continue;
            }

            $detail = '';
            if (is_int($status) && $status >= 400) {
                $d = json_decode(wp_json_encode($response->get_data()), true);
                $detail = '  (' . substr((string) ($d['message'] ?? $d['error'] ?? wp_json_encode($d)), 0, 140) . ')';
            }
            $report[] = sprintf('%-6s %-62s %s%s%s', $method, $route, $status, $found ? '  <-- ' . implode(', ', $found) : '', $detail);
            if ($found) {
                $offenders[] = "{$method} {$route} ({$status}): " . implode(', ', $found);
            }
        }

        if (getenv('HM_RESPONSE_REPORT')) {
            fwrite(STDERR, "\n" . implode("\n", $report) . "\n");
        }

        $this->assertSame([], $offenders, "Responses containing model/user objects:\n" . implode("\n", $offenders));

        $unexpected = array_diff_key($crashes, self::KNOWN_CRASHES);
        $this->assertSame([], $unexpected, 'Endpoints crashed (their responses could not be checked)');
        $fixed = array_diff_key(self::KNOWN_CRASHES, $crashes);
        $this->assertSame([], $fixed, 'These no longer crash: remove them from KNOWN_CRASHES');
    }

    /**
     * [user, method, route, body] for every endpoint.
     */
    private function cases()
    {
        // Label each case with its route template (/visitations/{id}) so
        // KNOWN_CRASHES doesn't depend on auto-increment IDs.
        return array_map(function ($case) {
            $case[4] = preg_replace('#/\d+(?=/|$)#', '/{id}', $case[2]);
            return $case;
        }, $this->rawCases());
    }

    private function rawCases()
    {
        $i = $this->ids;
        $future = date('Y-m-d', strtotime('+5 days'));

        return [
            // Patients
            ['admin', 'GET', '/patients', null],
            ['admin', 'GET', "/patients/{$i['patient']}", null],
            ['admin', 'GET', "/patients/{$i['patient']}/visitations", null],
            ['admin', 'GET', '/patients/search', ['search' => 'Ngozi']],
            ['patient_user', 'GET', '/patients/me', null],
            ['admin', 'POST', '/patients', ['first_name' => 'New', 'last_name' => 'Patient', 'phone' => '08012340000', 'gender' => 'Female', 'age' => 30]],
            ['admin', 'PUT', "/patients/{$i['patient']}", ['address' => '1 New Road']],
            ['admin', 'DELETE', "/patients/{$i['patient_spare']}", null],

            // Visitations
            ['admin', 'GET', '/visitations', null],
            ['admin', 'GET', "/visitations/{$i['visit']}", null],
            ['admin', 'POST', '/visitations', ['patient_id' => $i['patient'], 'doctor_id' => $i['doctor'], 'date' => current_time('Y-m-d'), 'time' => '09:30:00', 'complaint' => 'Cough', 'diagnosis' => 'URTI', 'treatment' => 'Rest']],
            ['admin', 'PUT', "/visitations/{$i['visit']}", ['patient_id' => $i['patient'], 'doctor_id' => $i['doctor'], 'date' => current_time('Y-m-d'), 'time' => '10:00:00', 'diagnosis' => 'Updated']],
            ['admin', 'DELETE', "/visitations/{$i['visit_spare']}", null],

            // Lab investigations
            ['admin', 'GET', '/lab-investigations', null],
            ['admin', 'GET', "/lab-investigations/{$i['lab']}", null],
            ['admin', 'GET', '/lab-investigations/pending', null],
            ['admin', 'GET', '/lab-categories', null],
            ['admin', 'GET', '/lab-test-definitions', null],
            ['admin', 'POST', '/lab-investigations', ['visitation_id' => $i['visit'], 'patient_id' => $i['patient'], 'doctor_id' => $i['doctor'], 'lab_tech_id' => $i['lab_tech'], 'test_type' => 'Lipid Panel']],
            ['admin', 'PUT', "/lab-investigations/{$i['lab']}", ['lab_notes' => 'Checked']],
            ['admin', 'PUT', "/lab-investigations/{$i['lab']}/status", ['status' => 'in_progress']],
            ['admin', 'PUT', "/lab-investigations/{$i['lab']}/results", ['test_results' => ['parameters' => [['name' => 'Hb', 'value' => 13]]]]],
            ['admin', 'DELETE', "/lab-investigations/{$i['lab_spare']}", null],

            // Chats
            ['patient_user', 'GET', '/chats', null],
            ['patient_user', 'GET', "/chats/{$i['chat']}/messages", null],
            ['patient_user', 'POST', "/chats/{$i['chat']}/messages", ['message' => 'Hello doctor']],
            ['patient_user', 'POST', '/chats/start', ['doctor_id' => $i['doctor_user']]],          // existing chat
            ['patient_user', 'POST', '/chats/start', ['doctor_id' => $i['doctor2_user']]],         // new chat
            ['patient_user', 'PUT', "/chats/{$i['chat']}/read", null],

            // Notifications
            ['patient_user', 'GET', '/notifications', null],
            ['patient_user', 'GET', '/notifications/unread', null],
            ['patient_user', 'PUT', "/notifications/{$i['notification']}/read", null],

            // Appointments
            ['admin', 'GET', '/appointments', null],
            ['admin', 'GET', "/appointments/{$i['appointment']}", null],
            ['admin', 'GET', '/appointments/availability', ['doctor_id' => $i['doctor'], 'date' => $future]],
            ['admin', 'GET', '/appointments/stats', ['doctor_id' => $i['doctor']]],
            ['patient_user', 'POST', '/appointments', ['doctor_id' => $i['doctor'], 'date' => $future, 'time' => '11:00:00', 'reason' => 'Review']],
            ['admin', 'POST', '/appointments', ['patient_id' => $i['patient'], 'doctor_id' => $i['doctor'], 'date' => $future, 'time' => '12:00:00', 'reason' => 'Review']],
            ['admin', 'PUT', "/appointments/{$i['appointment']}", ['status' => 'confirmed']],

            // Doctors
            ['admin', 'GET', '/doctors', null],
            ['admin', 'GET', "/doctors/{$i['doctor']}", null],
            ['admin', 'GET', "/doctors/{$i['doctor']}/patients", null],
            ['admin', 'GET', '/doctors/specialties', null],
            ['admin', 'POST', '/doctors', ['first_name' => 'New', 'last_name' => 'Doctor', 'phone' => '08055550000', 'specialty' => 'Cardiology', 'license_number' => 'MD123456', 'email' => 'new.doctor@example.com']],
            ['admin', 'PUT', "/doctors/{$i['doctor']}", ['office' => 'Room 9']],
            ['admin', 'DELETE', "/doctors/{$i['doctor_spare']}", null],

            // Inventory
            ['admin', 'GET', '/inventory', null],
            ['admin', 'GET', "/inventory/{$i['item']}", null],
            ['admin', 'GET', '/inventory/critical', null],
            ['admin', 'GET', '/inventory/expiring', null],
            ['admin', 'GET', '/inventory/summary', null],
            ['admin', 'GET', '/inventory/categories', null],
            ['admin', 'GET', '/inventory/permissions', null],
            ['admin', 'GET', '/inventory/dashboard', null],
            ['admin', 'POST', '/inventory', ['item_name' => 'Gloves', 'name' => 'Gloves', 'category' => 'Supplies', 'quantity' => 50, 'unit' => 'boxes', 'reorder_level' => 10]],
            ['admin', 'PUT', "/inventory/{$i['item']}", ['quantity' => 77]],
            ['admin', 'PUT', "/inventory/{$i['item']}/status", ['status' => 'Low Stock']],
            ['admin', 'PUT', '/inventory/bulk', ['items' => [['id' => $i['item'], 'quantity' => 60]], 'action' => 'update']],
            ['admin', 'DELETE', "/inventory/{$i['item_spare']}", null],

            // Inventory transactions / alerts / suppliers / reorders
            ['admin', 'GET', '/inventory/transactions', null],
            ['admin', 'POST', '/inventory/transactions', ['item_id' => $i['item'], 'type' => 'stock_in', 'quantity' => 5, 'reference' => 'PO-1']],
            ['admin', 'GET', '/inventory/alerts', null],
            ['admin', 'PUT', "/inventory/alerts/{$i['alert']}/acknowledge", null],
            ['admin', 'PUT', "/inventory/alerts/{$i['alert']}/resolve", null],
            ['admin', 'POST', '/inventory/alerts/generate', null],
            ['admin', 'GET', '/inventory/suppliers', null],
            ['admin', 'GET', "/inventory/suppliers/{$i['supplier']}", null],
            ['admin', 'POST', '/inventory/suppliers', ['name' => 'New Supplier Ltd', 'email' => 'orders@newsupplier.example']],
            ['admin', 'PUT', "/inventory/suppliers/{$i['supplier']}", ['phone' => '08099990000']],
            ['admin', 'DELETE', "/inventory/suppliers/{$i['supplier_spare']}", null],
            ['admin', 'GET', '/inventory/reorders', null],
            ['admin', 'GET', '/inventory/reorders/suggestions', null],
            ['admin', 'POST', '/inventory/reorders', ['item_id' => $i['item'], 'supplier_id' => $i['supplier'], 'quantity' => 20, 'priority' => 'high']],
            ['admin', 'PUT', "/inventory/reorders/{$i['reorder']}/approve", null],
            ['admin', 'PUT', "/inventory/reorders/{$i['reorder']}/complete", ['received_quantity' => 20, 'notes' => 'Delivered']],

            // Dashboards, stats, audit, HMOs, profile, access, users
            ['doctor_user', 'GET', '/dashboard', null],
            ['patient_user', 'GET', '/dashboard', null],
            ['lab_tech', 'GET', '/dashboard', null],
            ['admin', 'GET', '/dashboard/stats', null],
            ['admin', 'GET', '/stats', null],
            ['admin', 'GET', '/audit-logs', null],
            ['admin', 'GET', "/audit-logs/patient/{$i['patient']}", null],
            ['admin', 'GET', '/hmos', null],
            ['doctor_user', 'GET', '/profile', null],
            ['patient_user', 'GET', '/profile', null],
            ['patient_user', 'PUT', '/profile', ['phone' => '08031112222']],
            ['doctor_user', 'PUT', '/profile', ['office' => 'Room 4']],
            ['admin', 'GET', '/access', null],
            ['admin', 'POST', '/sync-roles', null],
            ['patient_user', 'POST', '/user/change-password', ['current_password' => 'wrong-password', 'new_password' => 'NewPassw0rd!']],
            ['admin', 'GET', '/auth/me', null],
            ['admin', 'POST', '/auth/refresh', null],
            ['admin', 'POST', '/auth/reset-password', ['email' => 'nobody@example.com']],
        ];
    }

    private function seedFixtures()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $this->ids['admin'] = $this->createUserWithRole('administrator');
        foreach (self::CAPS as $cap) {
            get_role('administrator')->add_cap($cap);
        }
        $this->ids['lab_tech'] = $this->createUserWithRole('lab_tech');

        $this->ids['doctor_user'] = $this->createUserWithRole('doctor');
        $this->ids['doctor'] = $this->createTestDoctor(['user_id' => $this->ids['doctor_user'], 'first_name' => 'Ada'])->ID;
        $this->ids['doctor2_user'] = $this->createUserWithRole('doctor');
        $this->createTestDoctor(['user_id' => $this->ids['doctor2_user']]);
        $this->ids['doctor_spare'] = $this->createTestDoctor(['user_id' => $this->createUserWithRole('doctor')])->ID;

        $this->ids['patient_user'] = $this->createUserWithRole('patient');
        $this->ids['patient'] = $this->createTestPatient(['user_id' => $this->ids['patient_user'], 'first_name' => 'Ngozi'])->ID;
        $this->ids['patient_spare'] = $this->createTestPatient(['user_id' => $this->createUserWithRole('patient')])->ID;

        $this->ids['appointment'] = $this->createTestAppointment($this->ids['patient'], $this->ids['doctor'], ['status' => 'pending'])->ID;
        $this->ids['visit'] = $this->createTestVisitation(['patient_id' => $this->ids['patient'], 'doctor_id' => $this->ids['doctor']])->ID;
        $this->ids['visit_spare'] = $this->createTestVisitation(['patient_id' => $this->ids['patient'], 'doctor_id' => $this->ids['doctor']])->ID;
        $lab = ['visitation_id' => $this->ids['visit'], 'patient_id' => $this->ids['patient'], 'doctor_id' => $this->ids['doctor'], 'lab_tech_id' => $this->ids['lab_tech']];
        $this->ids['lab'] = $this->createTestLabInvestigation($lab)->ID;
        $this->ids['lab_spare'] = $this->createTestLabInvestigation($lab)->ID;

        $wpdb->insert("{$p}hm_chats", ['doctor_id' => $this->ids['doctor_user'], 'patient_id' => $this->ids['patient_user'], 'status' => 'active', 'created_at' => current_time('mysql')]);
        $this->ids['chat'] = $wpdb->insert_id;
        $wpdb->insert("{$p}hm_chat_messages", ['chat_id' => $this->ids['chat'], 'sender_id' => $this->ids['doctor_user'], 'receiver_id' => $this->ids['patient_user'], 'message' => 'Hi', 'read' => 0, 'created_at' => current_time('mysql')]);
        $this->ids['notification'] = $this->createTestNotification(['user_id' => $this->ids['patient_user']])->ID;

        $item = ['category' => 'Medication', 'quantity' => 3, 'unit' => 'packs', 'reorder_level' => 10, 'status' => 'In Stock'];
        $wpdb->insert("{$p}hm_inventory", ['item_name' => 'Paracetamol'] + $item);
        $this->ids['item'] = $wpdb->insert_id;
        $wpdb->insert("{$p}hm_inventory", ['item_name' => 'Spare item'] + $item);
        $this->ids['item_spare'] = $wpdb->insert_id;
        $wpdb->insert("{$p}hm_inventory_suppliers", ['name' => 'MediPharma', 'is_active' => 1]);
        $this->ids['supplier'] = $wpdb->insert_id;
        $wpdb->insert("{$p}hm_inventory_suppliers", ['name' => 'Spare Supplier', 'is_active' => 1]);
        $this->ids['supplier_spare'] = $wpdb->insert_id;
        $wpdb->insert("{$p}hm_inventory_alerts", ['inventory_id' => $this->ids['item'], 'alert_type' => 'low_stock', 'severity' => 'high', 'title' => 'Low stock', 'message' => 'Below reorder level', 'is_active' => 1]);
        $this->ids['alert'] = $wpdb->insert_id;
        $wpdb->insert("{$p}hm_inventory_reorders", ['inventory_id' => $this->ids['item'], 'supplier_id' => $this->ids['supplier'], 'suggested_quantity' => 20, 'current_quantity' => 3, 'reorder_level' => 10, 'priority' => 'high', 'status' => 'pending', 'created_by' => $this->ids['admin']]);
        $this->ids['reorder'] = $wpdb->insert_id;
    }

    /**
     * Paths of any BaseModel / WP_User / WP_Post objects inside $data.
     */
    private function findObjects($data, $path = '$', $depth = 0)
    {
        if ($depth > 8) {
            return [];
        }
        if ($data instanceof BaseModel) {
            return ["{$path} = " . (new \ReflectionClass($data))->getShortName()];
        }
        if ($data instanceof \WP_User || $data instanceof \WP_Post) {
            return ["{$path} = " . get_class($data)];
        }
        $found = [];
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $found = array_merge($found, $this->findObjects($value, "{$path}[{$key}]", $depth + 1));
            }
        } elseif (is_object($data) && !($data instanceof \JsonSerializable)) {
            foreach (get_object_vars($data) as $key => $value) {
                $found = array_merge($found, $this->findObjects($value, "{$path}->{$key}", $depth + 1));
            }
        }
        return $found;
    }
}

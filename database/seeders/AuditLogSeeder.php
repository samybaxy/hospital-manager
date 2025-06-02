<?php

namespace HospitalManager\Database\Seeders;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Seeding audit logs...');
        
        global $wpdb;
        
        // Get user IDs
        $users = $this->getUsers();
        
        if (empty($users)) {
            $this->log('No users found. Cannot create audit logs.');
            return;
        }
        
        // Get entity IDs for reference
        $patients = $this->getEntityIds('hm_patients');
        $doctors = $this->getEntityIds('hm_doctors');
        $appointments = $this->getEntityIds('hm_appointments');
        $visitations = $this->getEntityIds('hm_visitations');
        $lab_investigations = $this->getEntityIds('hm_lab_investigations');
        $medical_reports = $this->getEntityIds('hm_medical_reports');
        
        // Define audit actions
        $actions = $this->getAuditActions();
        
        // Create audit logs
        $count = 0;
        $total_logs = 200; // Total audit logs to generate
        
        for ($i = 0; $i < $total_logs; $i++) {
            // Select random user
            $user_key = array_rand($users);
            $user = $users[$user_key];
            $user_id = $user['ID'];
            $role = $user['roles'][0];
            
            // Select action based on user role
            $role_actions = $this->getActionsByRole($role, $actions);
            if (empty($role_actions)) {
                continue;
            }
            
            $action_key = array_rand($role_actions);
            $action = $role_actions[$action_key];
            
            // Get entity type and ID based on action
            list($entity_type, $entity_id) = $this->getEntityForAction($action, [
                'patients' => $patients,
                'doctors' => $doctors,
                'appointments' => $appointments,
                'visitations' => $visitations,
                'lab_investigations' => $lab_investigations,
                'medical_reports' => $medical_reports
            ]);
            
            if (!$entity_id) {
                continue;
            }
            
            // Generate changes and details
            $changes = $this->generateChanges($action, $entity_type);
            $details = $this->generateDetails($action, $entity_type, $role);
            
            // Generate creation date (last 60 days)
            $days_ago = rand(0, 60);
            $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
            
            // Build audit log data
            $data = [
                'user_id' => $user_id,
                'action' => $action,
                'entity_type' => $entity_type,
                'entity_id' => $entity_id,
                'changes' => $changes ? json_encode($changes) : null,
                'details' => $details ? json_encode($details) : null,
                'created_at' => $created_at
            ];
            
            $wpdb->insert($wpdb->prefix . 'hm_audit_logs', $data);
            $count++;
        }
        
        $this->log("Created {$count} audit logs");
    }
    
    /**
     * Get WordPress users with their roles
     * 
     * @return array Array of users with roles
     */
    protected function getUsers()
    {
        $users = get_users([
            'fields' => ['ID', 'user_login', 'display_name']
        ]);
        
        $result = [];
        foreach ($users as $user) {
            $wp_user = new \WP_User($user->ID);
            if (!empty($wp_user->roles)) {
                $user->roles = $wp_user->roles;
                $result[] = (array)$user;
            }
        }
        
        return $result;
    }
    
    /**
     * Get entity IDs from a table
     * 
     * @param string $table Table name without prefix
     * @return array Array of IDs
     */
    protected function getEntityIds($table)
    {
        global $wpdb;
        return $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}{$table} LIMIT 30");
    }
    
    /**
     * Get entity type and ID for audit action
     * 
     * @param string $action Audit action
     * @param array $entities Array of entities
     * @return array [entity_type, entity_id]
     */
    protected function getEntityForAction($action, $entities)
    {
        // Map actions to entity types
        $action_map = [
            'view_patient' => ['patient', $entities['patients']],
            'create_patient' => ['patient', $entities['patients']],
            'update_patient' => ['patient', $entities['patients']],
            'delete_patient' => ['patient', $entities['patients']],
            
            'view_doctor' => ['doctor', $entities['doctors']],
            'create_doctor' => ['doctor', $entities['doctors']],
            'update_doctor' => ['doctor', $entities['doctors']],
            'delete_doctor' => ['doctor', $entities['doctors']],
            
            'book_appointment' => ['appointment', $entities['appointments']],
            'cancel_appointment' => ['appointment', $entities['appointments']],
            'reschedule_appointment' => ['appointment', $entities['appointments']],
            'complete_appointment' => ['appointment', $entities['appointments']],
            
            'create_visitation' => ['visitation', $entities['visitations']],
            'update_visitation' => ['visitation', $entities['visitations']],
            'delete_visitation' => ['visitation', $entities['visitations']],
            
            'create_lab_request' => ['lab_investigation', $entities['lab_investigations']],
            'update_lab_results' => ['lab_investigation', $entities['lab_investigations']],
            
            'create_medical_report' => ['medical_report', $entities['medical_reports']],
            'update_medical_report' => ['medical_report', $entities['medical_reports']],
            'delete_medical_report' => ['medical_report', $entities['medical_reports']],
            
            'login' => ['user', array_column($this->getUsers(), 'ID')],
            'logout' => ['user', array_column($this->getUsers(), 'ID')],
            'password_change' => ['user', array_column($this->getUsers(), 'ID')],
            'profile_update' => ['user', array_column($this->getUsers(), 'ID')],
        ];
        
        if (isset($action_map[$action])) {
            $entity_type = $action_map[$action][0];
            $entity_ids = $action_map[$action][1];
            
            if (!empty($entity_ids)) {
                return [$entity_type, $entity_ids[array_rand($entity_ids)]];
            }
        }
        
        // Default fallbacks
        return ['unknown', rand(1, 100)];
    }
    
    /**
     * Generate changes JSON based on action and entity type
     * 
     * @param string $action Audit action
     * @param string $entity_type Entity type
     * @return array|null Changes array or null
     */
    protected function generateChanges($action, $entity_type)
    {
        // Only generate changes for update/create actions
        if (strpos($action, 'update_') !== 0 && strpos($action, 'create_') !== 0) {
            return null;
        }
        
        $changes = [];
        
        switch ($entity_type) {
            case 'patient':
                $first_names = ['John', 'Jane', 'Mary', 'Michael', 'Sarah', 'David', 'Linda', 'Robert'];
                $last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
                $phones = ['+1234567890', '+2345678901', '+3456789012', '+4567890123'];
                
                if (rand(0, 1)) {
                    $changes['first_name'] = [
                        'old' => $first_names[array_rand($first_names)],
                        'new' => $first_names[array_rand($first_names)]
                    ];
                }
                if (rand(0, 1)) {
                    $changes['last_name'] = [
                        'old' => $last_names[array_rand($last_names)],
                        'new' => $last_names[array_rand($last_names)]
                    ];
                }
                if (rand(0, 1)) {
                    $changes['phone'] = [
                        'old' => $phones[array_rand($phones)],
                        'new' => $phones[array_rand($phones)]
                    ];
                }
                break;
                
            case 'appointment':
                if (rand(0, 1)) {
                    $changes['appointment_date'] = [
                        'old' => date('Y-m-d', strtotime('-3 days')),
                        'new' => date('Y-m-d', strtotime('+3 days'))
                    ];
                }
                if (rand(0, 1)) {
                    $old_statuses = ['pending', 'confirmed'];
                    $new_statuses = ['completed', 'cancelled'];
                    $changes['status'] = [
                        'old' => $old_statuses[array_rand($old_statuses)],
                        'new' => $new_statuses[array_rand($new_statuses)]
                    ];
                }
                break;
                
            case 'lab_investigation':
                if (strpos($action, 'update_') === 0) {
                    $changes['status'] = [
                        'old' => 'pending',
                        'new' => 'completed'
                    ];
                    $changes['results'] = [
                        'old' => null,
                        'new' => 'Test results added'
                    ];
                }
                break;
                
            case 'medical_report':
                if (rand(0, 1)) {
                    $changes['status'] = [
                        'old' => 'pending',
                        'new' => 'completed'
                    ];
                }
                if (rand(0, 1)) {
                    $changes['report_content'] = [
                        'old' => 'Initial draft',
                        'new' => 'Updated report content'
                    ];
                }
                break;
                
            case 'user':
                if ($action === 'profile_update') {
                    $emails = ['test1@example.com', 'test2@example.com', 'test3@example.com', 'test4@example.com'];
                    $changes['email'] = [
                        'old' => $emails[array_rand($emails)],
                        'new' => $emails[array_rand($emails)]
                    ];
                }
                break;
        }
        
        return $changes;
    }
    
    /**
     * Generate details JSON based on action, entity type and role
     * 
     * @param string $action Audit action
     * @param string $entity_type Entity type
     * @param string $role User role
     * @return array Details array
     */
    protected function generateDetails($action, $entity_type, $role)
    {
        // Generate random IP addresses
        $ip_addresses = ['192.168.1.100', '10.0.0.25', '172.16.0.50', '192.168.0.75'];
        
        // Generate random user agents
        $user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15'
        ];
        
        $details = [
            'ip_address' => $ip_addresses[array_rand($ip_addresses)],
            'user_agent' => $user_agents[array_rand($user_agents)]
        ];
        
        if ($action === 'login' || $action === 'logout') {
            $details['timestamp'] = time();
            $details['session_id'] = md5(uniqid());
        }
        
        if ($action === 'view_patient') {
            $all_sections = [
                'personal_info',
                'medical_history',
                'appointments',
                'lab_results',
                'prescriptions'
            ];
            $num_sections = rand(1, 3);
            $details['accessed_sections'] = array_slice($all_sections, 0, $num_sections);
        }
        
        if (strpos($action, 'appointment') !== false) {
            $appointment_types = [
                'regular_checkup',
                'consultation',
                'follow_up',
                'emergency'
            ];
            $details['appointment_type'] = $appointment_types[array_rand($appointment_types)];
        }
        
        return $details;
    }
    
    /**
     * Get actions appropriate for a user role
     * 
     * @param string $role User role
     * @param array $all_actions All available actions
     * @return array Actions for the role
     */
    protected function getActionsByRole($role, $all_actions)
    {
        $role_actions = [
            'administrator' => array_keys($all_actions),
            'doctor' => [
                'view_patient',
                'update_patient',
                'create_visitation',
                'update_visitation',
                'create_lab_request',
                'create_medical_report',
                'update_medical_report',
                'login',
                'logout',
                'profile_update'
            ],
            'patient' => [
                'book_appointment',
                'cancel_appointment',
                'reschedule_appointment',
                'profile_update',
                'login',
                'logout',
                'password_change'
            ],
            'receptionist' => [
                'create_patient',
                'update_patient',
                'book_appointment',
                'cancel_appointment',
                'reschedule_appointment',
                'login',
                'logout'
            ],
            'lab_tech' => [
                'view_patient',
                'update_lab_results',
                'login',
                'logout'
            ]
        ];
        
        // Default to basic actions if role not specified
        if (!isset($role_actions[$role])) {
            return ['login', 'logout', 'profile_update'];
        }
        
        return array_intersect_key($all_actions, array_flip($role_actions[$role]));
    }
    
    /**
     * Get all possible audit actions
     * 
     * @return array Audit actions
     */
    protected function getAuditActions()
    {
        return [
            'view_patient' => 'Viewed patient record',
            'create_patient' => 'Created new patient',
            'update_patient' => 'Updated patient information',
            'delete_patient' => 'Deleted patient record',
            
            'view_doctor' => 'Viewed doctor profile',
            'create_doctor' => 'Created new doctor',
            'update_doctor' => 'Updated doctor information',
            'delete_doctor' => 'Deleted doctor record',
            
            'book_appointment' => 'Booked new appointment',
            'cancel_appointment' => 'Cancelled appointment',
            'reschedule_appointment' => 'Rescheduled appointment',
            'complete_appointment' => 'Marked appointment as completed',
            
            'create_visitation' => 'Created patient visitation record',
            'update_visitation' => 'Updated visitation record',
            'delete_visitation' => 'Deleted visitation record',
            
            'create_lab_request' => 'Requested lab investigation',
            'update_lab_results' => 'Updated lab results',
            
            'create_medical_report' => 'Created medical report',
            'update_medical_report' => 'Updated medical report',
            'delete_medical_report' => 'Deleted medical report',
            
            'login' => 'User logged in',
            'logout' => 'User logged out',
            'password_change' => 'User changed password',
            'profile_update' => 'User updated profile'
        ];
    }
}

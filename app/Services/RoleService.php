<?php

namespace HospitalManager\Services;

class RoleService extends BaseService
{
    // Hospital roles
    const ROLE_ADMIN = 'administrator';
    const ROLE_DOCTOR = 'doctor';
    const ROLE_NURSE = 'hospital_nurse';
    const ROLE_STAFF = 'hospital_staff';
    const ROLE_PATIENT = 'patient';
    const ROLE_LAB_TECH = 'lab_tech';
    const ROLE_DESK_OFFICER = 'developer';
    const ROLE_INVENTORY_MANAGER = 'inventory_manager';
    const ROLE_PHARMACY = 'pharmacy_staff';

    // Grouped permissions for better organization
    const INVENTORY_PERMISSIONS = [
        'inventory_view',
        'inventory_create',
        'inventory_edit',
        'inventory_delete',
        'inventory_export',
        'inventory_reports',
        'inventory_bulk_operations',
        'inventory_critical_items',
    ];

    const ACCESS_PERMISSIONS = [
        'access_patients',
        'access_doctors',
        'access_departments',
        'access_appointments',
        'access_visitations',
        'access_chat',
        'access_notifications',
        'access_audit_log',
        'access_billing',
        'access_inventory',
        'access_reports',
        'access_statistics',
        'access_settings',
        'access_lab_dashboard',
    ];

    /**
     * Get all permissions for a role
     * 
     * @param string $role Role name
     * @return array Array of permissions
     */
    public static function getRolePermissions($role)
    {
        $role_permissions = [
            self::ROLE_ADMIN => array_merge(self::INVENTORY_PERMISSIONS, self::ACCESS_PERMISSIONS),
            self::ROLE_INVENTORY_MANAGER => array_merge(
                self::INVENTORY_PERMISSIONS,
                ['access_inventory', 'access_reports', 'access_notifications']
            ),
            self::ROLE_PHARMACY => array_merge(
                array_diff(self::INVENTORY_PERMISSIONS, ['inventory_delete', 'inventory_bulk_operations']),
                ['access_inventory', 'access_patients', 'access_reports', 'access_notifications']
            ),
            self::ROLE_DOCTOR => [
                'inventory_view',
                'inventory_reports',
                'inventory_critical_items',
                'access_patients',
                'access_doctors',
                'access_departments',
                'access_appointments',
                'access_visitations',
                'access_chat',
                'access_notifications',
                'access_reports',
                'access_lab_dashboard',
            ],
            self::ROLE_NURSE => [
                'inventory_view',
                'inventory_critical_items',
                'access_patients',
                'access_doctors',
                'access_departments',
                'access_appointments',
                'access_visitations',
                'access_chat',
                'access_notifications',
                'access_lab_dashboard',
            ],
            self::ROLE_STAFF => [
                'inventory_view',
                'access_patients',
                'access_doctors',
                'access_departments',
                'access_appointments',
                'access_visitations',
                'access_notifications',
                'access_reports',
                'access_lab_dashboard',
            ],
            self::ROLE_LAB_TECH => [
                'access_patients',
                'access_notifications',
                'access_lab_dashboard',
            ],
            self::ROLE_DESK_OFFICER => [
                'access_patients',
                'access_doctors',
                'access_departments',
                'access_appointments',
                'access_visitations',
                'access_notifications',
                'access_reports',
                'access_lab_dashboard',
            ],
            self::ROLE_PATIENT => [
                'access_doctors',
                'access_appointments',
                'access_visitations',
                'access_chat',
                'access_notifications',
                'access_reports',
                'access_lab_dashboard',
            ],
        ];

        return $role_permissions[$role] ?? [];
    }

    /**
     * Initialize all custom roles for the hospital management system
     */
    public static function initializeRoles()
    {
        self::add_roles();
        self::initializeInventoryRoles();
    }

    /**
     * Add all hospital-specific roles
     */
    private static function add_roles()
    {
        // Define common route access capabilities
        $route_capabilities = [
            'access_patients' => 'Access to patients page',
            'access_doctors' => 'Access to doctors page',
            'access_departments' => 'Access to departments page',
            'access_appointments' => 'Access to appointments page',
            'access_visitations' => 'Access to visitations page',
            'access_chat' => 'Access to chat feature',
            'access_notifications' => 'Access to notifications',
            'access_audit_log' => 'Access to audit logs',
            'access_billing' => 'Access to billing system',
            'access_inventory' => 'Access to inventory management',
            'access_reports' => 'Access to reports',
            'access_statistics' => 'Access to statistics',
            'access_settings' => 'Access to system settings',
            'access_lab_dashboard' => 'Access to laboratory dashboard',
        ];

        // Register all capabilities with WordPress
        foreach ($route_capabilities as $capability => $description) {
            if (!get_role('administrator')->has_cap($capability)) {
                // Only add if the capability doesn't exist already
                add_role('temp_role', 'Temporary Role', [$capability => true]);
                remove_role('temp_role');
            }
        }

        // Update existing administrator role with hospital capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = [
                'read' => true,
                'access_hospital_manager' => true,
                'view_patients' => true,
                'edit_patient' => true,
                'delete_patients' => true,
                'schedule_appointments' => true,
                'add_visitation' => true,
                'edit_visitation' => true,
                'manage_medical_reports' => true,
                // Grant all route access capabilities to administrators
                'access_patients' => true,
                'access_doctors' => true,
                'access_departments' => true,
                'access_appointments' => true,
                'access_visitations' => true,
                'access_chat' => true,
                'access_notifications' => true,
                'access_audit_log' => true,
                'access_billing' => true,
                'access_inventory' => true,
                'access_reports' => true,
                'access_statistics' => true,
                'access_settings' => true,
                'access_lab_dashboard' => true,
            ];
            
            foreach ($admin_capabilities as $cap => $enabled) {
                $admin_role->add_cap($cap, $enabled);
            }
        }

        // Add Doctor role
        add_role('doctor', 'Doctor', [
            'read' => true,
            'access_hospital_manager' => true,
            'create_patients' => true,
            'edit_patient' => false,
            'delete_patients' => false,
            'edit_doctors' => false,
            'add_lab_results' => false,
            'edit_lab_results' => false,
            'delete_lab_results' => false,
            'schedule_appointments' => false,
            'add_visitation' => true,
            'edit_visitation' => true,
            'access_own_records' => true,
            'access_patients' => true,
            'access_doctors' => true,
            'access_departments' => true,
            'access_appointments' => true,
            'access_visitations' => true,
            'access_chat' => true,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => true,
            'access_statistics' => true,
            'access_settings' => false,
            'access_lab_dashboard' => true,
        ]);

        // Add Patient role
        add_role('patient', 'Patient', [
            'read' => true,
            'access_hospital_manager' => true,
            'create_patients' => false,
            'edit_patients' => false,
            'edit_doctors' => false,
            'delete_patients' => false,
            'add_lab_results' => false,
            'edit_lab_results' => false,
            'delete_lab_results' => false,
            'access_own_records' => true,
            'access_patients' => false,
            'access_doctors' => true,
            'access_departments' => false,
            'access_appointments' => true,
            'access_visitations' => true,
            'access_chat' => true,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => true,
            'access_statistics' => false,
            'access_settings' => false,
            'access_lab_dashboard' => true,
        ]);

        // Add Lab Technician role
        add_role('lab_tech', 'Lab Technician', [
            'read' => true,
            'access_hospital_manager' => true,
            'manage_medical_reports' => false,
            'create_patients' => false,
            'edit_patients' => false,
            'edit_doctors' => false,
            'delete_patients' => false,
            'add_lab_results' => false,
            'edit_lab_results' => false,
            'delete_lab_results' => false,
            'access_patients' => true,
            'access_doctors' => false,
            'access_departments' => false,
            'access_appointments' => false,
            'access_visitations' => false,
            'access_chat' => false,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => false,
            'access_statistics' => false,
            'access_settings' => false,
            'access_lab_dashboard' => true,
        ]);

        // Add Developer role
        add_role('developer', 'Developer', [
            'read' => true,
            'access_hospital_manager' => true,
            'create_patients' => true,
            'edit_patients' => true,
            'edit_doctors' => false,
            'delete_patients' => false,
            'schedule_appointments' => false,
            'add_lab_results' => false,
            'edit_lab_results' => false,
            'delete_lab_results' => false,
            'access_patients' => true,
            'access_doctors' => true,
            'access_departments' => true,
            'access_appointments' => true,
            'access_visitations' => true,
            'access_chat' => false,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => true,
            'access_statistics' => false,
            'access_settings' => false,
            'access_lab_dashboard' => true,
        ]);
        
        // Add Hospital Nurse role
        add_role('hospital_nurse', 'Hospital Nurse', [
            'read' => true,
            'access_hospital_manager' => true,
            'create_patients' => false,
            'edit_patients' => true,
            'edit_doctors' => false,
            'delete_patients' => false,
            'schedule_appointments' => true,
            'add_lab_results' => false,
            'edit_lab_results' => false,
            'delete_lab_results' => false,
            'add_visitation' => true,
            'edit_visitation' => true,
            'access_patients' => true,
            'access_doctors' => true,
            'access_departments' => true,
            'access_appointments' => true,
            'access_visitations' => true,
            'access_chat' => true,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => true,
            'access_statistics' => false,
            'access_settings' => false,
            'access_lab_dashboard' => false,
        ]);
        
        // Add Hospital Staff role
        add_role('hospital_staff', 'Hospital Staff', [
            'read' => true,
            'access_hospital_manager' => true,
            'create_patients' => true,
            'edit_patients' => true,
            'edit_doctors' => false,
            'delete_patients' => false,
            'schedule_appointments' => true,
            'add_lab_results' => false,
            'edit_lab_results' => false,
            'delete_lab_results' => false,
            'access_patients' => true,
            'access_doctors' => true,
            'access_departments' => true,
            'access_appointments' => true,
            'access_visitations' => false,
            'access_chat' => true,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => true,
            'access_inventory' => false,
            'access_reports' => true,
            'access_statistics' => true,
            'access_settings' => false,
            'access_lab_dashboard' => false,
        ]);
    }

    /**
     * Initialize hospital-specific roles and capabilities for inventory
     */
    private static function initializeInventoryRoles()
    {
        // Remove default roles if needed and add hospital-specific roles
        $roles = [
            self::ROLE_ADMIN => [
                'display_name' => 'Hospital Administrator',
                'capabilities' => array_merge(
                    get_role('administrator')->capabilities ?? [],
                    array_fill_keys(self::getRolePermissions(self::ROLE_ADMIN), true)
                )
            ],
            self::ROLE_INVENTORY_MANAGER => [
                'display_name' => 'Inventory Manager',
                'capabilities' => array_fill_keys(self::getRolePermissions(self::ROLE_INVENTORY_MANAGER), true)
            ],
            self::ROLE_PHARMACY => [
                'display_name' => 'Pharmacy Staff',
                'capabilities' => array_fill_keys(self::getRolePermissions(self::ROLE_PHARMACY), true)
            ],
        ];

        foreach ($roles as $role_name => $role_data) {
            // Remove existing role to update capabilities
            remove_role($role_name);
            
            // Add role with updated capabilities
            add_role($role_name, $role_data['display_name'], $role_data['capabilities']);
        }

        // Grant inventory permissions to existing roles
        self::updateExistingRolesWithInventoryPermissions();

        // Grant inventory permissions to WordPress admin
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (self::getRolePermissions(self::ROLE_ADMIN) as $capability) {
                $admin_role->add_cap($capability);
            }
        }
    }

    /**
     * Update existing roles with inventory permissions
     */
    private static function updateExistingRolesWithInventoryPermissions()
    {
        $existing_roles = [
            'doctor' => self::getRolePermissions(self::ROLE_DOCTOR),
            'patient' => self::getRolePermissions(self::ROLE_PATIENT),
            'lab_tech' => self::getRolePermissions(self::ROLE_LAB_TECH),
            'developer' => self::getRolePermissions(self::ROLE_DESK_OFFICER),
        ];

        $existing_roles['hospital_nurse'] = self::getRolePermissions(self::ROLE_NURSE);
        $existing_roles['hospital_staff'] = self::getRolePermissions(self::ROLE_STAFF);
        
        foreach ($existing_roles as $role_name => $permissions) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($permissions as $permission) {
                    $role->add_cap($permission);
                }
            }
        }
    }

    /**
     * Get route access map based on user role
     * 
     * @param string $user_role User role name
     * @return array Route access map with route keys and boolean values
     */
    public static function getRouteAccessMap($user_role)
    {
        if (!$user_role) {
            return [];
        }
        
        $role = get_role($user_role);
        
        if (!$role) {
            return [];
        }
        
        // Get all capabilities for this role
        $capabilities = $role->capabilities;
        
        // Map route access capabilities to route names
        $route_access_map = [
            'patients' => isset($capabilities['access_patients']) ? $capabilities['access_patients'] : false,
            'doctors' => isset($capabilities['access_doctors']) ? $capabilities['access_doctors'] : false,
            'departments' => isset($capabilities['access_departments']) ? $capabilities['access_departments'] : false,
            'appointments' => isset($capabilities['access_appointments']) ? $capabilities['access_appointments'] : false,
            'visitations' => isset($capabilities['access_visitations']) ? $capabilities['access_visitations'] : false,
            'chat' => isset($capabilities['access_chat']) ? $capabilities['access_chat'] : false,
            'notifications' => isset($capabilities['access_notifications']) ? $capabilities['access_notifications'] : false,
            'audit_log' => isset($capabilities['access_audit_log']) ? $capabilities['access_audit_log'] : false,
            'billing' => isset($capabilities['access_billing']) ? $capabilities['access_billing'] : false,
            'inventory' => isset($capabilities['access_inventory']) ? $capabilities['access_inventory'] : false,
            'reports' => isset($capabilities['access_reports']) ? $capabilities['access_reports'] : false,
            'statistics' => isset($capabilities['access_statistics']) ? $capabilities['access_statistics'] : false,
            'settings' => isset($capabilities['access_settings']) ? $capabilities['access_settings'] : false,
            'lab_dashboard' => isset($capabilities['access_lab_dashboard']) ? $capabilities['access_lab_dashboard'] : false,
        ];
        
        return $route_access_map;
    }

    /**
     * Check if current user has specific inventory permission
     */
    public static function hasPermission($permission)
    {
        return current_user_can($permission);
    }

    /**
     * Check if user can view inventory
     *
     * @return bool
     */
    public static function canViewInventory()
    {
        return current_user_can('manage_inventory') || current_user_can('view_inventory') || current_user_can('manage_options');
    }

    /**
     * Check if user can create inventory items
     *
     * @return bool
     */
    public static function canCreateInventory()
    {
        return current_user_can('manage_inventory') || current_user_can('manage_options');
    }

    /**
     * Check if user can edit inventory items
     *
     * @return bool
     */
    public static function canEditInventory()
    {
        return current_user_can('manage_inventory') || current_user_can('manage_options');
    }

    /**
     * Check if user can delete inventory items
     *
     * @return bool
     */
    public static function canDeleteInventory()
    {
        return current_user_can('manage_inventory') || current_user_can('manage_options');
    }

    /**
     * Check if user can view critical items
     *
     * @return bool
     */
    public static function canViewCriticalItems()
    {
        return current_user_can('manage_inventory') || current_user_can('view_inventory') || current_user_can('manage_options');
    }

    /**
     * Check if user can view reports
     *
     * @return bool
     */
    public static function canViewReports()
    {
        return current_user_can('manage_inventory') || current_user_can('view_inventory') || current_user_can('manage_options');
    }

    /**
     * Check if user can perform bulk operations
     *
     * @return bool
     */
    public static function canPerformBulkOperations()
    {
        return current_user_can('manage_inventory') || current_user_can('manage_options');
    }

    /**
     * Check if user can export inventory
     *
     * @return bool
     */
    public static function canExportInventory()
    {
        return current_user_can('manage_inventory') || current_user_can('view_inventory') || current_user_can('manage_options');
    }

    /**
     * Get user's inventory permissions
     *
     * @return array
     */
    public static function getUserInventoryPermissions()
    {
        return [
            'view' => self::canViewInventory(),
            'create' => self::canCreateInventory(),
            'edit' => self::canEditInventory(),
            'delete' => self::canDeleteInventory(),
            'export' => self::canExportInventory(),
            'reports' => self::canViewReports(),
            'bulk_operations' => self::canPerformBulkOperations(),
            'critical_items' => self::canViewCriticalItems(),
        ];
    }

    /**
     * Get user's role display name
     *
     * @return string
     */
    public static function getUserRoleDisplayName()
    {
        $user = wp_get_current_user();
        if (empty($user->roles)) {
            return 'No Role';
        }
        
        $role = $user->roles[0];
        $wp_roles = wp_roles();
        
        return isset($wp_roles->role_names[$role]) ? $wp_roles->role_names[$role] : ucfirst($role);
    }

    /**
     * Get all available hospital roles
     */
    public static function getAvailableRoles()
    {
        return [
            self::ROLE_ADMIN => 'Hospital Administrator',
            self::ROLE_INVENTORY_MANAGER => 'Inventory Manager',
            self::ROLE_PHARMACY => 'Pharmacy Staff',
            self::ROLE_DOCTOR => 'Doctor',
            self::ROLE_NURSE => 'Nurse',
            self::ROLE_STAFF => 'Hospital Staff',
            self::ROLE_LAB_TECH => 'Lab Technician',
            self::ROLE_DESK_OFFICER => 'Developer',
            self::ROLE_PATIENT => 'Patient',
        ];
    }

    /**
     * Cleanup roles on plugin deactivation
     */
    public static function cleanupRoles()
    {
        $hospital_roles = [
            self::ROLE_INVENTORY_MANAGER,
            self::ROLE_PHARMACY,
            self::ROLE_DOCTOR,
            self::ROLE_NURSE,
            self::ROLE_STAFF,
            self::ROLE_LAB_TECH,
            self::ROLE_DESK_OFFICER,
            self::ROLE_PATIENT,
        ];

        foreach ($hospital_roles as $role) {
            remove_role($role);
        }
    }
}

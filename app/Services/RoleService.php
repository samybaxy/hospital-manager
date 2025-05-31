<?php

namespace HospitalManager\Services;

class RoleService
{
    const ROLE_ADMIN = 'hospital_admin';
    const ROLE_DOCTOR = 'hospital_doctor';
    const ROLE_NURSE = 'hospital_nurse';
    const ROLE_STAFF = 'hospital_staff';
    const ROLE_INVENTORY_MANAGER = 'inventory_manager';
    const ROLE_PHARMACY = 'pharmacy_staff';

    // Inventory permissions
    const PERM_INVENTORY_VIEW = 'inventory_view';
    const PERM_INVENTORY_CREATE = 'inventory_create';
    const PERM_INVENTORY_EDIT = 'inventory_edit';
    const PERM_INVENTORY_DELETE = 'inventory_delete';
    const PERM_INVENTORY_EXPORT = 'inventory_export';
    const PERM_INVENTORY_REPORTS = 'inventory_reports';
    const PERM_INVENTORY_BULK_OPERATIONS = 'inventory_bulk_operations';
    const PERM_INVENTORY_CRITICAL_ITEMS = 'inventory_critical_items';

    private static $rolePermissions = [
        self::ROLE_ADMIN => [
            self::PERM_INVENTORY_VIEW,
            self::PERM_INVENTORY_CREATE,
            self::PERM_INVENTORY_EDIT,
            self::PERM_INVENTORY_DELETE,
            self::PERM_INVENTORY_EXPORT,
            self::PERM_INVENTORY_REPORTS,
            self::PERM_INVENTORY_BULK_OPERATIONS,
            self::PERM_INVENTORY_CRITICAL_ITEMS,
        ],
        self::ROLE_INVENTORY_MANAGER => [
            self::PERM_INVENTORY_VIEW,
            self::PERM_INVENTORY_CREATE,
            self::PERM_INVENTORY_EDIT,
            self::PERM_INVENTORY_DELETE,
            self::PERM_INVENTORY_EXPORT,
            self::PERM_INVENTORY_REPORTS,
            self::PERM_INVENTORY_BULK_OPERATIONS,
            self::PERM_INVENTORY_CRITICAL_ITEMS,
        ],
        self::ROLE_PHARMACY => [
            self::PERM_INVENTORY_VIEW,
            self::PERM_INVENTORY_CREATE,
            self::PERM_INVENTORY_EDIT,
            self::PERM_INVENTORY_EXPORT,
            self::PERM_INVENTORY_REPORTS,
            self::PERM_INVENTORY_CRITICAL_ITEMS,
        ],
        self::ROLE_DOCTOR => [
            self::PERM_INVENTORY_VIEW,
            self::PERM_INVENTORY_REPORTS,
            self::PERM_INVENTORY_CRITICAL_ITEMS,
        ],
        self::ROLE_NURSE => [
            self::PERM_INVENTORY_VIEW,
            self::PERM_INVENTORY_CRITICAL_ITEMS,
        ],
        self::ROLE_STAFF => [
            self::PERM_INVENTORY_VIEW,
        ],
    ];

    /**
     * Initialize hospital-specific roles and capabilities
     */
    public static function initializeRoles()
    {
        // Remove default roles if needed and add hospital-specific roles
        $roles = [
            self::ROLE_ADMIN => [
                'display_name' => 'Hospital Administrator',
                'capabilities' => array_merge(
                    get_role('administrator')->capabilities ?? [],
                    array_fill_keys(self::$rolePermissions[self::ROLE_ADMIN], true)
                )
            ],
            self::ROLE_INVENTORY_MANAGER => [
                'display_name' => 'Inventory Manager',
                'capabilities' => array_fill_keys(self::$rolePermissions[self::ROLE_INVENTORY_MANAGER], true)
            ],
            self::ROLE_PHARMACY => [
                'display_name' => 'Pharmacy Staff',
                'capabilities' => array_fill_keys(self::$rolePermissions[self::ROLE_PHARMACY], true)
            ],
            self::ROLE_DOCTOR => [
                'display_name' => 'Doctor',
                'capabilities' => array_fill_keys(self::$rolePermissions[self::ROLE_DOCTOR], true)
            ],
            self::ROLE_NURSE => [
                'display_name' => 'Nurse',
                'capabilities' => array_fill_keys(self::$rolePermissions[self::ROLE_NURSE], true)
            ],
            self::ROLE_STAFF => [
                'display_name' => 'Hospital Staff',
                'capabilities' => array_fill_keys(self::$rolePermissions[self::ROLE_STAFF], true)
            ],
        ];

        foreach ($roles as $role_name => $role_data) {
            // Remove existing role to update capabilities
            remove_role($role_name);
            
            // Add role with updated capabilities
            add_role($role_name, $role_data['display_name'], $role_data['capabilities']);
        }

        // Grant inventory permissions to WordPress admin
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (self::$rolePermissions[self::ROLE_ADMIN] as $capability) {
                $admin_role->add_cap($capability);
            }
        }
    }

    /**
     * Check if current user has specific inventory permission
     */
    public static function hasPermission($permission)
    {
        return current_user_can($permission);
    }

    /**
     * Check if current user can perform inventory actions
     */
    public static function canViewInventory()
    {
        return self::hasPermission(self::PERM_INVENTORY_VIEW);
    }

    public static function canCreateInventory()
    {
        return self::hasPermission(self::PERM_INVENTORY_CREATE);
    }

    public static function canEditInventory()
    {
        return self::hasPermission(self::PERM_INVENTORY_EDIT);
    }

    public static function canDeleteInventory()
    {
        return self::hasPermission(self::PERM_INVENTORY_DELETE);
    }

    public static function canExportInventory()
    {
        return self::hasPermission(self::PERM_INVENTORY_EXPORT);
    }

    public static function canViewReports()
    {
        return self::hasPermission(self::PERM_INVENTORY_REPORTS);
    }

    public static function canPerformBulkOperations()
    {
        return self::hasPermission(self::PERM_INVENTORY_BULK_OPERATIONS);
    }

    public static function canViewCriticalItems()
    {
        return self::hasPermission(self::PERM_INVENTORY_CRITICAL_ITEMS);
    }

    /**
     * Get user's role display name
     */
    public static function getUserRoleDisplayName($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->roles)) {
            return 'No Role';
        }

        $role_names = [
            self::ROLE_ADMIN => 'Hospital Administrator',
            self::ROLE_INVENTORY_MANAGER => 'Inventory Manager',
            self::ROLE_PHARMACY => 'Pharmacy Staff',
            self::ROLE_DOCTOR => 'Doctor',
            self::ROLE_NURSE => 'Nurse',
            self::ROLE_STAFF => 'Hospital Staff',
            'administrator' => 'Administrator',
        ];

        $primary_role = $user->roles[0];
        return $role_names[$primary_role] ?? ucfirst(str_replace('_', ' ', $primary_role));
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
        ];
    }

    /**
     * Get user's inventory permissions
     */
    public static function getUserInventoryPermissions($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $permissions = [
            'view' => self::canViewInventory(),
            'create' => self::canCreateInventory(),
            'edit' => self::canEditInventory(),
            'delete' => self::canDeleteInventory(),
            'export' => self::canExportInventory(),
            'reports' => self::canViewReports(),
            'bulk_operations' => self::canPerformBulkOperations(),
            'critical_items' => self::canViewCriticalItems(),
        ];

        return $permissions;
    }

    /**
     * Cleanup roles on plugin deactivation
     */
    public static function cleanupRoles()
    {
        $hospital_roles = [
            self::ROLE_ADMIN,
            self::ROLE_INVENTORY_MANAGER,
            self::ROLE_PHARMACY,
            self::ROLE_DOCTOR,
            self::ROLE_NURSE,
            self::ROLE_STAFF,
        ];

        foreach ($hospital_roles as $role) {
            remove_role($role);
        }

        // Remove inventory capabilities from administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $all_permissions = array_merge(...array_values(self::$rolePermissions));
            foreach (array_unique($all_permissions) as $capability) {
                $admin_role->remove_cap($capability);
            }
        }
    }
}

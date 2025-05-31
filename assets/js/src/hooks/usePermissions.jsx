import { useState, useEffect } from 'react';
import roleService from '../services/roleService';

/**
 * Hook for managing role-based permissions in React components
 */
export const usePermissions = () => {
  const [permissions, setPermissions] = useState(null);
  const [userRole, setUserRole] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const initializePermissions = async () => {
      try {
        setLoading(true);
        setError(null);

        await roleService.initialize();
        const userPermissions = await roleService.getPermissions();
        const role = await roleService.getUserRole();

        setPermissions(userPermissions);
        setUserRole(role);
      } catch (err) {
        setError(err.message);
        console.error('Failed to initialize permissions:', err);
      } finally {
        setLoading(false);
      }
    };

    initializePermissions();
  }, []);

  return {
    permissions,
    userRole,
    loading,
    error,
    
    // Convenience methods
    hasPermission: (permission) => permissions?.[permission] || false,
    hasAnyPermission: (perms) => perms.some(p => permissions?.[p]),
    hasAllPermissions: (perms) => perms.every(p => permissions?.[p]),
    
    // Inventory-specific checks
    canView: permissions?.view || false,
    canCreate: permissions?.create || false,
    canEdit: permissions?.edit || false,
    canDelete: permissions?.delete || false,
    canExport: permissions?.export || false,
    canViewReports: permissions?.reports || false,
    canBulkOperations: permissions?.bulk_operations || false,
    canViewCritical: permissions?.critical_items || false,
  };
};

/**
 * HOC for wrapping components with permission checking
 */
export const withPermissions = (WrappedComponent, requiredPermissions = []) => {
  return function PermissionWrappedComponent(props) {
    const { permissions, loading, error, hasAllPermissions } = usePermissions();

    if (loading) {
      return (
        <div className="flex items-center justify-center p-8">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span className="ml-2 text-gray-600">Loading permissions...</span>
        </div>
      );
    }

    if (error) {
      return (
        <div className="bg-red-50 border border-red-200 rounded-md p-4">
          <div className="text-red-800 font-medium">Permission Error</div>
          <div className="text-red-600 text-sm mt-1">{error}</div>
        </div>
      );
    }

    if (requiredPermissions.length > 0 && !hasAllPermissions(requiredPermissions)) {
      return (
        <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
          <div className="text-yellow-800 font-medium">Access Restricted</div>
          <div className="text-yellow-600 text-sm mt-1">
            You do not have permission to access this feature.
          </div>
        </div>
      );
    }

    return <WrappedComponent {...props} permissions={permissions} userRole={userRole} />;
  };
};

/**
 * Component for conditionally rendering content based on permissions
 */
export const PermissionGate = ({ 
  children, 
  permission, 
  permissions = [], 
  requireAll = false,
  fallback = null 
}) => {
  const { hasPermission, hasAllPermissions, hasAnyPermission } = usePermissions();

  let hasAccess = false;

  if (permission) {
    hasAccess = hasPermission(permission);
  } else if (permissions.length > 0) {
    hasAccess = requireAll ? hasAllPermissions(permissions) : hasAnyPermission(permissions);
  } else {
    hasAccess = true; // No restrictions
  }

  return hasAccess ? children : fallback;
};

/**
 * Hook for getting filtered actions based on permissions
 */
export const useInventoryActions = () => {
  const permissions = usePermissions();

  const getTableActions = (item) => {
    const actions = [];

    if (permissions.canEdit) {
      actions.push({
        key: 'edit',
        label: 'Edit',
        icon: 'edit',
        variant: 'secondary',
        onClick: () => {} // To be implemented by component
      });
    }

    if (permissions.canDelete) {
      actions.push({
        key: 'delete',
        label: 'Delete',
        icon: 'trash',
        variant: 'danger',
        onClick: () => {} // To be implemented by component
      });
    }

    return actions;
  };

  const getHeaderActions = () => {
    const actions = [];

    if (permissions.canCreate) {
      actions.push({
        key: 'create',
        label: 'Add Item',
        icon: 'plus',
        variant: 'primary',
        shortcut: 'Ctrl+N'
      });
    }

    if (permissions.canExport) {
      actions.push({
        key: 'export',
        label: 'Export CSV',
        icon: 'download',
        variant: 'secondary'
      });
    }

    if (permissions.canBulkOperations) {
      actions.push({
        key: 'bulk',
        label: 'Bulk Actions',
        icon: 'layers',
        variant: 'secondary'
      });
    }

    return actions;
  };

  const getAvailableTabs = () => {
    const tabs = [];

    if (permissions.canView) {
      tabs.push({
        key: 'inventory',
        label: 'Inventory Items',
        icon: 'package'
      });
    }

    if (permissions.canViewReports) {
      tabs.push({
        key: 'reports',
        label: 'Reports & Analytics',
        icon: 'chart-bar'
      });
    }

    return tabs;
  };

  return {
    ...permissions,
    getTableActions,
    getHeaderActions,
    getAvailableTabs
  };
};

export default usePermissions;

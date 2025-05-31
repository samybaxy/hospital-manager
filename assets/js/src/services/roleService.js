/**
 * Service for handling role-based access control in the frontend
 */

class RoleService {
  constructor() {
    this.permissions = null;
    this.userRole = null;
    this.initialized = false;
  }

  /**
   * Initialize the role service by fetching user permissions
   */
  async initialize() {
    if (this.initialized) {
      return;
    }

    try {
      const response = await fetch(`${hospitalManagerData.apiUrl}/inventory/permissions`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': hospitalManagerData.nonce
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      
      if (result.success) {
        this.permissions = result.data.permissions;
        this.userRole = result.data.role;
        this.initialized = true;
      } else {
        throw new Error(result.message || 'Failed to fetch permissions');
      }
    } catch (error) {
      console.error('Failed to initialize role service:', error);
      // Set minimal permissions for safety
      this.permissions = {
        view: false,
        create: false,
        edit: false,
        delete: false,
        export: false,
        reports: false,
        bulk_operations: false,
        critical_items: false
      };
      this.userRole = 'Unknown';
      this.initialized = true;
    }
  }

  /**
   * Ensure the service is initialized before checking permissions
   */
  async ensureInitialized() {
    if (!this.initialized) {
      await this.initialize();
    }
  }

  /**
   * Check if user has a specific permission
   */
  async hasPermission(permission) {
    await this.ensureInitialized();
    return this.permissions[permission] || false;
  }

  /**
   * Check multiple permissions at once
   */
  async hasAnyPermission(permissions) {
    await this.ensureInitialized();
    return permissions.some(permission => this.permissions[permission]);
  }

  /**
   * Check if user has all specified permissions
   */
  async hasAllPermissions(permissions) {
    await this.ensureInitialized();
    return permissions.every(permission => this.permissions[permission]);
  }

  /**
   * Get all user permissions
   */
  async getPermissions() {
    await this.ensureInitialized();
    return { ...this.permissions };
  }

  /**
   * Get user role
   */
  async getUserRole() {
    await this.ensureInitialized();
    return this.userRole;
  }

  /**
   * Specific inventory permission checks
   */
  async canViewInventory() {
    return await this.hasPermission('view');
  }

  async canCreateInventory() {
    return await this.hasPermission('create');
  }

  async canEditInventory() {
    return await this.hasPermission('edit');
  }

  async canDeleteInventory() {
    return await this.hasPermission('delete');
  }

  async canExportInventory() {
    return await this.hasPermission('export');
  }

  async canViewReports() {
    return await this.hasPermission('reports');
  }

  async canPerformBulkOperations() {
    return await this.hasPermission('bulk_operations');
  }

  async canViewCriticalItems() {
    return await this.hasPermission('critical_items');
  }

  /**
   * Get permission-based actions for UI rendering
   */
  async getInventoryActions() {
    await this.ensureInitialized();
    
    const actions = [];
    
    if (this.permissions.create) {
      actions.push({
        key: 'create',
        label: 'Add Item',
        icon: 'plus',
        variant: 'primary'
      });
    }
    
    if (this.permissions.export) {
      actions.push({
        key: 'export',
        label: 'Export',
        icon: 'download',
        variant: 'secondary'
      });
    }
    
    if (this.permissions.bulk_operations) {
      actions.push({
        key: 'bulk',
        label: 'Bulk Actions',
        icon: 'layers',
        variant: 'secondary'
      });
    }
    
    return actions;
  }

  /**
   * Get permission-based tabs for the inventory page
   */
  async getInventoryTabs() {
    await this.ensureInitialized();
    
    const tabs = [];
    
    if (this.permissions.view) {
      tabs.push({
        key: 'inventory',
        label: 'Inventory Items',
        component: 'InventoryList'
      });
    }
    
    if (this.permissions.reports) {
      tabs.push({
        key: 'reports',
        label: 'Reports & Analytics',
        component: 'InventoryReports'
      });
    }
    
    return tabs;
  }

  /**
   * Filter table columns based on permissions
   */
  async getVisibleColumns(allColumns) {
    await this.ensureInitialized();
    
    return allColumns.filter(column => {
      // Hide sensitive columns for users without appropriate permissions
      if (column.key === 'cost' && !this.permissions.reports) {
        return false;
      }
      
      if (column.key === 'actions' && !this.permissions.edit && !this.permissions.delete) {
        return false;
      }
      
      return true;
    });
  }

  /**
   * Get filtered actions for table rows
   */
  async getRowActions(item) {
    await this.ensureInitialized();
    
    const actions = [];
    
    if (this.permissions.edit) {
      actions.push({
        key: 'edit',
        label: 'Edit',
        icon: 'edit',
        variant: 'secondary'
      });
    }
    
    if (this.permissions.delete) {
      actions.push({
        key: 'delete',
        label: 'Delete',
        icon: 'trash',
        variant: 'danger'
      });
    }
    
    return actions;
  }

  /**
   * Check if user can access a specific section
   */
  async canAccessSection(section) {
    await this.ensureInitialized();
    
    const sectionPermissions = {
      'critical-items': this.permissions.critical_items,
      'reports': this.permissions.reports,
      'bulk-operations': this.permissions.bulk_operations,
      'export': this.permissions.export
    };
    
    return sectionPermissions[section] || false;
  }

  /**
   * Get permission message for disabled features
   */
  getPermissionMessage(permission) {
    const messages = {
      'view': 'You do not have permission to view inventory items.',
      'create': 'You do not have permission to create inventory items.',
      'edit': 'You do not have permission to edit inventory items.',
      'delete': 'You do not have permission to delete inventory items.',
      'export': 'You do not have permission to export inventory data.',
      'reports': 'You do not have permission to view reports.',
      'bulk_operations': 'You do not have permission to perform bulk operations.',
      'critical_items': 'You do not have permission to view critical items.'
    };
    
    return messages[permission] || 'You do not have permission to perform this action.';
  }
}

// Create and export a singleton instance
const roleService = new RoleService();
export default roleService;

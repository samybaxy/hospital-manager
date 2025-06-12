/**
 * Unified User Access Service
 * Single source of truth for all access control throughout the application
 */

import { api } from './apiService';

class UserAccessService {
  constructor() {
    this.accessData = null;
    this.loading = false;
    this.error = null;
    this.listeners = [];
  }

  /**
   * Subscribe to access data changes
   * @param {Function} callback - Callback function to call when access data changes
   * @returns {Function} - Unsubscribe function
   */
  subscribe(callback) {
    this.listeners.push(callback);
    return () => {
      this.listeners = this.listeners.filter(listener => listener !== callback);
    };
  }

  /**
   * Notify all listeners of access data changes
   */
  notify() {
    this.listeners.forEach(listener => listener(this.accessData, this.loading, this.error));
  }

  /**
   * Fetch access permissions from the database via API
   * @returns {Promise<Object>} - Access data containing role and permissions
   */
  async fetchAccessData() {
    if (this.loading) {
      return this.accessData;
    }

    try {
      this.loading = true;
      this.error = null;
      this.notify();

      const response = await api.get('/access');
      
      if (response.data && response.data.success) {
        this.accessData = {
          role: response.data.data.role,
          permissions: response.data.data.access || {}
        };
      } else {
        throw new Error('Invalid response format from access API');
      }

      this.loading = false;
      this.notify();
      return this.accessData;

    } catch (error) {
      this.loading = false;
      this.error = error.response?.data?.message || 'Error loading access permissions';
      this.notify();
      throw error;
    }
  }

  /**
   * Get current access data (cached)
   * @returns {Object|null} - Current access data or null if not loaded
   */
  getAccessData() {
    return this.accessData;
  }

  /**
   * Get current user role
   * @returns {string|null} - Current user role or null if not loaded
   */
  getRole() {
    return this.accessData?.role || null;
  }

  /**
   * Get current user permissions
   * @returns {Object} - Current user permissions map
   */
  getPermissions() {
    return this.accessData?.permissions || {};
  }

  /**
   * Check if user has access to a specific route using database-driven permissions
   * @param {string} routeName - Route name to check access for
   * @returns {boolean} - Whether the user has access
   */
  hasAccess(routeName) {
    if (!this.accessData) {
      return false; // No access data loaded yet
    }

    const { role, permissions } = this.accessData;

    // Dashboard is always accessible
    if (routeName === 'dashboard') {
      return true;
    }

    // Use database-driven permissions - this is the single source of truth
    return permissions[routeName] === true;
  }

  /**
   * Check if user has a specific capability
   * @param {string} capability - Capability to check
   * @returns {boolean} - Whether the user has the capability
   */
  hasCapability(capability) {
    if (!this.accessData) {
      return false;
    }

    const { permissions } = this.accessData;
    return permissions[capability] === true;
  }

  /**
   * Get loading state
   * @returns {boolean} - Whether access data is currently being loaded
   */
  isLoading() {
    return this.loading;
  }

  /**
   * Get error state
   * @returns {string|null} - Current error message or null if no error
   */
  getError() {
    return this.error;
  }

  /**
   * Clear all access data (useful for logout)
   */
  clearAccessData() {
    this.accessData = null;
    this.loading = false;
    this.error = null;
    this.notify();
  }

  /**
   * Check if user is administrator
   * @returns {boolean} - Whether current user is administrator
   */
  isAdministrator() {
    return this.getRole() === 'administrator';
  }

  /**
   * Check if user is doctor
   * @returns {boolean} - Whether current user is doctor
   */
  isDoctor() {
    return this.getRole() === 'doctor';
  }

  /**
   * Check if user is patient
   * @returns {boolean} - Whether current user is patient
   */
  isPatient() {
    return this.getRole() === 'patient';
  }

  /**
   * Check if user is lab technician
   * @returns {boolean} - Whether current user is lab technician
   */
  isLabTech() {
    return this.getRole() === 'lab_tech';
  }

  /**
   * Check if user is developer
   * @returns {boolean} - Whether current user is developer
   */
  isDeskOfficer() {
    return this.getRole() === 'developer';
  }

  /**
   * Check if user is hospital nurse
   * @returns {boolean} - Whether current user is hospital nurse
   */
  isNurse() {
    return this.getRole() === 'hospital_nurse';
  }

  /**
   * Check if user is hospital staff
   * @returns {boolean} - Whether current user is hospital staff
   */
  isHospitalStaff() {
    return this.getRole() === 'hospital_staff';
  }

  /**
   * Check if user is pharmacy staff
   * @returns {boolean} - Whether current user is pharmacy staff
   */
  isPharmacyStaff() {
    return this.getRole() === 'pharmacy_staff';
  }

  /**
   * Check if user is inventory manager
   * @returns {boolean} - Whether current user is inventory manager
   */
  isInventoryManager() {
    return this.getRole() === 'inventory_manager';
  }

  /**
   * Get user-friendly role name
   * @returns {string} - User-friendly role name
   */
  getRoleDisplayName() {
    const roleMap = {
      'administrator': 'Administrator',
      'doctor': 'Doctor',
      'patient': 'Patient',
      'lab_tech': 'Lab Technician',
      'developer': 'Developer',
      'hospital_nurse': 'Hospital Nurse',
      'hospital_staff': 'Hospital Staff',
      'pharmacy_staff': 'Pharmacy Staff',
      'inventory_manager': 'Inventory Manager'
    };

    return roleMap[this.getRole()] || 'Unknown Role';
  }

  /**
   * Get all available routes for current user
   * @returns {Array} - Array of route names the user has access to
   */
  getAccessibleRoutes() {
    if (!this.accessData) {
      return [];
    }

    const { permissions } = this.accessData;
    return Object.keys(permissions).filter(route => permissions[route] === true);
  }

  /**
   * Check multiple permissions at once
   * @param {Array<string>} routeNames - Array of route names to check
   * @returns {Object} - Object with route names as keys and boolean access as values
   */
  checkMultipleAccess(routeNames) {
    const result = {};
    routeNames.forEach(route => {
      result[route] = this.hasAccess(route);
    });
    return result;
  }
}

// Create singleton instance
const userAccessService = new UserAccessService();

export default userAccessService;

import { apiClient } from './apiClient';

class InventoryService {
  constructor() {
    const { nonce, siteUrl } = window.hospitalManagerData || {};
    this.baseURL =`${siteUrl}/wp-json/hospital-manager/v1/inventory`;
  }

  /**
   * Get all inventory items with optional filters
   */
  async getItems(params = {}) {
    try {
      const response = await apiClient.get(this.baseURL, { params });
      // Return the full response data to preserve pagination metadata
      return response.data;
    } catch (error) {
      console.error('Error fetching inventory items:', error);
      throw error;
    }
  }

  /**
   * Get a single inventory item by ID
   */
  async getItem(id) {
    try {
      const response = await apiClient.get(`${this.baseURL}/${id}`);
      return response.data.data || null;
    } catch (error) {
      console.error('Error fetching inventory item:', error);
      throw error;
    }
  }

  /**
   * Create a new inventory item
   */
  async createItem(itemData) {
    try {
      const response = await apiClient.post(this.baseURL, itemData);
      return response.data.data || null;
    } catch (error) {
      console.error('Error creating inventory item:', error);
      throw error;
    }
  }

  /**
   * Update an existing inventory item
   */
  async updateItem(id, itemData) {
    try {
      const response = await apiClient.put(`${this.baseURL}/${id}`, itemData);
      return response.data.data || null;
    } catch (error) {
      console.error('Error updating inventory item:', error);
      throw error;
    }
  }

  /**
   * Delete an inventory item
   */
  async deleteItem(id) {
    try {
      const response = await apiClient.delete(`${this.baseURL}/${id}`);
      return response.data || null;
    } catch (error) {
      console.error('Error deleting inventory item:', error);
      throw error;
    }
  }

  /**
   * Get critical inventory items (below reorder level)
   */
  async getCriticalItems() {
    try {
      const response = await apiClient.get(`${this.baseURL}/critical`);
      return response.data.data || [];
    } catch (error) {
      console.error('Error fetching critical items:', error);
      throw error;
    }
  }

  /**
   * Get expiring inventory items
   */
  async getExpiringItems(days = 30) {
    try {
      const response = await apiClient.get(`${this.baseURL}/expiring`, {
        params: { days }
      });
      return response.data.data || [];
    } catch (error) {
      console.error('Error fetching expiring items:', error);
      throw error;
    }
  }

  /**
   * Get inventory summary statistics
   */
  async getSummary() {
    try {
      const response = await apiClient.get(`${this.baseURL}/summary`);
      return response.data.data || {};
    } catch (error) {
      console.error('Error fetching inventory summary:', error);
      throw error;
    }
  }

  /**
   * Bulk update inventory items
   */
  async bulkUpdate(updates) {
    try {
      const response = await apiClient.post(`${this.baseURL}/bulk-update`, {
        updates
      });
      return response.data.data || {};
    } catch (error) {
      console.error('Error performing bulk update:', error);
      throw error;
    }
  }

  /**
   * Export inventory data as CSV
   */
  async exportCSV(filters = {}) {
    try {
      const response = await apiClient.get(`${this.baseURL}/export`, {
        params: { ...filters, format: 'csv' }
      });
      
      if (response.data.data) {
        // Create blob and download
        const blob = new Blob([response.data.data], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = response.data.filename || 'inventory.csv';
        link.click();
        window.URL.revokeObjectURL(url);
      }
      
      return response.data;
    } catch (error) {
      console.error('Error exporting CSV:', error);
      throw error;
    }
  }

  // ============ INVENTORY TRANSACTIONS ============

  /**
   * Get inventory transactions with filters
   */
  async getTransactions(params = {}) {
    try {
      const response = await apiClient.get(`${this.baseURL}/transactions`, { params });
      return response.data;
    } catch (error) {
      console.error('Error fetching transactions:', error);
      throw error;
    }
  }

  /**
   * Record a new inventory transaction
   */
  async recordTransaction(transactionData) {
    try {
      const response = await apiClient.post(`${this.baseURL}/transactions`, transactionData);
      return response.data.data || null;
    } catch (error) {
      console.error('Error recording transaction:', error);
      throw error;
    }
  }

  // ============ INVENTORY ALERTS ============

  /**
   * Get inventory alerts with filters
   */
  async getAlerts(params = {}) {
    try {
      const response = await apiClient.get(`${this.baseURL}/alerts`, { params });
      return response.data || { data: [], total: 0 };
    } catch (error) {
      console.error('Error fetching alerts:', error);
      throw error;
    }
  }

  /**
   * Acknowledge an alert
   */
  async acknowledgeAlert(alertId) {
    try {
      const response = await apiClient.put(`/wp-json/hospital-manager/v1/inventory/alerts/${alertId}/acknowledge`);
      return response.data.data || null;
    } catch (error) {
      console.error('Error acknowledging alert:', error);
      throw error;
    }
  }

  /**
   * Resolve an alert
   */
  async resolveAlert(alertId) {
    try {
      const response = await apiClient.put(`/wp-json/hospital-manager/v1/inventory/alerts/${alertId}/resolve`);
      return response.data.data || null;
    } catch (error) {
      console.error('Error resolving alert:', error);
      throw error;
    }
  }

  /**
   * Generate alerts for all inventory items
   */
  async generateAlerts() {
    try {
      const response = await apiClient.post(`${this.baseURL}/alerts/generate`);
      return response.data.data || [];
    } catch (error) {
      console.error('Error generating alerts:', error);
      throw error;
    }
  }

  // ============ INVENTORY SUPPLIERS ============

  /**
   * Get all suppliers
   */
  async getSuppliers(params = {}) {
    try {
      const response = await apiClient.get(`${this.baseURL}/suppliers`, { params });
      return response.data;
    } catch (error) {
      console.error('Error fetching suppliers:', error);
      throw error;
    }
  }

  /**
   * Get a single supplier by ID
   */
  async getSupplier(id) {
    try {
      const response = await apiClient.get(`${this.baseURL}/suppliers/${id}`);
      return response.data.data || null;
    } catch (error) {
      console.error('Error fetching supplier:', error);
      throw error;
    }
  }

  /**
   * Create a new supplier
   */
  async createSupplier(supplierData) {
    try {
      const response = await apiClient.post(`${this.baseURL}/suppliers`, supplierData);
      return response.data.data || null;
    } catch (error) {
      console.error('Error creating supplier:', error);
      throw error;
    }
  }

  /**
   * Update an existing supplier
   */
  async updateSupplier(id, supplierData) {
    try {
      const response = await apiClient.put(`${this.baseURL}/suppliers/${id}`, supplierData);
      return response.data.data || null;
    } catch (error) {
      console.error('Error updating supplier:', error);
      throw error;
    }
  }

  /**
   * Delete a supplier
   */
  async deleteSupplier(id) {
    try {
      const response = await apiClient.delete(`${this.baseURL}/suppliers/${id}`);
      return response.data || null;
    } catch (error) {
      console.error('Error deleting supplier:', error);
      throw error;
    }
  }

  // ============ INVENTORY REORDERS ============

  /**
   * Get reorder suggestions with filters
   */
  async getReorders(params = {}) {
    try {
      const response = await apiClient.get(`${this.baseURL}/reorders`, { params });
      // Return the full response to preserve pagination metadata
      return response.data;
    } catch (error) {
      console.error('Error fetching reorders:', error);
      throw error;
    }
  }

  /**
   * Create a new reorder
   */
  async createReorder(reorderData) {
    try {
      const response = await apiClient.post(`${this.baseURL}/reorders`, reorderData);
      return response.data.data || null;
    } catch (error) {
      console.error('Error creating reorder:', error);
      throw error;
    }
  }

  /**
   * Approve a reorder
   */
  async approveReorder(reorderId) {
    try {
      const response = await apiClient.put(`/wp-json/hospital-manager/v1/inventory/reorders/${reorderId}/approve`);
      return response.data.data || null;
    } catch (error) {
      console.error('Error approving reorder:', error);
      throw error;
    }
  }

  /**
   * Complete a reorder (mark as received)
   */
  async completeReorder(reorderId, completionData = {}) {
    try {
      const response = await apiClient.put(`/wp-json/hospital-manager/v1/inventory/reorders/${reorderId}/complete`, completionData);
      return response.data.data || null;
    } catch (error) {
      console.error('Error completing reorder:', error);
      throw error;
    }
  }

  /**
   * Get reorder suggestions
   */
  async getReorderSuggestions(params = {}) {
    try {
      const response = await apiClient.get(`${this.baseURL}/reorders/suggestions`, { params });
      // Return the full response to preserve pagination metadata
      return response.data;
    } catch (error) {
      console.error('Error fetching reorder suggestions:', error);
      throw error;
    }
  }

  // ============ DASHBOARD DATA ============

  /**
   * Get dashboard data for inventory overview
   */
  async getDashboardData() {
    try {
      const response = await apiClient.get(`${this.baseURL}/dashboard`);
      return response.data.data || {};
    } catch (error) {
      console.error('Error fetching dashboard data:', error);
      throw error;
    }
  }

  // ============ HELPER FUNCTIONS ============

  /**
   * Get available categories
   */
  getCategories() {
    return [
      'Medication',
      'Equipment', 
      'Supplies',
      'PPE',
      'Consumables',
      'Instruments',
      'Furniture',
      'Electronics',
      'Safety',
      'Cleaning'
    ];
  }

  /**
   * Get available statuses
   */
  getStatuses() {
    return ['In Stock', 'Low Stock', 'Out of Stock', 'Expired'];
  }

  /**
   * Get status display text and color
   */
  getStatusInfo(status) {
    const statusMap = {
      'In Stock': { text: 'In Stock', color: 'green' },
      'Low Stock': { text: 'Low Stock', color: 'yellow' },
      'Out of Stock': { text: 'Out of Stock', color: 'red' },
      'Expired': { text: 'Expired', color: 'red' }
    };
    return statusMap[status] || { text: status, color: 'gray' };
  }

  /**
   * Get available units of measure
   */
  getUnits() {
    return [
      'units',
      'boxes',
      'packs', 
      'bottles',
      'vials',
      'tubes',
      'pieces',
      'sets',
      'pairs',
      'rolls',
      'sheets',
      'ml',
      'mg',
      'g',
      'kg'
    ];
  }

  /**
   * Get transaction types
   */
  getTransactionTypes() {
    return [
      { value: 'stock_in', label: 'Stock In' },
      { value: 'stock_out', label: 'Stock Out' },
      { value: 'adjustment', label: 'Adjustment' },
      { value: 'transfer', label: 'Transfer' },
      { value: 'expired', label: 'Expired' },
      { value: 'damaged', label: 'Damaged' },
      { value: 'returned', label: 'Returned' }
    ];
  }

  /**
   * Get alert types
   */
  getAlertTypes() {
    return [
      { value: 'low_stock', label: 'Low Stock' },
      { value: 'out_of_stock', label: 'Out of Stock' }, 
      { value: 'expired', label: 'Expired' },
      { value: 'expiring_soon', label: 'Expiring Soon' },
      { value: 'critical_level', label: 'Critical Level' },
      { value: 'reorder_point', label: 'Reorder Point' }
    ];
  }

  /**
   * Get severity levels for alerts
   */
  getSeverityLevels() {
    return [
      { value: 'low', label: 'Low', color: 'blue' },
      { value: 'medium', label: 'Medium', color: 'yellow' },
      { value: 'high', label: 'High', color: 'orange' },
      { value: 'critical', label: 'Critical', color: 'red' }
    ];
  }

  /**
   * Get alert severity information for badges
   */
  getAlertSeverityInfo(severity) {
    const severityMap = {
      'low': { color: 'blue', text: 'Low' },
      'medium': { color: 'yellow', text: 'Medium' },
      'high': { color: 'orange', text: 'High' },
      'critical': { color: 'red', text: 'Critical' }
    };
    
    return severityMap[severity] || { color: 'gray', text: severity || 'Unknown' };
  }
}

export default new InventoryService();

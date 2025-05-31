import { apiClient } from './apiClient';

class InventoryService {
  constructor() {
    this.baseURL = '/api/inventory';
  }

  /**
   * Get all inventory items with optional filters
   */
  async getItems(params = {}) {
    try {
      const response = await apiClient.get(this.baseURL, { params });
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
        params: { ...filters, format: 'csv' },
        responseType: 'blob'
      });
      
      // Create a blob URL and trigger download
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `inventory_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      
      return { success: true };
    } catch (error) {
      console.error('Error exporting inventory:', error);
      throw error;
    }
  }

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
      'Surgical'
    ];
  }

  /**
   * Get available statuses
   */
  getStatuses() {
    return [
      'in_stock',
      'low_stock', 
      'out_of_stock',
      'expired',
      'damaged',
      'on_order'
    ];
  }

  /**
   * Get status display text and color
   */
  getStatusInfo(status) {
    const statusMap = {
      'in_stock': { text: 'In Stock', color: 'green' },
      'low_stock': { text: 'Low Stock', color: 'yellow' },
      'out_of_stock': { text: 'Out of Stock', color: 'red' },
      'expired': { text: 'Expired', color: 'red' },
      'damaged': { text: 'Damaged', color: 'red' },
      'on_order': { text: 'On Order', color: 'blue' }
    };
    
    return statusMap[status] || { text: status, color: 'gray' };
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
      return response.data;
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
      return response.data;
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
      const response = await apiClient.patch(`${this.baseURL}/alerts/${alertId}/acknowledge`);
      return response.data;
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
      const response = await apiClient.patch(`${this.baseURL}/alerts/${alertId}/resolve`);
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      return response.data;
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
      const response = await apiClient.patch(`${this.baseURL}/reorders/${reorderId}/approve`);
      return response.data;
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
      const response = await apiClient.patch(`${this.baseURL}/reorders/${reorderId}/complete`, completionData);
      return response.data;
    } catch (error) {
      console.error('Error completing reorder:', error);
      throw error;
    }
  }

  /**
   * Get reorder suggestions
   */
  async getReorderSuggestions() {
    try {
      const response = await apiClient.get(`${this.baseURL}/reorders/suggestions`);
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
      return response.data;
    } catch (error) {
      console.error('Error fetching dashboard data:', error);
      throw error;
    }
  }

  // ============ UTILITY METHODS ============

  /**
   * Get transaction types
   */
  getTransactionTypes() {
    return [
      { value: 'stock_in', label: 'Stock In', color: 'green' },
      { value: 'stock_out', label: 'Stock Out', color: 'red' },
      { value: 'adjustment', label: 'Adjustment', color: 'blue' },
      { value: 'transfer', label: 'Transfer', color: 'purple' },
      { value: 'expired', label: 'Expired', color: 'orange' },
      { value: 'damaged', label: 'Damaged', color: 'red' },
      { value: 'returned', label: 'Returned', color: 'teal' }
    ];
  }

  /**
   * Get alert types
   */
  getAlertTypes() {
    return [
      { value: 'low_stock', label: 'Low Stock', severity: 'medium' },
      { value: 'out_of_stock', label: 'Out of Stock', severity: 'high' },
      { value: 'expired', label: 'Expired', severity: 'critical' },
      { value: 'expiring_soon', label: 'Expiring Soon', severity: 'medium' },
      { value: 'critical_level', label: 'Critical Level', severity: 'critical' },
      { value: 'reorder_point', label: 'Reorder Point', severity: 'medium' }
    ];
  }

  /**
   * Get alert severity info
   */
  getAlertSeverityInfo(severity) {
    const severityMap = {
      'low': { text: 'Low', color: 'blue', bgColor: 'bg-blue-100' },
      'medium': { text: 'Medium', color: 'yellow', bgColor: 'bg-yellow-100' },
      'high': { text: 'High', color: 'orange', bgColor: 'bg-orange-100' },
      'critical': { text: 'Critical', color: 'red', bgColor: 'bg-red-100' }
    };
    
    return severityMap[severity] || { text: severity, color: 'gray', bgColor: 'bg-gray-100' };
  }

  /**
   * Get reorder priorities
   */
  getReorderPriorities() {
    return [
      { value: 'low', label: 'Low', color: 'green' },
      { value: 'medium', label: 'Medium', color: 'yellow' },
      { value: 'high', label: 'High', color: 'orange' },
      { value: 'urgent', label: 'Urgent', color: 'red' }
    ];
  }

  /**
   * Get reorder statuses
   */
  getReorderStatuses() {
    return [
      { value: 'pending', label: 'Pending', color: 'yellow' },
      { value: 'approved', label: 'Approved', color: 'blue' },
      { value: 'ordered', label: 'Ordered', color: 'purple' },
      { value: 'received', label: 'Received', color: 'green' },
      { value: 'cancelled', label: 'Cancelled', color: 'red' }
    ];
  }
}

export default new InventoryService();

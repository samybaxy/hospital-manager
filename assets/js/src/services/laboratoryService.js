import { apiClient } from './apiClient';

export const laboratoryService = {
  /**
   * Get lab investigations with filtering and pagination
   */
  async getInvestigations(params = {}) {
    try {
      const queryParams = new URLSearchParams();
      
      if (params.patient_id) queryParams.append('patient_id', params.patient_id);
      if (params.lab_tech_id) queryParams.append('lab_tech_id', params.lab_tech_id);
      if (params.test_type) queryParams.append('test_type', params.test_type);
      if (params.status) queryParams.append('status', params.status);
      if (params.page) queryParams.append('page', params.page);
      if (params.per_page) queryParams.append('per_page', params.per_page);
      
      const response = await apiClient.get(`/lab-investigations?${queryParams.toString()}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching lab investigations:', error);
      throw error;
    }
  },

  /**
   * Get a single lab investigation by ID
   */
  async getInvestigation(id) {
    try {
      const response = await apiClient.get(`/lab-investigations/${id}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching lab investigation:', error);
      throw error;
    }
  },

  /**
   * Create a new lab investigation
   */
  async createInvestigation(data) {
    try {
      const response = await apiClient.post('/lab-investigations', data);
      return response.data;
    } catch (error) {
      console.error('Error creating lab investigation:', error);
      throw error;
    }
  },

  /**
   * Update an existing lab investigation
   */
  async updateInvestigation(id, data) {
    try {
      const response = await apiClient.put(`/lab-investigations/${id}`, data);
      return response.data;
    } catch (error) {
      console.error('Error updating lab investigation:', error);
      throw error;
    }
  },

  /**
   * Update lab test results
   */
  async updateResults(id, resultsData) {
    try {
      const response = await apiClient.put(`/lab-investigations/${id}/results`, resultsData);
      return response.data;
    } catch (error) {
      console.error('Error updating lab results:', error);
      throw error;
    }
  },

  /**
   * Update investigation status
   */
  async updateStatus(id, status) {
    try {
      const response = await apiClient.put(`/lab-investigations/${id}/status`, { status });
      return response.data;
    } catch (error) {
      console.error('Error updating investigation status:', error);
      throw error;
    }
  },

  /**
   * Delete a lab investigation
   */
  async deleteInvestigation(id) {
    try {
      const response = await apiClient.delete(`/lab-investigations/${id}`);
      return response.data;
    } catch (error) {
      console.error('Error deleting lab investigation:', error);
      throw error;
    }
  },

  /**
   * Get pending investigations
   */
  async getPendingInvestigations(params = {}) {
    try {
      const queryParams = new URLSearchParams();
      
      if (params.lab_tech_id) queryParams.append('lab_tech_id', params.lab_tech_id);
      if (params.limit) queryParams.append('limit', params.limit);
      
      const response = await apiClient.get(`/lab-investigations/pending?${queryParams.toString()}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching pending investigations:', error);
      throw error;
    }
  },

  /**
   * Get investigations for a specific patient
   */
  async getPatientInvestigations(patientId, params = {}) {
    try {
      const queryParams = new URLSearchParams({
        patient_id: patientId,
        ...params
      });
      
      const response = await apiClient.get(`/lab-investigations?${queryParams.toString()}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching patient investigations:', error);
      throw error;
    }
  },

  /**
   * Get lab dashboard statistics
   */
  async getDashboardStats(labTechId = null) {
    try {
      const params = labTechId ? `?lab_tech_id=${labTechId}` : '';
      const response = await apiClient.get(`/lab-investigations/dashboard${params}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching lab dashboard stats:', error);
      throw error;
    }
  },

  /**
   * Get status options
   */
  getStatusOptions() {
    return [
      { value: 'requested', label: 'Requested', color: 'bg-blue-100 text-blue-800' },
      { value: 'sample_collected', label: 'Sample Collected', color: 'bg-yellow-100 text-yellow-800' },
      { value: 'in_progress', label: 'In Progress', color: 'bg-orange-100 text-orange-800' },
      { value: 'completed', label: 'Completed', color: 'bg-green-100 text-green-800' },
      { value: 'verified', label: 'Verified', color: 'bg-emerald-100 text-emerald-800' },
      { value: 'cancelled', label: 'Cancelled', color: 'bg-red-100 text-red-800' }
    ];
  },

  /**
   * Get status info by value
   */
  getStatusInfo(status) {
    const statusOptions = this.getStatusOptions();
    return statusOptions.find(option => option.value === status) || 
           { value: status, label: status, color: 'bg-gray-100 text-gray-800' };
  },

  /**
   * Format test results for display
   */
  formatTestResults(results) {
    if (!results || !Array.isArray(results)) {
      return [];
    }

    return results.map(result => ({
      ...result,
      displayValue: this.formatParameterValue(result),
      isOutOfRange: this.isParameterOutOfRange(result),
      flagLabel: this.getFlagLabel(result.flag)
    }));
  },

  /**
   * Format a single parameter value
   */
  formatParameterValue(parameter) {
    if (!parameter || parameter.value === null || parameter.value === undefined) {
      return '--';
    }

    const value = typeof parameter.value === 'object' ? 
      `${parameter.value.min}-${parameter.value.max}` : 
      parameter.value;

    return `${value} ${parameter.unit || ''}`.trim();
  },

  /**
   * Check if parameter is out of range
   */
  isParameterOutOfRange(parameter) {
    if (!parameter || !parameter.reference_range) return false;
    
    const value = typeof parameter.value === 'object' ? 
      (parameter.value.min + parameter.value.max) / 2 : 
      parameter.value;

    if (value < parameter.reference_range.min || value > parameter.reference_range.max) {
      return true;
    }

    return false;
  },

  /**
   * Get flag label
   */
  getFlagLabel(flag) {
    const flagLabels = {
      'H': 'High',
      'HH': 'Critical High',
      'L': 'Low', 
      'LL': 'Critical Low',
      'A': 'Abnormal',
      'AA': 'Critical Abnormal'
    };

    return flagLabels[flag] || '';
  },

  /**
   * Get priority level based on flags
   */
  getPriorityLevel(investigation) {
    if (investigation.is_critical) {
      return { level: 'critical', label: 'Critical', color: 'bg-red-100 text-red-800 border-red-200' };
    }
    
    if (investigation.is_abnormal) {
      return { level: 'abnormal', label: 'Abnormal', color: 'bg-yellow-100 text-yellow-800 border-yellow-200' };
    }

    return { level: 'normal', label: 'Normal', color: 'bg-green-100 text-green-800 border-green-200' };
  },

  /**
   * Generate sample investigation data for testing
   */
  generateSampleData() {
    return {
      visitation_id: 1,
      patient_id: 1,
      doctor_id: 1,
      lab_tech_id: 1,
      sample_type: 'Blood',
      request_notes: 'Routine lab work as requested by physician.',
      status: 'requested'
    };
  },

  /**
   * Get lab categories
   */
  async getLabCategories() {
    try {
      const response = await apiClient.get('/lab-categories');
      return response.data;
    } catch (error) {
      console.error('Error fetching lab categories:', error);
      throw error;
    }
  },

  /**
   * Get test definitions
   */
  async getTestDefinitions(categoryId = null) {
    try {
      const params = categoryId ? `?category_id=${categoryId}` : '';
      const response = await apiClient.get(`/lab-test-definitions${params}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching test definitions:', error);
      throw error;
    }
  },

  /**
   * Get test definitions by category
   */
  async getTestDefinitionsByCategory(categoryId) {
    try {
      const response = await apiClient.get(`/lab-test-definitions?category_id=${categoryId}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching test definitions by category:', error);
      throw error;
    }
  },

  /**
   * Get a single test definition
   */
  async getTestDefinition(id) {
    try {
      const response = await apiClient.get(`/lab-test-definitions/${id}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching test definition:', error);
      throw error;
    }
  }
};

export default laboratoryService;

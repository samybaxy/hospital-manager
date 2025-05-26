import { api } from './apiService';

/**
 * Appointment API Service
 * Handles all appointment-related API calls
 */
const appointmentService = {
  /**
   * Get all appointments for the current user
   * @param {Object} params - Query parameters (page, per_page, status, etc.)
   * @returns {Promise} Promise with appointments data
   */
  getAppointments: (params = {}) => {
    // Ensure we have defaults for pagination parameters
    const requestParams = {
      page: params.page || 1,
      per_page: params.per_page || 10,
      ...params
    };
    
    return api.get('/appointments', { params: requestParams });
  },

  /**
   * Create a new appointment
   * @param {Object} appointmentData - The appointment data
   * @returns {Promise} Promise with created appointment
   */
  createAppointment: (appointmentData) => {
    return api.post('/appointments', appointmentData);
  },

  /**
   * Update an appointment
   * @param {number} ID - Appointment ID
   * @param {Object} appointmentData - Updated appointment data
   * @returns {Promise} Promise with updated appointment
   */
  updateAppointment: (ID, appointmentData) => {
    return api.put(`/appointments/${ID}`, appointmentData);
  },

  /**
   * Get a single appointment by ID
   * @param {number} ID - Appointment ID
   * @returns {Promise} Promise with appointment data
   */
  getAppointment: (ID) => {
    return api.get(`/appointments/${ID}`);
  },

  /**
   * Get available appointment slots for a doctor on a specific date
   * @param {number} doctorId - Doctor ID
   * @param {string} date - Date in YYYY-MM-DD format
   * @returns {Promise} Promise with available slots
   */
  getAvailableSlots: (doctorId, date) => {
    return api.get('/appointments/availability', {
      params: { doctor_id: doctorId, date }
    });
  },

  /**
   * Cancel an appointment
   * @param {number} ID - Appointment ID
   * @param {string} reason - Reason for cancellation
   * @returns {Promise} Promise with result
   */
  cancelAppointment: (ID, reason = '') => {
    return api.put(`/appointments/${ID}`, { 
      status: 'cancelled',
      notes: reason
    });
  }
};

export default appointmentService;

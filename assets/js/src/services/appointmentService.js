import { api } from './apiService';

/**
 * Appointment API Service
 * Handles all appointment-related API calls
 */
const appointmentService = {
  /**
   * Get all appointments for the current user
   * @param {Object} params - Query parameters
   * @returns {Promise} Promise with appointments data
   */
  getAppointments: (params = {}) => {
    return api.get('/appointments', { params });
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
   * @param {number} id - Appointment ID
   * @param {Object} appointmentData - Updated appointment data
   * @returns {Promise} Promise with updated appointment
   */
  updateAppointment: (id, appointmentData) => {
    return api.put(`/appointments/${id}`, appointmentData);
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
   * @param {number} id - Appointment ID
   * @returns {Promise} Promise with result
   */
  cancelAppointment: (id) => {
    return api.put(`/appointments/${id}`, { status: 'cancelled' });
  }
};

export default appointmentService;

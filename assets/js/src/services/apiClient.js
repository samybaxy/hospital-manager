import axios from 'axios';

/**
 * API client for the Hospital Manager WordPress plugin
 * Uses the hospital-manager/v1 namespace for all API requests
 */

// Get the WordPress data from the global object
const { apiUrl, nonce, siteUrl } = window.hospitalManagerData || {};

// Create an Axios instance with baseURL and default headers
const apiClient = axios.create({
  baseURL: apiUrl || `${siteUrl}/wp-json/hospital-manager/v1`,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor to attach authentication nonce to all requests
apiClient.interceptors.request.use(
  (config) => {
    if (nonce) {
      config.headers['X-WP-Nonce'] = nonce;
    }
    
    // Get the token from localStorage if available
    const token = localStorage.getItem('hospital_manager_token');
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor to handle common errors
apiClient.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    const { response } = error;
    
    if (response) {
      switch (response.status) {
        case 401:
          // Unauthorized - handle authentication errors
          console.error('Authentication error', response.data);
          // Redirect to login or display login modal
          // Example: window.location.href = '/login';
          break;
        case 403:
          // Forbidden - handle permission errors
          console.error('Permission denied', response.data);
          break;
        case 404:
          // Not found
          console.error('Resource not found', response.data);
          break;
        case 500:
          // Server error
          console.error('Server error', response.data);
          break;
        default:
          console.error(`Error ${response.status}:`, response.data);
      }
    } else if (error.request) {
      // The request was made but no response was received
      console.error('No response received:', error.request);
    } else {
      // Something else happened in setting up the request
      console.error('Error setting up request:', error.message);
    }
    
    return Promise.reject(error);
  }
);

/**
 * API functions for easy use throughout the application
 */
export const api = {
  /**
   * GET request
   * @param {string} url - The endpoint URL
   * @param {Object} params - URL parameters
   * @param {Object} config - Additional Axios config
   * @returns {Promise} - Axios promise
   */
  get: (url, params = {}, config = {}) => {
    return apiClient.get(url, { params, ...config });
  },
  
  /**
   * POST request
   * @param {string} url - The endpoint URL
   * @param {Object} data - The data to send
   * @param {Object} config - Additional Axios config
   * @returns {Promise} - Axios promise
   */
  post: (url, data = {}, config = {}) => {
    return apiClient.post(url, data, config);
  },
  
  /**
   * PUT request
   * @param {string} url - The endpoint URL
   * @param {Object} data - The data to send
   * @param {Object} config - Additional Axios config
   * @returns {Promise} - Axios promise
   */
  put: (url, data = {}, config = {}) => {
    return apiClient.put(url, data, config);
  },
  
  /**
   * PATCH request
   * @param {string} url - The endpoint URL
   * @param {Object} data - The data to send
   * @param {Object} config - Additional Axios config
   * @returns {Promise} - Axios promise
   */
  patch: (url, data = {}, config = {}) => {
    return apiClient.patch(url, data, config);
  },
  
  /**
   * DELETE request
   * @param {string} url - The endpoint URL
   * @param {Object} config - Additional Axios config
   * @returns {Promise} - Axios promise
   */
  delete: (url, config = {}) => {
    return apiClient.delete(url, config);
  },
  
  /**
   * Upload files
   * @param {string} url - The endpoint URL
   * @param {FormData} formData - FormData object with files
   * @param {Object} config - Additional Axios config
   * @returns {Promise} - Axios promise
   */
  upload: (url, formData, config = {}) => {
    return apiClient.post(url, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      ...config,
    });
  },
};

// Export both the raw axios instance and the api object
export default apiClient;

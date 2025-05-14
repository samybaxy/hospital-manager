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

// Create a wrapper for API calls
const api = {
  get: (url, params = {}, config = {}) => {
    return apiClient.get(url, { params, ...config });
  },
  post: (url, data = {}, config = {}) => {
    return apiClient.post(url, data, config);
  },
  put: (url, data = {}, config = {}) => {
    return apiClient.put(url, data, config);
  },
  delete: (url, config = {}) => {
    return apiClient.delete(url, config);
  },
  patch: (url, data = {}, config = {}) => {
    return apiClient.patch(url, data, config);
  },
  upload: (url, formData, config = {}) => {
    return apiClient.post(url, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      ...config,
    });
  }
};

export { apiClient, api };
export default apiClient;

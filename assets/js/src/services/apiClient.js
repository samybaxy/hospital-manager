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
  // Only prevent problematic redirects but allow normal status codes to work
  validateStatus: function (status) {
    return status >= 200 && status < 300 || status === 401;
  }
});

// Simple CSRF token manager to avoid circular dependencies
const csrfTokenManager = {
  updateToken: null, // Will be set by authService when it initializes
  
  setUpdateFunction: (updateFn) => {
    csrfTokenManager.updateToken = updateFn;
  },
  
  update: (token) => {
    if (csrfTokenManager.updateToken && typeof csrfTokenManager.updateToken === 'function') {
      csrfTokenManager.updateToken(token);
    } else {
      // Fallback: store in sessionStorage directly
      sessionStorage.setItem('hospital_manager_csrf_nonce', token);
    }
  }
};

// Add request interceptor to ensure we always send the latest CSRF token
apiClient.interceptors.request.use(
  (config) => {
    // Get the latest CSRF token for requests that need it
    if (config.method === 'post' || config.method === 'put' || config.method === 'patch' || config.method === 'delete') {
      // Get the current CSRF token from storage or global
      const csrfToken = sessionStorage.getItem('hospital_manager_csrf_nonce') ||
                       (window.hospitalManagerData && window.hospitalManagerData.nonce) ||
                       (window.wpApiSettings && window.wpApiSettings.nonce);
      
      if (csrfToken) {
        config.headers['X-WP-Nonce'] = csrfToken;
        
        // Also add it to the data if it's not already there and this is a login request
        if (config.url && config.url.includes('/auth/') && config.data && !config.data.nonce) {
          config.data.nonce = csrfToken;
        }
      }
    }
    
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Add response interceptor to handle redirects and CSRF token updates
apiClient.interceptors.response.use(
  (response) => {
    // Update CSRF token if provided in response data
    if (response.data && response.data.fresh_nonce) {
      csrfTokenManager.update(response.data.fresh_nonce);
      
      // Update global variables as well for immediate availability
      if (window.hospitalManagerData) {
        window.hospitalManagerData.nonce = response.data.fresh_nonce;
      }
      if (window.wpApiSettings) {
        window.wpApiSettings.nonce = response.data.fresh_nonce;
      }
    }
    
    // Update CSRF token if provided in response headers
    if (response.headers['x-wp-nonce']) {
      csrfTokenManager.update(response.headers['x-wp-nonce']);
      
      // Update global variables as well
      if (window.hospitalManagerData) {
        window.hospitalManagerData.nonce = response.headers['x-wp-nonce'];
      }
      if (window.wpApiSettings) {
        window.wpApiSettings.nonce = response.headers['x-wp-nonce'];
      }
    }
    
    // For auth endpoints, check if browser is trying to redirect to wp-login
    if (response.request && response.request.responseURL && 
        response.request.responseURL.includes('wp-login.php') &&
        response.config && response.config.url && 
        response.config.url.includes('/auth/')) {
      console.warn('WordPress login redirect detected for auth endpoint');
      
      // Only transform redirects for auth-related endpoints
      return {
        ...response,
        status: 401,
        data: {
          authenticated: false,
          message: 'Authentication failed. Please check your credentials.'
        }
      };
    }
    return response;
  },
  (error) => {
    // Update CSRF token if provided in error response
    if (error.response && error.response.data && error.response.data.fresh_nonce) {
      csrfTokenManager.update(error.response.data.fresh_nonce);
      
      // Update global variables as well for immediate availability
      if (window.hospitalManagerData) {
        window.hospitalManagerData.nonce = error.response.data.fresh_nonce;
      }
      if (window.wpApiSettings) {
        window.wpApiSettings.nonce = error.response.data.fresh_nonce;
      }
    }
    
    // If there's an auth-related redirect
    if (error.response && 
        error.response.status >= 300 && 
        error.response.status < 400 && 
        error.config && 
        error.config.url && 
        error.config.url.includes('/auth/')) {
      
      console.warn('Auth redirect prevented', error.response.headers.location);
      
      // Only transform redirects for auth-related endpoints
      return {
        status: 401,
        data: {
          authenticated: false,
          message: 'Authentication failed. Please check your credentials.'
        }
      };
    }
    
    // For other errors, just pass them through
    return Promise.reject(error);
  }
);

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

export { apiClient, api, csrfTokenManager };
export default apiClient;

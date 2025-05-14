import { apiClient, api } from './apiClient';
import authService from './authService';

/**
 * Enhanced API client for the Hospital Manager WordPress plugin
 * with authentication and interceptors
 */

// Get the WordPress data from the global object
const { nonce } = window.hospitalManagerData || {};

// Track ongoing token refresh to prevent multiple simultaneous refreshes
let isRefreshing = false;
let refreshPromise = null;
let failedQueue = [];

// Process the queue of failed requests
const processQueue = (error, token = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  
  failedQueue = [];
};

// Request interceptor to attach authentication nonce to all requests
apiClient.interceptors.request.use(
  async (config) => {
    // Add CSRF nonce for security
    // Try from global WP first, fallback to our custom data
    const csrfNonce = window.wpApiSettings?.nonce || nonce;
    if (csrfNonce) {
      config.headers['X-WP-Nonce'] = csrfNonce;
    }
    
    // Get token from auth service
    const token = authService.getToken();
    
    if (token) {
      // Check if token is expired or about to expire (within 5 minutes)
      if (authService.isTokenExpired() || authService.willExpireSoon(300)) {
        // Skip token refresh for auth-related endpoints to avoid infinite loops
        if (config.url.includes('/auth/login') || 
            config.url.includes('/auth/refresh') || 
            config.url.includes('/auth/logout')) {
          return config;
        }
        
        // If a refresh is already in progress, wait for it
        if (isRefreshing) {
          try {
            // Wait for the current refresh to complete
            const newToken = await new Promise((resolve, reject) => {
              failedQueue.push({ resolve, reject });
            });
            
            // Use the new token
            config.headers['Authorization'] = `Bearer ${newToken}`;
            return config;
          } catch (err) {
            return Promise.reject(err);
          }
        }
        
        // Start refreshing token
        isRefreshing = true;
        
        try {
          // Try to refresh token
          refreshPromise = authService.refreshToken();
          const refreshed = await refreshPromise;
          
          if (refreshed) {
            // Use the new token
            const newToken = authService.getToken();
            config.headers['Authorization'] = `Bearer ${newToken}`;
            
            // Process any queued requests with the new token
            processQueue(null, newToken);
          } else {
            // If refresh failed and not an auth request, handle failure
            processQueue(new Error('Failed to refresh token'));
            
            // If this wasn't an auth endpoint, redirect to login
            if (!config.url.includes('/auth/login')) {
              window.location.href = '/login';
              return Promise.reject(new Error('Authentication required'));
            }
          }
        } catch (error) {
          processQueue(error);
          return Promise.reject(error);
        } finally {
          isRefreshing = false;
          refreshPromise = null;
        }
      } else {
        // Token is valid, use it
        config.headers['Authorization'] = `Bearer ${token}`;
      }
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
    // Check for fresh nonce in response headers and update if present
    const freshNonce = response.headers['x-wp-nonce'];
    if (freshNonce) {
      authService.updateCsrfToken(freshNonce);
    }
    
    // Store token expiry if this is a login or refresh response
    if ((response.config.url.includes('/auth/login') || response.config.url.includes('/auth/refresh')) && 
        response.data && response.data.token) {
      try {
        const token = response.data.token;
        const payload = token.split('.')[1];
        const decoded = JSON.parse(atob(payload));
        
        if (decoded.exp) {
          // Determine storage based on whether token is in localStorage
          const storage = localStorage.getItem('hospital_manager_token') ? 
                         localStorage : sessionStorage;
          
          storage.setItem('hospital_manager_token_expiry', decoded.exp);
        }
      } catch (e) {
        console.warn('Could not process token expiry:', e);
      }
    }
    
    return response;
  },
  (error) => {
    // Handle 401 Unauthorized responses
    if (error.response && error.response.status === 401) {
      // Clear any invalid tokens
      authService.clearToken();
      
      // Redirect to login page if not already there
      if (!window.location.pathname.includes('login')) {
        window.location.href = '/login';
      }
    }
    
    // Handle 403 Forbidden responses (invalid CSRF)
    if (error.response && error.response.status === 403) {
      // Get a fresh CSRF token if available
      const freshNonce = error.response.headers['x-wp-nonce'];
      if (freshNonce) {
        authService.updateCsrfToken(freshNonce);
      }
    }
    
    return Promise.reject(error);
  }
);

// Export the configured API instance
export { apiClient, api };

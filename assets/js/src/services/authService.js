import { apiClient, csrfTokenManager } from './apiClient';

/**
 * Authentication service for centralized token management
 */

// Simple encryption/decryption for token storage
const TokenCrypto = {
  /**
   * Simple XOR encryption for tokens (basic obfuscation)
   * @param {string} text - Text to encrypt
   * @param {string} key - Encryption key
   * @returns {string} Encrypted text
   */
  encrypt: (text, key) => {
    if (!text || !key) return text;
    let result = '';
    for (let i = 0; i < text.length; i++) {
      result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length));
    }
    return btoa(result);
  },

  /**
   * Simple XOR decryption for tokens
   * @param {string} encryptedText - Encrypted text
   * @param {string} key - Decryption key
   * @returns {string} Decrypted text
   */
  decrypt: (encryptedText, key) => {
    if (!encryptedText || !key) return encryptedText;
    try {
      const text = atob(encryptedText);
      let result = '';
      for (let i = 0; i < text.length; i++) {
        result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length));
      }
      return result;
    } catch (e) {
      console.warn('Failed to decrypt token:', e);
      return null;
    }
  },

  /**
   * Generate a simple key based on browser fingerprint
   * @returns {string} Simple encryption key
   */
  getKey: () => {
    const userAgent = navigator.userAgent || '';
    const screen = `${window.screen.width}x${window.screen.height}`;
    const lang = navigator.language || 'en';
    return btoa(userAgent.slice(0, 20) + screen + lang).slice(0, 16);
  }
};

const authService = {
  /**
   * Store authentication token based on "remember me" preference with encryption
   * @param {string} token - JWT token to store
   * @param {boolean} rememberMe - Whether to store token persistently
   */
  setToken: (token, rememberMe = false) => {
    if (!token) return;
    
    // Validate token format (should be JWT)
    if (token.split('.').length !== 3) {
      console.warn('Invalid token format provided to setToken');
      return;
    }
    
    // Extract token expiry if it's a JWT
    let tokenExpiry = null;
    try {
      const payload = token.split('.')[1];
      if (payload) {
        const decoded = JSON.parse(atob(payload));
        tokenExpiry = decoded.exp;
      }
    } catch (e) {
      console.warn('Error extracting token expiration:', e);
    }
    
    // Get storage based on remember me preference
    const storage = rememberMe ? localStorage : sessionStorage;
    
    // Encrypt token before storage
    const encryptionKey = TokenCrypto.getKey();
    const encryptedToken = TokenCrypto.encrypt(token, encryptionKey);
    
    // Store encrypted token and expiry info
    storage.setItem('hospital_manager_token', encryptedToken);
    if (tokenExpiry) {
      storage.setItem('hospital_manager_token_expiry', tokenExpiry);
    }
    
    // Update Authorization header for future requests (use original unencrypted token)
    apiClient.defaults = {
      ...apiClient.defaults,
      headers: {
        ...apiClient.defaults?.headers,
        'Authorization': `Bearer ${token}`
      }
    };
  },
  
  /**
   * Get the stored authentication token with decryption
   * @returns {string|null} The token or null if not found
   */
  getToken: () => {
    const encryptedToken = localStorage.getItem('hospital_manager_token') || 
                          sessionStorage.getItem('hospital_manager_token');
    
    if (!encryptedToken) return null;
    
    // Decrypt the token
    const encryptionKey = TokenCrypto.getKey();
    const decryptedToken = TokenCrypto.decrypt(encryptedToken, encryptionKey);
    
    // Validate decrypted token
    if (!decryptedToken || decryptedToken.split('.').length !== 3) {
      console.warn('Failed to decrypt or invalid token format');
      authService.clearToken();
      return null;
    }
    
    return decryptedToken;
  },
  
  /**
   * Clear all stored tokens
   */
  clearToken: () => {
    // Remove all auth-related items
    localStorage.removeItem('hospital_manager_token');
    localStorage.removeItem('hospital_manager_token_expiry');
    
    sessionStorage.removeItem('hospital_manager_token');
    sessionStorage.removeItem('hospital_manager_token_expiry');
    
    // Clear the Authorization header
    if (apiClient.defaults && apiClient.defaults.headers) {
      delete apiClient.defaults.headers['Authorization'];
    }
  },
  
  /**
   * Check if the token is expired
   * @returns {boolean} True if token is expired or doesn't exist
   */
  isTokenExpired: () => {
    const token = authService.getToken();
    if (!token) return true;
    
    // First check if we already have the expiry stored
    const storedExpiry = localStorage.getItem('hospital_manager_token_expiry') || 
                         sessionStorage.getItem('hospital_manager_token_expiry');
    
    if (storedExpiry) {
      // Allow a 30-second margin for clock differences
      return (parseInt(storedExpiry) * 1000) < (Date.now() - 30000);
    }
    
    // If not stored, extract from token
    try {
      // Token structure: header.payload.signature
      const payload = token.split('.')[1];
      if (!payload) return true;
      
      const decoded = JSON.parse(atob(payload));
      const expiry = decoded.exp;
      
      // Check if token has expiry and if it's expired
      if (!expiry) return false; // No expiration set
      
      // Store for future checks
      if (localStorage.getItem('hospital_manager_token')) {
        localStorage.setItem('hospital_manager_token_expiry', expiry);
      } else if (sessionStorage.getItem('hospital_manager_token')) {
        sessionStorage.setItem('hospital_manager_token_expiry', expiry);
      }
      
      // Allow a 30-second margin for clock differences
      return (expiry * 1000) < (Date.now() - 30000);
    } catch (e) {
      console.error('Error checking token expiration:', e);
      return true; // If we can't check it, assume it's expired for safety
    }
  },
  
  /**
   * Attempt to refresh the token
   * @returns {Promise<boolean>} True if token was refreshed successfully
   */
  refreshToken: async () => {
    try {
      const currentToken = authService.getToken();
      if (!currentToken) return false;
      
      // Add CSRF token for additional security
      const csrfToken = authService.getCsrfToken();
      
      const response = await apiClient.post('/auth/refresh', {
        token: currentToken,
        nonce: csrfToken
      });
      
      if (response.data && response.data.token) {
        // Determine which storage the current token is in
        const isInLocalStorage = localStorage.getItem('hospital_manager_token');
        
        // Store in the same storage as before
        authService.setToken(response.data.token, !!isInLocalStorage);
        
        // Store token expiry time for easier checking
        const payload = response.data.token.split('.')[1];
        if (payload) {
          try {
            const decoded = JSON.parse(atob(payload));
            if (decoded.exp) {
              const storage = !!isInLocalStorage ? localStorage : sessionStorage;
              storage.setItem('hospital_manager_token_expiry', decoded.exp);
            }
          } catch (e) {
            console.warn('Could not extract token expiry:', e);
          }
        }
        
        return true;
      }
      return false;
    } catch (error) {
      console.error('Failed to refresh token:', error);
      
      // Clear invalid tokens if the server rejected the refresh
      if (error.response && (error.response.status === 401 || error.response.status === 403)) {
        authService.clearToken();
      }
      
      return false;
    }
  },
  
  /**
   * Handle CSRF token management - gets the freshest available token
   * @returns {string|null} Current CSRF token or null
   */
  getCsrfToken: () => {
    // Try from stored nonce (refreshed by API responses) - this is usually the freshest
    const storedNonce = sessionStorage.getItem('hospital_manager_csrf_nonce');
    if (storedNonce) {
      return storedNonce;
    }
    
    // Try to get from global WordPress variable
    if (window.wpApiSettings && window.wpApiSettings.nonce) {
      return window.wpApiSettings.nonce;
    }
    
    // Try to get from our custom global
    if (window.hospitalManagerData && window.hospitalManagerData.nonce) {
      return window.hospitalManagerData.nonce;
    }
    
    return null;
  },
  
  /**
   * Update the stored CSRF token when received from server
   * @param {string} newNonce - New CSRF nonce from server
   */
  updateCsrfToken: (newNonce) => {
    if (newNonce) {
      // Store in sessionStorage for persistence
      sessionStorage.setItem('hospital_manager_csrf_nonce', newNonce);
      
      // Update global variables for immediate availability
      if (window.hospitalManagerData) {
        window.hospitalManagerData.nonce = newNonce;
      }
      if (window.wpApiSettings) {
        window.wpApiSettings.nonce = newNonce;
      }
      
      console.log('CSRF token updated:', newNonce.slice(0, 10) + '...');
    }
  },
  
  /**
   * Set the CSRF token (alias for updateCsrfToken)
   * @param {string} newNonce - New CSRF nonce from server
   */
  setCsrfToken: (newNonce) => {
    authService.updateCsrfToken(newNonce);
  },
  
  /**
   * Request a password reset for an email address
   * @param {string} email - The user's email address
   * @returns {Promise} API response
   */
  requestPasswordReset: async (email) => {
    try {
      return await apiClient.post('/auth/reset-password', { email });
    } catch (error) {
      console.error('Password reset request failed:', error);
      throw error;
    }
  },
  
  /**
   * Complete a password reset with a token
   * @param {string} token - Reset token from email
   * @param {string} password - New password
   * @returns {Promise} API response
   */
  completePasswordReset: async (token, password) => {
    try {
      return await apiClient.post('/auth/reset-password/confirm', { 
        token, 
        password,
        nonce: authService.getCsrfToken() // Include CSRF protection
      });
    } catch (error) {
      console.error('Password reset completion failed:', error);
      throw error;
    }
  },
  
  /**
   * Check if the token will expire soon
   * @param {number} seconds - Number of seconds to consider as "soon"
   * @returns {boolean} True if token will expire within specified seconds
   */
  willExpireSoon: (seconds = 300) => {
    const token = authService.getToken();
    if (!token) return true;
    
    // First check if we already have the expiry stored
    const storedExpiry = localStorage.getItem('hospital_manager_token_expiry') || 
                         sessionStorage.getItem('hospital_manager_token_expiry');
    
    if (storedExpiry) {
      // Check if token will expire within the given seconds
      return (parseInt(storedExpiry) * 1000) < (Date.now() + (seconds * 1000));
    }
    
    // If not stored, extract from token
    try {
      const payload = token.split('.')[1];
      if (!payload) return true;
      
      const decoded = JSON.parse(atob(payload));
      const expiry = decoded.exp;
      
      if (!expiry) return false; // No expiration set
      
      // Store for future checks
      if (localStorage.getItem('hospital_manager_token')) {
        localStorage.setItem('hospital_manager_token_expiry', expiry);
      } else if (sessionStorage.getItem('hospital_manager_token')) {
        sessionStorage.setItem('hospital_manager_token_expiry', expiry);
      }
      
      // Check if token will expire within the given seconds
      return (expiry * 1000) < (Date.now() + (seconds * 1000));
    } catch (e) {
      console.error('Error checking token expiration:', e);
      return true; // If we can't check it, assume it will expire soon for safety
    }
  }
};

// Register the CSRF token update function with apiClient to avoid circular dependencies
csrfTokenManager.setUpdateFunction(authService.updateCsrfToken);

export default authService;

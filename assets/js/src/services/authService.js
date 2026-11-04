import { apiClient, getNonce, setNonce, clearNonce } from './apiClient';

/**
 * Authentication helpers.
 *
 * The server authenticates with the WordPress login cookie plus a REST nonce.
 * (A JWT used to be stored here and sent as a Bearer token; the server no
 * longer accepts Bearer tokens, so it isn't kept.)
 */

const LEGACY_KEYS = ['hospital_manager_token', 'hospital_manager_token_expiry'];

const authService = {
  /** Remove anything older versions of the app stored. */
  clearToken: () => {
    LEGACY_KEYS.forEach((key) => {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    });
    // The old dev-mode switch signed in a fake admin; make sure it's gone.
    localStorage.removeItem('hospital_manager_dev_mode');
  },

  /** End the local session state (cookie is cleared by /auth/logout). */
  clearSession: () => {
    authService.clearToken();
    clearNonce();
  },

  getCsrfToken: getNonce,
  updateCsrfToken: setNonce,
  setCsrfToken: setNonce,

  /**
   * Request a password reset for an email address.
   * @param {string} email
   */
  requestPasswordReset: async (email) => apiClient.post('/auth/reset-password', { email }),

  /**
   * Complete a password reset with a token.
   * @param {string} token
   * @param {string} password
   */
  completePasswordReset: async (token, password) =>
    apiClient.post('/auth/reset-password/confirm', { token, password, nonce: getNonce() }),
};

export default authService;

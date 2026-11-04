import axios from 'axios';

/**
 * API client for the Hospital Manager WordPress plugin (hospital-manager/v1).
 *
 * Authentication is the WordPress login cookie plus a REST nonce (X-WP-Nonce)
 * on every request. The nonce is tied to the session: a nonce created before
 * login is useless after it, so the freshest one (from the login response or
 * any response header) is kept in sessionStorage and read on every request.
 */

const NONCE_KEY = 'hospital_manager_csrf_nonce';

const { apiUrl, siteUrl } = window.hospitalManagerData || {};

// The page was just rendered for whoever holds the login cookie, so its nonce
// is the right one now; a nonce kept from an earlier session would be rejected.
if (window.hospitalManagerData && window.hospitalManagerData.nonce) {
  sessionStorage.setItem(NONCE_KEY, window.hospitalManagerData.nonce);
}

const apiClient = axios.create({
  baseURL: apiUrl || `${siteUrl || window.location.origin}/wp-json/hospital-manager/v1`,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true,
});

/** The nonce to send: the latest one the server gave us, else the page's. */
export const getNonce = () =>
  sessionStorage.getItem(NONCE_KEY) ||
  (window.hospitalManagerData && window.hospitalManagerData.nonce) ||
  (window.wpApiSettings && window.wpApiSettings.nonce) ||
  null;

/** Remember a nonce handed out by the server. */
export const setNonce = (nonce) => {
  if (!nonce) return;
  sessionStorage.setItem(NONCE_KEY, nonce);
  if (window.hospitalManagerData) window.hospitalManagerData.nonce = nonce;
  if (window.wpApiSettings) window.wpApiSettings.nonce = nonce;
};

/** Forget the session nonce (logout / expired session). */
export const clearNonce = () => sessionStorage.removeItem(NONCE_KEY);

// Kept for older imports.
const csrfTokenManager = {
  setUpdateFunction: () => {},
  update: setNonce,
};

// Listeners told when the server says the session is gone (401).
const unauthorizedListeners = new Set();
export const onUnauthorized = (fn) => {
  unauthorizedListeners.add(fn);
  return () => unauthorizedListeners.delete(fn);
};

const isAuthCall = (url = '') =>
  url.includes('/auth/login') || url.includes('/auth/logout') || url.includes('/auth/me') ||
  url.includes('/auth/reset-password');

apiClient.interceptors.request.use((config) => {
  const nonce = getNonce();
  if (nonce) {
    config.headers['X-WP-Nonce'] = nonce;
  }
  return config;
});

const pickUpNonce = (response) => {
  if (!response) return;
  if (response.data && response.data.fresh_nonce) {
    setNonce(response.data.fresh_nonce);
  }
  const headerNonce = response.headers && response.headers['x-wp-nonce'];
  if (headerNonce) {
    setNonce(headerNonce);
  }
};

apiClient.interceptors.response.use(
  (response) => {
    pickUpNonce(response);
    return response;
  },
  (error) => {
    const response = error.response;
    pickUpNonce(response);

    // Session expired or signed out elsewhere: tell the app (it clears its
    // state and shows the login screen). Login/me calls handle 401 themselves.
    if (response && response.status === 401 && !isAuthCall(error.config && error.config.url)) {
      unauthorizedListeners.forEach((fn) => {
        try { fn(); } catch (e) { /* ignore */ }
      });
    }

    return Promise.reject(error);
  }
);

const api = {
  get: (url, params = {}, config = {}) => apiClient.get(url, { params, ...config }),
  post: (url, data = {}, config = {}) => apiClient.post(url, data, config),
  put: (url, data = {}, config = {}) => apiClient.put(url, data, config),
  delete: (url, config = {}) => apiClient.delete(url, config),
  patch: (url, data = {}, config = {}) => apiClient.patch(url, data, config),
  upload: (url, formData, config = {}) =>
    apiClient.post(url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      ...config,
    }),
};

export { apiClient, api, csrfTokenManager };
export default apiClient;

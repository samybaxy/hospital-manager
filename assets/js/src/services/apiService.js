/**
 * Shared API client. All interceptors (nonce, 401 handling) live in apiClient.js;
 * this module re-exports it so existing imports keep working.
 */
export { apiClient, api } from './apiClient';

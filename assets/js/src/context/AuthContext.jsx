import { createContext, useState, useContext, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, onUnauthorized, setNonce, getNonce } from '../services/apiClient';
import authService from '../services/authService';
import userAccessService from '../services/UserAccessService';

/**
 * Authentication state.
 *
 * The WordPress login cookie is the source of truth: on load the app asks
 * /auth/me who is signed in. (A "development mode" used to sign in a fake
 * administrator without calling the server on any hostname containing
 * "local"; it was removed. It made real logins look like they did nothing.)
 */

const AuthContext = createContext();

/** The login/me responses carry the role next to the user object. */
const withRole = (data) => {
  if (!data || !data.user) return null;
  const user = { ...data.user };
  if (data.role && !user.roles) {
    user.roles = [data.role];
    user.role = data.role;
  }
  return user;
};

/**
 * Ask WordPress for a nonce for the current cookie session. Used when ours was
 * rejected (e.g. it belonged to a previous session).
 */
async function fetchSessionNonce() {
  const data = window.hospitalManagerData || {};
  const ajaxUrl = data.ajaxUrl || `${data.siteUrl || window.location.origin}/wp-admin/admin-ajax.php`;
  try {
    const res = await fetch(`${ajaxUrl}?action=rest-nonce`, { credentials: 'include' });
    if (!res.ok) return null;
    const text = (await res.text()).trim();
    // Core answers with the bare nonce; the plugin's handler with JSON.
    let nonce = text;
    if (text.startsWith('{')) {
      try { nonce = JSON.parse(text).nonce; } catch (e) { nonce = null; }
    }
    if (nonce && /^[a-f0-9]{10}$/i.test(nonce)) {
      setNonce(nonce);
      return nonce;
    }
  } catch (e) { /* network error: fall through */ }
  return null;
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  const clearLocalSession = useCallback(() => {
    authService.clearSession();
    setUser(null);
    userAccessService.clearAccessData();
  }, []);

  // Who is signed in? (cookie session)
  useEffect(() => {
    // Remove what older versions stored (JWT copies, the fake dev-mode switch).
    authService.clearToken();

    async function askServer(retry = true) {
      try {
        const response = await api.get('/auth/me');
        if (response.data && response.data.authenticated) {
          setUser(withRole(response.data));
          userAccessService.fetchAccessData().catch(() => {});
        } else {
          setUser(null);
          userAccessService.clearAccessData();
        }
      } catch (err) {
        // 403 rest_cookie_invalid_nonce: our nonce is from another session.
        const code = err.response && err.response.data && err.response.data.code;
        if (retry && code === 'rest_cookie_invalid_nonce') {
          authService.clearSession();
          await fetchSessionNonce();
          return askServer(false);
        }
        setUser(null);
        userAccessService.clearAccessData();
      }
    }

    askServer().finally(() => setLoading(false));
  }, []);

  // Session expired (any API call answered 401): back to the login screen.
  useEffect(() => {
    return onUnauthorized(() => {
      clearLocalSession();
      navigate('/login', { replace: true });
    });
  }, [navigate, clearLocalSession]);

  const login = async (username, password, rememberMe = false, isRetry = false) => {
    try {
      setLoading(true);
      setError(null);

      const response = await api.post('/auth/login', {
        username,
        password,
        remember: rememberMe,
        nonce: getNonce(),
      });

      if (response.data && response.data.authenticated) {
        // The nonce from the login response belongs to the new session; the
        // page's nonce (created while logged out) would now be rejected.
        if (response.data.fresh_nonce) {
          setNonce(response.data.fresh_nonce);
        }
        setUser(withRole(response.data));
        userAccessService.clearAccessData();
        userAccessService.fetchAccessData().catch(() => {});
        return { success: true };
      }

      const message = (response.data && response.data.message) || 'Invalid credentials';
      setError(message);
      return { success: false, message };
    } catch (err) {
      const data = err.response && err.response.data;
      const status = err.response && err.response.status;

      // Stale nonce: the server sent a fresh one; retry once.
      if (!isRetry && status === 403 && data && (data.code === 'csrf_failed' || data.code === 'rest_cookie_invalid_nonce')) {
        if (data.fresh_nonce) {
          setNonce(data.fresh_nonce);
        } else {
          authService.clearSession();
        }
        return login(username, password, rememberMe, true);
      }

      let message = 'Login failed';
      if (status === 429) {
        message = 'Too many login attempts. Please try again later.';
      } else if (data && data.code === 'csrf_failed') {
        message = 'Security verification failed. Please refresh the page and try again.';
      } else if (data && data.message) {
        message = data.message;
      } else if (!err.response) {
        message = 'Could not reach the server. Please check your connection.';
      }
      setError(message);
      return { success: false, message };
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    try {
      setLoading(true);
      await api.post('/auth/logout', { nonce: getNonce() });
    } catch (err) {
      // Clear local state even if the server call fails.
    } finally {
      clearLocalSession();
      // Logged-out requests need a logged-out nonce.
      await fetchSessionNonce();
      setLoading(false);
    }
  };

  const value = {
    user,
    isAuthenticated: !!user,
    loading,
    error,
    login,
    logout,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export default AuthContext;

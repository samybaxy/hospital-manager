import { createContext, useState, useContext, useEffect } from 'react';
import { api } from '../services/apiService';
import authService from '../services/authService';

// Create authentication context
const AuthContext = createContext();

// Auth Provider component
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Check if the user is authenticated on initial load
  useEffect(() => {
    async function checkAuthStatus() {
      try {
        setLoading(true);
        
        // Check if we have a token using authService
        const token = authService.getToken();
                         
        if (!token) {
          setUser(null);
          setLoading(false);
          return;
        }
        
        // Check if token is expired
        if (authService.isTokenExpired()) {
          // Try to refresh the token
          const refreshed = await authService.refreshToken().catch(() => false);
          
          if (!refreshed) {
            // If refresh failed, clear token and set unauthenticated
            authService.clearToken();
            setUser(null);
            setLoading(false);
            return;
          }
        }
        
        // Token is valid or was refreshed, get user info
        const response = await api.get('/auth/me');
        
        if (response.data.authenticated) {
          setUser(response.data.user);
          
          // Store CSRF nonce if provided in response
          if (response.headers['x-wp-nonce']) {
            authService.updateCsrfToken(response.headers['x-wp-nonce']);
          }
        } else {
          // Token is invalid, clear it
          authService.clearToken();
          setUser(null);
        }
      } catch (err) {
        console.error("Authentication check failed:", err);
        setError("Failed to authenticate");
        
        // Clear any invalid tokens
        authService.clearToken();
        setUser(null);
      } finally {
        setLoading(false);
      }
    }

    checkAuthStatus();
  }, []);

  // Login function
  const login = async (username, password, rememberMe = false) => {
    try {
      setLoading(true);
      setError(null);
      
      // Add CSRF protection
      const csrfToken = authService.getCsrfToken();
      
      const response = await api.post('/auth/login', { 
        username, 
        password,
        nonce: csrfToken
      });
      
      if (response.data.authenticated) {
        // Store token if provided
        if (response.data.token) {
          authService.setToken(response.data.token, rememberMe);
        }
        
        // Update CSRF token if provided
        if (response.headers['x-wp-nonce']) {
          authService.updateCsrfToken(response.headers['x-wp-nonce']);
        }
        
        setUser(response.data.user);
        return true;
      } else {
        setError(response.data.message || "Invalid credentials");
        return false;
      }
    } catch (err) {
      setError(err.response?.data?.message || "Login failed");
      return false;
    } finally {
      setLoading(false);
    }
  };

  // Logout function
  const logout = async () => {
    try {
      setLoading(true);
      
      // Add CSRF protection to logout request
      const csrfToken = authService.getCsrfToken();
      await api.post('/auth/logout', { 
        nonce: csrfToken 
      });
      
      // Use centralized auth service to clear tokens
      authService.clearToken();
      setUser(null);
    } catch (err) {
      console.error("Logout failed:", err);
      
      // Even if the API call fails, clear tokens
      authService.clearToken();
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  // Context value
  const value = {
    user,
    isAuthenticated: !!user,
    loading,
    error,
    login,
    logout
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// Custom hook to use the auth context
export const useAuth = () => {
  const context = useContext(AuthContext);
  
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  
  return context;
};

export default AuthContext;

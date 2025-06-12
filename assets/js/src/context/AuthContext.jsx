import { createContext, useState, useContext, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../services/apiService';
import authService from '../services/authService';
import userAccessService from '../services/UserAccessService';

// Development environment detection
const isDevelopment = () => {
    return false; // Set to true if you want to enable development mode bypass
    return window.location.hostname === 'localhost' || 
            window.location.hostname === '127.0.0.1' ||
            window.location.port === '10008';
};

// Create authentication context
const AuthContext = createContext();

// Auth Provider component
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  // Check if the user is authenticated on initial load
  useEffect(() => {
    async function checkAuthStatus() {
      try {
        setLoading(true);
        
        // Development bypass - automatically authenticate as admin
        if (isDevelopment()) {
          console.log('🔧 Development mode: Bypassing authentication');
          const mockUser = {
            ID: 1,
            display_name: 'Dev Admin',
            user_email: 'admin@dev.local',
            roles: ['administrator'],
            name: 'Dev Admin',
            first_name: 'Dev',
            last_name: 'Admin'
          };
          setUser(mockUser);
          setLoading(false);
          return;
        }
        
        // Check if we have a token using authService
        const token = authService.getToken();
                         
        if (!token) {
          setUser(null);
          userAccessService.clearAccessData();
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
            userAccessService.clearAccessData();
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
          
          // Fetch user access permissions using unified service
          userAccessService.fetchAccessData().catch(err => {
            console.error('Failed to fetch access data:', err);
          });
        } else {
          // Token is invalid, clear it
          authService.clearToken();
          setUser(null);
          userAccessService.clearAccessData();
        }
      } catch (err) {
        console.error("Authentication check failed:", err);
        setError("Failed to authenticate");
        
        // Clear any invalid tokens
        authService.clearToken();
        setUser(null);
        userAccessService.clearAccessData();
      } finally {
        setLoading(false);
      }
    }

    checkAuthStatus();
    
    // More focused handler for responses that might be redirects
    const originalOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function() {
      this.addEventListener('readystatechange', function() {
        if (this.readyState === 4) {
          // If response URL is wp-login.php, it means WordPress is trying to redirect
          const responseURL = this.responseURL;
          if (responseURL && responseURL.includes('wp-login.php')) {
            console.warn('Detected redirect to wp-login.php');
            
            // Only intervene if this is a hospital-manager API call
            const apiPath = '/wp-json/hospital-manager/';
            const requestURL = this.responseURL || '';
            const isOurApiCall = requestURL.includes(apiPath);
            
            if (isOurApiCall) {
              console.log('Intercepted wp-login redirect for our API endpoint');
              
              // Don't abort the request - just notify about it
              if (window.location.pathname !== '/login') {
                // Only redirect to login if not already there
                navigate('/login', { replace: true });
              }
            }
          }
        }
      });
      originalOpen.apply(this, arguments);
    };
    
    // Clean up the override when component unmounts
    return () => {
      XMLHttpRequest.prototype.open = originalOpen;
    };
  }, [navigate]);

  // Login function with automatic CSRF retry
  const login = async (username, password, rememberMe = false, isRetry = false) => {
    // Development bypass - always return success
    if (isDevelopment()) {
      console.log('🔧 Development mode: Bypassing login');
      const mockUser = {
        ID: 1,
        display_name: 'Dev Admin',
        user_email: 'admin@dev.local',
        roles: ['administrator'],
        name: 'Dev Admin',
        first_name: 'Dev',
        last_name: 'Admin'
      };
      setUser(mockUser);
      setLoading(false);
      return true;
    }
    
    try {
      setLoading(true);
      setError(null);
      
      // Ensure we have a fresh CSRF token before attempting login
      const csrfToken = isRetry ? 
        authService.getCsrfToken() : 
        await authService.ensureFreshCsrfToken();
      
      const response = await api.post('/auth/login', { 
        username, 
        password,
        remember: rememberMe, // Pass remember me preference
        nonce: csrfToken
      });
      
      if (response.data.authenticated) {
        // Store token if provided
        if (response.data.token) {
          authService.setToken(response.data.token, rememberMe);
        }
        
        // Update CSRF token if provided in response
        if (response.data.fresh_nonce) {
          authService.updateCsrfToken(response.data.fresh_nonce);
        }
        
        // Update CSRF token if provided in headers
        if (response.headers['x-wp-nonce']) {
          authService.updateCsrfToken(response.headers['x-wp-nonce']);
        }
        
        setUser(response.data.user);
        
        // Access permissions will be fetched automatically by UserAccessService when needed
        return true;
      } else {
        setError(response.data.message || "Invalid credentials");
        return false;
      }
    } catch (err) {
      // Don't log CSRF errors that will be auto-retried
      if (!(err.response && err.response.status === 403 && 
            err.response.data && err.response.data.code === 'csrf_failed' && 
            !isRetry)) {
        console.error('Login error:', err);
      }
      
      // Update CSRF token if provided in error response
      if (err.response && err.response.data && err.response.data.fresh_nonce) {
        authService.updateCsrfToken(err.response.data.fresh_nonce);
        
        // Auto-retry once on CSRF failure if we haven't already retried
        if (err.response.status === 403 && 
            err.response.data.code === 'csrf_failed' && 
            !isRetry) {
          console.log('CSRF token updated, automatically retrying login...');
          setError('Security token updated, retrying login...');
          
          // Brief delay to let user see the retry message
          await new Promise(resolve => setTimeout(resolve, 500));
          
          // Retry the login with the fresh CSRF token
          return await login(username, password, rememberMe, true);
        }
      }
      
      // Extract meaningful error message
      let errorMessage = "Login failed";
      if (err.response && err.response.data) {
        if (err.response.status === 403) {
          if (err.response.data.code === 'csrf_failed') {
            errorMessage = "Security verification failed. Please try again.";
          } else {
            errorMessage = err.response.data.message || "Access denied. Please check your credentials.";
          }
        } else if (err.response.status === 429) {
          errorMessage = "Too many login attempts. Please try again later.";
        } else {
          errorMessage = err.response.data.message || "Invalid credentials";
        }
      }
      
      setError(errorMessage);
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
      userAccessService.clearAccessData();
    } catch (err) {
      console.error("Logout failed:", err);
      
      // Even if the API call fails, clear tokens
      authService.clearToken();
      setUser(null);
      userAccessService.clearAccessData();
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

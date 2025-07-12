import { createContext, useState, useContext, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../services/apiService';
import authService from '../services/authService';
import userAccessService from '../services/UserAccessService';

// Enhanced development environment detection with multiple override options
const isDevelopment = () => {
    // Check URL parameters for quick override (e.g., ?dev_mode=true or ?dev_mode=false)
    const urlParams = new URLSearchParams(window.location.search);
    const devModeParam = urlParams.get('dev_mode');
    
    if (devModeParam !== null) {
        const isDevMode = devModeParam === 'true';
        console.log(`🔧 Development mode ${isDevMode ? 'ENABLED' : 'DISABLED'} via URL parameter`);
        return isDevMode;
    }
    
    // Check localStorage for persistent override (used by toggle component)
    const localStorageMode = localStorage.getItem('hospital_manager_dev_mode');
    if (localStorageMode !== null) {
        const isDevMode = localStorageMode === 'true';
        console.log(`🔧 Development mode ${isDevMode ? 'ENABLED' : 'DISABLED'} via localStorage`);
        return isDevMode;
    }
    
    // Check environment variables
    const envMode = import.meta.env.VITE_APP_MODE || import.meta.env.NODE_ENV;
    const devAuthEnabled = import.meta.env.VITE_ENABLE_DEBUG === 'true';
    
    if (envMode === 'development' && devAuthEnabled) {
        console.log('🔧 Development mode ENABLED via environment variables');
        return true;
    }
    
    // Check WordPress constants (passed from PHP)
    const wpData = window.hospitalManagerData || {};
    if (wpData.developmentMode || (wpData.isDebugMode && wpData.isLocalEnvironment)) {
        console.log('🔧 Development mode ENABLED via WordPress constants');
        return true;
    }
    
    // Fallback to hostname detection
    const hostnameDetection = window.location.hostname === 'localhost' || 
                             window.location.hostname === '127.0.0.1' ||
                             window.location.port === '10008' ||
                             window.location.hostname.includes('local');
    
    if (hostnameDetection) {
        console.log('🔧 Development mode ENABLED via hostname detection');
        return true;
    }
    
    console.log('🔧 Development mode DISABLED - running in production mode');
    return false;
};

// Get comprehensive environment information
const getEnvironmentInfo = () => {
    const isDev = isDevelopment();
    const wpData = window.hospitalManagerData || {};
    
    return {
        isDevelopment: isDev,
        mode: import.meta.env.VITE_APP_MODE || import.meta.env.NODE_ENV || 'production',
        devAuthEnabled: import.meta.env.VITE_ENABLE_DEBUG === 'true',
        wpDebug: wpData.isDebugMode,
        wpEnvironment: wpData.environmentType,
        hostname: window.location.hostname,
        port: window.location.port,
        urlOverride: new URLSearchParams(window.location.search).get('dev_mode'),
        localStorageOverride: localStorage.getItem('hospital_manager_dev_mode'),
        wpData: wpData
    };
};

// Utility functions for manual mode switching (available globally)
const enableDevelopmentMode = () => {
    localStorage.setItem('hospital_manager_dev_mode', 'true');
    console.log('🔧 Development mode ENABLED via localStorage');
    window.location.reload();
};

const disableDevelopmentMode = () => {
    localStorage.setItem('hospital_manager_dev_mode', 'false');
    console.log('🔧 Development mode DISABLED via localStorage');
    window.location.reload();
};

const clearDevelopmentModeOverride = () => {
    localStorage.removeItem('hospital_manager_dev_mode');
    console.log('🔧 Development mode override CLEARED - using default detection');
    window.location.reload();
};

// Make functions available globally for console access
if (typeof window !== 'undefined') {
    window.hospitalManagerDevMode = {
        enable: enableDevelopmentMode,
        disable: disableDevelopmentMode,
        clear: clearDevelopmentModeOverride,
        status: isDevelopment,
        info: getEnvironmentInfo
    };
}

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
            display_name: 'Development Admin',
            user_email: 'admin@dev.local',
            roles: ['administrator'],
            name: 'Development Admin',
            first_name: 'Development',
            last_name: 'Admin'
          };
          setUser(mockUser);
          
          // Set up mock access data for the development user
          userAccessService.accessData = {
            role: 'administrator',
            permissions: {
              // Route access
              patients: true,
              doctors: true,
              departments: true,
              appointments: true,
              visitations: true,
              chat: true,
              notifications: true,
              audit_log: true,
              billing: true,
              inventory: true,
              reports: true,
              statistics: true,
              settings: true,
              lab_dashboard: true,
              // Action capabilities
              create_patients: true,
              edit_patients: true,
              delete_patients: true,
              schedule_appointments: false, // Administrators should NOT be able to schedule appointments
              add_visitation: true,
              edit_visitation: true,
              manage_medical_reports: true,
              add_lab_results: true,
              edit_lab_results: true,
              delete_lab_results: true,
            }
          };
          userAccessService.loading = false;
          userAccessService.error = null;
          userAccessService.notify();
          
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
        display_name: 'Development Admin',
        user_email: 'admin@dev.local',
        roles: ['administrator'],
        name: 'Development Admin',
        first_name: 'Development',
        last_name: 'Admin'
      };
      setUser(mockUser);
      
      // Set up mock access data for the development user
      userAccessService.accessData = {
        role: 'administrator',
        permissions: {
          // Route access
          patients: true,
          doctors: true,
          departments: true,
          appointments: true,
          visitations: true,
          chat: true,
          notifications: true,
          audit_log: true,
          billing: true,
          inventory: true,
          reports: true,
          statistics: true,
          settings: true,
          lab_dashboard: true,
          // Action capabilities
          create_patients: true,
          edit_patients: true,
          delete_patients: true,
          schedule_appointments: false, // Administrators should NOT be able to schedule appointments
          add_visitation: true,
          edit_visitation: true,
          manage_medical_reports: true,
          add_lab_results: true,
          edit_lab_results: true,
          delete_lab_results: true,
        }
      };
      userAccessService.loading = false;
      userAccessService.error = null;
      userAccessService.notify();
      
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
      
      // Development mode - clear mock data but still perform actual logout actions
      if (isDevelopment()) {
        console.log('🔧 Development mode: Clearing mock authentication');
        
        // Clear the mock user data
        setUser(null);
        userAccessService.clearAccessData();
        
        // Even in development, try to call the real logout API if it exists
        try {
          const csrfToken = authService.getCsrfToken();
          await api.post('/auth/logout', { 
            nonce: csrfToken 
          }).catch(() => {
            // Ignore errors in development mode - the important thing is clearing local state
            console.log('🔧 Development mode: Logout API call failed (expected)');
          });
        } catch (err) {
          // Ignore API errors in development
          console.log('🔧 Development mode: Logout API not available (expected)');
        }
        
        // Clear any tokens that might exist
        authService.clearToken();
        
        setLoading(false);
        return;
      }
      
      // Production mode - full logout process
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
      
      // Even if the API call fails, clear tokens and user state
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

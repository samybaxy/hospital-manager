import { createContext, useState, useContext, useEffect } from 'react';
import { api } from '../services/apiClient';

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
        
        // Check if we have a token in either localStorage or sessionStorage
        const hasToken = localStorage.getItem('hospital_manager_token') || 
                         sessionStorage.getItem('hospital_manager_token');
                         
        if (!hasToken) {
          setUser(null);
          setLoading(false);
          return;
        }
        
        // We have a token, so check if it's valid
        const response = await api.get('/auth/me');
        
        if (response.data.authenticated) {
          setUser(response.data.user);
        } else {
          // Token is invalid, clear it
          localStorage.removeItem('hospital_manager_token');
          sessionStorage.removeItem('hospital_manager_token');
          setUser(null);
        }
      } catch (err) {
        console.error("Authentication check failed:", err);
        setError("Failed to authenticate");
        
        // Clear any invalid tokens
        localStorage.removeItem('hospital_manager_token');
        sessionStorage.removeItem('hospital_manager_token');
        setUser(null);
      } finally {
        setLoading(false);
      }
    }

    checkAuthStatus();
  }, []);

  // Login function
  const login = async (username, password) => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await api.post('/auth/login', { 
        username, 
        password 
      });
      
      if (response.data.authenticated) {
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
      await api.post('/auth/logout');
      
      // Clear tokens from both storage options
      localStorage.removeItem('hospital_manager_token');
      sessionStorage.removeItem('hospital_manager_token');
      
      setUser(null);
    } catch (err) {
      console.error("Logout failed:", err);
      
      // Even if the API call fails, clear local tokens
      localStorage.removeItem('hospital_manager_token');
      sessionStorage.removeItem('hospital_manager_token');
      
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

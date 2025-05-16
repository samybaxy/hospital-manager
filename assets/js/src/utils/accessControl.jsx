/**
 * Access Control utility for Hospital Manager frontend
 */

import React, { createContext, useState, useContext, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { api } from '../services/apiService';
import { useAuth } from '../context/AuthContext';
import { createAsyncThunk } from '@reduxjs/toolkit';

// Define API endpoint
const API_ENDPOINT = '/access';

// Create access context
const AccessContext = createContext();

// Access provider component
export function AccessProvider({ children }) {
  const { user, isAuthenticated } = useAuth();
  const [permissions, setPermissions] = useState({});
  const [role, setRole] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Fetch access permissions when authenticated status or user changes
  useEffect(() => {
    async function fetchAccessPermissions() {
      if (!isAuthenticated) {
        setPermissions({});
        setRole(null);
        setLoading(false);
        return;
      }

      try {
        setLoading(true);
        const response = await api.get(API_ENDPOINT);
        
        if (response.data && response.data.success) {
          setPermissions(response.data.access || {});
          setRole(response.data.role || null);
        } else {
          console.error("Access permissions API error:", response.data);
          setError("Failed to load access permissions");
        }
      } catch (err) {
        console.error("Error fetching access permissions:", err);
        setError(err.response?.data?.message || "Error loading access permissions");
      } finally {
        setLoading(false);
      }
    }

    fetchAccessPermissions();
  }, [isAuthenticated, user]);

  /**
   * Check if user has access to a specific route
   * @param {string} routeName - Route name to check access for
   * @returns {boolean} - Whether the user has access
   */
  const hasAccess = (routeName) => {
    // Administrator has access to everything
    if (role === 'administrator') {
      return true;
    }
    
    // Role-based access restrictions based on requirements
    if (role === 'doctor') {
      // Doctors can't access: Audit Log, Billing, Inventory, and Settings
      if (['audit_log', 'billing', 'inventory', 'settings'].includes(routeName)) {
        return false;
      }
    } else if (role === 'patient') {
      // Patients can't access: Patients, Doctors, Departments, Audit Log, Billing, 
      // Inventory, Reports, Statistics, and Settings
      if (['patients', 'doctors', 'departments', 'audit_log', 'billing', 
           'inventory', 'reports', 'statistics', 'settings'].includes(routeName)) {
        return false;
      }
    } else if (role === 'lab_tech') {
      // Lab techs can only access: Dashboard and Lab Dashboard
      return ['lab_dashboard', 'dashboard'].includes(routeName);
    } else if (role === 'desk_officer') {
      // Desk officers can't access: Chat, Audit Log, Billing, Inventory, Statistics, and Settings
      if (['chat', 'audit_log', 'billing', 'inventory', 'statistics', 'settings'].includes(routeName)) {
        return false;
      }
    }
    
    // For other permissions and roles, check the permission map from the API
    return permissions[routeName] === true;
  };

  // Context value
  const value = {
    role,
    permissions,
    loading,
    error,
    hasAccess,
    clearAccessData: () => {
      setPermissions({});
      setRole(null);
    }
  };

  return <AccessContext.Provider value={value}>{children}</AccessContext.Provider>;
}

// Custom hook to use the access context
export const useAccess = () => {
  const context = useContext(AccessContext);
  
  if (!context) {
    throw new Error('useAccess must be used within an AccessProvider');
  }
  
  return context;
};

/**
 * Higher-order component for protected routes
 * @param {Object} props 
 * @param {string} props.routeName - Name of the route to check access for
 * @param {React.ReactNode} props.children - Child components
 * @param {string} props.redirectTo - Path to redirect to if access is denied
 * @returns {React.ReactNode}
 */
export const RouteGuard = ({ routeName, children, redirectTo = '/unauthorized' }) => {
  const { hasAccess, loading } = useAccess();
  const navigate = useNavigate();
  const location = useLocation();
  
  // Check access when component mounts or route changes
  useEffect(() => {
    if (!loading && !hasAccess(routeName)) {
      navigate(redirectTo, { state: { from: location }, replace: true });
    }
  }, [hasAccess, loading, navigate, redirectTo, routeName, location]);
  
  // Show loading indicator while checking permissions
  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }
  
  // Render children only if user has access
  return hasAccess(routeName) ? children : null;
};

/**
 * Redux action to fetch user access permissions
 */
export const fetchUserAccess = createAsyncThunk(
  'access/fetchUserAccess',
  async (_, { rejectWithValue, getState }) => {
    // Check if we already have access data to avoid unnecessary fetches
    const state = getState();
    if (state.access && state.access.role) {
      return {
        data: {
          role: state.access.role,
          access: state.access.permissions
        }
      };
    }
    
    try {
      const response = await api.get(API_ENDPOINT);
      // Handle the response based on your API format
      if (response.data) {
        if (response.data.data) {
          // Format: { data: { role, access } }
          return response.data;
        } else {
          // Format: direct object with role and access
          return {
            data: {
              role: response.data.role,
              access: response.data.access
            }
          };
        }
      } else {
        return rejectWithValue('Invalid response format from access API');
      }
    } catch (error) {
      console.error('Failed to fetch access permissions:', error);
      return rejectWithValue(error.response?.data?.message || 'Error loading access permissions');
    }
  },
  {
    // Only allow one pending fetchUserAccess operation at a time
    condition: (_, { getState }) => {
      const { access } = getState();
      // Prevent multiple simultaneous requests
      if (access.isLoading) {
        return false;
      }
      return true;
    }
  }
);

/**
 * Custom hook to check if the current user has access to a specific route
 * @param {string} route - Route name to check access for
 * @returns {boolean} - Whether the user has access
 */
export const useRouteAccess = (route) => {
  const { hasAccess } = useAccess();
  return hasAccess(route);
};

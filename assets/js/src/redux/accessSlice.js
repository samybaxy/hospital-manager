/**
 * Redux slice for managing access control state in the hospital manager application
 */

import { createSlice } from '@reduxjs/toolkit';
import { fetchUserAccess } from '../utils/accessControl.jsx';

const initialState = {
  role: null,
  permissions: {},
  isLoading: false,
  error: null
};

export const accessSlice = createSlice({
  name: 'access',
  initialState,
  reducers: {
    clearAccessData: (state) => {
      state.role = null;
      state.permissions = {};
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Handle fetchUserAccess
      .addCase(fetchUserAccess.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchUserAccess.fulfilled, (state, action) => {
        state.isLoading = false;
        // Handle API response format: { data: { role, access } }
        if (action.payload.data) {
          state.role = action.payload.data.role;
          state.permissions = action.payload.data.access || {};
          console.log('Access data loaded to Redux:', state.role, state.permissions);
        } else if (action.payload.role) {
          state.role = action.payload.role;
          state.permissions = action.payload.access || {};
          console.log('Access data loaded to Redux:', state.role, state.permissions);
        } else {
          console.error('Invalid payload format for access data:', action.payload);
        }
      })
      .addCase(fetchUserAccess.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload || 'Failed to load permissions';
      });
  },
});

// Export actions
export const { clearAccessData } = accessSlice.actions;

// Export reducer
export default accessSlice.reducer;

// Export selectors
export const selectRole = (state) => state.access.role;
export const selectPermissions = (state) => state.access.permissions;
export const selectAccessLoading = (state) => state.access.isLoading;
export const selectAccessError = (state) => state.access.error;

/**
 * Selector to check if a user has access to a specific route based on their role
 * @param {Object} state - Redux state
 * @param {string} routeName - Route to check access for
 * @returns {boolean} - Whether the user has access
 */
export const selectHasAccess = (state, routeName) => {
  const { role, permissions } = state.access;
  
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

/**
 * React hooks for the unified UserAccessService
 */

import { useState, useEffect } from 'react';
import userAccessService from '../services/UserAccessService';
import { useAuth } from '../context/AuthContext';

/**
 * Hook to access user permissions and role data
 * @returns {Object} - Access data, loading state, error, and utility functions
 */
export const useUserAccess = () => {
  const [accessData, setAccessData] = useState(userAccessService.getAccessData());
  const [loading, setLoading] = useState(userAccessService.isLoading());
  const [error, setError] = useState(userAccessService.getError());
  const { isAuthenticated } = useAuth();

  useEffect(() => {
    // Subscribe to access service changes
    const unsubscribe = userAccessService.subscribe((data, isLoading, error) => {
      setAccessData(data);
      setLoading(isLoading);
      setError(error);
    });

    // Fetch access data when authenticated
    if (isAuthenticated && !userAccessService.getAccessData()) {
      userAccessService.fetchAccessData().catch(err => {
        console.error('Failed to fetch access data:', err);
      });
    }

    // Clear access data when not authenticated
    if (!isAuthenticated) {
      userAccessService.clearAccessData();
    }

    return unsubscribe;
  }, [isAuthenticated]);

  return {
    role: userAccessService.getRole(),
    permissions: userAccessService.getPermissions(),
    loading,
    error,
    hasAccess: (routeName) => userAccessService.hasAccess(routeName),
    hasCapability: (capability) => userAccessService.hasCapability(capability),
    isAdministrator: () => userAccessService.isAdministrator(),
    isDoctor: () => userAccessService.isDoctor(),
    isPatient: () => userAccessService.isPatient(),
    isLabTech: () => userAccessService.isLabTech(),
    isDeskOfficer: () => userAccessService.isDeskOfficer(),
    getRoleDisplayName: () => userAccessService.getRoleDisplayName(),
    getAccessibleRoutes: () => userAccessService.getAccessibleRoutes(),
    checkMultipleAccess: (routes) => userAccessService.checkMultipleAccess(routes),
    getAccessData: () => userAccessService.getAccessData(),
    refreshAccess: () => userAccessService.fetchAccessData()
  };
};

/**
 * Hook to check access to a specific route
 * @param {string} routeName - Route name to check access for
 * @returns {boolean} - Whether the user has access to the route
 */
export const useRouteAccess = (routeName) => {
  const { hasAccess } = useUserAccess();
  return hasAccess(routeName);
};

/**
 * Hook to check multiple route access at once
 * @param {Array<string>} routeNames - Array of route names to check
 * @returns {Object} - Object with route names as keys and boolean access as values
 */
export const useMultipleRouteAccess = (routeNames) => {
  const { checkMultipleAccess } = useUserAccess();
  return checkMultipleAccess(routeNames);
};

/**
 * Hook to get user role information
 * @returns {Object} - Role information and checking functions
 */
export const useUserRole = () => {
  const { 
    role, 
    isAdministrator, 
    isDoctor, 
    isPatient, 
    isLabTech, 
    isDeskOfficer,
    getRoleDisplayName 
  } = useUserAccess();

  return {
    role,
    displayName: getRoleDisplayName(),
    isAdministrator: isAdministrator(),
    isDoctor: isDoctor(),
    isPatient: isPatient(),
    isLabTech: isLabTech(),
    isDeskOfficer: isDeskOfficer()
  };
};

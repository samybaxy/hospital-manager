import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { useAuth } from '../context/AuthContext';
import { selectHasAccess, selectAccessLoading } from '../redux/accessSlice';

// Separate loading component to avoid conditional hook calls
const LoadingSpinner = () => (
  <div className="flex items-center justify-center h-screen">
    <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
  </div>
);

/**
 * ProtectedRoute component
 * Wraps routes that should only be accessible to authenticated users
 * Redirects to login if not authenticated or to unauthorized if no permission
 * 
 * @param {Object} props
 * @param {React.ReactNode} props.children - Child components
 * @param {string} props.routeName - Name of the route (used for permission checking)
 */
const ProtectedRoute = ({ children, routeName }) => {
  // Always call hooks in the same order and same number on every render
  const location = useLocation();
  const { isAuthenticated, loading: authLoading } = useAuth();
  const accessLoading = useSelector(selectAccessLoading);
  const hasAccess = useSelector(state => routeName ? selectHasAccess(state, routeName) : true);
  
  const loading = authLoading || accessLoading;

  // Handle different states using variables, not conditional hook calls
  let content = children;
  
  if (loading) {
    content = <LoadingSpinner />;
  } else if (!isAuthenticated) {
    content = <Navigate to="/login" state={{ from: location }} replace />;
  } else if (routeName && !hasAccess) {
    content = <Navigate to="/unauthorized" state={{ from: location }} replace />;
  }

  // Always return in a consistent way
  return content;
};

export default ProtectedRoute;

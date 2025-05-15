import { Navigate, useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { useAuth } from '../context/AuthContext';
import { selectHasAccess, selectAccessLoading } from '../redux/accessSlice';

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
  const { isAuthenticated, loading: authLoading } = useAuth();
  const accessLoading = useSelector(selectAccessLoading);
  const hasAccess = useSelector(state => routeName ? selectHasAccess(state, routeName) : true);
  const location = useLocation();
  
  const loading = authLoading || accessLoading;

  // Show loading state while checking authentication and permissions
  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  // Redirect to login if not authenticated
  if (!isAuthenticated) {
    // Pass the current location to redirect back after login
    return <Navigate to="/login" state={{ from: location }} replace />;
  }
  
  // Redirect to unauthorized if no access permission
  if (routeName && !hasAccess) {
    return <Navigate to="/unauthorized" state={{ from: location }} replace />;
  }

  // Render the protected component
  return children;
};

export default ProtectedRoute;

import React from 'react';
import { Navigate, useLocation, useParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useUserAccess } from '../hooks/useUserAccess';

// Separate loading component to avoid conditional hook calls
const LoadingSpinner = () => (
  <div className="flex items-center justify-center h-screen">
    <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
  </div>
);

/**
 * PatientDetailsProtectedRoute component
 * Special route protection for PatientDetails that allows:
 * 1. Staff (doctors, administrators, etc.) with "patients" permission
 * 2. Patients viewing their own records
 * 
 * @param {Object} props
 * @param {React.ReactNode} props.children - Child components (PatientDetails)
 */
const PatientDetailsProtectedRoute = ({ children }) => {
  const location = useLocation();
  const { patientId } = useParams();
  const { user, isAuthenticated, loading: authLoading } = useAuth();
  const { hasAccess, isPatient, loading: accessLoading } = useUserAccess();
  
  const loading = authLoading || accessLoading;

  // Handle different states using variables, not conditional hook calls
  let content = children;
  
  if (loading) {
    content = <LoadingSpinner />;
  } else if (!isAuthenticated) {
    content = <Navigate to="/login" state={{ from: location }} replace />;
  } else {
    // Check access permissions
    const hasStaffAccess = hasAccess('patients');
    const isPatientUser = isPatient();
    
    // Allow access if:
    // 1. User has staff access to patients route, OR
    // 2. User is a patient (they'll only see their own records due to backend restrictions)
    const hasAccess_to_route = hasStaffAccess || isPatientUser;
    
    if (!hasAccess_to_route) {
      content = <Navigate to="/unauthorized" state={{ from: location }} replace />;
    }
  }

  // Always return in a consistent way
  return content;
};

export default PatientDetailsProtectedRoute;

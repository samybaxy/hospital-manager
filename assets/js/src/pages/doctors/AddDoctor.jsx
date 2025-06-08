import React from 'react';
import { Navigate } from 'react-router-dom';
import DoctorForm from './DoctorForm';
import { useUserAccess } from '../../hooks/useUserAccess';

const AddDoctor = () => {
  const { isAdministrator, isDeskOfficer, loading } = useUserAccess();
  
  // Show loading spinner while checking access
  if (loading) {
    return (
      <div className="flex justify-center p-12">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }
  
  // Check if user has permission to add doctors
  const canAddDoctor = isAdministrator() || isDeskOfficer();
  
  if (!canAddDoctor) {
    return <Navigate to="/doctors" replace />;
  }

  return <DoctorForm />;
};

export default AddDoctor;

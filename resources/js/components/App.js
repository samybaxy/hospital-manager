import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Layout from './Layout';
import PatientDashboard from './dashboards/PatientDashboard';
import DoctorDashboard from './dashboards/DoctorDashboard';
import LabTechDashboard from './dashboards/LabTechDashboard';

const App = () => {
  const { user, role } = useAuth();

  if (!user) return <Navigate to="/wp-login.php" />;

  return (
    <Layout>
      <Routes>
        <Route path="/" element={
          role === 'patient' ? <PatientDashboard /> :
          role === 'doctor' ? <DoctorDashboard /> :
          role === 'lab_tech' ? <LabTechDashboard /> :
          <Navigate to="/wp-login.php" />
        } />
        {/* Add more routes as needed */}
      </Routes>
    </Layout>
  );
};

export default App;
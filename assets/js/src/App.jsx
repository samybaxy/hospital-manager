import React, { useState, useEffect } from 'react';
import { HashRouter as Router, Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';
import { AuthProvider } from './context/AuthContext';
import { AccessProvider } from './utils/accessControl.jsx';
import ProtectedRoute from './components/ProtectedRoute';
import Unauthorized from './pages/Unauthorized';

// Import all page components
import Dashboard from './pages/Dashboard';
import Patients from './pages/patients/Patients';
import PatientDetails from './pages/patients/PatientDetails';
import AddPatient from './pages/patients/AddPatient';
import EditPatient from './pages/patients/EditPatient';
import Doctors from './pages/doctors/Doctors';
import AddDoctor from './pages/doctors/AddDoctor';
import EditDoctor from './pages/doctors/EditDoctor';
import DoctorDetails from './pages/doctors/DoctorDetails';
import Appointments from './pages/appointments/Appointments';
import AppointmentView from './pages/appointments/AppointmentView';
import BookAppointment from './pages/appointments/BookAppointment';
import Departments from './pages/Departments';
import Billing from './pages/Billing';
import Inventory from './pages/Inventory';
import Reports from './pages/Reports';
import Settings from './pages/Settings';
import NotFound from './pages/NotFound';
import Login from './pages/Login';
// Import new pages based on API controllers
import LabInvestigations from './pages/LabInvestigations';
import Visitations from './pages/Visitations';
import Chat from './pages/Chat';
import AuditLogs from './pages/AuditLogs';
import Notifications from './pages/Notifications';
import Statistics from './pages/Statistics';

// Main content component with routes
const AppRoutes = () => {
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Example of accessing WordPress data passed via wp_localize_script
    const { apiUrl, nonce } = window.hospitalManagerData || {};
    
    // Simulate loading data from API
    const timer = setTimeout(() => {
      setLoading(false);
    }, 1000);
    
    return () => clearTimeout(timer);
  }, []);
  
  if (loading) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  return (
    <div>
      <Routes>
        {/* Public route for login and unauthorized */}
        <Route path="/login" element={<Login />} />
        <Route path="/unauthorized" element={<Unauthorized />} />
        
        {/* Protected routes that require authentication */}
        <Route path="/" element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        } />
        
        {/* Routes with role-based access control */}
        <Route path="/patients" element={
          <ProtectedRoute routeName="patients">
            <Patients />
          </ProtectedRoute>
        } />
        <Route path="/patients/new" element={
          <ProtectedRoute routeName="patients">
            <AddPatient />
          </ProtectedRoute>
        } />
        <Route path="/patients/:ID/edit" element={
          <ProtectedRoute routeName="patients">
            <EditPatient />
          </ProtectedRoute>
        } />
        <Route path="/patients/:ID" element={
          <ProtectedRoute routeName="patients">
            <PatientDetails />
          </ProtectedRoute>
        } />
        <Route path="/doctors" element={
          <ProtectedRoute routeName="doctors">
            <Doctors />
          </ProtectedRoute>
        } />
         <Route path="/doctors/new" element={
          <ProtectedRoute routeName="doctors">
            <AddDoctor />
          </ProtectedRoute>
        } />
        <Route path="/doctors/:ID/edit" element={
          <ProtectedRoute routeName="doctors">
            <EditDoctor />
          </ProtectedRoute>
        } />
        <Route path="/doctors/:ID" element={
          <ProtectedRoute routeName="doctors">
            <DoctorDetails />
          </ProtectedRoute>
        } />
        <Route path="/appointments" element={
          <ProtectedRoute routeName="appointments">
            <Appointments />
          </ProtectedRoute>
        } />
        <Route path="/appointments/book" element={
          <ProtectedRoute routeName="appointments">
            <BookAppointment />
          </ProtectedRoute>
        } />
        <Route path="/appointments/book/:doctorId" element={
          <ProtectedRoute routeName="appointments">
            <BookAppointment />
          </ProtectedRoute>
        } />
        <Route path="/appointments/:ID" element={
          <ProtectedRoute routeName="appointments">
            <AppointmentView />
          </ProtectedRoute>
        } />
        <Route path="/departments" element={
          <ProtectedRoute routeName="departments">
            <Departments />
          </ProtectedRoute>
        } />
        <Route path="/billing" element={
          <ProtectedRoute routeName="billing">
            <Billing />
          </ProtectedRoute>
        } />
        <Route path="/inventory" element={
          <ProtectedRoute routeName="inventory">
            <Inventory />
          </ProtectedRoute>
        } />
        <Route path="/reports" element={
          <ProtectedRoute routeName="reports">
            <Reports />
          </ProtectedRoute>
        } />
        <Route path="/settings" element={
          <ProtectedRoute routeName="settings">
            <Settings />
          </ProtectedRoute>
        } />
        <Route path="/lab-investigations" element={
          <ProtectedRoute routeName="lab_dashboard">
            <LabInvestigations />
          </ProtectedRoute>
        } />
        <Route path="/visitations" element={
          <ProtectedRoute routeName="visitations">
            <Visitations />
          </ProtectedRoute>
        } />
        <Route path="/chat" element={
          <ProtectedRoute routeName="chat">
            <Chat />
          </ProtectedRoute>
        } />
        <Route path="/audit-logs" element={
          <ProtectedRoute routeName="audit_log">
            <AuditLogs />
          </ProtectedRoute>
        } />
        <Route path="/notifications" element={
          <ProtectedRoute routeName="notifications">
            <Notifications />
          </ProtectedRoute>
        } />
        <Route path="/statistics" element={
          <ProtectedRoute routeName="statistics">
            <Statistics />
          </ProtectedRoute>
        } />
        <Route path="*" element={<NotFound />} />
      </Routes>
    </div>
  );
};

// Main App component with router
const App = () => {
  return (
    <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <AuthProvider>
        {/* Wrap the entire application with AccessProvider for permissions check */}
        <AccessProvider>
          <Routes>
            {/* Login route outside of Layout */}
            <Route path="/login" element={<Login />} />
            
            {/* Add an unauthorized page route */}
            <Route path="/unauthorized" element={<Unauthorized />} />
            
            {/* All other routes inside Layout */}
            <Route path="*" element={
              <Layout>
                <AppRoutes />
              </Layout>
            } />
          </Routes>
        </AccessProvider>
      </AuthProvider>
    </Router>
  );
};

export default App;

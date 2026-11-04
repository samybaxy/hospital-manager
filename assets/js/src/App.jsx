import React, { useState, useEffect } from 'react';
import { HashRouter as Router, Routes, Route, useLocation } from 'react-router-dom';
import Layout from './components/Layout';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import PatientDetailsProtectedRoute from './components/PatientDetailsProtectedRoute';
import Unauthorized from './pages/Unauthorized';

import ErrorBoundary from './components/ErrorBoundary';

// Import all page components
import Dashboard from './pages/Dashboard';
import Profile from './pages/Profile';
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
import Inventory from './pages/Inventory';
import NotFound from './pages/NotFound';
import Login from './pages/Login';
import PasswordResetConfirm from './pages/PasswordResetConfirm';
// Import new pages based on API controllers
import LabInvestigations from './pages/LabInvestigations';
import Visitations from './pages/visitations/Visitations';
import AddVisit from './pages/visitations/AddVisit';
import EditVisit from './pages/visitations/EditVisit';
import ViewVisitDetails from './pages/visitations/ViewVisitDetails';
import Chat from './pages/Chat';
import AuditLogs from './pages/AuditLogs';
import Notifications from './pages/Notifications';
import Statistics from './pages/Statistics';

// (A runtime <style id="hospital-manager-positioning"> block used to be injected
// here. It fought assets/css/src/frontend.css, used 100vw and overflow-x:hidden,
// and lost to it anyway; all app-level layout CSS now lives in frontend.css.)

// Simple scroll to top on route changes
const ScrollToTop = () => {
  const location = useLocation();

  useEffect(() => {
    // Simple scroll to top of page
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  }, [location.pathname]);

  return null;
};

// Main content component with routes
const AppRoutes = () => {
  const location = useLocation();

  return (
    <div>
      <ErrorBoundary resetKey={location.pathname}>
      <Routes>
        
        {/* Protected routes that require authentication */}
        <Route path="/" element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        } />
        
        <Route path="/profile" element={
          <ProtectedRoute>
            <Profile />
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
        <Route path="/patients/:patientId/edit" element={
          <ProtectedRoute routeName="patients">
            <EditPatient />
          </ProtectedRoute>
        } />
        <Route path="/patients/:patientId" element={
          <PatientDetailsProtectedRoute>
            <PatientDetails />
          </PatientDetailsProtectedRoute>
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
        <Route path="/doctors/:doctorId/edit" element={
          <ProtectedRoute routeName="doctors">
            <EditDoctor />
          </ProtectedRoute>
        } />
        <Route path="/doctors/:doctorId" element={
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
        <Route path="/appointments/:appointmentId" element={
          <ProtectedRoute routeName="appointments">
            <AppointmentView />
          </ProtectedRoute>
        } />
        <Route path="/inventory" element={
          <ProtectedRoute routeName="inventory">
            <Inventory />
          </ProtectedRoute>
        } />
        <Route path="/inventory/new" element={
          <ProtectedRoute routeName="inventory">
            <Inventory />
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
        <Route path="/visitations/new" element={
          <ProtectedRoute routeName="visitations">
            <AddVisit />
          </ProtectedRoute>
        } />
        <Route path="/visitations/new/:patientId" element={
          <ProtectedRoute routeName="visitations">
            <AddVisit />
          </ProtectedRoute>
        } />
        <Route path="/visitations/:visitId/edit" element={
          <ProtectedRoute routeName="visitations">
            <EditVisit />
          </ProtectedRoute>
        } />
        <Route path="/visitations/:visitId" element={
          <ProtectedRoute routeName="visitations">
            <ViewVisitDetails />
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

        {/* Billing, Reports and Settings are not built yet; they fall through to NotFound. */}
        <Route path="*" element={<NotFound />} />
      </Routes>
      </ErrorBoundary>
    </div>
  );
};

// Main App component with router
const App = () => {
  return (
    <div className="hospital-manager-app">
      <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <AuthProvider>
          <ScrollToTop />
          <Routes>
            {/* Login route outside of Layout */}
            <Route path="/login" element={<Login />} />

            {/* Public: target of the emailed reset link (#/reset-password?token=...) */}
            <Route path="/reset-password" element={<PasswordResetConfirm />} />
            
            {/* Add an unauthorized page route */}
            <Route path="/unauthorized" element={<Unauthorized />} />
            
            {/* All other routes inside Layout */}
            <Route path="*" element={
              <Layout>
                <AppRoutes />
              </Layout>
            } />
          </Routes>
        </AuthProvider>
      </Router>
    </div>
  );
};

export default App;

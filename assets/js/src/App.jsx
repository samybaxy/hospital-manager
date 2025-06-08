import React, { useState, useEffect } from 'react';
import { HashRouter as Router, Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';
import { AuthProvider } from './context/AuthContext';
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
import Billing from './pages/Billing';
import Inventory from './pages/Inventory';
import Reports from './pages/Reports';
import Settings from './pages/Settings';
import NotFound from './pages/NotFound';
import Login from './pages/Login';
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

// Add global styles for the hospital manager app positioning
const appStyles = `
  /* Target the hospital manager root with higher specificity */
  body #hospital-manager-root,
  #hospital-manager-root {
    margin-top: -100px !important;
    position: relative !important;
    z-index: 999 !important;
    transform: translateY(-20px) !important;
  }
  
  /* Remove any default WordPress spacing */
  #hospital-manager-root .hospital-manager-app {
    margin-top: 0 !important;
    padding-top: 10px !important;
    min-height: calc(100vh - 80px);
  }
  
  /* Override any theme-specific margins */
  .wp-site-blocks #hospital-manager-root,
  .site-content #hospital-manager-root,
  main #hospital-manager-root {
    margin-top: -120px !important;
    margin-bottom: 0 !important;
  }
  
  /* Adjust for WordPress admin bar if present */
  body.admin-bar #hospital-manager-root {
    margin-top: -60px !important;
  }
  
  /* Force positioning for any container elements */
  #hospital-manager-root * {
    box-sizing: border-box;
  }
`;

// More aggressive CSS injection
const injectStyles = () => {
  if (typeof document !== 'undefined') {
    // Remove any existing styles first
    const existingStyle = document.getElementById('hospital-manager-positioning');
    if (existingStyle) {
      existingStyle.remove();
    }
    
    const styleElement = document.createElement('style');
    styleElement.id = 'hospital-manager-positioning';
    styleElement.textContent = appStyles;
    document.head.appendChild(styleElement);
    
    // Also apply direct styles to the root element if it exists
    const rootElement = document.getElementById('hospital-manager-root');
    if (rootElement) {
      rootElement.style.marginTop = '-60px';
      rootElement.style.position = 'relative';
      rootElement.style.zIndex = '999';
      rootElement.style.transform = 'translateY(-20px)';
    }
  }
};

// Inject styles immediately and on DOM ready
injectStyles();
if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', injectStyles);
}

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
        <Route path="/patients/:patientId/edit" element={
          <ProtectedRoute routeName="patients">
            <EditPatient />
          </ProtectedRoute>
        } />
        <Route path="/patients/:patientId" element={
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
        <Route path="/inventory/new" element={
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

        <Route path="*" element={<NotFound />} />
      </Routes>
    </div>
  );
};

// Main App component with router
const App = () => {
  // Apply positioning fix when component mounts
  useEffect(() => {
    injectStyles();
    
    // Continuously check and apply styles in case they get overridden
    const intervalId = setInterval(() => {
      const rootElement = document.getElementById('hospital-manager-root');
      if (rootElement && rootElement.style.marginTop !== '-60px') {
        injectStyles();
      }
    }, 1000);
    
    return () => clearInterval(intervalId);
  }, []);

  return (
    <div 
      className="hospital-manager-app" 
      style={{ 
        marginTop: '0', 
        paddingTop: '10px',
        minHeight: 'calc(100vh - 80px)',
        position: 'relative',
        zIndex: 999
      }}
    >
      <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <AuthProvider>
          {/* Wrap the entire application with AccessProvider for permissions check */}
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
        </AuthProvider>
      </Router>
    </div>
  );
};

export default App;

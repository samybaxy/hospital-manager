import React, { useState, useEffect } from 'react';
import { HashRouter as Router, Routes, Route, useLocation } from 'react-router-dom';
import Layout from './components/Layout';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import PatientDetailsProtectedRoute from './components/PatientDetailsProtectedRoute';
import Unauthorized from './pages/Unauthorized';

// Import development utilities
import './utils/devConsole';

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

// Clean responsive styles - no complex positioning hacks
const appStyles = `
  /* Clean foundation for hospital manager app */
  #hospital-manager-root {
    /* Reset all problematic positioning */
    position: static !important;
    margin: 0 !important;
    padding: 1rem 0 !important;
    transform: none !important;
    z-index: 1 !important;
    
    /* Full width, proper box model */
    width: 100% !important;
    max-width: none !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
  }
  
  /* Clean app container */
  .hospital-manager-app {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
  }
  
  /* MOBILE: Aggressive WordPress override */
  @media (max-width: 767px) {
    /* Force the root to break out of any WordPress containers */
    #hospital-manager-root {
      position: relative !important;
      width: 100vw !important;
      max-width: 100vw !important;
      left: 50% !important;
      right: 50% !important;
      margin-left: -50vw !important;
      margin-right: -50vw !important;
      padding: 0.5rem 0 !important;
    }
    
    .hospital-manager-app {
      width: 100vw !important;
      max-width: 100vw !important;
    }
    
    /* Override any parent WordPress block padding */
    #hospital-manager-root .wp-block-group,
    #hospital-manager-root .wp-site-blocks,
    #hospital-manager-root .entry-content {
      padding: 0 !important;
      margin: 0 !important;
    }
  }
  
  /* Responsive padding system for larger screens */
  @media (min-width: 768px) {
    #hospital-manager-root {
      padding: 1rem 0 !important;
    }
  }
  
  @media (min-width: 1024px) {
    #hospital-manager-root {
      padding: 1.5rem 0 !important;
    }
  }
`;

// Simple, clean style injection
const injectStyles = () => {
  if (typeof document !== 'undefined') {
    const existingStyle = document.getElementById('hospital-manager-positioning');
    if (existingStyle) {
      existingStyle.remove();
    }
    
    const styleElement = document.createElement('style');
    styleElement.id = 'hospital-manager-positioning';
    styleElement.textContent = appStyles;
    document.head.appendChild(styleElement);
  }
};

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
  // Simple style injection on mount
  useEffect(() => {
    injectStyles();
  }, []);

  return (
    <div className="hospital-manager-app">
      <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <AuthProvider>
          <ScrollToTop />
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

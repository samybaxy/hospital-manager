import React, { useState, useEffect } from 'react';
import { HashRouter as Router, Routes, Route, useLocation } from 'react-router-dom';
import Layout from './components/Layout';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import PatientDetailsProtectedRoute from './components/PatientDetailsProtectedRoute';
import Unauthorized from './pages/Unauthorized';

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

// Add global styles for the hospital manager app positioning
const appStyles = `
  /* Target the hospital manager root with higher specificity */
  body #hospital-manager-root,
  #hospital-manager-root {
    margin-top: -100px !important;
    position: relative !important;
    z-index: 999 !important;
    transform: translateY(-20px) !important;
    width: 100% !important;
    max-width: 100% !important;
  }
  
  /* Remove any default WordPress spacing */
  #hospital-manager-root .hospital-manager-app {
    margin-top: 0 !important;
    padding-top: 10px !important;
    min-height: calc(100vh - 80px);
    width: 100% !important;
    max-width: 100% !important;
  }
  
  /* Override any theme-specific margins */
  .wp-site-blocks #hospital-manager-root,
  .site-content #hospital-manager-root,
  main #hospital-manager-root {
    margin-top: -90px !important;
    margin-bottom: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
  }
  
  /* Adjust for WordPress admin bar if present */
  body.admin-bar #hospital-manager-root {
    margin-top: -60px !important;
  }
  
  /* Mobile responsive fixes */
  @media (max-width: 768px) {
    /* Ensure the root element takes full width on mobile */
    .wp-site-blocks > header {
        display: none !important; /* Hide header on mobile */
    }
    .sidebar-mobile-fix {
        margin-top: 30px !important; /* Adjust sidebar margin for mobile */
    }

    body #hospital-manager-root,
    #hospital-manager-root {
      margin-left: 0 !important;
      margin-right: 0 !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
      width: 100vw !important;
      max-width: 100vw !important;
      overflow-x: hidden !important;
    }
    
    #hospital-manager-root .hospital-manager-app {
      padding-left: 0 !important;
      padding-right: 0 !important;
      width: 100% !important;
      max-width: 100% !important;
    }
    
    /* Ensure WordPress container doesn't add padding on mobile */
    .wp-site-blocks #hospital-manager-root,
    .site-content #hospital-manager-root,
    main #hospital-manager-root {
      padding-left: 0 !important;
      padding-right: 0 !important;
      margin-left: 0 !important;
      margin-right: 0 !important;
    }
  }
  
  /* Fluid width for larger screens */
  @media (min-width: 1280px) {
    body #hospital-manager-root,
    #hospital-manager-root,
    #hospital-manager-root .hospital-manager-app,
    .wp-site-blocks #hospital-manager-root,
    .site-content #hospital-manager-root,
    main #hospital-manager-root {
      max-width: none !important;
      width: 100% !important;
    }
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
      rootElement.style.marginTop = '-90px';
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

// ScrollToTop component for smooth scrolling on route changes
const ScrollToTop = () => {
  const location = useLocation();

  useEffect(() => {
    // Function to smoothly scroll to the top of the app
    const scrollToAppTop = () => {
      const appElement = document.getElementById('hospital-manager-root');
      if (appElement) {
        // Get the position of the app element
        const appRect = appElement.getBoundingClientRect();
        const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const targetPosition = currentScrollTop + appRect.top - 20; // 20px padding from top
        
        // Smooth scroll animation
        const startPosition = currentScrollTop;
        const distance = targetPosition - startPosition;
        const duration = 800; // 800ms for smooth animation
        let startTime = null;

        const animateScroll = (currentTime) => {
          if (startTime === null) startTime = currentTime;
          const timeElapsed = currentTime - startTime;
          const progress = Math.min(timeElapsed / duration, 1);
          
          // Easing function for smooth animation (ease-in-out)
          const easeInOutCubic = (t) => {
            return t < 0.5 ? 4 * t * t * t : (t - 1) * (2 * t - 2) * (2 * t - 2) + 1;
          };
          
          const easedProgress = easeInOutCubic(progress);
          const currentPosition = startPosition + (distance * easedProgress);
          
          window.scrollTo(0, currentPosition);
          
          if (progress < 1) {
            requestAnimationFrame(animateScroll);
          }
        };

        requestAnimationFrame(animateScroll);
      } else {
        // Fallback: scroll to top of page if app element not found
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }
    };

    // Small delay to ensure the new route content has rendered
    const scrollTimer = setTimeout(() => {
      scrollToAppTop();
    }, 100);

    return () => clearTimeout(scrollTimer);
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
        paddingLeft: '0',
        paddingRight: '0',
        minHeight: 'calc(100vh - 80px)',
        position: 'relative',
        zIndex: 999,
        width: '100%',
        maxWidth: '100%',
        overflowX: 'hidden'
      }}
    >
      <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <AuthProvider>
          <ScrollToTop />
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

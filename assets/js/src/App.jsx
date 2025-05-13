import React, { useState, useEffect } from 'react';
import { HashRouter as Router, Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';

// Import all page components
import Dashboard from './pages/Dashboard';
import Patients from './pages/Patients';
import Doctors from './pages/Doctors';
import Appointments from './pages/Appointments';
import Departments from './pages/Departments';
import Billing from './pages/Billing';
import Inventory from './pages/Inventory';
import Reports from './pages/Reports';
import Settings from './pages/Settings';
import NotFound from './pages/NotFound';
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
        <Route path="/" element={<Dashboard />} />
        <Route path="/patients" element={<Patients />} />
        <Route path="/doctors" element={<Doctors />} />
        <Route path="/appointments" element={<Appointments />} />
        <Route path="/departments" element={<Departments />} />
        <Route path="/billing" element={<Billing />} />
        <Route path="/inventory" element={<Inventory />} />
        <Route path="/reports" element={<Reports />} />
        <Route path="/settings" element={<Settings />} />
        <Route path="/lab-investigations" element={<LabInvestigations />} />
        <Route path="/visitations" element={<Visitations />} />
        <Route path="/chat" element={<Chat />} />
        <Route path="/audit-logs" element={<AuditLogs />} />
        <Route path="/notifications" element={<Notifications />} />
        <Route path="/statistics" element={<Statistics />} />
        <Route path="*" element={<NotFound />} />
      </Routes>
    </div>
  );
};

// Main App component with router
const App = () => {
  return (
    <Router>
      <Layout>
        <AppRoutes />
      </Layout>
    </Router>
  );
};

export default App;

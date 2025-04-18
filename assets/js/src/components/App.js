import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { Box, CssBaseline } from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { NotificationProvider } from '../services/NotificationService';

// Import layouts and components
import MainLayout from './layouts/MainLayout';
import Login from './auth/Login';
import ProtectedRoute from './ProtectedRoute';

// Import dashboards
import PatientDashboard from './dashboards/PatientDashboard';
import DoctorDashboard from './dashboards/DoctorDashboard';
import LabTechDashboard from './dashboards/LabTechDashboard';
import DeskOfficerDashboard from './dashboards/DeskOfficerDashboard';
import AdminDashboard from './dashboards/AdminDashboard';

// Import other pages
import PatientProfile from './pages/PatientProfile';
import VisitationForm from './pages/VisitationForm';
import LabResultsForm from './pages/LabResultsForm';
import Unauthorized from './pages/Unauthorized';

const App = () => {
    const { auth } = useAuth();
    
    // Redirect to appropriate dashboard based on role
    const getDashboardByRole = () => {
        switch (auth.role) {
            case 'patient':
                return <PatientDashboard />;
            case 'doctor':
                return <DoctorDashboard />;
            case 'lab_tech':
                return <LabTechDashboard />;
            case 'desk_officer':
                return <DeskOfficerDashboard />;
            case 'administrator':
                return <AdminDashboard />;
            default:
                return <Navigate to="/login" />;
        }
    };

    if (auth.loading) {
        return <div>Loading...</div>;
    }

    return (
        <NotificationProvider>
            <Box sx={{ display: 'flex' }}>
                <CssBaseline />
                <Routes>
                    <Route path="/login" element={
                        auth.isAuthenticated ? 
                            <Navigate to="/" replace /> : 
                            <Login />
                    } />

                    <Route path="/unauthorized" element={<Unauthorized />} />

                    <Route path="/" element={
                        <ProtectedRoute>
                            <MainLayout />
                        </ProtectedRoute>
                    }>
                        <Route index element={getDashboardByRole()} />

                        {/* Doctor and Desk Officer Routes */}
                        <Route path="patients/:id" element={
                            <ProtectedRoute allowedRoles={['doctor', 'desk_officer']}>
                                <PatientProfile />
                            </ProtectedRoute>
                        } />

                        {/* Doctor Routes */}
                        <Route path="visitations/new" element={
                            <ProtectedRoute allowedRoles={['doctor']}>
                                <VisitationForm />
                            </ProtectedRoute>
                        } />

                        {/* Lab Tech Routes */}
                        <Route path="lab-results/:id" element={
                            <ProtectedRoute allowedRoles={['lab_tech']}>
                                <LabResultsForm />
                            </ProtectedRoute>
                        } />
                    </Route>
                </Routes>
            </Box>
        </NotificationProvider>
    );
};

export default App;

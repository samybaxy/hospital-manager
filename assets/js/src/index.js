import React from 'react';
import ReactDOM from 'react-dom';
import { BrowserRouter as Router } from 'react-router-dom';
import App from './components/App';
import { ThemeProvider, createTheme } from '@mui/material';
import { HospitalProvider } from './contexts/HospitalContext';
import { AuthProvider } from './contexts/AuthContext';
import { QueryClient, QueryClientProvider } from 'react-query';

const theme = createTheme({
  palette: {
    primary: {
      main: '#1976d2',
    },
    secondary: {
      main: '#dc004e',
    },
  },
});

// Initialize QueryClient
const queryClient = new QueryClient();

// Initialize app once DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const rootElement = document.getElementById('hospital-manager-root');
    if (rootElement) {
    ReactDOM.render(
        <QueryClientProvider client={queryClient}>
            <ThemeProvider theme={theme}>
                <AuthProvider>
                    <HospitalProvider>
                        <Router basename="/hospital-manager">
                            <App isFrontend={window.hospitalManagerData?.isFrontend || false} />
                        </Router>
                    </HospitalProvider>
                </AuthProvider>
            </ThemeProvider>
        </QueryClientProvider>,
        rootElement
    );
    }
});

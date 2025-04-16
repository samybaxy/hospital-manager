import React from 'react';
import ReactDOM from 'react-dom';
import { BrowserRouter as Router } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from 'react-query';
import { ThemeProvider } from '@mui/material';
import App from './components/App';
import { AuthProvider } from './contexts/AuthContext';
import theme from './theme';

const queryClient = new QueryClient();

const root = document.getElementById('hospital-manager-root');
if (root) {
    ReactDOM.render(
        <QueryClientProvider client={queryClient}>
            <ThemeProvider theme={theme}>
                <AuthProvider>
                    <Router basename="/hospital-manager">
                        <App />
                    </Router>
                </AuthProvider>
            </ThemeProvider>
        </QueryClientProvider>,
        root
    );
}
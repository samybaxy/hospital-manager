import React from 'react';
import ReactDOM from 'react-dom';
import { BrowserRouter } from 'react-router-dom';
import { ThemeProvider } from '@mui/material';
import { QueryClient, QueryClientProvider } from 'react-query';
import App from './components/App';
import theme from './theme';
import { AuthProvider } from './contexts/AuthContext';

const queryClient = new QueryClient();

const rootElement = document.getElementById('hospital-manager-root');
if (rootElement) {
  ReactDOM.render(
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <AuthProvider>
          <BrowserRouter>
            <App />
          </BrowserRouter>
        </AuthProvider>
      </ThemeProvider>
    </QueryClientProvider>,
    rootElement
  );
}
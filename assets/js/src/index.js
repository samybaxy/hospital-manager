import React from 'react';
import ReactDOM from 'react-dom';
import App from './components/App';
import { ThemeProvider, createTheme } from '@mui/material';
import { HospitalProvider } from './contexts/HospitalContext';

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

const rootElement = document.getElementById('hospital-manager-root');
if (rootElement) {
  ReactDOM.render(
    <ThemeProvider theme={theme}>
      <HospitalProvider>
        <App />
      </HospitalProvider>
    </ThemeProvider>,
    rootElement
  );
}

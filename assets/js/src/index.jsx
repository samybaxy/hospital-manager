import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import '../../css/src/frontend.css'; // Import CSS from assets/css/src
import './services/apiService'; // Import apiService to initialize interceptors

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('hospital-manager-root');
  
  if (container) {
    const root = createRoot(container);
    root.render(
      <React.StrictMode>
        <App />
      </React.StrictMode>
    );
  }
});

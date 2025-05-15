import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import App from './App';
import store from './redux/store';
import '../../css/src/frontend.css'; // Import CSS from assets/css/src
import './services/apiService'; // Import apiService to initialize interceptors

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('hospital-manager-root');
  
  if (container) {
    const root = createRoot(container);
    root.render(
      <React.StrictMode>
        <Provider store={store}>
          <App />
        </Provider>
      </React.StrictMode>
    );
  }
});

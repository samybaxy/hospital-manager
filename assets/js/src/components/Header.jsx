import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const Header = ({ title }) => {
  const { user, logout, isAuthenticated } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    console.log('Header logout initiated...');
    
    // Preserve dev mode setting
    const devMode = localStorage.getItem('hospital_manager_dev_mode');
    
    try {
      await logout();
    } catch (error) {
      console.error('Logout error:', error);
    }
    
    // Clear storage but preserve dev mode
    localStorage.clear();
    sessionStorage.clear();
    
    if (devMode !== null) {
      localStorage.setItem('hospital_manager_dev_mode', devMode);
    }
    
    // Redirect to homepage, not login page
    window.location.href = window.location.origin + '/';
  };

  return (
    <div className="shadow" style={{ backgroundColor: 'rgb(247, 251, 255)' }}>
      <div className="w-full mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <h1 className="text-3xl font-bold text-gray-900">{title || 'Hospital Manager'}</h1>
        
        {isAuthenticated && (
          <div className="flex items-center space-x-4">
            <div className="text-gray-700">
              <span className="font-medium">Welcome, {user?.name || 'User'}</span>
            </div>
            <button
              onClick={handleLogout}
              className="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded-md transition-colors"
            >
              Logout
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default Header;

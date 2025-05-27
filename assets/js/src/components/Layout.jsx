import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { Link, useLocation, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useUserAccess } from '../hooks/useUserAccess';
import AccessDebug from './AccessDebug';
import Sidebar from './Sidebar';

// Separate loading component to avoid conditional hook calls
const LoadingSpinner = () => (
  <div className="flex items-center justify-center h-screen">
    <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
  </div>
);

const Layout = ({ children }) => {
  // Call all hooks unconditionally at the top
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [isSigningOut, setIsSigningOut] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();
  const { user, isAuthenticated, loading: authLoading, logout } = useAuth();
  const { role, isLoading: accessLoading } = useUserAccess();
  
  // Auto-collapse sidebar for appointments page
  useEffect(() => {
    if (location.pathname === '/appointments' || location.pathname === '/patients') {
      setSidebarCollapsed(true);
    } else {
      setSidebarCollapsed(false);
    }
  }, [location.pathname]);
  
  // Thorough sign out process
  const handleSignOut = useCallback(async () => {
    try {
      setIsSigningOut(true);
      
      // Close the user menu dropdown immediately
      setUserMenuOpen(false);
      
      // Use AuthContext logout function which handles tokens and API calls
      await logout();
      
      // Short delay to ensure all state changes are processed
      setTimeout(() => {
        // Redirect to WordPress home page
        window.location.href = '/'; // This will navigate to WordPress home, not the React app root
      }, 100);
    } catch (error) {
      console.error("Error during sign out process:", error);
      // Even if there's an error, try to redirect
      setTimeout(() => {
        window.location.href = '/';
      }, 100);
    } finally {
      setIsSigningOut(false);
    }
  }, [logout]);

  // We've moved all navigation items to the Sidebar component
  // This allows for proper separation of concerns

  // Toggle sidebar on mobile - use useCallback to memoize functions
  const toggleSidebar = useCallback(() => {
    setSidebarOpen(prevState => !prevState);
  }, []);

  // Toggle sidebar collapsed state
  const toggleSidebarCollapse = useCallback(() => {
    setSidebarCollapsed(prevState => !prevState);
  }, []);

  // Toggle user menu dropdown
  const toggleUserMenu = useCallback(() => {
    setUserMenuOpen(prevState => !prevState);
  }, []);

  // Handle loading and authentication states without conditional hook calls
  if (authLoading || isSigningOut) {
    return <LoadingSpinner />;
  }

  if (!isAuthenticated && location.pathname !== '/login') {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Render the main layout
  return (
    <div className="min-h-screen flex bg-gray-100 w-full">
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div 
          className="md:hidden fixed inset-0 z-40 bg-gray-600 bg-opacity-75 transition-opacity ease-linear"
          onClick={toggleSidebar}
        ></div>
      )}

      {/* Sidebar */}
      <div className={`
        ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} 
        md:translate-x-0 fixed md:sticky top-0 h-screen z-50 md:z-auto left-0
        ${sidebarCollapsed ? 'w-16' : 'w-64'}
        transition-all duration-300 transform bg-primary-800 overflow-y-auto flex-shrink-0
      `}>
        <Sidebar 
          isOpen={sidebarOpen} 
          isCollapsed={sidebarCollapsed} 
          onToggleCollapse={toggleSidebarCollapse} 
        />
      </div>

      {/* Main Content */}
      <div className="flex-1 flex flex-col">
        {/* Header */}
        <header className="shadow-sm z-10 sticky top-0 bg-gradient-to-r from-blue-50 to-primary-50">
          <div className="px-4 sm:px-6 lg:px-8 py-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                {/* Mobile menu button */}
                <button 
                  onClick={toggleSidebar}
                  className="md:hidden inline-flex items-center justify-center p-2 rounded-md text-primary-600 hover:text-primary-800 hover:bg-primary-100 focus:outline-none"
                >
                  <span className="sr-only">Open sidebar</span>
                  <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
                </button>
                
                {/* Toggle Collapse Button */}
                <div className="hidden md:flex items-center ml-2">
                  <button 
                    onClick={toggleSidebarCollapse}
                    className="p-1.5 rounded-full bg-primary-100 hover:bg-primary-200 text-primary-700 transition-colors duration-200 border border-primary-200 shadow-sm flex items-center justify-center h-7 w-7"
                    title={sidebarCollapsed ? "Expand Sidebar" : "Collapse Sidebar"}
                  >
                    <svg 
                    className="h-4 w-4" 
                    fill="none" 
                    viewBox="0 0 24 24" 
                    stroke="currentColor"
                    style={{ transition: 'transform 0.3s ease' }}
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={sidebarCollapsed ? "M9 5l7 7-7 7" : "M15 19l-7-7 7-7"} />
                  </svg>
                  </button>
                </div>

                {/* Role information */}
                <div className="hidden sm:flex items-center ml-3 h-7">
                  <div className="text-primary-700 flex items-center h-full" title="Your current role">
                    <span className="text-xs uppercase tracking-wide font-semibold bg-primary-50 px-3 py-0.5 rounded-full border border-primary-200 shadow-sm inline-flex items-center">
                      {role || 'Guest'}
                    </span>
                  </div>
                </div>
              </div>

              {/* Empty space to replace search bar */}
              <div className="flex-1"></div>

              {/* User menu and notifications */}
              <div className="flex items-center">
                {/* Notifications */}
                <button className="p-2 rounded-full text-primary-600 hover:text-primary-800 hover:bg-primary-100 focus:outline-none relative transition-colors duration-200">
                  <span className="sr-only">View notifications</span>
                  <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  <span className="absolute top-0 right-0 h-2 w-2 bg-red-500 rounded-full"></span>
                </button>

                {/* User dropdown */}
                <div className="ml-4 relative flex-shrink-0">
                  <div>
                    <button 
                      onClick={toggleUserMenu}
                      className="flex text-sm rounded-full focus:outline-none"
                    >
                      <span className="sr-only">Open user menu</span>
                      <div className="h-8 w-8 rounded-full bg-primary-700 text-white flex items-center justify-center">
                        <span className="text-sm font-medium">
                          {user && user.name ? 
                            user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() 
                            : 'U'}
                        </span>
                      </div>
                    </button>
                  </div>

                  {/* Dropdown menu */}
                  {userMenuOpen && (
                    <div 
                      className="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none"
                      role="menu"
                    >
                      <Link to="/profile" className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        Your Profile
                      </Link>
                      <Link to="/settings" className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        Settings
                      </Link>
                      <button 
                        onClick={handleSignOut} 
                        className="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                      >
                        Sign out
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </header>

        {/* Main content area */}
        <main className="flex-1 bg-gray-100">
          <div className="py-6 px-4 md:px-6 lg:px-8 w-full max-w-full">
            {children}
          </div>
        </main>
      </div>
      
      {/* Debug component for development */}
      <AccessDebug />
    </div>
  );
};

export default Layout;

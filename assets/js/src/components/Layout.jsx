import React, { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import { Link, useLocation, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useUserAccess } from '../hooks/useUserAccess';
import AccessDebug from './AccessDebug';
import Sidebar from './Sidebar';
import DevModeToggle from './DevModeToggle';
import DevModeStatus from './DevModeStatus';

// Separate loading component to avoid conditional hook calls
const LoadingSpinner = ({ message = "Loading..." }) => (
  <div className="flex items-center justify-center h-screen flex-col">
    <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mb-4"></div>
    <p className="text-gray-600">{message}</p>
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
  const { role, isLoading: accessLoading, isAdministrator } = useUserAccess();
  
  // Ref for user menu dropdown
  const userMenuRef = useRef(null);
  
  // Auto-collapse sidebar for appointments page
  useEffect(() => {
    if (location.pathname === '/patients' || 
        location.pathname === '/doctors' || 
        location.pathname === '/appointments' || 
        location.pathname.startsWith('/appointments/book/') ||
        location.pathname === '/visitations' || 
        location.pathname === '/inventory' || 
        location.pathname === '/lab-investigations'
    ) {
      setSidebarCollapsed(true);
    } else {
      setSidebarCollapsed(false);
    }
  }, [location.pathname]);

  // Close mobile sidebar when location changes (navigation occurs)
  useEffect(() => {
    setSidebarOpen(false);
  }, [location.pathname]);
  
  // Handle click outside user menu
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (userMenuRef.current && !userMenuRef.current.contains(event.target)) {
        setUserMenuOpen(false);
      }
    };

    if (userMenuOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [userMenuOpen]);
  
  // Thorough sign out process
  const handleSignOut = useCallback(async () => {
    try {
      setIsSigningOut(true);
      
      // Close the user menu dropdown immediately
      setUserMenuOpen(false);
      
      console.log('Starting logout process...');
      
      // Use AuthContext logout function which handles tokens and API calls
      await logout();
      
      console.log('Logout completed, redirecting...');
      
      // Redirect to WordPress login page or home page
      // Use a more direct approach to ensure redirection works
      const redirectTo = window.location.origin + '/wp-login.php';
      
      // Clear any remaining application state
      localStorage.clear();
      sessionStorage.clear();
      
      // Force a page reload to completely clear the React state
      window.location.replace(redirectTo);
      
    } catch (error) {
      console.error("Error during sign out process:", error);
      
      // Even if there's an error, force the redirect
      console.log('Logout failed, forcing redirect anyway...');
      
      // Clear storage manually
      localStorage.clear();
      sessionStorage.clear();
      
      // Force redirect to WordPress login
      window.location.replace(window.location.origin + '/wp-login.php');
    } finally {
      // This might not execute due to window.location.replace
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
  if (authLoading) {
    return <LoadingSpinner message="Authenticating..." />;
  }
  
  if (isSigningOut) {
    return <LoadingSpinner message="Signing out..." />;
  }

  if (!isAuthenticated && location.pathname !== '/login') {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Render the main layout
  return (
    <div className="flex bg-gray-100 w-full">
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div 
          className="md:hidden fixed inset-0 z-40 bg-gray-600 bg-opacity-75"
          onClick={toggleSidebar}
        ></div>
      )}

      {/* Sidebar */}
      <div className={`
        ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} 
        md:translate-x-0 fixed md:sticky top-0 h-screen md:h-auto ${sidebarOpen ? 'z-50' : 'z-40'} md:z-auto left-0
        ${sidebarCollapsed ? 'w-16' : 'w-64'}
        transition-all duration-300 transform bg-primary-800 overflow-y-auto flex-shrink-0
        md:block sidebar-mobile-fix ${sidebarOpen ? 'mobile-sidebar-open' : ''}
      `}>
        <Sidebar 
          isOpen={sidebarOpen} 
          isCollapsed={sidebarCollapsed} 
          onToggleCollapse={toggleSidebarCollapse} 
        />
      </div>

      {/* Main Content */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header */}
        <header className={`shadow-sm ${sidebarOpen ? 'z-40' : 'z-50'} sticky top-0 bg-gradient-to-r from-blue-50 to-primary-50`}>
          <div className="hospital-manager-container py-4">
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
                
                {/* Development Mode Status */}
                <DevModeStatus />
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
                <div className="ml-4 relative flex-shrink-0" ref={userMenuRef}>
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
                      className="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50"
                      role="menu"
                    >
                      <Link 
                        to="/profile" 
                        className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        onClick={() => setUserMenuOpen(false)}
                      >
                        Your Profile
                      </Link>
                      <Link 
                        to="/settings" 
                        className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        onClick={() => setUserMenuOpen(false)}
                      >
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
        <main className="flex-1 bg-gray-100 min-w-0">
          <div className="hospital-manager-container mobile-content-padding py-6">
            {children}
          </div>
        </main>
      </div>
      
      {/* Debug component for administrators only */}
      {isAdministrator() && <AccessDebug />}
      
      {/* Development mode toggle - visible to admins and developers */}
      <DevModeToggle />
    </div>
  );
};

export default Layout;

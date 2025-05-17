import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { Link, useLocation, Navigate, useNavigate } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { useAuth } from '../context/AuthContext';
import { selectAccessLoading, clearAccessData } from '../redux/accessSlice';
import { fetchUserAccess } from '../utils/accessControl.jsx';
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
  const dispatch = useDispatch();
  const { user, isAuthenticated, loading: authLoading, logout } = useAuth();
  const accessLoading = useSelector(selectAccessLoading);
  const accessState = useSelector(state => state.access);
  const role = accessState?.role;
  const permissions = accessState?.permissions || {};
  
  // Prefetch user access data
  useEffect(() => {
    if (isAuthenticated && !accessState?.role && !accessLoading) {
      dispatch(fetchUserAccess());
    }
  }, [dispatch, isAuthenticated, accessState?.role, accessLoading]);
  
  // We no longer need to create a comprehensive user data object for Sidebar
  // as it will use Redux directly

  // Thorough sign out process
  const handleSignOut = useCallback(async () => {
    try {
      setIsSigningOut(true);
      
      // Close the user menu dropdown immediately
      setUserMenuOpen(false);
      
      // Use AuthContext logout function which handles tokens and API calls
      await logout();
      
      // Ensure Redux state is cleared (though this should be done in logout function already)
      dispatch(clearAccessData());
      
      // Short delay to ensure all state changes are processed
      setTimeout(() => {
        // Redirect to WordPress home page
        window.location.href = '/'; // This will navigate to WordPress home, not the React app root
      }, 100);
    } catch (error) {
      console.error("Error during sign out process:", error);
      // Even if there's an error, try to clear everything and redirect
      dispatch(clearAccessData());
      
      setTimeout(() => {
        window.location.href = '/';
      }, 100);
    } finally {
      setIsSigningOut(false);
    }
  }, [logout, dispatch]);

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
  // Only check authLoading and isSigningOut, not accessLoading to prevent infinite loops
  // caused by Sidebar's fetchUserAccess affecting Layout rendering
  if (authLoading || isSigningOut) {
    return <LoadingSpinner />;
  }

  if (!isAuthenticated && location.pathname !== '/login') {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Render the main layout
  return (
    <div className="min-h-screen flex bg-gray-100">
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
        transition-all duration-300 transform bg-primary-800 overflow-y-auto
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
        <header className="shadow-sm z-10 sticky top-0" style={{ backgroundColor: 'rgb(247, 251, 255)' }}>
          <div className="px-4 sm:px-6 lg:px-8 py-4">
            <div className="flex items-center justify-between">
              {/* Mobile menu button */}
              <button 
                onClick={toggleSidebar}
                className="md:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 focus:outline-none"
              >
                <span className="sr-only">Open sidebar</span>
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>

              {/* Empty space to replace search bar */}
              <div className="flex-1"></div>

              {/* User menu and notifications */}
              <div className="flex items-center">
                {/* Notifications */}
                <button className="p-2 rounded-full text-gray-500 hover:text-gray-900 hover:bg-gray-100 focus:outline-none relative">
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
          <div className="py-6 px-2 md:px-4 lg:px-6">
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

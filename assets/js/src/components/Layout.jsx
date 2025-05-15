import React, { useState, useMemo, useCallback } from 'react';
import { Link, NavLink, useLocation, Navigate, useNavigate } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { useAuth } from '../context/AuthContext';
import { selectAccessLoading, clearAccessData } from '../redux/accessSlice';
import AccessDebug from './AccessDebug';

// Separate loading component to avoid conditional hook calls
const LoadingSpinner = () => (
  <div className="flex items-center justify-center h-screen">
    <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
  </div>
);

const Layout = ({ children }) => {
  // Call all hooks unconditionally at the top
  const [sidebarOpen, setSidebarOpen] = useState(false);
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

  // All available navigation items
  const allNavItems = [
    { path: '/', label: 'Dashboard', routeName: null, icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { path: '/patients', label: 'Patients', routeName: 'patients', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { path: '/doctors', label: 'Doctors', routeName: 'doctors', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { path: '/appointments', label: 'Appointments', routeName: 'appointments', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { path: '/visitations', label: 'Visitations', routeName: 'visitations', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { path: '/lab-investigations', label: 'Lab Tests', routeName: 'lab_dashboard', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
    { path: '/departments', label: 'Departments', routeName: 'departments', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { path: '/chat', label: 'Chat', routeName: 'chat', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { path: '/notifications', label: 'Notifications', routeName: 'notifications', icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' },
    { path: '/audit-logs', label: 'Audit Logs', routeName: 'audit_log', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { path: '/billing', label: 'Billing', routeName: 'billing', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z' },
    { path: '/inventory', label: 'Inventory', routeName: 'inventory', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    { path: '/reports', label: 'Reports', routeName: 'reports', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { path: '/statistics', label: 'Statistics', routeName: 'statistics', icon: 'M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { path: '/settings', label: 'Settings', routeName: 'settings', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
  ];

  // Pre-calculate access permissions for each route once at render time
  const navItems = useMemo(() => {
    // Function to check access for a route
    const hasAccess = (routeName) => {
      // Always include dashboard or items without routeName requirement
      if (routeName === null) return true;
      
      // Administrator has access to everything
      if (role === 'administrator') {
        return true;
      }
      
      // Role-based access restrictions based on requirements
      if (role === 'doctor') {
        if (['audit_log', 'billing', 'inventory', 'settings'].includes(routeName)) {
          return false;
        }
      } else if (role === 'patient') {
        if (['patients', 'doctors', 'departments', 'audit_log', 'billing', 
             'inventory', 'reports', 'statistics', 'settings'].includes(routeName)) {
          return false;
        }
      } else if (role === 'lab_tech') {
        return ['lab_dashboard', 'dashboard'].includes(routeName);
      } else if (role === 'desk_officer') {
        if (['chat', 'audit_log', 'billing', 'inventory', 'statistics', 'settings'].includes(routeName)) {
          return false;
        }
      }
      
      // For other permissions and roles, check the permission map from the API
      return permissions[routeName] === true;
    };

    // Filter navigation items based on access permissions
    return allNavItems.filter(item => hasAccess(item.routeName));
  }, [role, permissions]); // Only recalculate when role or permissions change

  // Toggle sidebar on mobile - use useCallback to memoize functions
  const toggleSidebar = useCallback(() => {
    setSidebarOpen(prevState => !prevState);
  }, []);

  // Toggle user menu dropdown
  const toggleUserMenu = useCallback(() => {
    setUserMenuOpen(prevState => !prevState);
  }, []);

  // Handle loading and authentication states without conditional hook calls
  if (authLoading || accessLoading || isSigningOut) {
    return <LoadingSpinner />;
  }

  if (!isAuthenticated && location.pathname !== '/login') {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Render the main layout
  return (
    <div className="h-screen flex overflow-hidden bg-gray-100">
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
        md:translate-x-0 fixed md:relative z-50 md:z-auto inset-y-0 left-0 w-64 
        transition duration-300 transform bg-primary-800 overflow-y-auto
      `}>
        <div className="flex items-center justify-center h-16">
          <Link to="/" className="flex items-center">
            <svg className="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
            <span className="ml-2 text-white font-bold text-xl">Hospital Manager</span>
          </Link>
        </div>

        <div className="px-2 py-4">
          <div className="space-y-1">
            {navItems.map((item) => {
              const isActive = location.pathname === item.path;
              return (
                <NavLink
                  key={item.path}
                  to={item.path}
                  className={`
                    flex items-center px-3 py-2 text-base font-medium rounded-md group
                    ${isActive 
                      ? 'bg-primary-900 text-white' 
                      : 'text-primary-100 hover:bg-primary-700 hover:text-white'}
                  `}
                >
                  <svg
                    className={`mr-3 h-5 w-5 ${isActive ? 'text-primary-300' : 'text-primary-400 group-hover:text-primary-300'}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                  >
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={item.icon} />
                </svg>
                {item.label}
              </NavLink>
            )})}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Header */}
        <header className="shadow-sm z-10" style={{ backgroundColor: 'rgb(247, 251, 255)' }}>
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
        <main className="flex-1 overflow-y-auto bg-gray-100">
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

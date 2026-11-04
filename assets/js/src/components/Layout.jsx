import React, { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import { Link, useLocation, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useUserAccess } from '../hooks/useUserAccess';
import Sidebar from './Sidebar';

// Separate loading component to avoid conditional hook calls
const LoadingSpinner = ({ message = "Loading..." }) => (
  <div className="flex items-center justify-center min-h-[60dvh] flex-col">
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
  const drawerRef = useRef(null);

  // md (768px) and up: sidebar is part of the page; below: off-canvas drawer
  const [isDesktop, setIsDesktop] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(min-width: 768px)').matches
  );
  useEffect(() => {
    const mq = window.matchMedia('(min-width: 768px)');
    const onChange = (e) => {
      setIsDesktop(e.matches);
      if (e.matches) setSidebarOpen(false);
    };
    mq.addEventListener('change', onChange);
    return () => mq.removeEventListener('change', onChange);
  }, []);

  // Mobile drawer: Escape closes it, the page behind doesn't scroll, and while it
  // is closed its links are out of the tab order (inert)
  useEffect(() => {
    const drawer = drawerRef.current;
    if (drawer) {
      if (!isDesktop && !sidebarOpen) drawer.setAttribute('inert', '');
      else drawer.removeAttribute('inert');
    }
    if (!sidebarOpen) return undefined;
    const onKey = (e) => { if (e.key === 'Escape') setSidebarOpen(false); };
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', onKey);
    };
  }, [sidebarOpen, isDesktop]);
  
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

    const handleKey = (event) => { if (event.key === 'Escape') setUserMenuOpen(false); };

    if (userMenuOpen) {
      // pointerdown covers mouse and touch (mousedown alone missed some taps)
      document.addEventListener('pointerdown', handleClickOutside);
      document.addEventListener('keydown', handleKey);
      return () => {
        document.removeEventListener('pointerdown', handleClickOutside);
        document.removeEventListener('keydown', handleKey);
      };
    }
  }, [userMenuOpen]);
  
  // Thorough sign out process
  const handleSignOut = useCallback(async () => {
    setIsSigningOut(true);
    
    // Close the user menu dropdown immediately
    setUserMenuOpen(false);
    
    console.log('Starting logout process...');
    
    try {
      // Use AuthContext logout function which handles tokens and API calls
      await logout();
      
      console.log('Logout completed successfully');
      
    } catch (error) {
      console.error("Error during logout API call:", error);
      // Continue with cleanup even if API call failed
    }
    
    console.log('Clearing local state and redirecting to homepage...');
    
    // Clear all application state
    localStorage.clear();
    sessionStorage.clear();
    
    // Always redirect to homepage (never wp-login.php)
    const homeUrl = window.location.origin + '/';
    console.log('Redirecting to:', homeUrl);
    
    // Use window.location.href for a clean redirect
    window.location.href = homeUrl;
    
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

  // The icon-only "collapsed" sidebar is a desktop option; the phone drawer is
  // always full width so labels are readable.
  const collapsed = sidebarCollapsed && isDesktop;

  // Render the main layout
  return (
    <div className="flex bg-gray-100 w-full">
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div
          className="md:hidden fixed inset-0 z-40 bg-gray-600/75 motion-safe:transition-opacity"
          onClick={toggleSidebar}
          aria-hidden="true"
        ></div>
      )}

      {/* Sidebar: off-canvas drawer below md, sticky column from md.
          --hm-top-offset keeps it below the WordPress admin bar. */}
      <div
        ref={drawerRef}
        id="hm-sidebar"
        className={`
          ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} md:translate-x-0
          fixed left-0 bottom-0 top-[var(--hm-top-offset)] z-50 w-72 max-w-[85vw]
          md:sticky md:z-auto md:self-start md:max-w-none md:h-[calc(100dvh-var(--hm-top-offset))]
          ${collapsed ? 'md:w-16' : 'md:w-64'}
          motion-safe:transition-[transform,width] motion-safe:duration-300 bg-primary-800 overflow-y-auto overscroll-contain flex-shrink-0
        `}
      >
        <Sidebar
          isOpen={sidebarOpen}
          isCollapsed={collapsed}
          onToggleCollapse={toggleSidebarCollapse}
        />
      </div>

      {/* Main Content */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header */}
        <header className="shadow-sm z-30 sticky top-[var(--hm-top-offset)] bg-gradient-to-r from-blue-50 to-primary-50">
          <div className="px-2 sm:px-6 lg:px-8 py-1.5 sm:py-3">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center min-w-0">
                {/* Mobile menu button */}
                <button
                  onClick={toggleSidebar}
                  className="md:hidden inline-flex items-center justify-center size-11 rounded-md text-primary-600 hover:text-primary-800 hover:bg-primary-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                  aria-controls="hm-sidebar"
                  aria-expanded={sidebarOpen}
                >
                  <span className="sr-only">{sidebarOpen ? 'Close menu' : 'Open menu'}</span>
                  <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
                </button>

                {/* Toggle Collapse Button (desktop) */}
                <div className="hidden md:flex items-center">
                  <button
                    onClick={toggleSidebarCollapse}
                    className="rounded-full bg-primary-100 hover:bg-primary-200 text-primary-700 transition-colors duration-200 border border-primary-200 shadow-sm flex items-center justify-center size-9"
                    title={sidebarCollapsed ? "Expand Sidebar" : "Collapse Sidebar"}
                    aria-label={sidebarCollapsed ? "Expand sidebar" : "Collapse sidebar"}
                  >
                    <svg
                      className="size-4"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      style={{ transition: 'transform 0.3s ease' }}
                      aria-hidden="true"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={sidebarCollapsed ? "M9 5l7 7-7 7" : "M15 19l-7-7 7-7"} />
                    </svg>
                  </button>
                </div>

                {/* Role information */}
                <div className="hidden sm:flex items-center ml-3 min-w-0" title="Your current role">
                  <span className="text-xs uppercase tracking-wide font-semibold text-primary-700 bg-primary-50 px-3 py-0.5 rounded-full border border-primary-200 shadow-sm inline-flex items-center truncate">
                    {role || 'Guest'}
                  </span>
                </div>
              </div>

              {/* User menu and notifications */}
              <div className="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                {/* Notifications */}
                <Link
                  to="/notifications"
                  className="inline-flex items-center justify-center size-11 rounded-full text-primary-600 hover:text-primary-800 hover:bg-primary-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 relative transition-colors duration-200"
                >
                  <span className="sr-only">View notifications</span>
                  <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  <span className="absolute top-2 right-2 size-2 bg-red-500 rounded-full" aria-hidden="true"></span>
                </Link>

                {/* User dropdown */}
                <div className="relative flex-shrink-0" ref={userMenuRef}>
                  <button
                    onClick={toggleUserMenu}
                    className="flex items-center justify-center size-11 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    aria-haspopup="menu"
                    aria-expanded={userMenuOpen}
                  >
                    <span className="sr-only">Open user menu</span>
                    <span className="size-9 rounded-full bg-primary-700 text-white flex items-center justify-center text-sm font-medium" aria-hidden="true">
                      {user && user.name ?
                        user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()
                        : 'U'}
                    </span>
                  </button>

                  {/* Dropdown menu */}
                  {userMenuOpen && (
                    <div
                      className="origin-top-right absolute right-0 mt-2 w-56 max-w-[calc(100vw-1rem)] rounded-md shadow-lg py-1 bg-white ring-1 ring-black/5 focus:outline-none z-50"
                      role="menu"
                    >
                      {user && (user.name || user.email) && (
                        <div className="px-4 py-2 border-b border-gray-100 text-sm">
                          <p className="font-medium text-gray-900 truncate">{user.name}</p>
                          {role && <p className="text-xs uppercase tracking-wide text-gray-500 sm:hidden">{role}</p>}
                        </div>
                      )}
                      <Link
                        to="/profile"
                        role="menuitem"
                        className="flex items-center min-h-11 px-4 text-sm text-gray-700 hover:bg-gray-100"
                        onClick={() => setUserMenuOpen(false)}
                      >
                        Your Profile
                      </Link>
                      <button
                        onClick={handleSignOut}
                        role="menuitem"
                        className="w-full text-left flex items-center min-h-11 px-4 text-sm text-gray-700 hover:bg-gray-100"
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
          <div className="px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
            {children}
          </div>
        </main>
      </div>

    </div>
  );
};

export default Layout;

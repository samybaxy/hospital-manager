import React, { useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { Link, useLocation } from 'react-router-dom';
import { fetchUserAccess } from '../utils/accessControl.jsx';
import { selectHasAccess, selectRole, selectAccessLoading, selectAccessError, selectPermissions } from '../redux/accessSlice';

/**
 * Navigation item with access control
 * @param {Object} props - Component props
 * @param {string} props.route - Route name
 * @param {string} props.icon - Icon path for SVG
 * @param {string} props.label - Display label
 * @param {boolean} props.isCollapsed - Whether the sidebar is collapsed
 */
const NavItemBase = ({ route, icon, label, isCollapsed }) => {
  // Get role from Redux
  const role = useSelector(selectRole);
  
  // Determine access using Redux selectors
  let hasAccess = false;
  
  // Quick check if administrator or if it's the dashboard
  if (role === 'administrator' || route === "dashboard") {
    hasAccess = true;
  } else {
    // Use the selector for complex permission checks
    hasAccess = useSelector((state) => selectHasAccess(state, route));
  }
  
  // Don't render if no access
  if (!hasAccess) {
    return null;
  }
  
  // Get the location to determine if this item is active
  const location = useLocation();
  
  // Find the item in ALL_NAV_ITEMS to get the correct path
  const navItem = ALL_NAV_ITEMS.find(item => item.route === route);
  const linkPath = navItem ? navItem.path : '/';
  
  // Check if current path matches this nav item's path
  const isActive = location.pathname === linkPath;
  
  return (
    <li className="w-full">
      <Link 
        to={linkPath} 
        className={`
          flex items-center px-3 py-2 text-base font-medium rounded-md group w-full
          ${isActive 
            ? 'bg-primary-900 text-white' 
            : 'text-primary-100 hover:bg-primary-700 hover:text-white'}
          ${isCollapsed ? 'justify-center' : ''}
        `}
        title={isCollapsed ? label : ''}
      >
        <svg
          className={`${isCollapsed ? 'mr-0' : 'mr-3'} h-5 w-5 flex-shrink-0 ${isActive ? 'text-primary-300' : 'text-primary-400 group-hover:text-primary-300'}`}
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          aria-hidden="true"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={icon} />
        </svg>
        {!isCollapsed && <span className="truncate">{label}</span>}
      </Link>
    </li>
  );
};

/**
 * Utility function to determine access without using Redux
 * @param {string} role - User role
 * @param {Object} permissions - User permissions map
 * @param {string} routeName - Route to check access for
 * @returns {boolean} - Whether user has access
 */
const checkAccess = (role, permissions, routeName) => {
  // Dashboard is always available
  if (routeName === "dashboard") {
    return true;
  }
  
  // Administrator has access to everything
  if (role === 'administrator') {
    return true;
  }
  
  // Role-based access restrictions
  if (role === 'doctor') {
    if (['audit_log', 'billing', 'inventory', 'settings'].includes(routeName)) {
      return false;
    }
  } else if (role === 'patient') {
    if (['patients', 'departments', 'audit_log', 'billing', 
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
  
  // For other permissions and roles, check the permission map
  return permissions && permissions[routeName] === true;
};

// Wrap with React.memo to prevent unnecessary re-renders
const NavItem = React.memo(NavItemBase, (prevProps, nextProps) => {
  // Custom comparison function to prevent unnecessary re-renders
  // Return true if props are equal (component should NOT re-render)
  return prevProps.route === nextProps.route && prevProps.isCollapsed === nextProps.isCollapsed;
});

/**
 * All navigation items configuration
 */
const ALL_NAV_ITEMS = [
  { 
    route: "dashboard", 
    path: '/', 
    label: 'Dashboard', 
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' 
  },
  { 
    route: "patients", 
    path: '/patients', 
    label: 'Patients', 
    icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' 
  },
  { 
    route: "doctors", 
    path: '/doctors', 
    label: 'Doctors', 
    icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' 
  },
  { 
    route: "appointments", 
    path: '/appointments', 
    label: 'Appointments', 
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' 
  },
  { 
    route: "visitations", 
    path: '/visitations', 
    label: 'Visitations', 
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' 
  },
  { 
    route: "lab_dashboard", 
    path: '/lab-investigations', 
    label: 'Lab Tests', 
    icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' 
  },
  { 
    route: "departments", 
    path: '/departments', 
    label: 'Departments', 
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' 
  },
  { 
    route: "chat", 
    path: '/chat', 
    label: 'Chat', 
    icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' 
  },
  { 
    route: "notifications", 
    path: '/notifications', 
    label: 'Notifications', 
    icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' 
  },
  { 
    route: "audit_log", 
    path: '/audit-logs', 
    label: 'Audit Logs', 
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' 
  },
  { 
    route: "billing", 
    path: '/billing', 
    label: 'Billing', 
    icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z' 
  },
  { 
    route: "inventory", 
    path: '/inventory', 
    label: 'Inventory', 
    icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' 
  },
  { 
    route: "reports", 
    path: '/reports', 
    label: 'Reports', 
    icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' 
  },
  { 
    route: "statistics", 
    path: '/statistics', 
    label: 'Statistics', 
    icon: 'M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' 
  },
  { 
    route: "settings", 
    path: '/settings', 
    label: 'Settings', 
    icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' 
  }
];

/**
 * Main Sidebar Navigation with access control
 */
const Sidebar = ({ isOpen, isCollapsed, onToggleCollapse }) => {
  const dispatch = useDispatch();
  
  // Get data from Redux state
  const role = useSelector(selectRole);
  const isLoading = useSelector(selectAccessLoading);
  const error = useSelector(selectAccessError);
  const user = useSelector(state => state.access?.user);
  
  // Fetch access data if needed
  useEffect(() => {
    if (!isLoading && !role) {
      dispatch(fetchUserAccess());
    }
  }, [dispatch, isLoading, role]);
  
  // Show loading state
  if (isLoading) {
    return <div className="py-4 px-2 text-white">Loading navigation...</div>;
  }
  
  // Show error state
  if (error) {
    return <div className="py-4 px-2 text-white">Error loading permissions</div>;
  }
  
  return (
    <div className="h-full flex flex-col w-full">
      
      {/* Header container - logo for sticky positioning */}
      <div className="sticky top-0 z-20 bg-primary-900">
        {/* App Logo and Brand */}
        <div className={`flex items-center ${isCollapsed ? 'justify-center' : 'justify-center'} h-16`}>
          <Link to="/" className="flex items-center">
            <svg className="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
            {!isCollapsed && <span className="ml-2 text-white font-bold text-xl overflow-hidden whitespace-nowrap transition-all duration-300">Hospital Manager</span>}
          </Link>
        </div>
      </div>
      
      {/* Navigation Menu */}
      <nav className="mt-4 flex-1">
        <ul className={`space-y-1 ${isCollapsed ? 'px-1' : 'px-2'} w-full`}>
          {/* Render all navigation items with access control */}
          {ALL_NAV_ITEMS.map((item) => (
            <NavItem 
              key={item.route}
              route={item.route} 
              icon={item.icon} 
              label={item.label}
              isCollapsed={isCollapsed}
            />
          ))}
        </ul>
      </nav>
    </div>
  );
};

// Final wrapper with memo for optimal performance
// We need to check isOpen and isCollapsed props 
export default React.memo(Sidebar, (prevProps, nextProps) => {
  return prevProps.isOpen === nextProps.isOpen && 
         prevProps.isCollapsed === nextProps.isCollapsed;
});

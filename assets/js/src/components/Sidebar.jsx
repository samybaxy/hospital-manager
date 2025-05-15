import React, { useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { Link } from 'react-router-dom';
import { fetchUserAccess } from '../utils/accessControl.jsx';
import { selectHasAccess, selectRole, selectAccessLoading, selectAccessError } from '../redux/accessSlice';

/**
 * Navigation item with access control using Redux
 */
const NavItem = ({ route, icon, label }) => {
  const role = useSelector(selectRole);
  
  // Use the Redux selector to check access
  // Ensure administrators always have access, even if permissions object doesn't match
  const hasAccess = role === 'administrator' ? true : useSelector((state) => selectHasAccess(state, route));
  
  // For debugging
  console.log(`NavItem ${label} (${route}): role=${role}, hasAccess=${hasAccess}`);
  
  // Don't render if no access
  if (!hasAccess) {
    return null;
  }
  
  return (
    <li className="nav-item">
      <Link to={`/${route}`} className="nav-link">
        <i className={`nav-icon ${icon}`}></i>
        <p>{label}</p>
      </Link>
    </li>
  );
};

/**
 * Main Sidebar Navigation with access control
 */
const Sidebar = () => {
  const dispatch = useDispatch();
  const role = useSelector(selectRole);
  const isLoading = useSelector(selectAccessLoading);
  const error = useSelector(selectAccessError);
  
  // Load access permissions when component mounts
  useEffect(() => {
    dispatch(fetchUserAccess());
  }, [dispatch]);
  
  if (isLoading) {
    return <div className="sidebar-loading">Loading...</div>;
  }
  
  if (error) {
    return <div className="sidebar-error">Error loading permissions</div>;
  }
  
  return (
    <div className="main-sidebar">
      <div className="sidebar">
        <div className="user-panel">
          <div className="info">
            <p>Welcome, {role}</p>
          </div>
        </div>
        
        <nav className="mt-2">
          <ul className="nav nav-pills nav-sidebar flex-column">
            {/* Dashboard is available to all users */}
            <li className="nav-item">
              <Link to="/" className="nav-link">
                <i className="nav-icon fas fa-tachometer-alt"></i>
                <p>Dashboard</p>
              </Link>
            </li>
            
            {/* Access controlled routes */}
            <NavItem route="patients" icon="fas fa-user-injured" label="Patients" />
            <NavItem route="doctors" icon="fas fa-user-md" label="Doctors" />
            <NavItem route="departments" icon="fas fa-hospital" label="Departments" />
            <NavItem route="appointments" icon="fas fa-calendar-check" label="Appointments" />
            <NavItem route="visitations" icon="fas fa-procedures" label="Visitations" />
            <NavItem route="chat" icon="fas fa-comments" label="Chat" />
            <NavItem route="notifications" icon="fas fa-bell" label="Notifications" />
            <NavItem route="audit_log" icon="fas fa-history" label="Audit Log" />
            <NavItem route="billing" icon="fas fa-file-invoice-dollar" label="Billing" />
            <NavItem route="inventory" icon="fas fa-boxes" label="Inventory" />
            <NavItem route="reports" icon="fas fa-chart-bar" label="Reports" />
            <NavItem route="statistics" icon="fas fa-chart-line" label="Statistics" />
            <NavItem route="settings" icon="fas fa-cogs" label="Settings" />
            <NavItem route="lab_dashboard" icon="fas fa-flask" label="Lab Dashboard" />
          </ul>
        </nav>
      </div>
    </div>
  );
};

export default Sidebar;

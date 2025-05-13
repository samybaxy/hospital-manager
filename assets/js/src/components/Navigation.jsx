import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';

const Navigation = () => {
  const location = useLocation();
  const navItems = [
    { path: '/', label: 'Dashboard' },
    { path: '/patients', label: 'Patients' },
    { path: '/doctors', label: 'Doctors' },
    { path: '/appointments', label: 'Appointments' },
    { path: '/visitations', label: 'Visitations' },
    { path: '/departments', label: 'Departments' },
    { path: '/lab-investigations', label: 'Lab' },
    { path: '/billing', label: 'Billing' },
    { path: '/inventory', label: 'Inventory' },
    { path: '/reports', label: 'Reports' },
    { path: '/statistics', label: 'Statistics' },
    { path: '/chat', label: 'Chat' },
    { path: '/notifications', label: 'Notifications' },
    { path: '/audit-logs', label: 'Audit Logs' },
    { path: '/settings', label: 'Settings' }
  ];

  // Simpler approach without using a function that gets serialized
  const activeClass = "bg-primary-900 text-white px-3 py-2 rounded-md text-sm font-medium";
  const inactiveClass = "text-white hover:bg-primary-800 hover:text-white px-3 py-2 rounded-md text-sm font-medium";
  
  return (
    <nav className="bg-primary-700 text-white shadow-lg">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          <div className="flex-shrink-0">
            <span className="font-bold text-xl">Hospital Manager</span>
          </div>
          <div className="hidden md:block">
            <div className="ml-10 flex items-baseline space-x-4">
              {navItems.map((item) => (
                <NavLink
                  key={item.path}
                  to={item.path}
                  className={location.pathname === item.path ? activeClass : inactiveClass}
                >
                  {item.label}
                </NavLink>
              ))}
            </div>
          </div>
        </div>
      </div>
      
      {/* Mobile menu */}
      <div className="md:hidden bg-primary-800">
        <div className="px-2 pt-2 pb-3 space-y-1 sm:px-3 flex flex-col">
          {navItems.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              className={location.pathname === item.path ? activeClass : inactiveClass}
            >
              {item.label}
            </NavLink>
          ))}
        </div>
      </div>
    </nav>
  );
};

export default Navigation;

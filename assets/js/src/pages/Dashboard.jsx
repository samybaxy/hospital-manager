import React, { useState, useEffect, useRef } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import { Link } from 'react-router-dom';
import { api } from '../services/apiService';
import { useUserAccess } from '../hooks/useUserAccess';

const Dashboard = () => {
  const { 
    isPatient, 
    hasAccess, 
    canCreatePatients, 
    canEditPatients, 
    canScheduleAppointments 
  } = useUserAccess();
  const [stats, setStats] = useState({
    patients: 0,
    doctors: 0,
    appointments: 0,
    departments: 0,
    inventory_summary: {
      total_items: 0,
      critical_items: 0,
      expiring_soon: 0,
      expired_items: 0,
      out_of_stock: 0,
      total_value: 0
    }
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [upcomingAppointments, setUpcomingAppointments] = useState([]);
  const [shouldStack, setShouldStack] = useState(false);
  const [shouldStackAppointments, setShouldStackAppointments] = useState(false);
  const [shouldStackCards, setShouldStackCards] = useState(false);
  const resourcesRef = useRef(null);
  const appointmentsRef = useRef(null);
  const cardsContainerRef = useRef(null);
  
  useEffect(() => {
    const fetchDashboardData = async () => {
      try {
        setLoading(true);
        const response = await api.get('/dashboard/stats');
        
        if (response.data) {
          setStats({
            patients: response.data.patients_count || 0,
            doctors: response.data.doctors_count || 0,
            appointments: response.data.appointments_count || 0,
            departments: response.data.departments_count || 0,
            inventory_summary: response.data.inventory_summary || {
              total_items: 0,
              critical_items: 0,
              expiring_soon: 0,
              expired_items: 0,
              out_of_stock: 0,
              total_value: 0
            }
          });
          
          // Set upcoming appointments if available
          if (response.data.upcoming_appointments) {
            setUpcomingAppointments(response.data.upcoming_appointments);
          }
        }
      } catch (err) {
        console.error('Error fetching dashboard data:', err);
        
        // Provide more helpful error message depending on the error
        if (err.response && err.response.status === 404) {
          setError('Dashboard API endpoint not found. Please ensure the backend API is properly set up.');
        } else if (err.response && err.response.status === 401) {
          setError('Authentication required. Please log in again.');
        } else if (err.message === 'Network Error') {
          setError('Network error. Please check your connection and try again.');
        } else {
          setError('Failed to load dashboard data. Please try again later.');
        }
        
        // Set empty data for demonstration purposes
        setStats({
          patients: 12,
          doctors: 5,
          appointments: 24,
          departments: 8,
          inventory_summary: {
            total_items: 250,
            critical_items: 15,
            expiring_soon: 8,
            expired_items: 3,
            out_of_stock: 5,
            total_value: 45000
          }
        });
      } finally {
        setLoading(false);
      }
    };
    
    fetchDashboardData();
  }, []);

  // Monitor container width to determine stacking
  useEffect(() => {
    const checkContainerWidth = () => {
      if (resourcesRef.current) {
        const width = resourcesRef.current.offsetWidth;
        setShouldStack(width <= 300);
      }
      
      if (appointmentsRef.current) {
        const width = appointmentsRef.current.offsetWidth;
        setShouldStackAppointments(width <= 300);
      }
      
      if (cardsContainerRef.current) {
        const containerWidth = cardsContainerRef.current.offsetWidth;
        // Calculate individual card width in 2-column layout (accounting for gap)
        const cardWidth = (containerWidth - 24) / 2; // 24px is the gap
        setShouldStackCards(cardWidth <= 153);
      }
    };

    // Check on mount
    checkContainerWidth();

    // Set up ResizeObserver to monitor container width changes
    const resizeObserver = new ResizeObserver(checkContainerWidth);
    if (resourcesRef.current) {
      resizeObserver.observe(resourcesRef.current);
    }
    if (appointmentsRef.current) {
      resizeObserver.observe(appointmentsRef.current);
    }
    if (cardsContainerRef.current) {
      resizeObserver.observe(cardsContainerRef.current);
    }

    return () => {
      resizeObserver.disconnect();
    };
  }, [loading]); // Re-run when loading changes

  return (
    <div className="space-y-6 px-4 md:px-6 lg:px-8">
      {/* Welcome section and title removed as requested */}
      
      <div className="space-y-8 mt-6">
        <Card title="Dashboard Overview">
          {error && (
            <div className="bg-red-50 p-4 mb-4 rounded-md border border-red-200 text-red-700">
              {error}
            </div>
          )}
          
          {loading ? (
            <div className="bg-gray-50 p-8 rounded-md flex justify-center">
              <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
            </div>
          ) : (
            <div className="bg-primary-50 p-4 rounded-md border border-primary-200">
              <h2 className="font-semibold text-primary-900">Quick Stats</h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                <div className="bg-white p-3 rounded shadow-sm text-center min-w-0">
                  <span className="block text-xl sm:text-2xl font-bold truncate">{stats.patients}</span>
                  <span className="text-xs sm:text-sm text-gray-500 truncate">Patients</span>
                </div>
                <div className="bg-white p-3 rounded shadow-sm text-center min-w-0">
                  <span className="block text-xl sm:text-2xl font-bold truncate">{stats.doctors}</span>
                  <span className="text-xs sm:text-sm text-gray-500 truncate">Doctors</span>
                </div>
                <div className="bg-white p-3 rounded shadow-sm text-center min-w-0">
                  <span className="block text-xl sm:text-2xl font-bold truncate">{stats.appointments}</span>
                  <span className="text-xs sm:text-sm text-gray-500 truncate">Appointments</span>
                </div>
                <div className="bg-white p-3 rounded shadow-sm text-center min-w-0">
                  <span className="block text-xl sm:text-2xl font-bold truncate">{stats.departments}</span>
                  <span className="text-xs sm:text-sm text-gray-500 truncate">Departments</span>
                </div>
              </div>
            </div>
          )}
          
          <div className="mt-6 flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4">
            {/* Add New Patient Button - Only show if user can create patients */}
            {canCreatePatients() && (
              <div className="relative group flex-1 sm:flex-none">
                <Link to="/patients/new" className="flex-1 sm:flex-none">
                  <Button variant="primary" className="w-full sm:w-auto text-sm sm:text-base px-3 py-2 sm:px-4 sm:py-2">
                    Add New Patient
                  </Button>
                </Link>
              </div>
            )}
            
            {/* View All Patients Button - Only show if user can access patients */}
            {hasAccess('patients') && (
              <div className="relative group flex-1 sm:flex-none">
                <Link to="/patients" className="flex-1 sm:flex-none">
                  <Button variant="secondary" className="w-full sm:w-auto text-sm sm:text-base px-3 py-2 sm:px-4 sm:py-2">
                    View All Patients
                  </Button>
                </Link>
              </div>
            )}
            
            {/* Book Appointment Button - Show for patients or users who can schedule appointments */}
            {(isPatient() || canScheduleAppointments()) ? (
              <div className="relative group flex-1 sm:flex-none">
                <Link 
                  to={isPatient() ? "/doctors?from=dashboard" : "/appointments/new"} 
                  state={isPatient() ? { from: 'dashboard' } : undefined}
                >
                  <Button 
                    variant="secondary" 
                    className="w-full sm:w-auto text-sm sm:text-base px-3 py-2 sm:px-4 sm:py-2"
                  >
                    Book Appointment
                  </Button>
                </Link>
              </div>
            ) : (
              /* Show disabled button with icon for users who can't schedule appointments */
              <div className="relative group flex-1 sm:flex-none">
                <div className="pointer-events-none">
                  <Button 
                    variant="secondary" 
                    disabled={true}
                    className="w-full sm:w-auto text-sm sm:text-base px-3 py-2 sm:px-4 sm:py-2 relative !cursor-not-allowed !opacity-50 !bg-gray-100 !border-gray-300 !text-gray-400 hover:!bg-gray-100 hover:!border-gray-300"
                    style={{
                      cursor: 'not-allowed !important',
                      backgroundColor: '#f3f4f6 !important',
                      borderColor: '#d1d5db !important',
                      color: '#9ca3af !important'
                    }}
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                    }}
                  >
                    <div className="flex items-center justify-center">
                      <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        className="h-4 w-4 mr-2 text-red-400" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke="currentColor"
                      >
                        <path 
                          strokeLinecap="round" 
                          strokeLinejoin="round" 
                          strokeWidth={2} 
                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 715.636 5.636m12.728 12.728L5.636 5.636" 
                        />
                      </svg>
                      Book Appointment
                    </div>
                  </Button>
                </div>
                <div className="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg shadow-lg whitespace-nowrap z-10">
                  <div className="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1 text-red-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    {isPatient() ? 'Patient access only' : 'Admin override: Appointment scheduling disabled'}
                  </div>
                  <div className="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                </div>
              </div>
            )}
          </div>
        </Card>
        
        <div className={`gap-6 ${shouldStackCards ? 'flex flex-col' : 'grid grid-cols-1 md:grid-cols-2'}`} ref={cardsContainerRef}>
          <Card title="Upcoming Appointments">
            <div ref={appointmentsRef}>
            {loading ? (
              <div className="flex justify-center p-6">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
              </div>
            ) : upcomingAppointments.length > 0 ? (
              <div className="divide-y divide-gray-100">
                {upcomingAppointments.map((appointment) => (
                  <div key={appointment.ID} className={`py-3 ${shouldStackAppointments ? 'flex flex-col space-y-2' : 'flex justify-between items-center'}`}>
                    <div className="min-w-0 flex-1">
                      <p className="font-medium truncate">
                        {isPatient() ? (
                          // For patients: Show doctor name instead of patient name for privacy
                          appointment.doctor_name ? `Dr. ${appointment.doctor_name}` : 'Doctor'
                        ) : (
                          // For non-patients: Show patient name as before
                          `${appointment.first_name} ${appointment.last_name}`
                        )}
                      </p>
                      <div className="flex items-center mt-1 flex-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-gray-500 mr-1 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span className="text-sm text-gray-600 truncate">{appointment.formatted_date}</span>
                        <span className="text-sm text-gray-600 mx-1">•</span>
                        <span className="text-sm text-gray-600">
                          {new Date(`2000-01-01T${appointment.appointment_time}`).toLocaleTimeString('en-US', { 
                            hour: 'numeric', 
                            minute: 'numeric',
                            hour12: true 
                          })}
                        </span>
                        {isPatient() && appointment.doctor_name && (
                          <>
                            <span className="text-sm text-gray-600 mx-1">•</span>
                            <span className="text-sm text-green-600">Available</span>
                          </>
                        )}
                      </div>
                    </div>
                    {!isPatient() && (
                      <div className={shouldStackAppointments ? 'self-start' : 'flex-shrink-0'}>
                        <Link to={`/appointments/${appointment.ID}`} state={{ returnTo: 'dashboard', returnPath: '/' }}>
                          <Button variant="secondary" className="text-xs px-3 py-1">
                            Details
                          </Button>
                        </Link>
                      </div>
                    )}
                  </div>
                ))}
                <div className="pt-3 text-right">
                  <Link to="/appointments" className="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    {isPatient() ? 'View my appointments' : 'View all appointments'} →
                  </Link>
                </div>
              </div>
            ) : (
              <div className="text-center py-6">
                <p className="text-gray-500 mb-4">No upcoming appointments</p>
                {isPatient() ? (
                  <Link to="/doctors">
                    <Button variant="primary" className="text-sm">Schedule Appointment</Button>
                  </Link>
                ) : canScheduleAppointments() ? (
                  <Link to="/appointments/new">
                    <Button variant="primary" className="text-sm">Schedule Appointment</Button>
                  </Link>
                ) : (
                  <div className="relative group inline-block">
                    <div className="pointer-events-none">
                      <Button 
                        variant="primary" 
                        className="text-sm !cursor-not-allowed !opacity-50 !bg-gray-100 !border-gray-300 !text-gray-400 hover:!bg-gray-100 hover:!border-gray-300 relative" 
                        disabled
                        style={{
                          cursor: 'not-allowed !important',
                          backgroundColor: '#f3f4f6 !important',
                          borderColor: '#d1d5db !important',
                          color: '#9ca3af !important'
                        }}
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                        }}
                      >
                        <div className="flex items-center justify-center">
                          <svg 
                            xmlns="http://www.w3.org/2000/svg" 
                            className="h-4 w-4 mr-2 text-red-400" 
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke="currentColor"
                          >
                            <path 
                              strokeLinecap="round" 
                              strokeLinejoin="round" 
                              strokeWidth={2} 
                              d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 715.636 5.636m12.728 12.728L5.636 5.636" 
                            />
                          </svg>
                          Schedule Appointment
                        </div>
                      </Button>
                    </div>
                    <div className="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg shadow-lg whitespace-nowrap z-10">
                      <div className="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1 text-red-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Admin override: Appointment scheduling disabled
                      </div>
                      <div className="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                    </div>
                  </div>
                )}
              </div>
            )}
            </div>
          </Card>
          
          <Card title="Hospital Resources">
            <div className="space-y-4" ref={resourcesRef}>
              {/* Inventory Summary */}
              <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <h3 className="font-semibold text-blue-900 mb-3 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                  </svg>
                  Inventory Overview
                </h3>
                <div className={`gap-3 ${shouldStack ? 'flex flex-col' : 'grid grid-cols-3'}`}>
                  <div className="bg-white p-3 rounded shadow-sm text-center min-w-0">
                    <span className="block text-base sm:text-lg font-bold text-gray-700 truncate">{stats.inventory_summary.total_items}</span>
                    <span className="text-xs text-gray-500 truncate">Total Items</span>
                  </div>
                  <div className="bg-white p-3 rounded shadow-sm text-center min-w-0">
                    <span className="block text-base sm:text-lg font-bold text-red-600 truncate">{stats.inventory_summary.critical_items}</span>
                    <span className="text-xs text-gray-500 truncate">Critical</span>
                  </div>
                  <div className="bg-white p-3 rounded shadow-sm text-center min-w-0">
                    <span className="block text-base sm:text-lg font-bold text-orange-600 truncate">{stats.inventory_summary.expiring_soon}</span>
                    <span className="text-xs text-gray-500 truncate">Expiring Soon</span>
                  </div>
                </div>
                
                {/* Critical Items Alert */}
                {stats.inventory_summary.critical_items > 0 && (
                  <div className="mt-3 p-2 bg-red-100 border border-red-300 rounded-md">
                    <div className="flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.982 16.5c-.77.833.192 2.5 1.732 2.5z" />
                      </svg>
                      <span className="text-sm text-red-700">
                        {stats.inventory_summary.critical_items} items need restocking
                      </span>
                    </div>
                  </div>
                )}
                
                {/* Expiring Soon Alert */}
                {stats.inventory_summary.expiring_soon > 0 && (
                  <div className="mt-2 p-2 bg-orange-100 border border-orange-300 rounded-md">
                    <div className="flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-orange-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span className="text-sm text-orange-700">
                        {stats.inventory_summary.expiring_soon} items expiring within 30 days
                      </span>
                    </div>
                  </div>
                )}
              </div>

              {/* Bed Management */}
              <div className="bg-green-50 p-4 rounded-lg border border-green-200">
                <h3 className="font-semibold text-green-900 mb-3 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                  </svg>
                  Bed Management
                </h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span>Available Beds</span>
                    <span className="font-medium">12/20</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div className="bg-green-600 h-2.5 rounded-full" style={{ width: '60%' }}></div>
                  </div>
                  
                  <div className="flex justify-between items-center mt-4">
                    <span>ICU Capacity</span>
                    <span className="font-medium">4/8</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div className="bg-green-600 h-2.5 rounded-full" style={{ width: '50%' }}></div>
                  </div>
                </div>
              </div>

              {/* Quick Actions */}
              <div className="flex flex-wrap gap-2">
                {hasAccess('inventory') && (
                  <Link to="/inventory" className="flex-1 min-w-0">
                    <Button variant="outline" className="w-full text-sm">
                      View Inventory
                    </Button>
                  </Link>
                )}
                {hasAccess('inventory') && !isPatient() && (
                  <Link to="/inventory/new" className="flex-1 min-w-0">
                    <Button variant="secondary" className="w-full text-sm">
                      Add Item
                    </Button>
                  </Link>
                )}
              </div>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;

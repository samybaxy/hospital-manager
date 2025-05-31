import React, { useState, useEffect } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import { Link } from 'react-router-dom';
import { api } from '../services/apiService';
import { useAuth } from '../context/AuthContext';

const Dashboard = () => {
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
  const [recentActivities, setRecentActivities] = useState([]);
  const [upcomingAppointments, setUpcomingAppointments] = useState([]);
  
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
          
          // Set recent activities if available
          if (response.data.recent_activities) {
            setRecentActivities(response.data.recent_activities);
          }
          
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
        
        setRecentActivities([
          { ID: 1, message: 'Sample activity 1', created_at: 'May 14, 2025 10:45 am' },
          { ID: 2, message: 'Sample activity 2', created_at: 'May 14, 2025 9:30 am' }
        ]);
        
        setUpcomingAppointments([
          { ID: 1, first_name: 'John', last_name: 'Doe', formatted_date: 'May 15, 2025', appointment_time: '09:00:00' },
          { ID: 2, first_name: 'Jane', last_name: 'Smith', formatted_date: 'May 16, 2025', appointment_time: '14:30:00' }
        ]);
      } finally {
        setLoading(false);
      }
    };
    
    fetchDashboardData();
  }, []);

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
              <div className="grid grid-cols-2 gap-4 mt-3 sm:grid-cols-4">
                <div className="bg-white p-3 rounded shadow-sm text-center">
                  <span className="block text-2xl font-bold">{stats.patients}</span>
                  <span className="text-sm text-gray-500">Patients</span>
                </div>
                <div className="bg-white p-3 rounded shadow-sm text-center">
                  <span className="block text-2xl font-bold">{stats.doctors}</span>
                  <span className="text-sm text-gray-500">Doctors</span>
                </div>
                <div className="bg-white p-3 rounded shadow-sm text-center">
                  <span className="block text-2xl font-bold">{stats.appointments}</span>
                  <span className="text-sm text-gray-500">Appointments</span>
                </div>
                <div className="bg-white p-3 rounded shadow-sm text-center">
                  <span className="block text-2xl font-bold">{stats.departments}</span>
                  <span className="text-sm text-gray-500">Departments</span>
                </div>
              </div>
            </div>
          )}
          
          <div className="mt-6 flex flex-wrap gap-4">
            <Link to="/patients/new">
              <Button variant="primary">Add New Patient</Button>
            </Link>
            <Link to="/patients">
              <Button variant="secondary">View All Patients</Button>
            </Link>
            <Link to="/doctors?from=dashboard" state={{ from: 'dashboard' }}>
              <Button variant="secondary">Book Appointment</Button>
            </Link>
          </div>
        </Card>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card title="Upcoming Appointments">
            {loading ? (
              <div className="flex justify-center p-6">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
              </div>
            ) : upcomingAppointments.length > 0 ? (
              <div className="divide-y divide-gray-100">
                {upcomingAppointments.map((appointment) => (
                  <div key={appointment.ID} className="py-3 flex justify-between items-center">
                    <div>
                      <p className="font-medium">
                        {appointment.first_name} {appointment.last_name}
                      </p>
                      <div className="flex items-center mt-1">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-gray-500 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span className="text-sm text-gray-600">{appointment.formatted_date}</span>
                        <span className="text-sm text-gray-600 mx-1">•</span>
                        <span className="text-sm text-gray-600">
                          {new Date(`2000-01-01T${appointment.appointment_time}`).toLocaleTimeString('en-US', { 
                            hour: 'numeric', 
                            minute: 'numeric',
                            hour12: true 
                          })}
                        </span>
                      </div>
                    </div>
                    <Link to={`/appointments/${appointment.ID}`} state={{ returnTo: 'dashboard', returnPath: '/' }}>
                      <Button variant="secondary" className="text-xs px-3 py-1">
                        Details
                      </Button>
                    </Link>
                  </div>
                ))}
                <div className="pt-3 text-right">
                  <Link to="/appointments" className="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View all appointments →
                  </Link>
                </div>
              </div>
            ) : (
              <div className="text-center py-6">
                <p className="text-gray-500 mb-4">No upcoming appointments</p>
                <Link to="/doctors">
                  <Button variant="primary" className="text-sm">Schedule Appointment</Button>
                </Link>
              </div>
            )}
          </Card>
          
          <Card title="Hospital Resources">
            <div className="space-y-4">
              {/* Inventory Summary */}
              <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <h3 className="font-semibold text-blue-900 mb-3 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                  </svg>
                  Inventory Overview
                </h3>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                  <div className="bg-white p-3 rounded shadow-sm text-center">
                    <span className="block text-lg font-bold text-gray-700">{stats.inventory_summary.total_items}</span>
                    <span className="text-xs text-gray-500">Total Items</span>
                  </div>
                  <div className="bg-white p-3 rounded shadow-sm text-center">
                    <span className="block text-lg font-bold text-red-600">{stats.inventory_summary.critical_items}</span>
                    <span className="text-xs text-gray-500">Critical</span>
                  </div>
                  <div className="bg-white p-3 rounded shadow-sm text-center">
                    <span className="block text-lg font-bold text-orange-600">{stats.inventory_summary.expiring_soon}</span>
                    <span className="text-xs text-gray-500">Expiring Soon</span>
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
                <Link to="/inventory" className="flex-1 min-w-0">
                  <Button variant="outline" className="w-full text-sm">
                    View Inventory
                  </Button>
                </Link>
                <Link to="/inventory/new" className="flex-1 min-w-0">
                  <Button variant="secondary" className="w-full text-sm">
                    Add Item
                  </Button>
                </Link>
              </div>
            </div>
          </Card>
        </div>
        
        {/* Recent Activities */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card title="Recent Activities">
            {loading ? (
              <div className="flex justify-center p-6">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
              </div>
            ) : recentActivities.length > 0 ? (
              <ul className="divide-y divide-gray-200">
                {recentActivities.map((activity) => (
                  <li key={activity.ID} className="py-3">
                    <div className="flex space-x-3">
                      <div className="flex-shrink-0">
                        {activity.type === 'appointment' && (
                          <span className="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-500">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                              <path fillRule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clipRule="evenodd" />
                            </svg>
                          </span>
                        )}
                        {activity.type === 'patient' && (
                          <span className="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-100 text-green-500">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                              <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                            </svg>
                          </span>
                        )}
                        {activity.type === 'doctor' && (
                          <span className="inline-flex items-center justify-center h-8 w-8 rounded-full bg-purple-100 text-purple-500">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                              <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clipRule="evenodd" />
                            </svg>
                          </span>
                        )}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="text-sm text-gray-800">{activity.description}</p>
                        <p className="text-xs text-gray-500">{activity.time}</p>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="py-8 text-center text-gray-500">No recent activities</div>
            )}
            
            <div className="mt-4 text-right">
              <Link to="/audit-logs" className="text-sm text-blue-600 hover:text-blue-800">
                View all activity →
              </Link>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;

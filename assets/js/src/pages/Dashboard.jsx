import React, { useState, useEffect } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import { Link } from 'react-router-dom';
import { api } from '../services/apiClient';

const Dashboard = () => {
  const [stats, setStats] = useState({
    patients: 0,
    doctors: 0,
    appointments: 0,
    departments: 0
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
            departments: response.data.departments_count || 0
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
          departments: 8
        });
        
        setRecentActivities([
          { id: 1, message: 'Sample activity 1', created_at: 'May 14, 2025 10:45 am' },
          { id: 2, message: 'Sample activity 2', created_at: 'May 14, 2025 9:30 am' }
        ]);
        
        setUpcomingAppointments([
          { id: 1, first_name: 'John', last_name: 'Doe', formatted_date: 'May 15, 2025', appointment_time: '09:00:00' },
          { id: 2, first_name: 'Jane', last_name: 'Smith', formatted_date: 'May 16, 2025', appointment_time: '14:30:00' }
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
            <Link to="/appointments/new">
              <Button variant="secondary">Schedule Appointment</Button>
            </Link>
          </div>
        </Card>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card title="Recent Appointments">
            <div className="space-y-4">
              <div className="flex justify-between p-2 bg-gray-50 rounded">
                <div>
                  <p className="font-medium">John Doe</p>
                  <p className="text-sm text-gray-600">General Checkup</p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-medium text-primary-700">Today, 2:00 PM</p>
                  <p className="text-xs text-gray-500">Dr. Smith</p>
                </div>
              </div>
              
              <div className="flex justify-between p-2 bg-gray-50 rounded">
                <div>
                  <p className="font-medium">Jane Smith</p>
                  <p className="text-sm text-gray-600">Follow-up</p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-medium text-primary-700">Tomorrow, 10:00 AM</p>
                  <p className="text-xs text-gray-500">Dr. Johnson</p>
                </div>
              </div>
            </div>
          </Card>
          
          <Card title="Hospital Resources">
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span>Available Beds</span>
                <span className="font-medium">12/20</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div className="bg-primary-600 h-2.5 rounded-full" style={{ width: '60%' }}></div>
              </div>
              
              <div className="flex justify-between items-center mt-4">
                <span>ICU Capacity</span>
                <span className="font-medium">4/8</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div className="bg-primary-600 h-2.5 rounded-full" style={{ width: '50%' }}></div>
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
                  <li key={activity.id} className="py-3">
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
          
          {/* Upcoming Appointments */}
          <Card title="Upcoming Appointments">
            {loading ? (
              <div className="flex justify-center p-6">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
              </div>
            ) : upcomingAppointments.length > 0 ? (
              <ul className="divide-y divide-gray-200">
                {upcomingAppointments.map((appointment) => (
                  <li key={appointment.id} className="py-3">
                    <div className="flex justify-between">
                      <div className="flex-grow">
                        <p className="text-sm font-medium text-gray-800">{appointment.patient_name}</p>
                        <p className="text-xs text-gray-500">with Dr. {appointment.doctor_name}</p>
                        <p className="text-xs text-gray-500 mt-1">{appointment.service}</p>
                      </div>
                      <div className="flex-shrink-0 text-right">
                        <p className="text-sm font-medium text-gray-800">{appointment.date}</p>
                        <p className="text-xs text-gray-500">{appointment.time}</p>
                        <span 
                          className={`text-xs px-2 py-1 rounded-full ${
                            appointment.status === 'confirmed' 
                              ? 'bg-green-100 text-green-800' 
                              : appointment.status === 'pending' 
                              ? 'bg-yellow-100 text-yellow-800' 
                              : 'bg-gray-100 text-gray-800'
                          }`}>
                          {appointment.status}
                        </span>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="py-8 text-center text-gray-500">No upcoming appointments</div>
            )}
            
            <div className="mt-4 text-right">
              <Link to="/appointments" className="text-sm text-blue-600 hover:text-blue-800">
                Manage appointments →
              </Link>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;

import React, { useState, useEffect } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import appointmentService from '../services/appointmentService';
import authService from '../services/authService';
import { Link } from 'react-router-dom';

const Appointments = () => {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [userRole, setUserRole] = useState(null);
  const [filterStatus, setFilterStatus] = useState('all');
  const [sortBy, setSortBy] = useState('date');
  const [sortDirection, setSortDirection] = useState('asc');

  const [cancellationState, setCancellationState] = useState({
    showConfirmation: false,
    appointmentId: null,
    isLoading: false,
    error: '',
  });

  useEffect(() => {
    // Get user role and load appointments
    const user = authService.getCurrentUser();
    setUserRole(user?.role);
    
    fetchAppointments();
  }, []);

  const fetchAppointments = async () => {
    setLoading(true);
    try {
      const response = await appointmentService.getAppointments();
      setAppointments(response.data);
      setError('');
    } catch (err) {
      console.error('Error fetching appointments:', err);
      setError('Failed to load appointments. Please try again later.');
    } finally {
      setLoading(false);
    }
  };

  const handleCancelClick = (appointmentId) => {
    setCancellationState({
      showConfirmation: true,
      appointmentId,
      isLoading: false,
      error: '',
    });
  };

  const handleCancelConfirm = async () => {
    setCancellationState(prev => ({
      ...prev,
      isLoading: true,
      error: '',
    }));

    try {
      await appointmentService.cancelAppointment(cancellationState.appointmentId);
      // Refresh the appointments list
      fetchAppointments();
      // Reset cancellation state
      setCancellationState({
        showConfirmation: false,
        appointmentId: null,
        isLoading: false,
        error: '',
      });
    } catch (err) {
      console.error('Error cancelling appointment:', err);
      setCancellationState(prev => ({
        ...prev,
        isLoading: false,
        error: err.response?.data?.message || 'Failed to cancel appointment. Please try again.',
      }));
    }
  };

  const handleCancelDismiss = () => {
    setCancellationState({
      showConfirmation: false,
      appointmentId: null,
      isLoading: false,
      error: '',
    });
  };

  const formatDateTime = (date, time) => {
    const dateObj = new Date(`${date}T${time}`);
    return dateObj.toLocaleString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      hour12: true
    });
  };

  const getStatusBadgeClass = (status) => {
    switch (status) {
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'confirmed':
        return 'bg-green-100 text-green-800';
      case 'completed':
        return 'bg-blue-100 text-blue-800';
      case 'cancelled':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  // Filter appointments based on selected status
  const getFilteredAppointments = () => {
    if (filterStatus === 'all') {
      return appointments;
    }
    return appointments.filter(app => app.status === filterStatus);
  };

  // Sort appointments based on selected criteria
  const getSortedAppointments = () => {
    const filtered = getFilteredAppointments();
    
    return [...filtered].sort((a, b) => {
      let valueA, valueB;
      
      if (sortBy === 'date') {
        const dateTimeA = new Date(`${a.appointment_date}T${a.appointment_time}`);
        const dateTimeB = new Date(`${b.appointment_date}T${b.appointment_time}`);
        valueA = dateTimeA.getTime();
        valueB = dateTimeB.getTime();
      } else if (sortBy === 'status') {
        valueA = a.status;
        valueB = b.status;
      } else if (sortBy === 'doctor') {
        valueA = a.doctor_name || '';
        valueB = b.doctor_name || '';
      } else {
        valueA = a[sortBy];
        valueB = b[sortBy];
      }
      
      // For string comparison
      if (typeof valueA === 'string') {
        valueA = valueA.toLowerCase();
        valueB = valueB.toLowerCase();
      }
      
      // Apply sort direction
      const direction = sortDirection === 'asc' ? 1 : -1;
      
      if (valueA < valueB) return -1 * direction;
      if (valueA > valueB) return 1 * direction;
      return 0;
    });
  };

  // Toggle sort direction and set the sort field
  const handleSort = (field) => {
    if (sortBy === field) {
      // Toggle direction if same field
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      // Set new field and reset direction to ascending
      setSortBy(field);
      setSortDirection('asc');
    }
  };

  const renderCancellationConfirmation = () => {
    if (!cancellationState.showConfirmation) return null;
    
    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-white rounded-lg p-6 max-w-md w-full">
          <h3 className="text-lg font-semibold text-red-700 mb-2">Cancel Appointment</h3>
          
          <p className="mb-4 text-gray-700">
            Are you sure you want to cancel this appointment? This action cannot be undone.
          </p>
          
          {cancellationState.error && (
            <div className="bg-red-50 p-3 rounded-md border border-red-200 mb-4 text-red-700 text-sm">
              {cancellationState.error}
            </div>
          )}
          
          <div className="flex space-x-3 justify-end">
            <Button
              onClick={handleCancelDismiss}
              disabled={cancellationState.isLoading}
              className="bg-gray-200 hover:bg-gray-300 text-gray-800"
            >
              Keep Appointment
            </Button>
            
            <Button
              onClick={handleCancelConfirm}
              disabled={cancellationState.isLoading}
              className="bg-red-600 hover:bg-red-700 text-white"
            >
              {cancellationState.isLoading ? (
                <>
                  <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Processing...
                </>
              ) : "Yes, Cancel Appointment"}
            </Button>
          </div>
        </div>
      </div>
    );
  };

  const renderAppointmentList = () => {
    if (loading) {
      return (
        <div className="flex justify-center py-8">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
        </div>
      );
    }

    if (error) {
      return (
        <div className="bg-red-50 p-4 rounded-md border border-red-200 text-red-700">
          <div className="flex">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
            {error}
          </div>
        </div>
      );
    }

    const sortedAppointments = getSortedAppointments();

    if (sortedAppointments.length === 0) {
      return (
        <div className="text-center py-8">
          <p className="text-gray-500 mb-4">
            {filterStatus !== 'all' 
              ? `You don't have any ${filterStatus} appointments.` 
              : "You don't have any appointments yet."}
          </p>
          {userRole === 'patient' && (
            <Link to="/doctors">
              <Button
                variant="primary" 
                className="px-4 py-2"
              >
                Book an Appointment
              </Button>
            </Link>
          )}
        </div>
      );
    }

    return (
      <>
        <div className="flex flex-wrap gap-4 mb-4">
          <div>
            <label htmlFor="statusFilter" className="block text-sm font-medium text-gray-700 mb-1">Filter by Status</label>
            <select
              id="statusFilter"
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              <option value="all">All Appointments</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th 
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('date')}
                >
                  <div className="flex items-center">
                    Date & Time
                    {sortBy === 'date' && (
                      <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        className={`ml-1 h-4 w-4 ${sortDirection === 'desc' ? 'transform rotate-180' : ''}`} 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke="currentColor"
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                      </svg>
                    )}
                  </div>
                </th>
                {userRole !== 'patient' && (
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Patient
                  </th>
                )}
                {userRole !== 'doctor' && (
                  <th 
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                    onClick={() => handleSort('doctor')}
                  >
                    <div className="flex items-center">
                      Doctor
                      {sortBy === 'doctor' && (
                        <svg 
                          xmlns="http://www.w3.org/2000/svg" 
                          className={`ml-1 h-4 w-4 ${sortDirection === 'desc' ? 'transform rotate-180' : ''}`} 
                          fill="none" 
                          viewBox="0 0 24 24" 
                          stroke="currentColor"
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                        </svg>
                      )}
                    </div>
                  </th>
                )}
                <th 
                  className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  onClick={() => handleSort('status')}
                >
                  <div className="flex items-center">
                    Status
                    {sortBy === 'status' && (
                      <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        className={`ml-1 h-4 w-4 ${sortDirection === 'desc' ? 'transform rotate-180' : ''}`} 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke="currentColor"
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                      </svg>
                    )}
                  </div>
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Reason
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {sortedAppointments.map((appointment) => (
                <tr key={appointment.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    {formatDateTime(appointment.appointment_date, appointment.appointment_time)}
                  </td>
                  {userRole !== 'patient' && (
                    <td className="px-6 py-4 whitespace-nowrap">
                      {appointment.patient_name}
                    </td>
                  )}
                  {userRole !== 'doctor' && (
                    <td className="px-6 py-4 whitespace-nowrap">
                      Dr. {appointment.doctor_name}
                    </td>
                  )}
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeClass(appointment.status)}`}>
                      {appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    <div className="max-w-xs truncate">{appointment.reason || 'No reason provided'}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    {appointment.status === 'pending' && userRole === 'patient' && (
                      <button
                        onClick={() => handleCancelClick(appointment.id)}
                        className="text-red-600 hover:text-red-900 mr-4"
                      >
                        Cancel
                      </button>
                    )}
                    {userRole === 'doctor' && appointment.status === 'pending' && (
                      <div className="space-x-3">
                        <button
                          onClick={() => appointmentService.updateAppointment(appointment.id, { status: 'confirmed' }).then(fetchAppointments)}
                          className="text-green-600 hover:text-green-900"
                        >
                          Confirm
                        </button>
                        <button
                          onClick={() => handleCancelClick(appointment.id)}
                          className="text-red-600 hover:text-red-900"
                        >
                          Decline
                        </button>
                      </div>
                    )}
                    {userRole === 'doctor' && appointment.status === 'confirmed' && (
                      <button
                        onClick={() => appointmentService.updateAppointment(appointment.id, { status: 'completed' }).then(fetchAppointments)}
                        className="text-blue-600 hover:text-blue-900"
                      >
                        Mark Completed
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </>
    );
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold">Appointments</h1>
        {userRole === 'patient' && (
          <Link to="/doctors">
            <Button 
              variant="primary"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Book New Appointment
            </Button>
          </Link>
        )}
      </div>
      <Card>
        {renderAppointmentList()}
      </Card>
      {renderCancellationConfirmation()}
    </div>
  );
};

export default Appointments;

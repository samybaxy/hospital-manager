import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link, useLocation } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { api } from '../../services/apiService';
import { useAuth } from '../../context/AuthContext';

// Helper functions for formatting dates and times
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  return new Date(dateString).toLocaleDateString('en-US', options);
};

const formatTime = (timeString) => {
  if (!timeString) return 'N/A';
  const [hours, minutes] = timeString.split(':');
  const hour = parseInt(hours, 10);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const formattedHour = hour % 12 || 12;
  return `${formattedHour}:${minutes} ${ampm}`;
};

const AppointmentView = () => {
  const { ID } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuth();
  
  // Get the source page information from the location state
  const returnTo = location.state?.returnTo || 'appointments';
  const returnPath = location.state?.returnPath || '/appointments';
  const sourceDoctorName = location.state?.doctorName;
  
  const [doctor, setDoctor] = useState(null);
  const [patient, setPatient] = useState(null);
  const [appointment, setAppointment] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  // Fetch appointment details
  useEffect(() => {
    const fetchAppointmentDetails = async () => {
      if (!ID) {
        setError('No appointment ID provided');
        setIsLoading(false);
        return;
      }
      
      try {
        setIsLoading(true);
        console.log('Fetching appointment with ID:', ID, 'Type:', typeof ID);
        
        // First try to get the appointment directly
        let response;
        try {
          response = await api.get(`/appointments/${ID}`);
        } catch (directError) {
          console.log('Direct fetch failed, trying to find in appointments list:', directError);
          
          // If direct fetch fails, try to get from appointments list
          const listResponse = await api.get('/appointments');
          const appointments = listResponse.data?.data || listResponse.data || [];
          const foundAppointment = appointments.find(app => app.ID == ID);
          
          if (foundAppointment) {
            response = { data: foundAppointment };
          } else {
            throw directError; // Throw the original error if not found in list either
          }
        }
        
        // Handle different API response formats
        const appointmentData = response.data;
        console.log('Received appointment data:', appointmentData);
        
        if (!appointmentData) {
          throw new Error('No appointment data received');
        }
        
        setAppointment(appointmentData);
        
        // If we have doctor_id in the appointment data, fetch doctor details
        if (appointmentData.doctor_id) {
          try {
            const doctorResponse = await api.get(`/doctors/${appointmentData.doctor_id}`);
            if (doctorResponse.data && doctorResponse.data.data) {
              setDoctor(doctorResponse.data.data);
            } else {
              setDoctor(doctorResponse.data);
            }
          } catch (err) {
            console.error('Error fetching doctor details:', err);
          }
        }
        
        // If we have patient_id in the appointment data, fetch patient details
        if (appointmentData.patient_id) {
          try {
            const patientResponse = await api.get(`/patients/${appointmentData.patient_id}`);
            if (patientResponse.data && patientResponse.data.data) {
              setPatient(patientResponse.data.data);
            } else {
              setPatient(patientResponse.data);
            }
          } catch (err) {
            console.error('Error fetching patient details:', err);
          }
        }
        
        setError('');
      } catch (err) {
        console.error('Error fetching appointment details:', err);
        
        let errorMessage = 'Failed to load appointment information. Please try again.';
        
        if (err.response?.status === 404) {
          errorMessage = 'Appointment not found. It may have been deleted or you may not have permission to view it.';
        } else if (err.response?.status === 401 || err.response?.status === 403) {
          errorMessage = 'You do not have permission to view this appointment.';
        } else if (err.response?.data?.message) {
          errorMessage = err.response.data.message;
        }
        
        setError(errorMessage);
      } finally {
        setIsLoading(false);
      }
    };
    
    fetchAppointmentDetails();
  }, [ID]);

  // Handle cancelling an appointment
  const handleCancelAppointment = async () => {
    if (!confirm('Are you sure you want to cancel this appointment?')) {
      return;
    }
    
    try {
      setIsLoading(true);
      await api.put(`/appointments/${ID}`, { status: 'cancelled' });
      setSuccessMessage('Appointment cancelled successfully');
      
      // Redirect to the source page after short delay
      setTimeout(() => {
        navigate(returnPath);
      }, 2000);
    } catch (err) {
      console.error('Error cancelling appointment:', err);
      setError('Failed to cancel appointment. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  if (isLoading && !appointment) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }
  
  // Format doctor name safely if doctor data is available
  const doctorName = doctor ? 
    `Dr. ${doctor.first_name || ''} ${doctor.last_name || ''}`.trim() : 
    'the doctor';

  // Get status badge color based on appointment status
  const getStatusBadgeColor = (status) => {
    switch (status) {
      case 'confirmed':
        return 'bg-green-100 text-green-800 border border-green-200';
      case 'pending':
        return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
      case 'cancelled':
        return 'bg-red-100 text-red-800 border border-red-200';
      case 'completed':
        return 'bg-blue-100 text-blue-800 border border-blue-200';
      default:
        return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header with back button and status */}
      <div className="bg-white rounded-lg shadow-sm p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          {appointment && (
            <span className={`mt-2 inline-block px-3 py-1 text-sm font-medium rounded-full ${getStatusBadgeColor(appointment.status)}`}>
              {appointment.status ? appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1) : 'Pending'}
            </span>
          )}
        </div>
        <Link to={returnPath}>
          <Button variant="secondary" className="whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clipRule="evenodd" />
            </svg>
            {returnTo === 'doctor' && sourceDoctorName ? 
              `Back to Dr. ${sourceDoctorName}` : 
              returnTo === 'dashboard' ? 
                'Back to Dashboard' : 
                'Back to Appointments'}
          </Button>
        </Link>
      </div>

      {/* Notifications */}
      {error && (
        <div className="bg-red-50 p-4 rounded-lg shadow-sm border border-red-200 text-red-700 animate-fade-in">
          <div className="flex">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
            {error}
          </div>
        </div>
      )}

      {successMessage && (
        <div className="bg-green-50 p-4 rounded-lg shadow-sm border border-green-200 text-green-700 animate-fade-in">
          <div className="flex">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
            </svg>
            {successMessage}
          </div>
        </div>
      )}

      {appointment && (
        <div className="bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg border border-gray-100">
          {/* Date and Time Header - Highlighted area */}
          <div className="bg-gradient-to-r from-blue-500 to-indigo-600 p-8 text-white relative overflow-hidden">
            <div className="absolute top-0 right-0 opacity-10">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-48 w-48" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 className="text-2xl font-bold mb-6 relative">Appointment Schedule</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-8">
              <div className="flex items-center backdrop-blur-sm bg-white/10 rounded-lg p-4 transition-all duration-300 hover:bg-white/20">
                <div className="bg-white p-3 rounded-full shadow-md mr-4 text-blue-600">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div>
                  <div className="text-sm font-medium text-blue-100">Date</div>
                  <div className="text-lg font-bold">
                    {formatDate(appointment.appointment_date || appointment.date)}
                  </div>
                </div>
              </div>
              <div className="flex items-center backdrop-blur-sm bg-white/10 rounded-lg p-4 transition-all duration-300 hover:bg-white/20">
                <div className="bg-white p-3 rounded-full shadow-md mr-4 text-blue-600">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <div className="text-sm font-medium text-blue-100">Time</div>
                  <div className="text-lg font-bold">
                    {formatTime(appointment.appointment_time || appointment.time)}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="p-6 space-y-6">
            {/* Doctor Information */}
            {doctor && (
              <div className="p-6 rounded-xl border border-gray-200 bg-white shadow-sm hover:shadow-lg transition-all duration-300">
                <h3 className="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 mr-2 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd" />
                  </svg>
                  Doctor Information
                </h3>
                <div className="flex flex-col sm:flex-row sm:items-center gap-6">
                  <div className="flex-shrink-0 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full p-3 w-20 h-20 flex items-center justify-center shadow-lg">
                    <span className="text-2xl text-white font-bold">
                      {doctor.first_name?.charAt(0)}{doctor.last_name?.charAt(0)}
                    </span>
                  </div>
                  <div className="flex-1">
                    <div className="text-xl font-semibold text-gray-900 mb-2">{doctorName}</div>
                    {doctor.specialty && (
                      <span className="inline-block bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-sm font-medium">
                        {doctor.specialty}
                      </span>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* Patient Information (if viewing as admin/doctor) */}
            {patient && user?.role !== 'patient' && (
              <div className="p-6 rounded-xl border border-gray-200 bg-white shadow-sm hover:shadow-lg transition-all duration-300">
                <h3 className="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 mr-2 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                  </svg>
                  Patient Information
                </h3>
                <div className="flex flex-col sm:flex-row sm:items-center gap-6">
                  <div className="flex-shrink-0 bg-gradient-to-br from-green-500 to-teal-600 rounded-full p-3 w-20 h-20 flex items-center justify-center shadow-lg">
                    <span className="text-2xl text-white font-bold">
                      {patient.first_name?.charAt(0)}{patient.last_name?.charAt(0)}
                    </span>
                  </div>
                  <div className="flex-1">
                    <div className="text-xl font-semibold text-gray-900 mb-2">
                      {`${patient.first_name || ''} ${patient.last_name || ''}`.trim()}
                    </div>
                    {patient.phone && (
                      <div className="flex items-center text-gray-600 bg-gray-50 px-3 py-1 rounded-full inline-block">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        {patient.phone}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* Reason for Visit */}
            <div className="p-4 rounded-lg border border-gray-200 bg-white shadow-sm hover:shadow-md transition-all duration-300">
              <h3 className="text-md font-semibold text-gray-700 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                </svg>
                Reason for Visit
              </h3>
              <div className="text-gray-700 bg-gray-50 p-4 rounded-md border border-gray-100">
                {appointment.reason || 'No specific reason provided'}
              </div>
            </div>

            {/* Notes (if any) */}
            {appointment.notes && (
              <div className="p-4 rounded-lg border border-gray-200 bg-white shadow-sm hover:shadow-md transition-all duration-300">
                <h3 className="text-md font-semibold text-gray-700 mb-3 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5zm0 2h10v10H5V5z" clipRule="evenodd" />
                  </svg>
                  Additional Notes
                </h3>
                <div className="text-gray-700 bg-gray-50 p-4 rounded-md border border-gray-100">
                  {appointment.notes}
                </div>
              </div>
            )}

            {/* Action Buttons */}
            {appointment.status === 'pending' && user?.role === 'patient' && (
              <div className="flex justify-end pt-6">
                <Button 
                  variant="danger" 
                  onClick={handleCancelAppointment}
                  disabled={isLoading}
                  className="transition-all duration-200 hover:shadow-md"
                >
                  {isLoading ? 'Cancelling...' : 'Cancel Appointment'}
                </Button>
              </div>
            )}

            {/* Doctor Actions */}
            {user?.role === 'doctor' && appointment.status === 'pending' && (
              <div className="flex justify-end space-x-3 pt-6">
                <Button 
                  variant="secondary"
                  onClick={async () => {
                    try {
                      await api.put(`/appointments/${appointment.ID}`, { status: 'cancelled' });
                      setSuccessMessage('Appointment declined successfully');
                      setTimeout(() => navigate(returnPath), 2000);
                    } catch (err) {
                      setError('Failed to decline appointment');
                    }
                  }}
                  disabled={isLoading}
                  className="transition-all duration-200"
                >
                  Decline
                </Button>
                <Button 
                  variant="primary"
                  onClick={async () => {
                    try {
                      await api.put(`/appointments/${appointment.ID}`, { status: 'confirmed' });
                      setSuccessMessage('Appointment confirmed successfully');
                      setTimeout(() => navigate(returnPath), 2000);
                    } catch (err) {
                      setError('Failed to confirm appointment');
                    }
                  }}
                  disabled={isLoading}
                  className="transition-all duration-200 hover:shadow-md"
                >
                  Confirm
                </Button>
              </div>
            )}

            {user?.role === 'doctor' && appointment.status === 'confirmed' && (
              <div className="flex justify-end pt-6">
                <Button 
                  variant="primary"
                  onClick={async () => {
                    try {
                      await api.put(`/appointments/${appointment.ID}`, { status: 'completed' });
                      setSuccessMessage('Appointment marked as completed');
                      setTimeout(() => navigate(returnPath), 2000);
                    } catch (err) {
                      setError('Failed to mark appointment as completed');
                    }
                  }}
                  disabled={isLoading}
                  className="transition-all duration-200 hover:shadow-md"
                >
                  Mark as Completed
                </Button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default AppointmentView;
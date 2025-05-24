import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link, useLocation } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { api } from '../../services/apiService';
// Define formatting functions locally since they don't exist in appointmentService
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
  const { id } = useParams();
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
      if (!id) {
        setError('No appointment ID provided');
        setIsLoading(false);
        return;
      }
      
      try {
        setIsLoading(true);
        console.log('Fetching appointment with ID:', id, 'Type:', typeof id);
        
        // First try to get the appointment directly
        let response;
        try {
          response = await api.get(`/appointments/${id}`);
        } catch (directError) {
          console.log('Direct fetch failed, trying to find in appointments list:', directError);
          
          // If direct fetch fails, try to get from appointments list
          const listResponse = await api.get('/appointments');
          const appointments = listResponse.data?.data || listResponse.data || [];
          const foundAppointment = appointments.find(app => app.id == id);
          
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
  }, [id]);

  // Handle cancelling an appointment
  const handleCancelAppointment = async () => {
    if (!confirm('Are you sure you want to cancel this appointment?')) {
      return;
    }
    
    try {
      setIsLoading(true);
      await api.put(`/appointments/${id}`, { status: 'cancelled' });
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
        return 'bg-green-100 text-green-800';
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'cancelled':
        return 'bg-red-100 text-red-800';
      case 'completed':
        return 'bg-blue-100 text-blue-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold">Appointment Details</h1>
        <Link to={returnPath}>
          <Button variant="secondary">
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

      {error && (
        <div className="bg-red-50 p-4 rounded-md border border-red-200 text-red-700">
          <div className="flex">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
            {error}
          </div>
        </div>
      )}

      {successMessage && (
        <div className="bg-green-50 p-4 rounded-md border border-green-200 text-green-700">
          <div className="flex">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
            </svg>
            {successMessage}
          </div>
        </div>
      )}

      {appointment && (
        <Card>
          <div className="space-y-6">
            {/* Appointment Status */}
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold">Appointment Information</h2>
              <span className={`px-3 py-1 text-sm font-medium rounded-full ${getStatusBadgeColor(appointment.status)}`}>
                {appointment.status ? appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1) : 'Pending'}
              </span>
            </div>

            {/* Appointment Details Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Date and Time */}
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                  <div className="text-lg text-gray-900">
                    {formatDate(appointment.appointment_date || appointment.date)}
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Time</label>
                  <div className="text-lg text-gray-900">
                    {formatTime(appointment.appointment_time || appointment.time)}
                  </div>
                </div>
              </div>

              {/* Doctor Information */}
              {doctor && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Doctor</label>
                    <div className="text-lg text-gray-900">{doctorName}</div>
                  </div>
                  {doctor.specialty && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Specialty</label>
                      <div className="text-sm text-gray-600">{doctor.specialty}</div>
                    </div>
                  )}
                </div>
              )}

              {/* Patient Information (if viewing as admin/doctor) */}
              {patient && user?.role !== 'patient' && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Patient</label>
                    <div className="text-lg text-gray-900">
                      {`${patient.first_name || ''} ${patient.last_name || ''}`.trim()}
                    </div>
                  </div>
                  {patient.phone && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Contact</label>
                      <div className="text-sm text-gray-600">{patient.phone}</div>
                    </div>
                  )}
                </div>
              )}

              {/* Reason */}
              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-700 mb-1">Reason for Visit</label>
                <div className="text-gray-900 bg-gray-50 p-3 rounded-md">
                  {appointment.reason || 'No specific reason provided'}
                </div>
              </div>

              {/* Notes (if any) */}
              {appointment.notes && (
                <div className="md:col-span-2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                  <div className="text-gray-900 bg-gray-50 p-3 rounded-md">
                    {appointment.notes}
                  </div>
                </div>
              )}
            </div>

            {/* Action Buttons */}
            {appointment.status === 'pending' && user?.role === 'patient' && (
              <div className="flex justify-end space-x-3 pt-4 border-t">
                <Button 
                  variant="danger" 
                  onClick={handleCancelAppointment}
                  disabled={isLoading}
                >
                  {isLoading ? 'Cancelling...' : 'Cancel Appointment'}
                </Button>
              </div>
            )}

            {/* Doctor Actions */}
            {user?.role === 'doctor' && appointment.status === 'pending' && (
              <div className="flex justify-end space-x-3 pt-4 border-t">
                <Button 
                  variant="secondary"
                  onClick={async () => {
                    try {
                      await api.put(`/appointments/${appointment.id}`, { status: 'cancelled' });
                      setSuccessMessage('Appointment declined successfully');
                      setTimeout(() => navigate(returnPath), 2000);
                    } catch (err) {
                      setError('Failed to decline appointment');
                    }
                  }}
                  disabled={isLoading}
                >
                  Decline
                </Button>
                <Button 
                  variant="primary"
                  onClick={async () => {
                    try {
                      await api.put(`/appointments/${appointment.id}`, { status: 'confirmed' });
                      setSuccessMessage('Appointment confirmed successfully');
                      setTimeout(() => navigate(returnPath), 2000);
                    } catch (err) {
                      setError('Failed to confirm appointment');
                    }
                  }}
                  disabled={isLoading}
                >
                  Confirm
                </Button>
              </div>
            )}

            {user?.role === 'doctor' && appointment.status === 'confirmed' && (
              <div className="flex justify-end pt-4 border-t">
                <Button 
                  variant="primary"
                  onClick={async () => {
                    try {
                      await api.put(`/appointments/${appointment.id}`, { status: 'completed' });
                      setSuccessMessage('Appointment marked as completed');
                      setTimeout(() => navigate(returnPath), 2000);
                    } catch (err) {
                      setError('Failed to mark appointment as completed');
                    }
                  }}
                  disabled={isLoading}
                >
                  Mark as Completed
                </Button>
              </div>
            )}
          </div>
        </Card>
      )}
    </div>
  );
};

export default AppointmentView;
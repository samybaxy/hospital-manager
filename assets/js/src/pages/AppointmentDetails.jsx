import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import Card from '../components/Card';
import Button from '../components/Button';
import StatusMessage from '../components/StatusMessage';
import DateTimeSelector from '../components/DateTimeSelector';
import appointmentService from '../services/appointmentService';
import { api } from '../services/apiService';
import { useAuth } from '../context/AuthContext';

const AppointmentDetails = () => {
  const { id, doctorId } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [doctor, setDoctor] = useState(null);
  const [patient, setPatient] = useState(null);
  const [appointment, setAppointment] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [appointmentData, setAppointmentData] = useState({
    doctor_id: doctorId || '',
    date: '',
    time: '',
    reason: '',
  });
  const [availability, setAvailability] = useState({
    isLoading: false,
    data: [],
    error: '',
  });
  
  const viewMode = !!id; // If we have an ID, we're viewing an existing appointment
  const bookingMode = !!doctorId && !id; // If we have a doctorId but no id, we're booking a new appointment

  // Fetch appointment details if we're in view mode
  useEffect(() => {
    if (viewMode) {
      const fetchAppointmentDetails = async () => {
        try {
          setIsLoading(true);
          const response = await appointmentService.getAppointment(id);
          
          // Handle different API response formats
          const appointmentData = response.data;
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
          setError('Failed to load appointment information. Please try again.');
        } finally {
          setIsLoading(false);
        }
      };
      
      fetchAppointmentDetails();
    }
  }, [id, viewMode]);

  // Fetch doctor details if we're in booking mode
  useEffect(() => {
    if (bookingMode && doctorId) {
      const fetchDoctorDetails = async () => {
        try {
          setIsLoading(true);
          const response = await api.get(`/doctors/${doctorId}`);
          
          // Handle different API response formats (may be nested under data)
          if (response.data && response.data.data) {
            setDoctor(response.data.data);
          } else {
            setDoctor(response.data);
          }
          
          setError('');
        } catch (err) {
          console.error('Error fetching doctor details:', err);
          setError('Failed to load doctor information. Please try again.');
        } finally {
          setIsLoading(false);
        }
      };

      fetchDoctorDetails();
    }
  }, [doctorId, bookingMode]);

  // Handle form input changes
  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setAppointmentData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  // Handle date-time selection from calendar component
  const handleDateTimeSelect = (dateTimeObj) => {
    setAppointmentData((prev) => ({
      ...prev,
      date: dateTimeObj.date,
      time: dateTimeObj.time,
    }));
  };

  // Handle form submission
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Validate form data
    if (!appointmentData.date || !appointmentData.time) {
      setError('Please select a date and time for your appointment');
      return;
    }

    setIsLoading(true);
    setError('');
    setSuccessMessage('');

    try {
      // Create a copy of the appointment data with the patient ID if available
      const appointmentPayload = {
        ...appointmentData,
        patient_id: user?.id // Include patient ID if available from context
      };
      
      const response = await appointmentService.createAppointment(appointmentPayload);
      setSuccessMessage('Your appointment was booked successfully! You will receive a confirmation soon.');
      
      // Reset form
      setAppointmentData({
        doctor_id: doctorId,
        date: '',
        time: '',
        reason: '',
      });
      
      // Redirect to appointments page after short delay
      setTimeout(() => {
        navigate('/appointments');
      }, 3000);
    } catch (err) {
      console.error('Error booking appointment:', err);
      const errorMessage = err.response?.data?.message || 'Failed to book appointment. Please try again.';
      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  // Handle cancelling an appointment
  const handleCancelAppointment = async () => {
    if (!confirm('Are you sure you want to cancel this appointment?')) {
      return;
    }
    
    try {
      setIsLoading(true);
      await appointmentService.cancelAppointment(id);
      setSuccessMessage('Appointment cancelled successfully');
      
      // Redirect to appointments page after short delay
      setTimeout(() => {
        navigate('/appointments');
      }, 2000);
    } catch (err) {
      console.error('Error cancelling appointment:', err);
      setError('Failed to cancel appointment. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  if (isLoading && !doctor && !appointment) {
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

  // Format date and time for display in view mode
  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
  };
  
  const formatTime = (timeString) => {
    if (!timeString) return 'N/A';
    
    // If timeString is just a time (HH:MM:SS)
    if (timeString.length <= 8) {
      const date = new Date(`2000-01-01T${timeString}`);
      return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: 'numeric', hour12: true });
    }
    
    // If timeString is a full datetime
    const date = new Date(timeString);
    return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: 'numeric', hour12: true });
  };

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
        <h1 className="text-2xl font-bold">
          {viewMode ? 'Appointment Details' : 'Book Appointment'}
        </h1>
        <Link to={viewMode ? "/appointments" : "/doctors"}>
          <Button variant="secondary">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clipRule="evenodd" />
            </svg>
            Back to {viewMode ? 'Appointments' : 'Doctors'}
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
          <p className="text-sm mt-2">Redirecting to appointments page...</p>
        </div>
      )}

      {/* View mode - show appointment details */}
      {viewMode && appointment && !successMessage && (
        <Card>
          <div className="flex flex-col md:flex-row">
            <div className="md:w-1/3 p-4 border-r">
              {doctor && (
                <>
                  <div className="h-24 w-24 rounded-full bg-gray-200 flex items-center justify-center mb-4 text-2xl font-bold text-gray-500">
                    {doctor.first_name?.[0]}{doctor.last_name?.[0]}
                  </div>
                  <h2 className="text-xl font-semibold mb-2">{doctorName}</h2>
                  <p className="font-semibold text-blue-700 mb-1">{doctor.specialty}</p>
                  <p className="text-gray-600 mb-1">{doctor.education}</p>
                  <p className="text-gray-600 mb-1">Experience: {doctor.years_experience || 'N/A'}</p>
                  <p className="text-gray-500 text-sm">Office: {doctor.office || 'N/A'}</p>
                </>
              )}
            </div>

            <div className="md:w-2/3 p-4">
              <div className="mb-6">
                <span className={`px-2 py-1 text-sm font-semibold inline-block rounded-md ${getStatusBadgeColor(appointment.status)}`}>
                  {appointment.status ? appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1) : 'Unknown'}
                </span>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h3 className="text-sm font-medium text-gray-500">Date</h3>
                  <p className="font-medium">{formatDate(appointment.date)}</p>
                </div>
                
                <div>
                  <h3 className="text-sm font-medium text-gray-500">Time</h3>
                  <p className="font-medium">{formatTime(appointment.time)}</p>
                </div>
                
                {patient && (
                  <div className="md:col-span-2">
                    <h3 className="text-sm font-medium text-gray-500">Patient</h3>
                    <p className="font-medium">{patient.first_name} {patient.last_name}</p>
                  </div>
                )}
                
                <div className="md:col-span-2">
                  <h3 className="text-sm font-medium text-gray-500">Reason for Visit</h3>
                  <p>{appointment.reason || 'No reason provided'}</p>
                </div>
                
                {appointment.notes && (
                  <div className="md:col-span-2">
                    <h3 className="text-sm font-medium text-gray-500">Additional Notes</h3>
                    <p>{appointment.notes}</p>
                  </div>
                )}
              </div>
              
              {/* Actions */}
              <div className="flex justify-end space-x-2 mt-6">
                {appointment.status !== 'cancelled' && appointment.status !== 'completed' && (
                  <Button
                    type="button"
                    variant="danger"
                    onClick={handleCancelAppointment}
                    disabled={isLoading}
                  >
                    {isLoading ? 'Processing...' : 'Cancel Appointment'}
                  </Button>
                )}
                
                <Link to="/appointments">
                  <Button variant="secondary">
                    Back to Appointments
                  </Button>
                </Link>
              </div>
            </div>
          </div>
        </Card>
      )}

      {/* Booking mode - show appointment booking form */}
      {bookingMode && doctor && !successMessage && (
        <Card>
          <div className="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
            <div className="flex items-start">
              <div className="flex-shrink-0 mt-1">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                </svg>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-blue-800">Booking Instructions</h3>
                <div className="mt-2 text-sm text-blue-700">
                  <p>1. Select a date from the calendar below.</p>
                  <p>2. Choose an available time slot.</p>
                  <p>3. Enter the reason for your visit.</p>
                  <p>4. Click "Book Appointment" to confirm.</p>
                </div>
              </div>
            </div>
          </div>

          <div className="flex flex-col md:flex-row">
            <div className="md:w-1/3 p-4 border-r">
              <div className="h-24 w-24 rounded-full bg-gray-200 flex items-center justify-center mb-4 text-2xl font-bold text-gray-500">
                {doctor.first_name?.[0]}{doctor.last_name?.[0]}
              </div>
              <h2 className="text-xl font-semibold mb-2">{doctorName}</h2>
              <p className="font-semibold text-blue-700 mb-1">{doctor.specialty}</p>
              <p className="text-gray-600 mb-1">{doctor.education}</p>
              <p className="text-gray-600 mb-1">Experience: {doctor.years_experience || 'N/A'}</p>
              <p className="text-gray-500 text-sm">Office: {doctor.office || 'N/A'}</p>
            </div>

            <div className="md:w-2/3 p-4">
              <form onSubmit={handleSubmit} className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium mb-4">Select Appointment Date and Time</h3>
                  <DateTimeSelector 
                    doctorId={doctorId} 
                    onSelectDateTime={handleDateTimeSelect} 
                  />
                </div>

                <div>
                  <label htmlFor="reason" className="block text-sm font-medium text-gray-700 mb-1">
                    Reason for Visit <span className="text-red-500">*</span>
                  </label>
                  <textarea
                    id="reason"
                    name="reason"
                    value={appointmentData.reason}
                    onChange={handleInputChange}
                    required
                    className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    rows={3}
                    placeholder="Please describe the reason for your appointment"
                  />
                </div>

                <div className="flex justify-end space-x-2">
                  <Link to="/doctors">
                    <Button
                      type="button"
                      variant="secondary"
                    >
                      Cancel
                    </Button>
                  </Link>
                  <Button
                    type="submit"
                    variant="primary"
                    disabled={isLoading || !appointmentData.date || !appointmentData.time}
                  >
                    {isLoading ? (
                      <>
                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Booking...
                      </>
                    ) : "Book Appointment"}
                  </Button>
                </div>
              </form>
            </div>
          </div>
        </Card>
      )}
    </div>
  );
};

export default AppointmentDetails;
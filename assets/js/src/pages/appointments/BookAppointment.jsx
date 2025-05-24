import React, { useState, useEffect } from 'react';
import { useNavigate, useParams, useLocation } from 'react-router-dom';
import Calendar from 'react-calendar';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { api } from '../../services/apiService';
import { useAuth } from '../../context/AuthContext';
import 'react-calendar/dist/Calendar.css';

// Custom styles for react-calendar
const calendarStyles = `
  .react-calendar {
    width: 100%;
    border: none;
    font-family: inherit;
  }
  .react-calendar__tile {
    max-width: 100%;
    padding: 10px 6px;
    background: none;
    text-align: center;
    line-height: 16px;
    border-radius: 6px;
  }
  .react-calendar__tile:enabled:hover,
  .react-calendar__tile:enabled:focus {
    background-color: #e6f3ff;
  }
  .react-calendar__tile--active {
    background: #3b82f6 !important;
    color: white;
  }
  .react-calendar__tile--now {
    background: #f3f4f6;
  }
  .react-calendar__tile:disabled {
    background-color: #f9fafb;
    color: #d1d5db;
  }
  .react-calendar__navigation {
    display: flex;
    height: 44px;
    margin-bottom: 1em;
  }
  .react-calendar__navigation button {
    min-width: 44px;
    background: none;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 16px;
    margin: 0 2px;
  }
  .react-calendar__navigation button:enabled:hover,
  .react-calendar__navigation button:enabled:focus {
    background-color: #e6f3ff;
  }
`;

// Add styles to document head
if (typeof document !== 'undefined') {
  const styleElement = document.createElement('style');
  styleElement.textContent = calendarStyles;
  document.head.appendChild(styleElement);
}
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

const BookAppointment = () => {
  const { doctorId } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  
  const [doctor, setDoctor] = useState(null);
  const [selectedDate, setSelectedDate] = useState(null);
  const [selectedTime, setSelectedTime] = useState('');
  const [availableTimes, setAvailableTimes] = useState([]);
  const [reason, setReason] = useState('');
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [patientId, setPatientId] = useState(null);
  
  // Fetch doctor details and current patient ID when component mounts
  useEffect(() => {
    async function fetchInitialData() {
      if (!doctorId) return;
      
      try {
        setLoading(true);
        
        // Fetch doctor details
        const doctorResponse = await api.get(`/doctors/${doctorId}`);
        if (doctorResponse.data) {
          setDoctor(doctorResponse.data);
        }
        
        // Fetch current patient ID from patients table using logged-in user
        if (user?.id) {
          const patientResponse = await api.get(`/patients/me`);
          if (patientResponse.data && patientResponse.data.success) {
            setPatientId(patientResponse.data.data.id);
          } else {
            setError('You must be a registered patient to book appointments.');
            return;
          }
        }
      } catch (err) {
        console.error('Error fetching data:', err);
        if (err.response?.status === 403) {
          setError('You must be a registered patient to book appointments.');
        } else {
          setError('Failed to load booking data.');
        }
      } finally {
        setLoading(false);
      }
    }
    
    fetchInitialData();
  }, [doctorId, user]);
  
  // Fetch available time slots when date is selected
  useEffect(() => {
    async function fetchAvailableTimes() {
      if (!doctorId || !selectedDate) return;
      
      try {
        const dateString = selectedDate.toISOString().split('T')[0]; // Convert Date to YYYY-MM-DD
        const response = await api.get(`/appointments/availability?doctor_id=${doctorId}&date=${dateString}`);
        if (response.data && response.data.available_slots) {
          setAvailableTimes(response.data.available_slots);
        } else {
          setAvailableTimes([]);
        }
      } catch (err) {
        console.error('Error fetching available times:', err);
        setError('Failed to load available time slots.');
        setAvailableTimes([]);
      }
    }
    
    if (selectedDate) {
      fetchAvailableTimes();
    }
  }, [doctorId, selectedDate]);
  
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!doctorId || !patientId || !selectedDate || !selectedTime || !reason) {
      setError('Please fill in all required fields.');
      return;
    }
    
    try {
      setSubmitting(true);
      setError(null);
      
      const appointmentData = {
        doctor_id: doctorId,
        patient_id: patientId,
        appointment_date: selectedDate.toISOString().split('T')[0], // Convert Date to YYYY-MM-DD
        appointment_time: selectedTime,
        reason,
        notes,
        status: 'pending'
      };
      
      const response = await api.post('/appointments', appointmentData);
      
      if (response.data && response.data.data) {
        setSuccess(true);
        setTimeout(() => {
          // Redirect to appointment details
          navigate(`/appointments/${response.data.data.id}`);
        }, 2000);
      }
    } catch (err) {
      console.error('Error creating appointment:', err);
      setError('Failed to create appointment. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };
  
  // Handle calendar date selection
  const handleDateChange = (date) => {
    setSelectedDate(date);
    setSelectedTime(''); // Reset time selection when date changes
    setAvailableTimes([]); // Clear available times
  };

  // Check if a date should be disabled (past dates)
  const tileDisabled = ({ date, view }) => {
    if (view === 'month') {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      return date < today;
    }
    return false;
  };
  
  // Render time slots
  const renderTimeSlots = () => {
    if (!selectedDate) {
      return (
        <div className="text-center py-4">
          <p>Please select a date first.</p>
        </div>
      );
    }
    
    if (!availableTimes.length) {
      return (
        <div className="text-center py-4">
          <p>No available time slots for the selected date.</p>
        </div>
      );
    }
    
    return (
      <div className="grid grid-cols-4 gap-2 mt-4">
        {availableTimes.map(timeSlot => (
          <button
            key={timeSlot}
            onClick={() => setSelectedTime(timeSlot)}
            className={`py-2 px-4 rounded-md ${
              selectedTime === timeSlot
                ? 'bg-blue-500 text-white'
                : 'bg-blue-100 text-blue-800 hover:bg-blue-200'
            }`}
          >
            {formatTime(timeSlot)}
          </button>
        ))}
      </div>
    );
  };
  
  if (loading) {
    return (
      <div className="flex justify-center p-12">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }
  
  if (success) {
    return (
      <Card>
        <div className="text-center py-8">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            className="h-16 w-16 text-green-500 mx-auto mb-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <h2 className="text-2xl font-bold text-gray-800 mb-2">Appointment Booked!</h2>
          <p className="text-gray-600 mb-4">Your appointment has been successfully scheduled.</p>
          <p className="text-gray-600 mb-4">Redirecting to appointment details...</p>
        </div>
      </Card>
    );
  }
  
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Book Appointment</h1>
      
      {doctor && (
        <Card>
          <div className="p-4">
            <h2 className="text-lg font-semibold mb-2">
              {doctor.first_name} {doctor.last_name}
            </h2>
            <p className="text-gray-600">{doctor.specialty || 'Doctor'}</p>
          </div>
        </Card>
      )}
      
      <Card>
        {error && (
          <div className="bg-red-50 p-4 rounded-md border border-red-200 text-red-700 mb-4">
            {error}
          </div>
        )}
        
        <form onSubmit={handleSubmit}>
          <div className="space-y-6 p-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-3">
                Select Date <span className="text-red-500">*</span>
              </label>
              <div className="border border-gray-300 rounded-md p-4">
                <Calendar
                  onChange={handleDateChange}
                  value={selectedDate}
                  tileDisabled={tileDisabled}
                  minDate={new Date()}
                  selectRange={false}
                  className="react-calendar"
                />
              </div>
              {selectedDate && (
                <p className="mt-2 text-sm text-gray-600">
                  Selected date: {formatDate(selectedDate.toISOString().split('T')[0])}
                </p>
              )}
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Select Time <span className="text-red-500">*</span>
              </label>
              {renderTimeSlots()}
            </div>
            
            <div>
              <label htmlFor="reason" className="block text-sm font-medium text-gray-700 mb-1">
                Reason for Visit <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                id="reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>
            
            <div>
              <label htmlFor="notes" className="block text-sm font-medium text-gray-700 mb-1">
                Additional Notes
              </label>
              <textarea
                id="notes"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={3}
                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
              ></textarea>
            </div>
            
            <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
              <Button
                variant="secondary"
                onClick={() => navigate(-1)}
                disabled={submitting}
              >
                Cancel
              </Button>
              <Button
                variant="primary"
                type="submit"
                disabled={submitting || !patientId || !selectedDate || !selectedTime || !reason}
              >
                {submitting ? 'Booking...' : 'Book Appointment'}
              </Button>
            </div>
          </div>
        </form>
      </Card>
    </div>
  );
};

export default BookAppointment;
import React, { useState, useEffect } from 'react';
import { useNavigate, useParams, useLocation } from 'react-router-dom';
import Calendar from 'react-calendar';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { api } from '../../services/apiService';
import { useAuth } from '../../context/AuthContext';
import 'react-calendar/dist/Calendar.css';

// Custom styles for react-calendar - Modern and Fun Design
const calendarStyles = `
  .react-calendar {
    width: 100%;
    border: none;
    font-family: inherit;
    background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 20px 40px rgba(96, 165, 250, 0.15);
  }
  
  .react-calendar__navigation {
    display: flex;
    height: 60px;
    margin-bottom: 20px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 15px;
    padding: 10px;
    backdrop-filter: blur(10px);
  }
  
  .react-calendar__navigation button {
    min-width: 50px;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 12px;
    font-size: 18px;
    font-weight: 600;
    color: white;
    margin: 0 5px;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
  }
  
  .react-calendar__navigation button:enabled:hover,
  .react-calendar__navigation button:enabled:focus {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
  }
  
  .react-calendar__navigation__label {
    font-size: 20px !important;
    font-weight: 700 !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }
  
  .react-calendar__month-view__weekdays {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    margin-bottom: 15px;
    padding: 10px 0;
  }
  
  .react-calendar__month-view__weekdays__weekday {
    color: white;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 8px;
    text-decoration: none;
  }
  
  .react-calendar__month-view__days {
    gap: 8px;
    display: grid !important;
    grid-template-columns: repeat(7, 1fr);
  }
  
  .react-calendar__tile {
    max-width: 100%;
    height: 50px;
    background: rgba(255, 255, 255, 0.9);
    text-align: center;
    border-radius: 12px;
    font-weight: 600;
    color: #4a5568;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
  }
  
  .react-calendar__tile::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, #3b82f6, #1d4ed8);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1;
  }
  
  .react-calendar__tile:enabled:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    color: white;
    border-color: rgba(255, 255, 255, 0.3);
  }
  
  .react-calendar__tile:enabled:hover::before {
    opacity: 1;
  }
  
  .react-calendar__tile--active {
    background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%) !important;
    color: white !important;
    transform: scale(1.1);
    box-shadow: 0 15px 30px rgba(96, 165, 250, 0.4);
    border-color: rgba(255, 255, 255, 0.5);
  }
  
  .react-calendar__tile--now {
    background: linear-gradient(135deg, #93c5fd 0%, #60a5fa 100%);
    color: white;
    border: 2px solid #3b82f6;
    font-weight: 700;
    box-shadow: 0 5px 15px rgba(96, 165, 250, 0.3);
  }
  
  .react-calendar__tile--now:enabled:hover {
    background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
    color: white;
  }
  
  .react-calendar__tile:disabled {
    background: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.5) !important;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
  }
  
  .react-calendar__tile--weekend {
    background: rgba(147, 197, 253, 0.9);
    color: #1e40af;
  }
  
  .react-calendar__tile--weekend:enabled:hover {
    background: linear-gradient(135deg, #bfdbfe 0%, #93c5fd 100%);
    color: #1e40af;
  }
  
  /* Add a subtle animation to the entire calendar */
  .react-calendar {
    animation: fadeInUp 0.6s ease-out;
  }
  
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  /* Add pulse animation for active dates */
  .react-calendar__tile--active {
    animation: pulse 2s infinite;
  }
  
  @keyframes pulse {
    0% {
      box-shadow: 0 15px 30px rgba(96, 165, 250, 0.4);
    }
    50% {
      box-shadow: 0 15px 30px rgba(96, 165, 250, 0.6);
    }
    100% {
      box-shadow: 0 15px 30px rgba(96, 165, 250, 0.4);
    }
  }
  
  /* Time slot animations */
  .time-slot {
    animation: slideInUp 0.6s ease-out forwards;
    opacity: 0;
    transform: translateY(20px);
  }
  
  @keyframes slideInUp {
    to {
      opacity: 1;
      transform: translateY(0);
    }
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
        <div className="text-center py-8 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border-2 border-dashed border-gray-300">
          <svg className="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p className="text-gray-500 font-medium">Please select a date first to see available times</p>
        </div>
      );
    }
    
    if (!availableTimes.length) {
      return (
        <div className="text-center py-8 bg-gradient-to-br from-red-50 to-pink-50 rounded-xl border border-red-200">
          <svg className="w-12 h-12 mx-auto text-red-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          <p className="text-red-600 font-medium">No available time slots for the selected date</p>
          <p className="text-red-500 text-sm mt-1">Please try selecting a different date</p>
        </div>
      );
    }
    
    return (
      <div className="mt-4">
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          {availableTimes.map((timeSlot, index) => (
            <button
              key={timeSlot}
              onClick={() => setSelectedTime(timeSlot)}
              className={`time-slot relative py-3 px-4 rounded-xl font-semibold transition-all duration-300 transform ${
                selectedTime === timeSlot
                  ? 'bg-gradient-to-r from-purple-500 to-blue-500 text-white scale-105 shadow-lg'
                  : 'bg-gradient-to-r from-blue-50 to-purple-50 text-gray-700 hover:from-blue-100 hover:to-purple-100 hover:scale-105 hover:shadow-md border border-blue-200'
              }`}
              style={{
                animationDelay: `${index * 0.1}s`
              }}
            >
              <div className="flex items-center justify-center">
                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {formatTime(timeSlot)}
              </div>
              {selectedTime === timeSlot && (
                <div className="absolute -top-1 -right-1 w-6 h-6 bg-green-400 rounded-full flex items-center justify-center">
                  <svg className="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                </div>
              )}
            </button>
          ))}
        </div>
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
              <label className="block text-sm font-medium text-gray-700 mb-4">
                <span className="flex items-center">
                  <svg className="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Select Date <span className="text-red-500">*</span>
                </span>
              </label>
              <div className="relative">
                <div className="bg-gradient-to-br from-purple-50 to-blue-50 p-2 rounded-xl shadow-lg border border-purple-100">
                  <Calendar
                    onChange={handleDateChange}
                    value={selectedDate}
                    tileDisabled={tileDisabled}
                    minDate={new Date()}
                    selectRange={false}
                    className="react-calendar"
                  />
                </div>
                {/* Decorative elements */}
                <div className="absolute -top-2 -left-2 w-4 h-4 bg-yellow-400 rounded-full opacity-60 animate-bounce"></div>
                <div className="absolute -top-1 -right-3 w-3 h-3 bg-pink-400 rounded-full opacity-70 animate-pulse"></div>
                <div className="absolute -bottom-2 left-4 w-5 h-5 bg-blue-400 rounded-full opacity-50 animate-bounce" style={{animationDelay: '0.5s'}}></div>
              </div>
              {selectedDate && (
                <div className="mt-4 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl">
                  <div className="flex items-center">
                    <svg className="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <p className="text-sm font-medium text-green-800">
                      Selected date: {formatDate(selectedDate.toISOString().split('T')[0])}
                    </p>
                  </div>
                </div>
              )}
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-3">
                <span className="flex items-center">
                  <svg className="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Select Time <span className="text-red-500">*</span>
                </span>
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
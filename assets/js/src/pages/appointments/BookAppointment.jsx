import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import Calendar from 'react-calendar';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { api } from '../../services/apiService';
import { useAuth } from '../../context/AuthContext';
import 'react-calendar/dist/Calendar.css';

// Custom styles for react-calendar - Inspiring Healing Journey Design
const calendarStyles = `
  .react-calendar {
    width: 100%;
    max-width: 100%;
    border: none;
    font-family: inherit;
    background: linear-gradient(145deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    border-radius: 24px;
    padding: 20px;
    box-shadow: 0 25px 50px rgba(102, 126, 234, 0.25), 
                0 10px 30px rgba(118, 75, 162, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
    position: relative;
    overflow: hidden;
  }
  
  .react-calendar::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    animation: shimmer 6s ease-in-out infinite;
    pointer-events: none;
  }
  
  @keyframes shimmer {
    0%, 100% { transform: translate(-50%, -50%) rotate(0deg); opacity: 0.3; }
    50% { transform: translate(-50%, -50%) rotate(180deg); opacity: 0.6; }
  }
  
  .react-calendar__navigation {
    display: flex;
    height: 64px;
    margin-bottom: 20px;
    background: rgba(255, 255, 255, 0.25);
    border-radius: 16px;
    padding: 8px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  }
  
  .react-calendar__navigation button {
    min-width: 48px;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    color: white;
    margin: 0 4px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    overflow: hidden;
  }
  
  .react-calendar__navigation button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s;
  }
  
  .react-calendar__navigation button:enabled:hover::before {
    left: 100%;
  }
  
  .react-calendar__navigation button:enabled:hover,
  .react-calendar__navigation button:enabled:focus {
    background: rgba(255, 255, 255, 0.35);
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
  }
  
  .react-calendar__navigation__label {
    font-size: 18px !important;
    font-weight: 700 !important;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    letter-spacing: 0.5px;
  }
  
  .react-calendar__navigation__label__labelText {
    font-size: inherit !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
  }
  
  /* Weekdays Header - Elegant Design */
  .react-calendar__month-view__weekdays {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    margin-bottom: 16px;
    padding: 16px 8px;
    display: grid !important;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }
  
  .react-calendar__month-view__weekdays__weekday {
    color: rgba(255, 255, 255, 0.95);
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    width: 100%;
    text-align: center;
    padding: 0 !important;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    position: relative;
  }
  
  .react-calendar__month-view__weekdays__weekday::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    border-radius: 1px;
  }
  
  /* Override abbr tag styling for weekdays */
  .react-calendar__month-view__weekdays__weekday span,
  .react-calendar__month-view__weekdays__weekday abbr {
    text-decoration: none !important;
    border-bottom: none !important;
  }
  
  /* Days Grid - Responsive & Beautiful */
  .react-calendar__month-view__days {
    display: grid !important;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    width: 100%;
  }
  
  .react-calendar__tile {
    width: 100%;
    aspect-ratio: 1;
    min-height: 52px;
    max-height: 52px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 14px;
    font-weight: 600;
    font-size: 15px;
    color: #4a5568;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    margin: 0;
    text-align: center;
    line-height: 1;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(5px);
  }
  
  .react-calendar__tile::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    opacity: 0;
    transition: all 0.4s ease;
    z-index: -1;
    border-radius: 12px;
  }
  
  .react-calendar__tile::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.6) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: all 0.4s ease;
    z-index: 1;
  }
  
  .react-calendar__tile:enabled:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
    color: white;
    border-color: rgba(255, 255, 255, 0.4);
  }
  
  .react-calendar__tile:enabled:hover::before {
    opacity: 1;
  }
  
  .react-calendar__tile:enabled:hover::after {
    width: 30px;
    height: 30px;
  }
  
  .react-calendar__tile--active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    transform: scale(1.08);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
    border-color: rgba(255, 255, 255, 0.6);
    z-index: 2;
  }
  
  .react-calendar__tile--active::before {
    opacity: 1;
  }
  
  .react-calendar__tile--now {
    background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    color: #d97706;
    border: 2px solid #f59e0b;
    font-weight: 700;
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
    position: relative;
  }
  
  .react-calendar__tile--now::before {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  }
  
  .react-calendar__tile--now:enabled:hover {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
  }
  
  .react-calendar__tile:disabled {
    background: rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.4) !important;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
    opacity: 0.5;
  }
  
  .react-calendar__tile--weekend {
    background: linear-gradient(135deg, #a7f3d0 0%, #6ee7b7 100%);
    color: #065f46;
    border: 1px solid rgba(16, 185, 129, 0.3);
  }
  
  .react-calendar__tile--weekend:enabled:hover {
    background: linear-gradient(135deg, #6ee7b7 0%, #34d399 100%);
    color: #064e3b;
  }
  
  /* Neighboring month tiles styling */
  .react-calendar__tile--neighboringMonth {
    opacity: 0.3 !important;
    background: rgba(200, 200, 200, 0.2) !important;
    color: rgba(255, 255, 255, 0.6) !important;
  }
  
  /* Make sure disabled neighboring month dates are visually distinct */
  .react-calendar__month-view__days button:disabled.react-calendar__tile--neighboringMonth {
    opacity: 0.15 !important;
    background: rgba(200, 200, 200, 0.1) !important;
    color: rgba(255, 255, 255, 0.3) !important;
    cursor: not-allowed !important;
    text-decoration: line-through !important;
    box-shadow: none !important;
  }
  
  /* Ensure hovered neighboring month dates don't look selectable */
  .react-calendar__tile--neighboringMonth:hover {
    transform: none !important;
    box-shadow: none !important;
    cursor: not-allowed !important;
  }
  
  /* Calendar container animations */
  .react-calendar {
    animation: calendarAppear 0.8s cubic-bezier(0.4, 0, 0.2, 1);
  }
  
  @keyframes calendarAppear {
    from {
      opacity: 0;
      transform: translateY(40px) scale(0.95);
    }
    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }
  
  /* Active date pulse animation */
  .react-calendar__tile--active {
    animation: selectedPulse 2s ease-in-out infinite;
  }
  
  @keyframes selectedPulse {
    0%, 100% {
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
    }
    50% {
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6), 0 0 20px rgba(118, 75, 162, 0.4);
    }
  }
  
  /* Responsive adjustments */
  @media (max-width: 768px) {
    .react-calendar {
      padding: 16px;
      border-radius: 20px;
    }
    
    .react-calendar__tile {
      min-height: 44px;
      max-height: 44px;
      font-size: 14px;
    }
    
    .react-calendar__month-view__weekdays__weekday {
      font-size: 10px;
      height: 32px;
    }
    
    .react-calendar__month-view__days {
      gap: 4px;
    }
    
    .react-calendar__navigation {
      height: 56px;
      margin-bottom: 16px;
    }
    
    .react-calendar__navigation button {
      min-width: 40px;
      font-size: 14px;
    }
    
    .react-calendar__navigation__label {
      font-size: 16px !important;
    }
    
    .react-calendar__navigation__label__labelText {
      font-size: 16px !important;
    }
  }
  
  @media (max-width: 480px) {
    .react-calendar {
      padding: 12px;
    }
    
    .react-calendar__tile {
      min-height: 38px;
      max-height: 38px;
      font-size: 13px;
      border-radius: 10px;
    }
    
    .react-calendar__month-view__weekdays__weekday {
      font-size: 9px;
      height: 28px;
    }
    
    .react-calendar__month-view__days {
      gap: 3px;
    }
    
    .react-calendar__navigation {
      height: 48px;
      padding: 6px;
    }
    
    .react-calendar__navigation button {
      min-width: 36px;
      font-size: 13px;
      border-radius: 8px;
    }
    
    .react-calendar__navigation__label {
      font-size: 14px !important;
      letter-spacing: 0.3px;
    }
    
    .react-calendar__navigation__label__labelText {
      font-size: 14px !important;
    }
  }
  
  /* Time slot animations and styling */
  .time-slot {
    animation: timeSlotAppear 0.6s ease-out forwards;
    opacity: 0;
    transform: translateY(20px) scale(0.9);
  }
  
  @keyframes timeSlotAppear {
    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }
  
  /* Healing journey visual effects */
  .healing-glow {
    position: relative;
    overflow: hidden;
  }
  
  .healing-glow::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(45deg, #667eea, #764ba2, #f093fb, #667eea);
    border-radius: inherit;
    z-index: -1;
    background-size: 200% 200%;
    animation: healingFlow 3s ease-in-out infinite;
  }
  
  @keyframes healingFlow {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
  }
`;

// Enhanced inspirational animations
const inspirationalStyles = `
  @keyframes hopeFloat {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    33% { transform: translateY(-10px) rotate(1deg); }
    66% { transform: translateY(-5px) rotate(-1deg); }
  }
  
  @keyframes gentleGlow {
    0%, 100% { 
      box-shadow: 0 0 20px rgba(102, 126, 234, 0.3),
                  0 0 40px rgba(118, 75, 162, 0.2),
                  0 0 60px rgba(240, 147, 251, 0.1);
    }
    50% { 
      box-shadow: 0 0 30px rgba(102, 126, 234, 0.4),
                  0 0 60px rgba(118, 75, 162, 0.3),
                  0 0 80px rgba(240, 147, 251, 0.2);
    }
  }
  
  @keyframes hopePulse {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.05); opacity: 1; }
  }
  
  .hope-element {
    animation: hopeFloat 4s ease-in-out infinite;
  }
  
  .gentle-glow {
    animation: gentleGlow 3s ease-in-out infinite;
  }
  
  .hope-pulse {
    animation: hopePulse 2s ease-in-out infinite;
  }
`;

// Add styles to document head
if (typeof document !== 'undefined') {
  const styleElement = document.createElement('style');
  styleElement.textContent = calendarStyles + inspirationalStyles;
  document.head.appendChild(styleElement);
}

// Helper functions for formatting dates and times
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  
  // Make sure we're working with a string in YYYY-MM-DD format
  let dateObj;
  if (dateString instanceof Date) {
    dateObj = dateString;
  } else {
    // If it's a string, parse it
    dateObj = new Date(dateString);
  }
  
  // Ensure we get a consistent date regardless of timezone
  return dateObj.toLocaleDateString('en-US', options);
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
        
        // Fetch doctor details - making sure to get availability data
        const doctorResponse = await api.get(`/doctors/${doctorId}`);
        if (doctorResponse.data) {
            setDoctor(doctorResponse.data);
        }
        
        // Fetch current patient ID from patients table using logged-in user
        if (user?.ID) {
          try {
            const patientResponse = await api.get(`/patients/me`);
            
            // Handle different API response formats
            if (patientResponse.data) {
              if (patientResponse.data.success && patientResponse.data.data && patientResponse.data.data.ID) {
                // Format: { success: true, data: { ID: 123, ... } }
                const ID = patientResponse.data.data.ID;
                setPatientId(ID);
              } else if (patientResponse.data.ID) {
                // Format: { ID: 123, ... }
                setPatientId(patientResponse.data.ID);
              } else if (Array.isArray(patientResponse.data) && patientResponse.data[0]?.ID) {
                // Format: [{ ID: 123, ... }]
                setPatientId(patientResponse.data[0].ID);
              } else {
                console.error('No valid patient ID found in response:', patientResponse.data);
                setError('Could not find your patient record.');
              }
            } else {
              console.error('Empty patient response data');
              setError('No patient data received from server.');
            }
          } catch (patientErr) {
            console.error('Error fetching patient data:', patientErr);
            setError('Could not load your patient information.');
          }
        } else {
          console.error('No user ID found');
          setError('You must be logged in to book appointments.');
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
      if (!doctorId || !selectedDate || !doctor) return;
      
      try {
        // Format date to YYYY-MM-DD consistently
        const year = selectedDate.getFullYear();
        const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
        const day = String(selectedDate.getDate()).padStart(2, '0');
        const dateString = `${year}-${month}-${day}`;
        
        // Get the day of the week (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
        const dayOfWeek = selectedDate.getDay();
        const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const dayName = daysOfWeek[dayOfWeek];

        // Check if doctor has availability for this day in their schedule
        let availableTimesFromSchedule = [];
        
        if (doctor.appointment_availability) {
          // Parse the doctor's availability schedule
          let availabilityData;
          try {
            if (typeof doctor.appointment_availability === 'string') {
              availabilityData = JSON.parse(doctor.appointment_availability);
            } else {
              availabilityData = doctor.appointment_availability;
            }
            
            // Get day name in lowercase to match the data format
            const dayKey = dayName.toLowerCase();
            
            // Find the availability for the current day
            const dayAvailability = availabilityData[dayKey];
            
            // Check if there are any time slots for this day
            if (dayAvailability && dayAvailability.length > 0) {
              // We're only using the first time slot for each day as per the format
              const timeSlot = dayAvailability[0];
              const startTime = timeSlot.start;
              const endTime = timeSlot.end;
              
              if (startTime && endTime) {
                // Generate 30 minute slots between start and end times
                availableTimesFromSchedule = generateTimeSlots(startTime, endTime);
              }
            }
          } catch (e) {
            console.error('Error parsing doctor availability:', e);
          }
        }
        
        // If we have doctor-specific availability, use that
        if (availableTimesFromSchedule.length > 0) {
          setAvailableTimes(availableTimesFromSchedule);
        } else {
          // Fallback to API if no schedule or unable to parse
          const response = await api.get(`/appointments/availability?doctor_id=${doctorId}&date=${dateString}`);
          if (response.data && response.data.available_slots) {
            setAvailableTimes(response.data.available_slots);
          } else {
            setAvailableTimes([]);
          }
        }
      } catch (err) {
        console.error('Error fetching available times:', err);
        setError('Failed to load available time slots.');
        setAvailableTimes([]);
      }
    }
    
    // Helper function to generate time slots
    const generateTimeSlots = (startTime, endTime) => {
      const slots = [];
      
      // Parse start and end times
      const [startHour, startMinute] = startTime.split(':').map(Number);
      const [endHour, endMinute] = endTime.split(':').map(Number);
      
      // Convert to minutes since midnight for easier calculation
      const startTimeMinutes = startHour * 60 + startMinute;
      const endTimeMinutes = endHour * 60 + endMinute;
      
      // Generate slots at 30-minute intervals
      for (let timeMinutes = startTimeMinutes; timeMinutes < endTimeMinutes; timeMinutes += 30) {
        const hour = Math.floor(timeMinutes / 60);
        const minute = timeMinutes % 60;
        
        const formattedHour = String(hour).padStart(2, '0');
        const formattedMinute = String(minute).padStart(2, '0');
        
        slots.push(`${formattedHour}:${formattedMinute}`);
      }
      
      return slots;
    };
    
    if (selectedDate && doctor) {
      fetchAvailableTimes();
    }
  }, [doctorId, selectedDate, doctor]);
  
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Debug form field values
    console.log('Form submission check - values:', {
      doctorId,
      patientId,
      selectedDate,
      selectedTime,
      reason
    });
    
    if (!doctorId || !patientId || !selectedDate || !selectedTime || !reason) {
      setError('Please fill in all required fields.');
      return;
    }
    
    try {
      setSubmitting(true);
      setError(null);
      
      // Format date in YYYY-MM-DD format ensuring consistency regardless of timezone
      const year = selectedDate.getFullYear();
      const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
      const day = String(selectedDate.getDate()).padStart(2, '0');
      const formattedDate = `${year}-${month}-${day}`;
      
      const appointmentData = {
        doctor_id: parseInt(doctorId, 10),
        patient_id: parseInt(patientId, 10),
        appointment_date: formattedDate,
        appointment_time: selectedTime,
        reason,
        notes,
        status: 'pending'
      };
      
      console.log('Submitting appointment data:', appointmentData);
      
      const response = await api.post('/appointments', appointmentData);
      console.log('Appointment creation response:', response);
      
      if (response.data && response.data.data) {
        setSuccess(true);
        setTimeout(() => {
          // Redirect to appointment details
          navigate(`/appointments/${response.data.data.ID}`);
        }, 2000);
      }
    } catch (err) {
      console.error('Error creating appointment:', err);
      // Show more detailed error message
      const errorMessage = err.response?.data?.message || 'Failed to create appointment. Please try again.';
      setError(`Error: ${errorMessage}`);
    } finally {
      setSubmitting(false);
    }
  };
  
  // Handle calendar date selection with proper timezone handling
  const handleDateChange = (date) => {
    // Create a new date object to avoid timezone issues
    const localDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    setSelectedDate(localDate);
    setSelectedTime(''); // Reset time selection when date changes
    setAvailableTimes([]); // Clear available times
    
    // Debug selected date
    console.log('Selected date:', localDate, formatDate(localDate));
    
    // Scroll to "Perfect Choice!" section on mobile after date selection
    setTimeout(() => {
      const perfectChoiceElement = document.querySelector('[data-scroll-target="perfect-choice"]');
      if (perfectChoiceElement && window.innerWidth <= 768) {
        perfectChoiceElement.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });
      }
    }, 300); // Small delay to allow state update
  };

  // Check if a date should be disabled (past dates and dates from neighboring months)
  const tileDisabled = ({ date, view, activeStartDate }) => {
    if (view === 'month') {
      // Current date for comparing with past dates
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      // Get the current month being displayed in the calendar
      const currentViewMonth = activeStartDate.getMonth();
      
      // Disable past dates
      if (date < today) {
        return true;
      }
      
      // Disable dates from neighboring months
      if (date.getMonth() !== currentViewMonth) {
        return true;
      }
    }
    return false;
  };

  // Render time slots with inspirational design
  const renderTimeSlots = () => {
    if (!selectedDate) {
      return (
        <div className="text-center py-12 relative">
          <div className="absolute inset-0 bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 rounded-2xl opacity-60"></div>
          <div className="relative z-10">
            <div className="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center hope-element">
              <svg className="w-10 h-10 text-blue-500 hope-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 className="text-xl font-semibold text-gray-700 mb-2">Choose Your Day of Hope</h3>
            <p className="text-gray-500 font-medium max-w-sm mx-auto leading-relaxed">
              Select a date above to begin your journey toward better health and well-being
            </p>
            <div className="mt-4 flex justify-center space-x-2">
              <div className="w-2 h-2 bg-blue-300 rounded-full hope-pulse" style={{animationDelay: '0s'}}></div>
              <div className="w-2 h-2 bg-purple-300 rounded-full hope-pulse" style={{animationDelay: '0.3s'}}></div>
              <div className="w-2 h-2 bg-pink-300 rounded-full hope-pulse" style={{animationDelay: '0.6s'}}></div>
            </div>
          </div>
        </div>
      );
    }
    
    if (!availableTimes.length) {
      return (
        <div className="text-center py-12 relative">
          <div className="absolute inset-0 bg-gradient-to-br from-amber-50 via-orange-50 to-red-50 rounded-2xl opacity-60"></div>
          <div className="relative z-10">
            <div className="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-amber-100 to-orange-100 rounded-full flex items-center justify-center hope-element">
              <svg className="w-10 h-10 text-amber-600 hope-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <h3 className="text-xl font-semibold text-gray-700 mb-2">Another Day Awaits</h3>
            <p className="text-gray-500 font-medium max-w-sm mx-auto leading-relaxed">
              This day is fully booked, but hope is never far away. Try selecting another date for your healing journey.
            </p>
            <div className="mt-6">
              <span className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-amber-100 to-orange-100 rounded-full text-amber-700 text-sm font-medium">
                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                More dates available
              </span>
            </div>
          </div>
        </div>
      );
    }
    
    return (
      <div className="mt-6">
        <div className="text-center mb-6">
          <h3 className="text-xl font-semibold text-gray-700 mb-2 flex items-center justify-center">
            <svg className="w-6 h-6 mr-2 text-purple-500 hope-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Choose Your Time of Healing
          </h3>
          <p className="text-gray-500 max-w-md mx-auto">
            Each appointment time represents a step forward in your wellness journey
          </p>
        </div>
        
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {availableTimes.map((timeSlot, index) => (
            <button
              key={timeSlot}
              type="button"
              onClick={() => {
                console.log(`Time selected: ${timeSlot}`);
                setSelectedTime(timeSlot);
                
                // Scroll to "Perfect! Your appointment time is set" section on mobile after time selection
                setTimeout(() => {
                  const timeSetElement = document.querySelector('[data-scroll-target="time-set"]');
                  if (timeSetElement && window.innerWidth <= 768) {
                    timeSetElement.scrollIntoView({
                      behavior: 'smooth',
                      block: 'center'
                    });
                  }
                }, 300); // Small delay to allow state update and DOM render
              }}
              className={`time-slot group relative py-4 px-6 rounded-2xl font-semibold transition-all duration-500 transform ${
                selectedTime === timeSlot
                  ? 'bg-gradient-to-br from-purple-500 via-blue-500 to-teal-500 text-white scale-105 shadow-2xl gentle-glow'
                  : 'bg-gradient-to-br from-white via-blue-50 to-purple-50 text-gray-700 hover:from-blue-100 hover:via-purple-100 hover:to-pink-100 hover:scale-105 hover:shadow-xl border-2 border-blue-100 hover:border-purple-200'
              }`}
              style={{
                animationDelay: `${index * 0.1}s`
              }}
            >
              <div className="flex flex-col items-center justify-center space-y-2">
                <div className="flex items-center">
                  <svg className={`w-5 h-5 mr-2 transition-colors duration-300 ${
                    selectedTime === timeSlot ? 'text-white' : 'text-purple-500 group-hover:text-purple-600'
                  }`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span className="text-lg font-bold">{formatTime(timeSlot)}</span>
                </div>
                
                {selectedTime === timeSlot && (
                  <div className="flex items-center space-x-1 text-white">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                    <span className="text-sm font-medium">Selected</span>
                  </div>
                )}
                
                {selectedTime !== timeSlot && (
                  <div className="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <span className="text-xs text-purple-600 font-medium">Choose this time</span>
                  </div>
                )}
              </div>
              
              {/* Decorative elements */}
              <div className={`absolute top-2 right-2 w-3 h-3 rounded-full transition-all duration-300 ${
                selectedTime === timeSlot 
                  ? 'bg-white opacity-80' 
                  : 'bg-purple-200 opacity-0 group-hover:opacity-60'
              }`}></div>
              
              {selectedTime === timeSlot && (
                <div className="absolute -top-1 -right-1 w-7 h-7 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center shadow-lg hope-pulse">
                  <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                </div>
              )}
              
              {/* Hover glow effect */}
              <div className="absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-20 transition-opacity duration-300 bg-gradient-to-br from-purple-400 to-pink-400 pointer-events-none"></div>
            </button>
          ))}
        </div>
        
        {selectedTime && (
          <div className="mt-6 text-center" data-scroll-target="time-set">
            <div className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-100 via-emerald-100 to-teal-100 border border-green-200 rounded-2xl gentle-glow">
              <svg className="w-5 h-5 mr-3 text-green-600 hope-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              <p className="text-sm font-semibold text-green-800">
                Perfect! Your appointment time is set for {formatTime(selectedTime)}
              </p>
            </div>
          </div>
        )}
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
      <div className="min-h-screen bg-gradient-to-br from-green-50 via-emerald-50 to-teal-50 flex items-center justify-center p-4">
        <Card className="max-w-2xl w-full">
          <div className="text-center py-12 px-6">
            <div className="w-24 h-24 mx-auto mb-8 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center gentle-glow">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                className="h-12 w-12 text-white hope-pulse"
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
            </div>
            
            <h2 className="text-3xl font-bold bg-gradient-to-r from-green-600 to-emerald-600 bg-clip-text text-transparent mb-4">
              Your Journey Begins Now!
            </h2>
            
            <div className="space-y-4 mb-8">
              <p className="text-lg text-gray-700 font-medium">
                🎉 Your appointment has been successfully scheduled
              </p>
              <p className="text-gray-600 max-w-lg mx-auto leading-relaxed">
                This is more than just an appointment – it's the first step toward a healthier, happier you. 
                We're excited to be part of your healing journey.
              </p>
            </div>
            
            <div className="bg-gradient-to-r from-green-100 to-emerald-100 rounded-2xl p-6 mb-8 border border-green-200">
              <div className="flex items-center justify-center mb-4">
                <svg className="w-6 h-6 text-green-600 mr-2 hope-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 className="text-lg font-semibold text-green-800">What's Next?</h3>
              </div>
              <ul className="text-sm text-green-700 space-y-2 max-w-md mx-auto">
                <li className="flex items-center">
                  <span className="w-2 h-2 bg-green-400 rounded-full mr-3"></span>
                  You'll receive appointment details shortly
                </li>
                <li className="flex items-center">
                  <span className="w-2 h-2 bg-green-400 rounded-full mr-3"></span>
                  Prepare any questions you'd like to discuss
                </li>
                <li className="flex items-center">
                  <span className="w-2 h-2 bg-green-400 rounded-full mr-3"></span>
                  Arrive a few minutes early for check-in
                </li>
              </ul>
            </div>
            
            <div className="flex items-center justify-center space-x-2 text-green-600 mb-6">
              <div className="flex space-x-1">
                <div className="w-3 h-3 bg-green-300 rounded-full hope-pulse" style={{animationDelay: '0s'}}></div>
                <div className="w-3 h-3 bg-emerald-300 rounded-full hope-pulse" style={{animationDelay: '0.3s'}}></div>
                <div className="w-3 h-3 bg-teal-300 rounded-full hope-pulse" style={{animationDelay: '0.6s'}}></div>
              </div>
              <span className="text-sm font-medium">Redirecting to appointment details...</span>
            </div>
            
            <p className="text-sm text-gray-500 italic">
              "Every step forward is a victory worth celebrating" ✨
            </p>
          </div>
        </Card>
      </div>
    );
  }
  
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 py-6 px-4 md:px-6 lg:px-8">
      {/* Decorative background elements */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-20 left-10 w-32 h-32 bg-purple-200 rounded-full opacity-20 hope-element"></div>
        <div className="absolute top-40 right-20 w-24 h-24 bg-blue-200 rounded-full opacity-25 hope-element" style={{animationDelay: '1s'}}></div>
        <div className="absolute bottom-40 left-1/4 w-40 h-40 bg-pink-200 rounded-full opacity-15 hope-element" style={{animationDelay: '2s'}}></div>
        <div className="absolute bottom-20 right-10 w-28 h-28 bg-indigo-200 rounded-full opacity-20 hope-element" style={{animationDelay: '3s'}}></div>
      </div>
      
      <div className="relative z-10 max-w-6xl mx-auto">
        {/* Inspirational Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full mb-6 gentle-glow">
            <svg className="w-8 h-8 text-white hope-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
          </div>
          
          <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold bg-gradient-to-r from-purple-600 via-blue-600 to-teal-600 bg-clip-text text-transparent mb-4">
            Your Journey to Wellness Starts Here
          </h1>
          
          <p className="text-lg md:text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed">
            Every great healing story begins with a single step. Today, you're taking that brave step forward toward better health and renewed hope.
          </p>
          
          <div className="mt-6 flex justify-center space-x-2">
            <span className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-100 to-blue-100 rounded-full text-purple-700 text-sm font-medium hope-element">
              <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              Step 1: Choose Your Time
            </span>
          </div>
        </div>

        {/* Doctor Information Card */}
        {doctor && (
          <div className="mb-8">
            <Card className="overflow-hidden healing-glow">
              <div className="bg-gradient-to-br from-white via-blue-50 to-purple-50 p-6">
                <div className="flex flex-col sm:flex-row items-center sm:items-start space-y-4 sm:space-y-0 sm:space-x-6">
                  <div className="w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold gentle-glow flex-shrink-0">
                    {doctor.first_name?.[0]}{doctor.last_name?.[0]}
                  </div>
                  
                  <div className="flex-1 text-center sm:text-left">
                    <h2 className="text-2xl font-bold text-gray-800 mb-2">
                      Dr. {doctor.first_name} {doctor.last_name}
                    </h2>
                    <p className="text-lg text-blue-600 font-semibold mb-3">{doctor.specialty || 'Healthcare Specialist'}</p>
                    
                    <div className="flex flex-wrap justify-center sm:justify-start gap-3">
                      <span className="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Available Today
                      </span>
                      <span className="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        Caring Professional
                      </span>
                    </div>
                  </div>
                  
                  <div className="text-center">
                    <div className="text-3xl mb-2 hope-pulse">✨</div>
                    <p className="text-sm text-gray-600 font-medium">Ready to help you heal</p>
                  </div>
                </div>
              </div>
            </Card>
          </div>
        )}

        {/* Main Booking Form */}
        <Card className="overflow-hidden healing-glow">
          <div className="bg-gradient-to-br from-white via-purple-50 to-blue-50">
            {error && (
              <div className="m-6 mb-0 bg-gradient-to-r from-red-50 to-pink-50 p-6 rounded-2xl border border-red-200">
                <div className="flex items-center">
                  <svg className="w-6 h-6 text-red-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                  </svg>
                  <div>
                    <h3 className="text-lg font-semibold text-red-800 mb-1">Let's Try Again</h3>
                    <p className="text-red-700">{error}</p>
                  </div>
                </div>
              </div>
            )}
            
            <form onSubmit={handleSubmit}>
              <div className="space-y-8 p-6">
                {/* Calendar Section */}
                <div>
                  <div className="text-center mb-6">
                    <h3 className="text-2xl font-bold text-gray-800 mb-2 flex items-center justify-center">
                      <svg className="w-7 h-7 mr-3 text-purple-500 hope-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      Choose Your Day of Hope
                    </h3>
                    <p className="text-gray-600 max-w-md mx-auto">
                      Select the perfect day to begin your healing journey. Each date represents new possibilities and renewed hope.
                    </p>
                  </div>
                  
                  <div className="relative max-w-2xl mx-auto">
                    <div className="bg-gradient-to-br from-slate-50 to-blue-50 p-4 md:p-6 rounded-2xl shadow-xl border border-slate-200">
                      <Calendar
                        onChange={handleDateChange}
                        value={selectedDate}
                        tileDisabled={tileDisabled}
                        tileClassName={({ date, view, activeStartDate }) => {
                          let classes = [];
                          
                          if (view === 'month') {
                            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
                            if (isWeekend) {
                              classes.push('react-calendar__tile--weekend');
                            }
                            
                            if (date.getMonth() !== activeStartDate.getMonth()) {
                              classes.push('react-calendar__tile--neighboringMonth');
                            }
                          }
                          
                          return classes.join(' ');
                        }}
                        minDate={new Date()}
                        calendarType="gregory"
                        formatDay={(locale, date) => date.getDate()}
                        selectRange={false}
                        showNeighboringMonth={true}
                        showFixedNumberOfWeeks={false}
                        className="react-calendar"
                      />
                    </div>
                    
                    {/* Decorative floating elements */}
                    <div className="absolute -top-3 -left-3 w-6 h-6 bg-yellow-400 rounded-full opacity-60 hope-element"></div>
                    <div className="absolute -top-2 -right-4 w-4 h-4 bg-pink-400 rounded-full opacity-70 hope-element" style={{animationDelay: '1s'}}></div>
                    <div className="absolute -bottom-3 left-8 w-5 h-5 bg-blue-400 rounded-full opacity-50 hope-element" style={{animationDelay: '2s'}}></div>
                  </div>
                  
                  {selectedDate && (
                    <div className="mt-6 max-w-lg mx-auto" data-scroll-target="perfect-choice">
                      <div className="bg-gradient-to-r from-green-100 via-emerald-100 to-teal-100 border border-green-200 rounded-2xl p-6 gentle-glow">
                        <div className="flex items-center justify-center">
                          <svg className="w-6 h-6 mr-3 text-green-600 hope-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                          </svg>
                          <div className="text-center">
                            <p className="text-lg font-bold text-green-800 mb-1">
                              Perfect Choice! 🌟
                            </p>
                            <p className="text-sm font-medium text-green-700">
                              You've selected {formatDate(selectedDate)} for your appointment
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
                
                {/* Time Selection */}
                <div>
                  {renderTimeSlots()}
                </div>
                
                {/* Appointment Details */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label htmlFor="reason" className="block text-lg font-semibold text-gray-700 mb-3">
                      <span className="flex items-center">
                        <svg className="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        What brings you here today? <span className="text-red-500">*</span>
                      </span>
                    </label>
                    <input
                      type="text"
                      id="reason"
                      value={reason}
                      onChange={(e) => setReason(e.target.value)}
                      className="w-full px-4 py-3 border-2 border-blue-200 rounded-xl shadow-sm focus:outline-none focus:ring-4 focus:ring-blue-200 focus:border-blue-400 bg-gradient-to-r from-white to-blue-50 transition-all duration-300"
                      placeholder="e.g., Regular checkup, Follow-up visit, New symptoms..."
                      required
                    />
                  </div>
                  
                  <div>
                    <label htmlFor="notes" className="block text-lg font-semibold text-gray-700 mb-3">
                      <span className="flex items-center">
                        <svg className="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Additional Notes
                      </span>
                    </label>
                    <textarea
                      id="notes"
                      value={notes}
                      onChange={(e) => setNotes(e.target.value)}
                      rows={3}
                      className="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:outline-none focus:ring-4 focus:ring-purple-200 focus:border-purple-400 bg-gradient-to-r from-white to-purple-50 transition-all duration-300 resize-none"
                      placeholder="Share any concerns, symptoms, or questions you'd like to discuss..."
                    ></textarea>
                  </div>
                </div>
                
                {/* Action Buttons */}
                <div className="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6 pt-8 border-t border-gray-200">
                  <Button
                    variant="secondary"
                    onClick={() => navigate(-1)}
                    disabled={submitting}
                    className="w-full sm:w-auto px-8 py-3 text-lg font-semibold"
                  >
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Go Back
                  </Button>
                  
                  <Button
                    variant="primary"
                    type="submit"
                    disabled={submitting || !patientId || !selectedDate || !selectedTime || !reason}
                    className={`w-full sm:w-auto px-12 py-4 text-lg font-bold transition-all duration-300 ${
                      submitting || !patientId || !selectedDate || !selectedTime || !reason
                        ? 'opacity-50 cursor-not-allowed'
                        : 'gentle-glow hope-pulse bg-gradient-to-r from-purple-600 via-blue-600 to-teal-600 hover:from-purple-700 hover:via-blue-700 hover:to-teal-700 transform hover:scale-105'
                    }`}
                  >
                    {submitting ? (
                      <div className="flex items-center">
                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Scheduling Your Hope...
                      </div>
                    ) : (
                      <div className="flex items-center">
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        Book My Healing Appointment ✨
                      </div>
                    )}
                  </Button>
                </div>
                
                {/* Inspirational Footer */}
                <div className="text-center pt-6 border-t border-gray-200">
                  <p className="text-gray-600 italic font-medium mb-2">
                    "The greatest healing therapy is friendship and love." - Hubert Humphrey
                  </p>
                  <div className="flex justify-center space-x-1">
                    <span className="text-yellow-400 hope-pulse">⭐</span>
                    <span className="text-pink-400 hope-pulse" style={{animationDelay: '0.3s'}}>💖</span>
                    <span className="text-blue-400 hope-pulse" style={{animationDelay: '0.6s'}}>🌟</span>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </Card>
      </div>
    </div>
  );
};

export default BookAppointment;
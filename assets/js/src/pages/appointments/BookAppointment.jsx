import React, { useState, useEffect } from 'react';
import { useNavigate, useParams, useLocation } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { api } from '../../services/apiService';
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
  const location = useLocation();
  
  const [doctor, setDoctor] = useState(null);
  const [patient, setPatient] = useState(null);
  const [patients, setPatients] = useState([]);
  const [selectedPatient, setSelectedPatient] = useState('');
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedTime, setSelectedTime] = useState('');
  const [availableDates, setAvailableDates] = useState([]);
  const [availableTimes, setAvailableTimes] = useState([]);
  const [reason, setReason] = useState('');
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [calendarView, setCalendarView] = useState('month');
  
  // Fetch doctor details when component mounts
  useEffect(() => {
    async function fetchDoctor() {
      if (!doctorId) return;
      
      try {
        setLoading(true);
        const response = await api.get(`/doctors/${doctorId}`);
        if (response.data) {
          setDoctor(response.data);
        }
      } catch (err) {
        console.error('Error fetching doctor:', err);
        setError('Failed to load doctor details.');
      } finally {
        setLoading(false);
      }
    }
    
    fetchDoctor();
  }, [doctorId]);
  
  // Fetch patients for dropdown
  useEffect(() => {
    async function fetchPatients() {
      try {
        const response = await api.get('/patients');
        if (response.data && response.data.data) {
          setPatients(response.data.data);
        } else if (Array.isArray(response.data)) {
          setPatients(response.data);
        }
      } catch (err) {
        console.error('Error fetching patients:', err);
      }
    }
    
    fetchPatients();
  }, []);
  
  // Fetch available dates for the selected doctor
  useEffect(() => {
    async function fetchAvailableDates() {
      if (!doctorId) return;
      
      try {
        const response = await api.get(`/appointments/availability?doctor_id=${doctorId}`);
        if (response.data && Array.isArray(response.data)) {
          setAvailableDates(response.data);
        }
      } catch (err) {
        console.error('Error fetching available dates:', err);
        setError('Failed to load available appointment dates.');
      }
    }
    
    if (doctorId) {
      fetchAvailableDates();
    }
  }, [doctorId]);
  
  // Fetch available time slots when date is selected
  useEffect(() => {
    async function fetchAvailableTimes() {
      if (!doctorId || !selectedDate) return;
      
      try {
        const response = await api.get(`/appointments/availability?doctor_id=${doctorId}&date=${selectedDate}`);
        if (response.data && Array.isArray(response.data)) {
          setAvailableTimes(response.data);
        }
      } catch (err) {
        console.error('Error fetching available times:', err);
        setError('Failed to load available time slots.');
      }
    }
    
    if (selectedDate) {
      fetchAvailableTimes();
    }
  }, [doctorId, selectedDate]);
  
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!doctorId || !selectedPatient || !selectedDate || !selectedTime) {
      setError('Please fill in all required fields.');
      return;
    }
    
    try {
      setSubmitting(true);
      setError(null);
      
      const appointmentData = {
        doctor_id: doctorId,
        patient_id: selectedPatient,
        appointment_date: selectedDate,
        appointment_time: selectedTime,
        reason,
        notes,
        status: 'pending'
      };
      
      const response = await api.post('/appointments', appointmentData);
      
      if (response.data) {
        setSuccess(true);
        setTimeout(() => {
          // Redirect to appointment details
          navigate(`/appointments/${response.data.id}`);
        }, 2000);
      }
    } catch (err) {
      console.error('Error creating appointment:', err);
      setError('Failed to create appointment. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };
  
  // Helper function to generate a calendar view
  const renderCalendar = () => {
    if (!availableDates.length) {
      return (
        <div className="text-center py-8">
          <p>No available dates found for this doctor.</p>
        </div>
      );
    }
    
    // Simple calendar display
    return (
      <div className="grid grid-cols-7 gap-2">
        {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
          <div key={day} className="text-center font-medium text-gray-700 py-2">
            {day}
          </div>
        ))}
        
        {availableDates.map(date => {
          const isAvailable = date.available;
          const isSelected = date.date === selectedDate;
          
          return (
            <button
              key={date.date}
              onClick={() => isAvailable && setSelectedDate(date.date)}
              disabled={!isAvailable}
              className={`py-2 rounded-md ${
                isSelected
                  ? 'bg-blue-500 text-white'
                  : isAvailable
                  ? 'bg-green-100 text-green-800 hover:bg-green-200'
                  : 'bg-gray-100 text-gray-400 cursor-not-allowed'
              }`}
            >
              {new Date(date.date).getDate()}
            </button>
          );
        })}
      </div>
    );
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
        {availableTimes.map(slot => (
          <button
            key={slot.time}
            onClick={() => setSelectedTime(slot.time)}
            disabled={!slot.available}
            className={`py-2 px-4 rounded-md ${
              selectedTime === slot.time
                ? 'bg-blue-500 text-white'
                : slot.available
                ? 'bg-blue-100 text-blue-800 hover:bg-blue-200'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
            }`}
          >
            {formatTime(slot.time)}
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
          <div className="space-y-4 p-4">
            <div>
              <label htmlFor="patient" className="block text-sm font-medium text-gray-700 mb-1">
                Select Patient <span className="text-red-500">*</span>
              </label>
              <select
                id="patient"
                value={selectedPatient}
                onChange={(e) => setSelectedPatient(e.target.value)}
                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="">-- Select Patient --</option>
                {patients.map((patient) => (
                  <option key={patient.id} value={patient.id}>
                    {patient.first_name} {patient.last_name}
                  </option>
                ))}
              </select>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Select Date <span className="text-red-500">*</span>
              </label>
              <div className="border border-gray-300 rounded-md p-4">
                {renderCalendar()}
              </div>
              {selectedDate && (
                <p className="mt-2 text-sm text-gray-600">
                  Selected date: {formatDate(selectedDate)}
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
                disabled={submitting || !selectedPatient || !selectedDate || !selectedTime || !reason}
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
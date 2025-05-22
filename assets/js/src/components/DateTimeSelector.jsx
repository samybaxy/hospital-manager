import React, { useState, useEffect } from 'react';
import appointmentService from '../services/appointmentService';

/**
 * Date and Time Selector for appointments
 * Shows a calendar with available dates and time slots based on doctor's availability
 */
const DateTimeSelector = ({ doctorId, onSelectDateTime }) => {
  const [selectedDate, setSelectedDate] = useState('');
  const [availableSlots, setAvailableSlots] = useState([]);
  const [selectedTime, setSelectedTime] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  // Generate the next 30 days for date selection
  const generateDateOptions = () => {
    const options = [];
    const today = new Date();
    
    for (let i = 1; i <= 30; i++) {
      const date = new Date();
      date.setDate(today.getDate() + i);
      
      // Skip weekends if needed (uncomment to exclude weekends)
      // const dayOfWeek = date.getDay();
      // if (dayOfWeek === 0 || dayOfWeek === 6) continue; // Skip Sunday (0) and Saturday (6)
      
      // Format as YYYY-MM-DD
      const formattedDate = date.toISOString().split('T')[0];
      
      // Format for display
      const displayDate = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        month: 'short', 
        day: 'numeric' 
      });
      
      options.push({ value: formattedDate, label: displayDate });
    }
    
    return options;
  };

  // Fetch available time slots when a date is selected
  useEffect(() => {
    if (!selectedDate || !doctorId) return;
    
    const fetchAvailableSlots = async () => {
      setLoading(true);
      setError('');
      setMessage('');
      
      try {
        const response = await appointmentService.getAvailableSlots(doctorId, selectedDate);
        
        if (response.data.available_slots && response.data.available_slots.length > 0) {
          setAvailableSlots(response.data.available_slots || []);
        } else {
          setAvailableSlots([]);
          // Check if there's a message in the response
          if (response.data.message) {
            setMessage(response.data.message);
          } else {
            setMessage('No available time slots for this date. The doctor may not be working or all slots are booked.');
          }
        }
        
        // Reset selected time if it's no longer available
        if (selectedTime && !response.data.available_slots.includes(selectedTime)) {
          setSelectedTime('');
        }
      } catch (err) {
        console.error('Error fetching available slots:', err);
        setError('Failed to fetch available time slots. Please try again.');
        setAvailableSlots([]);
      } finally {
        setLoading(false);
      }
    };
    
    fetchAvailableSlots();
  }, [doctorId, selectedDate]);

  // When both date and time are selected, call the parent component's callback
  useEffect(() => {
    if (selectedDate && selectedTime) {
      onSelectDateTime({ date: selectedDate, time: selectedTime });
    }
  }, [selectedDate, selectedTime, onSelectDateTime]);

  // Format time for display (24h to 12h)
  const formatTimeForDisplay = (time) => {
    if (!time) return '';
    
    try {
      const [hours, minutes, seconds] = time.split(':');
      return new Date(0, 0, 0, hours, minutes).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: 'numeric',
        hour12: true
      });
    } catch (e) {
      console.error('Error formatting time:', e);
      return time; // Return the original time if there's an error
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <label htmlFor="appointment-date" className="block text-sm font-medium text-gray-700 mb-1">
          Appointment Date <span className="text-red-500">*</span>
        </label>
        <select
          id="appointment-date"
          value={selectedDate}
          onChange={(e) => {
            setSelectedDate(e.target.value);
            setSelectedTime('');
          }}
          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
          required
        >
          <option value="">Select a date</option>
          {generateDateOptions().map((date) => (
            <option key={date.value} value={date.value}>
              {date.label}
            </option>
          ))}
        </select>
      </div>

      {selectedDate && (
        <div>
          <label htmlFor="appointment-time" className="block text-sm font-medium text-gray-700 mb-1">
            Available Time Slots <span className="text-red-500">*</span>
          </label>
          
          {loading ? (
            <div className="py-3 flex items-center justify-center">
              <div className="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-500"></div>
              <span className="ml-2 text-gray-600">Loading available times...</span>
            </div>
          ) : error ? (
            <div className="py-3 text-red-500 bg-red-50 px-3 rounded border border-red-200">
              {error}
            </div>
          ) : availableSlots.length === 0 ? (
            <div className="py-3 text-amber-600 bg-amber-50 px-3 rounded border border-amber-200">
              {message || "No available time slots for this date. Please select another date."}
            </div>
          ) : (
            <>
              <div className="grid grid-cols-3 gap-2 md:grid-cols-4 lg:grid-cols-5">
                {availableSlots.map((slot) => (
                  <button
                    key={slot}
                    type="button"
                    className={`py-2 px-4 border rounded-md text-sm font-medium
                      ${selectedTime === slot
                        ? 'bg-blue-100 border-blue-500 text-blue-700'
                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                      }`}
                    onClick={() => setSelectedTime(slot)}
                  >
                    {formatTimeForDisplay(slot)}
                  </button>
                ))}
              </div>
              {selectedTime && (
                <div className="mt-3 p-2 bg-green-50 border border-green-100 rounded-md text-green-700 text-sm">
                  You selected: <span className="font-semibold">{formatTimeForDisplay(selectedTime)}</span>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
};

export default DateTimeSelector;

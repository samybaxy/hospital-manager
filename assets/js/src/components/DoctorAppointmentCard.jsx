import React, { useState } from 'react';
import Card from './Card';
import Button from './Button';
import DateTimeSelector from './DateTimeSelector';
import appointmentService from '../services/appointmentService';

/**
 * Doctor Card with appointment booking functionality
 */
const DoctorAppointmentCard = ({ doctor }) => {
  const [showBooking, setShowBooking] = useState(false);
  const [selectedDateTime, setSelectedDateTime] = useState(null);
  const [reason, setReason] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  const handleBooking = async (e) => {
    e.preventDefault();
    if (!selectedDateTime || !selectedDateTime.date || !selectedDateTime.time) {
      setError('Please select a date and time for your appointment');
      return;
    }

    setLoading(true);
    setError('');
    setSuccessMessage('');

    try {
      const appointmentData = {
        doctor_id: doctor.ID,
        date: selectedDateTime.date,
        time: selectedDateTime.time,
        reason: reason
      };

      await appointmentService.createAppointment(appointmentData);
      setSuccessMessage('Appointment booked successfully!');
      setShowBooking(false);
      setSelectedDateTime(null);
      setReason('');
    } catch (err) {
      console.error('Error booking appointment:', err);
      const errorMessage = err.response?.data?.message || 'Failed to book appointment. Please try again.';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card 
      title={`Dr. ${doctor.first_name} ${doctor.last_name}`}
      className="shadow-md hover:shadow-lg transition-shadow duration-300"
    >
      <div className="space-y-4">
        <div className="flex flex-wrap justify-between">
          <div>
            <p className="font-semibold text-blue-700">{doctor.specialty}</p>
            <p className="text-gray-600">{doctor.education}</p>
            <p className="text-gray-500 text-sm">Office: {doctor.office}</p>
          </div>
          <div className="flex-shrink-0">
            <p className="text-sm text-gray-500">Experience: {doctor.years_experience} years</p>
            {doctor.status === 'active' && (
              <span className="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                Available
              </span>
            )}
          </div>
        </div>

        {!showBooking ? (
          <div className="mt-4">
            <Button
              onClick={() => setShowBooking(true)}
              label="Book Appointment"
              className="bg-blue-600 hover:bg-blue-700 text-white"
            />
          </div>
        ) : (
          <div className="mt-4 border-t pt-4">
            <h3 className="text-lg font-medium mb-4">Book an Appointment</h3>
            <form onSubmit={handleBooking} className="space-y-4">
              <DateTimeSelector 
                doctorId={doctor.ID} 
                onSelectDateTime={setSelectedDateTime} 
              />
              
              <div>
                <label htmlFor="reason" className="block text-sm font-medium text-gray-700 mb-1">
                  Reason for Visit
                </label>
                <textarea
                  id="reason"
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                  rows={3}
                  placeholder="Please describe the reason for your appointment"
                />
              </div>

              {error && (
                <div className="text-red-500 text-sm">{error}</div>
              )}

              {successMessage && (
                <div className="text-green-500 text-sm">{successMessage}</div>
              )}

              <div className="flex justify-end space-x-2">
                <Button
                  type="button"
                  onClick={() => setShowBooking(false)}
                  label="Cancel"
                  className="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"
                />
                <Button
                  type="submit"
                  label={loading ? "Booking..." : "Confirm Booking"}
                  disabled={loading || !selectedDateTime}
                  className="bg-blue-600 hover:bg-blue-700 text-white"
                />
              </div>
            </form>
          </div>
        )}
      </div>
    </Card>
  );
};

export default DoctorAppointmentCard;

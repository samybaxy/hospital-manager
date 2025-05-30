import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import Card from './Card';
import Button from './Button';
import { api } from '../services/apiService';

const VisitForm = ({ 
  initialData = {}, 
  onSubmit, 
  submitButtonText = 'Save Visit',
  title,
  subtitle,
  loading = false,
  error = null
}) => {
  const [formData, setFormData] = useState({
    patient_id: '',
    doctor_id: '',
    appointment_id: '',
    date: '',
    time: '',
    medical_history: '',
    diagnosis: '',
    treatment: '',
    complaint: '',
    ...initialData
  });
  
  const [patients, setPatients] = useState([]);
  const [doctors, setDoctors] = useState([]);
  const [appointments, setAppointments] = useState([]);
  const [dataLoading, setDataLoading] = useState(true);
  const [dataError, setDataError] = useState(null);
  
  const navigate = useNavigate();

  // Fetch required data for dropdowns only once
  useEffect(() => {
    const fetchData = async () => {
      setDataLoading(true);
      setDataError(null);
      
      try {
        const [patientsRes, doctorsRes, appointmentsRes] = await Promise.all([
          api.get('/patients').catch(() => ({ data: { success: false, data: [] } })),
          api.get('/doctors').catch(() => ({ data: { success: false, data: [] } })),
          api.get('/appointments').catch(() => ({ data: { success: false, data: [] } }))
        ]);

        // Handle patients response
        if (patientsRes.data?.success && patientsRes.data.data) {
          // Check for nested structure with items array (pagination)
          if (patientsRes.data.data.patients && patientsRes.data.data.patients.items) {
            setPatients(patientsRes.data.data.patients.items);
          } else if (Array.isArray(patientsRes.data.data)) {
            setPatients(patientsRes.data.data);
          } else {
            setPatients([]);
          }
        } else if (Array.isArray(patientsRes.data)) {
          setPatients(patientsRes.data);
        }

        // Handle doctors response
        if (doctorsRes.data?.success && doctorsRes.data.data) {
          // Check for nested structure with items array (pagination)
          if (doctorsRes.data.data.doctors && doctorsRes.data.data.doctors.items) {
            setDoctors(doctorsRes.data.data.doctors.items);
          } else if (Array.isArray(doctorsRes.data.data)) {
            setDoctors(doctorsRes.data.data);
          } else {
            setDoctors([]);
          }
        } else if (Array.isArray(doctorsRes.data)) {
          setDoctors(doctorsRes.data);
        }

        // Handle appointments response
        if (appointmentsRes.data?.success && appointmentsRes.data.data) {
          // Check for nested structure with items array (pagination)
          if (appointmentsRes.data.data.appointments && appointmentsRes.data.data.appointments.items) {
            setAppointments(appointmentsRes.data.data.appointments.items);
          } else if (Array.isArray(appointmentsRes.data.data)) {
            setAppointments(appointmentsRes.data.data);
          } else {
            setAppointments([]);
          }
        } else if (Array.isArray(appointmentsRes.data)) {
          setAppointments(appointmentsRes.data);
        }

      } catch (err) {
        console.error('Error fetching form data:', err);
        setDataError('Failed to load form data. Some dropdowns may be empty.');
      } finally {
        setDataLoading(false);
      }
    };

    fetchData();
  }, []); // Empty dependency array - only run once

  // Update form data when initialData changes - only run when initialData actually changes
  const initialDataRef = useRef(initialData);
  useEffect(() => {
    // Only update if initialData has actually changed
    if (JSON.stringify(initialDataRef.current) === JSON.stringify(initialData)) {
      return;
    }
    
    // Store the new initialData reference
    initialDataRef.current = initialData;
    
    setFormData(prev => {
      const newData = { ...prev, ...initialData };
      
      // Format date to YYYY-MM-DD if it's not already in that format
      if (newData.date && newData.date.includes(' ')) {
        // Handle datetime format like "2023-12-15 14:30:00"
        newData.date = newData.date.split(' ')[0];
      }
      
      // Format time to HH:mm if it's not already in that format
      if (newData.time && newData.time.includes(':') && newData.time.length > 5) {
        // Handle time format like "14:30:00"
        newData.time = newData.time.substring(0, 5);
      }
      
      return newData;
    });
  }, [initialData]);

  // Handle form input changes
  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  // Handle form submission
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Validate required fields
    if (!formData.patient_id || !formData.doctor_id || !formData.date || !formData.time) {
      setDataError('Please fill in all required fields (Patient, Doctor, Date, and Time)');
      return;
    }

    // Clear any existing errors
    setDataError(null);

    if (onSubmit) {
      await onSubmit(formData);
    }
  };

  if (dataLoading) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="bg-gradient-to-r from-green-600 to-blue-600 rounded-lg p-6 text-white">
        <h1 className="text-3xl font-bold">{title}</h1>
        <p className="text-green-100 mt-2">{subtitle}</p>
      </div>

      <Card className="shadow-lg">
        <form onSubmit={handleSubmit} className="space-y-6">
          {(error || dataError) && (
            <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-4">
              <p>{error || dataError}</p>
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Patient Selection */}
            <div>
              <label htmlFor="patient_id" className="block text-sm font-semibold text-gray-700 mb-2">
                Patient <span className="text-red-500">*</span>
              </label>
              <select
                id="patient_id"
                name="patient_id"
                value={formData.patient_id}
                onChange={handleInputChange}
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select a patient</option>
                {patients.map(patient => (
                  <option key={patient.ID} value={patient.ID}>
                    {patient.first_name} {patient.last_name} (ID: {patient.ID})
                  </option>
                ))}
              </select>
            </div>

            {/* Doctor Selection */}
            <div>
              <label htmlFor="doctor_id" className="block text-sm font-semibold text-gray-700 mb-2">
                Doctor <span className="text-red-500">*</span>
              </label>
              <select
                id="doctor_id"
                name="doctor_id"
                value={formData.doctor_id}
                onChange={handleInputChange}
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select a doctor</option>
                {doctors.map(doctor => (
                  <option key={doctor.ID} value={doctor.ID}>
                    Dr. {doctor.first_name} {doctor.last_name} - {doctor.specialty}
                  </option>
                ))}
              </select>
            </div>

            {/* Appointment (Optional) */}
            <div>
              <label htmlFor="appointment_id" className="block text-sm font-semibold text-gray-700 mb-2">
                Related Appointment (Optional)
              </label>
              <select
                id="appointment_id"
                name="appointment_id"
                value={formData.appointment_id}
                onChange={handleInputChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select an appointment (optional)</option>
                {appointments.map(appointment => (
                  <option key={appointment.ID} value={appointment.ID}>
                    Appointment #{appointment.ID} - {appointment.appointment_date || appointment.date}
                  </option>
                ))}
              </select>
            </div>

            {/* Visit Date */}
            <div>
              <label htmlFor="date" className="block text-sm font-semibold text-gray-700 mb-2">
                Visit Date <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                id="date"
                name="date"
                value={formData.date}
                onChange={handleInputChange}
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            {/* Visit Time */}
            <div>
              <label htmlFor="time" className="block text-sm font-semibold text-gray-700 mb-2">
                Visit Time <span className="text-red-500">*</span>
              </label>
              <input
                type="time"
                id="time"
                name="time"
                value={formData.time}
                onChange={handleInputChange}
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>

          {/* Complaint */}
          <div>
            <label htmlFor="complaint" className="block text-sm font-semibold text-gray-700 mb-2">
              Patient Complaint
            </label>
            <textarea
              id="complaint"
              name="complaint"
              value={formData.complaint}
              onChange={handleInputChange}
              rows={3}
              placeholder="Describe the patient's complaint or reason for visit..."
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          {/* Medical History */}
          <div>
            <label htmlFor="medical_history" className="block text-sm font-semibold text-gray-700 mb-2">
              Medical History
            </label>
            <textarea
              id="medical_history"
              name="medical_history"
              value={formData.medical_history}
              onChange={handleInputChange}
              rows={3}
              placeholder="Relevant medical history for this visit..."
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          {/* Diagnosis */}
          <div>
            <label htmlFor="diagnosis" className="block text-sm font-semibold text-gray-700 mb-2">
              Diagnosis
            </label>
            <textarea
              id="diagnosis"
              name="diagnosis"
              value={formData.diagnosis}
              onChange={handleInputChange}
              rows={3}
              placeholder="Medical diagnosis..."
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          {/* Treatment */}
          <div>
            <label htmlFor="treatment" className="block text-sm font-semibold text-gray-700 mb-2">
              Treatment Plan
            </label>
            <textarea
              id="treatment"
              name="treatment"
              value={formData.treatment}
              onChange={handleInputChange}
              rows={3}
              placeholder="Treatment plan and recommendations..."
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          {/* Form Actions */}
          <div className="flex justify-end space-x-4 pt-6 border-t border-gray-200">
            <Button
              type="button"
              variant="secondary"
              onClick={() => navigate('/visitations')}
              disabled={loading}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={loading}
              className="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700"
            >
              {loading ? (
                <span className="flex items-center gap-2">
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                  {submitButtonText}...
                </span>
              ) : (
                submitButtonText
              )}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
};

export default VisitForm;
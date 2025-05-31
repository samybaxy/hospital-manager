import React, { useState, useEffect, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { api } from '../../services/apiService';

const VisitForm = ({ 
  initialData = {}, 
  onSubmit, 
  submitButtonText = 'Save Visit',
  title,
  subtitle,
  loading = false,
  error = null,
  isEditMode = false
}) => {
    console.log('VisitForm rendered with initialData:', initialData);
  // Initialize form data with empty strings to avoid null values in form controls
  const [formData, setFormData] = useState(() => {
    const initialFormData = {
      patient_id: '',
      doctor_id: '',
      appointment_id: '',
      date: '',
      time: '',
      medical_history: '',
      diagnosis: '',
      treatment: '',
      complaint: '',
    };
    
    // Handle potential null values in initialData by converting them to empty strings
    if (initialData) {
      Object.entries(initialData).forEach(([key, value]) => {
        initialFormData[key] = value === null ? '' : value;
      });
      
      console.log('Initial form data from props:', initialFormData);
    }
    
    return initialFormData;
  });
  
  const [patients, setPatients] = useState([]);
  const [doctors, setDoctors] = useState([]);
  const [appointments, setAppointments] = useState([]);
  const [dataLoading, setDataLoading] = useState(true);
  const [dataError, setDataError] = useState(null);
  
  const navigate = useNavigate();
  
  // Use refs to prevent unnecessary re-renders
  const isInitialMount = useRef(true);
  const hasFetchedData = useRef(false);

  // Memoize initialData to detect actual changes
  const memoizedInitialData = useMemo(() => 
    JSON.stringify(initialData), [initialData]
  );

  // Load initial data only once
  useEffect(() => {
    if (!isInitialMount.current) return;
    
    console.log('Processing initialData in useEffect:', initialData);
    
    setFormData(prev => {
      // Create a new object with the previous state and initialData
      const newData = { ...prev };
      
      // Process each property from initialData, handling potential null values
      if (initialData) {
        Object.entries(initialData).forEach(([key, value]) => {
          // Convert null values to empty strings to avoid React warnings
          newData[key] = value === null ? '' : value;
        });
      }
      
      // Format date to YYYY-MM-DD if it's not already in that format
      if (newData.date && newData.date.includes(' ')) {
        newData.date = newData.date.split(' ')[0];
      }
      
      // Format time to HH:mm if it's not already in that format
      if (newData.time && newData.time.includes(':') && newData.time.length > 5) {
        newData.time = newData.time.substring(0, 5);
      }
      
      console.log('Updated formData with initialData:', newData);
      return newData;
    });
    
    isInitialMount.current = false;
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [memoizedInitialData]);

  // Fetch required data for dropdowns only once
  useEffect(() => {
    if (hasFetchedData.current) return;
    hasFetchedData.current = true;
    
    const fetchData = async () => {
      setDataLoading(true);
      setDataError(null);
      
      try {
        // If we have patient_id from initial data but no name, fetch patient details
        const patientId = formData.patient_id || initialData.patient_id;
        
        if (patientId && !formData.patient_name) {
          try {
            console.log(`Fetching patient data for ID: ${patientId}`);
            const patientRes = await api.get(`/patients/${patientId}`);
            console.log('Patient Response:', patientRes);
            if (patientRes.data?.success && patientRes.data.data) {
              const patientData = patientRes.data.data;
              // Update the form data with patient information
              setFormData(prev => ({
                ...prev,
                patient_id: patientId,
                patient_name: `${patientData.first_name} ${patientData.last_name}`
              }));
            }
          } catch (err) {
            console.error('Error fetching patient details:', err);
          }
        } else {
          console.log('Patient data already available:', {
            patient_id: formData.patient_id,
            patient_name: formData.patient_name
          });
        }
        
        // In edit mode with a doctor_id, fetch only that specific doctor
        let doctorsRes;
        if (isEditMode && formData.doctor_id && formData.doctor_name) {
          console.log('Edit mode: Using existing doctor data instead of fetching all doctors');
          // Create a response structure that matches the expected format but only includes the current doctor
          doctorsRes = {
            data: {
              success: true,
              data: [{
                ID: formData.doctor_id,
                first_name: formData.doctor_name.split(' ')[0] || '',
                last_name: formData.doctor_name.split(' ').slice(1).join(' ') || '',
                specialty: formData.doctor_specialty || 'Doctor'
              }]
            }
          };
        } else {
          // For new visits, fetch all doctors
          doctorsRes = await api.get('/doctors').catch(() => ({ data: { success: false, data: [] } }));
        }
        
        // Always fetch appointments but filter for valid ones (confirmed & future dates)
        const appointmentsRes = await api.get('/appointments').catch(() => ({ data: { success: false, data: [] } }));
        
        console.log('Doctors Response:', doctorsRes.data);
        console.log('Appointments Response:', appointmentsRes.data);
        
        // Current date for filtering appointments
        const currentDate = new Date().toISOString().split('T')[0]; // Format: YYYY-MM-DD
        
        // No need to set patients array anymore since patient is pre-selected and field is disabled

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
        let allAppointments = [];
        if (appointmentsRes.data?.success && appointmentsRes.data.data) {
          // Check for nested structure with items array (pagination)
          if (appointmentsRes.data.data.appointments && appointmentsRes.data.data.appointments.items) {
            allAppointments = appointmentsRes.data.data.appointments.items;
          } else if (Array.isArray(appointmentsRes.data.data)) {
            allAppointments = appointmentsRes.data.data;
          }
        } else if (Array.isArray(appointmentsRes.data)) {
          allAppointments = appointmentsRes.data;
        }
        
        // Filter appointments to show only confirmed future appointments
        // In edit mode, also include the current appointment if set
        const currentAppointmentId = isEditMode ? formData.appointment_id : null;
        
        const filteredAppointments = allAppointments.filter(appointment => {
          // Always include the current appointment in edit mode
          if (currentAppointmentId && appointment.ID == currentAppointmentId) {
            return true;
          }
          
          // Convert appointment date to YYYY-MM-DD format for comparison
          const appointmentDate = appointment.appointment_date || appointment.date;
          const formattedDate = appointmentDate ? 
            (appointmentDate.includes(' ') ? appointmentDate.split(' ')[0] : appointmentDate) : 
            '';
            
          // Check if appointment is confirmed and in the future
          const isConfirmed = appointment.status === 'confirmed' || appointment.status === 'approved';
          const isFutureDate = formattedDate >= currentDate;
          
          return isConfirmed && isFutureDate;
        });
        
        console.log(`Found ${filteredAppointments.length} valid future confirmed appointments out of ${allAppointments.length} total`);
        setAppointments(filteredAppointments);
      } catch (err) {
        console.error('Error fetching form data:', err);
        setDataError('Failed to load form data. Some dropdowns may be empty.');
      } finally {
        setDataLoading(false);
      }
    };

    fetchData();
  }, []);

  // Handle form input changes - ensure we never set null values
  const handleInputChange = (e) => {
    const { name, value } = e.target;
    // Ensure we never set null values, use empty string instead
    const safeValue = value === null ? '' : value;
    setFormData(prev => ({ ...prev, [name]: safeValue }));
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

    // Create a sanitized version of formData with only the fields that match the table structure
    const sanitizedData = {
      patient_id: formData.patient_id,
      doctor_id: formData.doctor_id,
      appointment_id: formData.appointment_id || null,
      date: formData.date,
      time: formData.time,
      medical_history: formData.medical_history || '',
      diagnosis: formData.diagnosis || '',
      treatment: formData.treatment || '',
      complaint: formData.complaint || ''
    };
    
    console.log('Submitting sanitized data:', sanitizedData);

    if (onSubmit) {
      await onSubmit(sanitizedData);
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
            {/* Patient Selection - Always disabled and populated */}
            <div>
              <label htmlFor="patient_name" className="block text-sm font-semibold text-gray-700 mb-2">
                Patient <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <input
                  type="text"
                  id="patient_name"
                  name="patient_name"
                  value={formData.patient_name || `Patient #${formData.patient_id}` || ''}
                  readOnly
                  disabled
                  required
                  className="w-full px-3 py-2 pl-10 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed focus:outline-none"
                />
                <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                  </svg>
                </div>
                <input 
                  type="hidden" 
                  name="patient_id" 
                  id="patient_id" 
                  value={formData.patient_id || ''} 
                />
              </div>
              <p className="mt-1 text-xs text-gray-500 italic">This field is locked and cannot be changed</p>
            </div>

            {/* Doctor Selection */}
            <div>
              <label htmlFor="doctor_id" className="block text-sm font-semibold text-gray-700 mb-2">
                Doctor <span className="text-red-500">*</span>
                {isEditMode && <span className="ml-2 text-xs text-blue-500">(Locked in edit mode)</span>}
              </label>
              <div className="relative">
                {isEditMode ? (
                  // In edit mode, show a disabled text input instead of a dropdown
                  <input
                    type="text"
                    id="doctor_name"
                    name="doctor_name"
                    value={formData.doctor_name || `Doctor #${formData.doctor_id}` || ''}
                    readOnly
                    disabled
                    required
                    className="w-full px-3 py-2 pl-10 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed focus:outline-none"
                  />
                ) : (
                  // In add mode, show the dropdown
                  <select
                    id="doctor_id"
                    name="doctor_id"
                    value={formData.doctor_id || ''}
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
                )}
                {isEditMode && (
                  <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                    </svg>
                  </div>
                )}
                <input 
                  type="hidden" 
                  name="doctor_id" 
                  id="doctor_id" 
                  value={formData.doctor_id || ''} 
                />
              </div>
              {isEditMode && (
                <p className="mt-1 text-xs text-gray-500 italic">Doctor cannot be changed in edit mode</p>
              )}
            </div>

            {/* Appointment (Optional) */}
            <div>
              <label htmlFor="appointment_id" className="block text-sm font-semibold text-gray-700 mb-2">
                Related Appointment (Optional)
                {appointments.length === 0 && (
                  <span className="ml-2 text-xs text-gray-500">(No eligible appointments available)</span>
                )}
              </label>
              <select
                id="appointment_id"
                name="appointment_id"
                value={formData.appointment_id || ''}
                onChange={handleInputChange}
                disabled={appointments.length === 0}
                className={`w-full px-3 py-2 border border-gray-300 rounded-md ${
                  appointments.length === 0 
                    ? 'bg-gray-100 cursor-not-allowed' 
                    : 'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent'
                }`}
              >
                <option value="">
                  {appointments.length > 0 
                    ? 'Select an appointment (optional)' 
                    : 'No confirmed future appointments available'
                  }
                </option>
                {appointments.map(appointment => (
                  <option key={appointment.ID} value={appointment.ID}>
                    Appointment #{appointment.ID} - {appointment.appointment_date || appointment.date}
                    {appointment.appointment_time && ` at ${appointment.appointment_time.substring(0, 5)}`}
                  </option>
                ))}
              </select>
              {appointments.length === 0 && (
                <p className="mt-1 text-xs text-gray-500 italic">
                  Only confirmed future appointments can be linked to a visit
                </p>
              )}
            </div>

            {/* Visit Date */}
            <div>
              <label htmlFor="date" className="block text-sm font-semibold text-gray-700 mb-2">
                Visit Date <span className="text-red-500">*</span>
              </label>
              <div className="relative" onClick={() => document.getElementById('date').showPicker()}>
                <input
                  type="date"
                  id="date"
                  name="date"
                  value={formData.date || ''}
                  onChange={handleInputChange}
                  required
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
                />
              </div>
            </div>

            {/* Visit Time */}
            <div>
              <label htmlFor="time" className="block text-sm font-semibold text-gray-700 mb-2">
                Visit Time <span className="text-red-500">*</span>
              </label>
              <div className="relative" onClick={() => document.getElementById('time').showPicker()}>
                <input
                  type="time"
                  id="time"
                  name="time"
                  value={formData.time || ''}
                  onChange={handleInputChange}
                  required
                  className="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
                />
                <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
              </div>
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
              value={formData.complaint || ''}
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
              value={formData.medical_history || ''}
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
              value={formData.diagnosis || ''}
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
              value={formData.treatment || ''}
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
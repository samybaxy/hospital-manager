import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { useUserAccess } from '../../hooks/useUserAccess';
import { api } from '../../services/apiService';

const AddVisitForm = () => {
  const [formData, setFormData] = useState({
    patient_id: '',
    doctor_id: '',
    appointment_id: '',
    date: '',
    time: '',
    medical_history: '',
    diagnosis: '',
    treatment: '',
    complaint: ''
  });
  
  const [patients, setPatients] = useState([]);
  const [doctors, setDoctors] = useState([]);
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(false);
  const [submitLoading, setSubmitLoading] = useState(false);
  const [error, setError] = useState(null);
  
  const { hasAccess, role } = useUserAccess();
  const { patientId } = useParams();
  const navigate = useNavigate();

  // Pre-fill patient ID if coming from patients page
  useEffect(() => {
    if (patientId) {
      setFormData(prev => ({ ...prev, patient_id: patientId }));
    }
  }, [patientId]);

  // Fetch required data for dropdowns
  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const [patientsRes, doctorsRes, appointmentsRes] = await Promise.all([
          api.get('/patients'),
          api.get('/doctors'),
          api.get('/appointments')
        ]);

        if (patientsRes.data?.success) {
          setPatients(patientsRes.data.data || []);
        }
        if (doctorsRes.data?.success) {
          setDoctors(doctorsRes.data.data || []);
        }
        if (appointmentsRes.data?.success) {
          setAppointments(appointmentsRes.data.data || []);
        }
      } catch (err) {
        console.error('Error fetching form data:', err);
        setError('Failed to load form data');
      } finally {
        setLoading(false);
      }
    };

    if (hasAccess('visitations')) {
      fetchData();
    }
  }, [hasAccess]);

  // Handle form input changes
  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  // Handle form submission
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!hasAccess('visitations')) {
      setError('You do not have permission to add visits');
      return;
    }

    setSubmitLoading(true);
    setError(null);

    try {
      // Validate required fields
      if (!formData.patient_id || !formData.doctor_id || !formData.date || !formData.time) {
        throw new Error('Please fill in all required fields');
      }

      const response = await api.post('/visitations', formData);

      if (response.data?.success) {
        // Redirect back to visitations page with success message
        navigate('/visitations?success=Visit added successfully');
      } else {
        throw new Error(response.data?.message || 'Failed to add visit');
      }
    } catch (err) {
      console.error('Error adding visit:', err);
      setError(err.response?.data?.message || err.message || 'Failed to add visit');
    } finally {
      setSubmitLoading(false);
    }
  };

  // Check access permissions
  if (!hasAccess('visitations') || (role !== 'administrator' && role !== 'doctor' && role !== 'desk_officer')) {
    return (
      <div className="max-w-2xl mx-auto py-8">
        <Card>
          <div className="text-center py-8">
            <p className="text-red-600">You do not have permission to add visits.</p>
            <Button variant="secondary" className="mt-4" onClick={() => navigate('/visitations')}>
              Back to Visitations
            </Button>
          </div>
        </Card>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="bg-gradient-to-r from-green-600 to-blue-600 rounded-lg p-6 text-white">
        <h1 className="text-3xl font-bold">Add New Visit</h1>
        <p className="text-green-100 mt-2">Record a new patient visit and consultation details</p>
      </div>

      <Card className="shadow-lg">
        <form onSubmit={handleSubmit} className="space-y-6">
          {error && (
            <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-4">
              <p>{error}</p>
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
                    Appointment #{appointment.ID} - {appointment.appointment_date}
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
              disabled={submitLoading}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={submitLoading}
              className="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700"
            >
              {submitLoading ? (
                <span className="flex items-center gap-2">
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                  Adding Visit...
                </span>
              ) : (
                'Add Visit'
              )}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
};

export default AddVisitForm;
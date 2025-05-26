import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { useAccess } from '../../utils/accessControl';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../services/apiService';

const EditVisitForm = () => {
  const { visitId } = useParams();
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
  const [loading, setLoading] = useState(true);
  const [submitLoading, setSubmitLoading] = useState(false);
  const [error, setError] = useState(null);
  const [visit, setVisit] = useState(null);
  
  const { hasAccess, role } = useAccess();
  const { user } = useAuth();
  const navigate = useNavigate();

  // Fetch visit data and form options
  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);

        const [visitRes, patientsRes, doctorsRes, appointmentsRes] = await Promise.all([
          api.get(`/visitations/${visitId}`),
          api.get('/patients'),
          api.get('/doctors'),
          api.get('/appointments')
        ]);

        if (visitRes.data?.success) {
          const visitData = visitRes.data.data;
          setVisit(visitData);
          setFormData({
            patient_id: visitData.patient_id || '',
            doctor_id: visitData.doctor_id || '',
            appointment_id: visitData.appointment_id || '',
            date: visitData.date || '',
            time: visitData.time || '',
            medical_history: visitData.medical_history || '',
            diagnosis: visitData.diagnosis || '',
            treatment: visitData.treatment || '',
            complaint: visitData.complaint || ''
          });
        } else {
          throw new Error('Visit not found');
        }

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
        console.error('Error fetching data:', err);
        setError(err.response?.data?.message || err.message || 'Failed to load visit data');
      } finally {
        setLoading(false);
      }
    };

    if (hasAccess('visitations') && visitId) {
      fetchData();
    } else {
      setLoading(false);
      setError('You do not have permission to edit visits');
    }
  }, [hasAccess, visitId]);

  // Check if user can edit this visit
  const canEditVisit = (visitData) => {
    if (!hasAccess('visitations') || !visitData) return false;
    
    // Admins can edit any visit
    if (role === 'administrator') return true;
    
    // Doctors can edit visits they conducted
    if (role === 'doctor' && visitData.doctor_id === user?.ID) return true;
    
    // Desk officers can edit any visit
    if (role === 'desk_officer') return true;
    
    return false;
  };

  // Handle form input changes
  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  // Handle form submission
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!canEditVisit(visit)) {
      setError('You do not have permission to edit this visit');
      return;
    }

    setSubmitLoading(true);
    setError(null);

    try {
      // Validate required fields
      if (!formData.patient_id || !formData.doctor_id || !formData.date || !formData.time) {
        throw new Error('Please fill in all required fields');
      }

      const response = await api.put(`/visitations/${visitId}`, formData);

      if (response.data?.success) {
        // Redirect back to visitations page with success message
        navigate('/visitations?success=Visit updated successfully');
      } else {
        throw new Error(response.data?.message || 'Failed to update visit');
      }
    } catch (err) {
      console.error('Error updating visit:', err);
      setError(err.response?.data?.message || err.message || 'Failed to update visit');
    } finally {
      setSubmitLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  if (error || !visit) {
    return (
      <div className="max-w-2xl mx-auto py-8">
        <Card>
          <div className="text-center py-8">
            <p className="text-red-600">{error || 'Visit not found'}</p>
            <Button variant="secondary" className="mt-4" onClick={() => navigate('/visitations')}>
              Back to Visitations
            </Button>
          </div>
        </Card>
      </div>
    );
  }

  if (!canEditVisit(visit)) {
    return (
      <div className="max-w-2xl mx-auto py-8">
        <Card>
          <div className="text-center py-8">
            <p className="text-red-600">You do not have permission to edit this visit.</p>
            <Button variant="secondary" className="mt-4" onClick={() => navigate('/visitations')}>
              Back to Visitations
            </Button>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="bg-gradient-to-r from-purple-600 to-blue-600 rounded-lg p-6 text-white">
        <h1 className="text-3xl font-bold">Edit Visit #{visit.ID}</h1>
        <p className="text-purple-100 mt-2">Update visit details and consultation information</p>
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
              className="bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700"
            >
              {submitLoading ? (
                <span className="flex items-center gap-2">
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                  Updating Visit...
                </span>
              ) : (
                'Update Visit'
              )}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
};

export default EditVisitForm;
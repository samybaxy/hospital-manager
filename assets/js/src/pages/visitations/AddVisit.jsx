import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import VisitForm from './VisitForm';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { useUserAccess } from '../../hooks/useUserAccess';
import { api } from '../../services/apiService';

const AddVisit = () => {
  const [submitLoading, setSubmitLoading] = useState(false);
  const [error, setError] = useState(null);
  const [initialData, setInitialData] = useState(null); // Changed to null initially
  const [loading, setLoading] = useState(true);
  
  const { hasAccess, role } = useUserAccess();
  const { patientId } = useParams();
  const navigate = useNavigate();

  // Fetch patient details when component mounts
  useEffect(() => {
    const fetchPatientData = async () => {
      if (!patientId) {
        navigate('/patients?error=Please select a patient first');
        return;
      }
      
      try {
        setLoading(true);
        console.log('Fetching patient data for ID:', patientId);
        const response = await api.get(`/patients/${patientId}`);
        
        if (response.data?.success) {
          const patientData = response.data.data;
          const patientFormData = {
            patient_id: patientData.ID,
            patient_name: `${patientData.first_name} ${patientData.last_name}`
          };
          console.log('Successfully loaded patient data:', patientFormData);
          setInitialData(patientFormData);
        } else {
          setError('Failed to load patient data');
          setInitialData({ patient_id: patientId });
        }
      } catch (err) {
        console.error('Error fetching patient data:', err);
        setError('Failed to load patient data');
        setInitialData({ patient_id: patientId });
      } finally {
        setLoading(false);
      }
    };
    
    fetchPatientData();
  }, [patientId, navigate]);

  // Handle form submission
  const handleSubmit = async (formData) => {
    if (!hasAccess('visitations')) {
      setError('You do not have permission to add visits');
      return;
    }

    setSubmitLoading(true);
    setError(null);

    try {
      const response = await api.post('/visitations', formData);

      if (response.data?.success) {
        // Get the ID of the newly created visit if available, or use a generic message
        const newVisitId = response.data.data?.ID || '';
        const patientName = initialData?.patient_name || `Patient #${initialData.patient_id}`;
        const successMessage = newVisitId 
          ? `Visit #${newVisitId} for ${patientName} has been added successfully` 
          : `New visit for ${patientName} has been added successfully`;
          
        // Redirect back to visitations page with success message
        navigate(`/visitations?success=${encodeURIComponent(successMessage)}`);
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
  if (!hasAccess('visitations') || (role !== 'administrator' && role !== 'doctor' && role !== 'developer')) {
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

  // Show loading indicator while patient data is being fetched
  if (loading || initialData === null) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
        <span className="ml-3 text-gray-700">Loading patient data...</span>
      </div>
    );
  }

  return (
    <VisitForm
      initialData={initialData}
      onSubmit={handleSubmit}
      submitButtonText="Add Visit"
      title="Add New Visit"
      subtitle="Record a new patient visit and consultation details"
      loading={submitLoading}
      error={error}
    />
  );
};

export default AddVisit;
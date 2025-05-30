import React, { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import VisitForm from './VisitForm';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { useUserAccess } from '../../hooks/useUserAccess';
import { api } from '../../services/apiService';

const AddVisit = () => {
  const [submitLoading, setSubmitLoading] = useState(false);
  const [error, setError] = useState(null);
  
  const { hasAccess, role } = useUserAccess();
  const { patientId } = useParams();
  const navigate = useNavigate();

  // Initial form data - include patient ID if coming from patients page
  const initialData = patientId ? { patient_id: patientId } : {};

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
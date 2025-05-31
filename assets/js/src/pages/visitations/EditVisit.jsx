import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import VisitForm from './VisitForm';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { useUserAccess } from '../../hooks/useUserAccess';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../services/apiService';

const EditVisit = () => {
  const { visitId } = useParams();
  const [visitData, setVisitData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitLoading, setSubmitLoading] = useState(false);
  const [error, setError] = useState(null);
  
  const { hasAccess, role } = useUserAccess();
  const { user } = useAuth();
  const navigate = useNavigate();

  // Fetch visit data only once when component mounts
  useEffect(() => {
    // Create a flag to track if component is mounted
    let isMounted = true;
    
    const fetchVisitData = async () => {
      if (!hasAccess('visitations') || !visitId) {
        setLoading(false);
        setError('You do not have permission to edit visits');
        return;
      }

      try {
        setLoading(true);
        setError(null);
        console.log(`Fetching visit data for ID: ${visitId}`);

        const visitRes = await api.get(`/visitations/${visitId}`);

        // Only update state if component is still mounted
        if (!isMounted) return;

        if (visitRes.data?.success) {
          const visitData = visitRes.data.data;
          console.log('Visit data loaded:', visitData);
          
          // Fetch associated patient data to ensure we have patient_name
          if (visitData.patient_id) {
            try {
              console.log(`Fetching patient data for ID: ${visitData.patient_id}`);
              const patientRes = await api.get(`/patients/${visitData.patient_id}`);
              if (patientRes.data?.success) {
                const patientData = patientRes.data.data;
                visitData.patient_name = `${patientData.first_name} ${patientData.last_name}`;
                console.log('Patient name set to:', visitData.patient_name);
              }
            } catch (err) {
              console.error('Error fetching patient data:', err);
              // If we can't fetch patient name, use a fallback format
              visitData.patient_name = `Patient #${visitData.patient_id}`;
            }
          }
          
          setVisitData(visitData);
        } else {
          throw new Error('Visit not found');
        }
      } catch (err) {
        // Only update state if component is still mounted
        if (!isMounted) return;
        console.error('Error fetching visit data:', err);
        setError(err.response?.data?.message || err.message || 'Failed to load visit data');
      } finally {
        // Only update state if component is still mounted
        if (isMounted) {
          setLoading(false);
        }
      }
    };

    fetchVisitData();
    
    // Cleanup function to set flag when component unmounts
    return () => {
      isMounted = false;
    };
    
    // hasAccess is triggering re-renders, so we'll exclude it from dependencies
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visitId]);

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

  // Handle form submission
  const handleSubmit = async (formData) => {
    if (!canEditVisit(visitData)) {
      setError('You do not have permission to edit this visit');
      return;
    }

    setSubmitLoading(true);
    setError(null);

    try {
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

  if (error || !visitData) {
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

  if (!canEditVisit(visitData)) {
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
    <VisitForm
      initialData={visitData}
      onSubmit={handleSubmit}
      submitButtonText="Update Visit"
      title={`Edit Visit #${visitData.ID}`}
      subtitle="Update visit details and consultation information"
      loading={submitLoading}
      error={error}
      isEditMode={true}
    />
  );
};

export default EditVisit;
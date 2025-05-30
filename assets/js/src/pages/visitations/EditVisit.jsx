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

  // Fetch visit data only
  useEffect(() => {
    const fetchVisitData = async () => {
      if (!hasAccess('visitations') || !visitId) {
        setLoading(false);
        setError('You do not have permission to edit visits');
        return;
      }

      try {
        setLoading(true);
        setError(null);

        const visitRes = await api.get(`/visitations/${visitId}`);

        if (visitRes.data?.success) {
          const visitData = visitRes.data.data;
          setVisitData(visitData);
        } else {
          throw new Error('Visit not found');
        }
      } catch (err) {
        console.error('Error fetching visit data:', err);
        setError(err.response?.data?.message || err.message || 'Failed to load visit data');
      } finally {
        setLoading(false);
      }
    };

    fetchVisitData();
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
    />
  );
};

export default EditVisit;
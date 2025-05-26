import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import Card from './Card';
import Button from './Button';
import { useAccess } from '../utils/accessControl';
import { useAuth } from '../context/AuthContext';
import { api } from '../services/apiService';

const ViewVisitDetails = () => {
  const { visitId } = useParams();
  const [visit, setVisit] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  const { hasAccess, role } = useAccess();
  const { user } = useAuth();
  const navigate = useNavigate();

  // Fetch visit details
  useEffect(() => {
    const fetchVisit = async () => {
      try {
        setLoading(true);
        setError(null);

        const response = await api.get(`/visitations/${visitId}`);

        if (response.data?.success) {
          setVisit(response.data.data);
        } else {
          throw new Error('Visit not found');
        }
      } catch (err) {
        console.error('Error fetching visit:', err);
        setError(err.response?.data?.message || err.message || 'Failed to load visit details');
      } finally {
        setLoading(false);
      }
    };

    if (hasAccess('visitations') && visitId) {
      fetchVisit();
    } else {
      setLoading(false);
      setError('You do not have permission to view visits');
    }
  }, [hasAccess, visitId]);

  // Check if user can view this visit
  const canViewVisit = (visitData) => {
    if (!hasAccess('visitations') || !visitData) return false;
    
    // Admins can view any visit
    if (role === 'administrator') return true;
    
    // Doctors can view visits they conducted
    if (role === 'doctor' && visitData.doctor_id === user?.ID) return true;
    
    // Desk officers can view any visit
    if (role === 'desk_officer') return true;
    
    // Patients can only view their own visits
    if (role === 'patient' && visitData.patient_id === user?.ID) return true;
    
    return false;
  };

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

  // Format date and time for display
  const formatDateTime = (date, time) => {
    if (!date) return '-';
    
    try {
      const dateObj = new Date(date);
      const formattedDate = dateObj.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
      
      if (time) {
        const timeObj = new Date(`1970-01-01T${time}`);
        const formattedTime = timeObj.toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: true
        });
        return `${formattedDate} at ${formattedTime}`;
      }
      
      return formattedDate;
    } catch (err) {
      return date;
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  if (error || !visit || !canViewVisit(visit)) {
    return (
      <div className="max-w-2xl mx-auto py-8">
        <Card>
          <div className="text-center py-8">
            <p className="text-red-600">{error || 'You do not have permission to view this visit'}</p>
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
      <div className="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg p-6 text-white">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-3xl font-bold">Visit Details #{visit.ID}</h1>
            <p className="text-indigo-100 mt-2">Complete information about this patient visit</p>
          </div>
          <div className="flex space-x-3">
            {canEditVisit(visit) && (
              <Button
                variant="secondary"
                onClick={() => navigate(`/visitations/${visit.ID}/edit`)}
                className="bg-white/20 text-white border-white/30 hover:bg-white/30"
              >
                Edit Visit
              </Button>
            )}
            <Button
              variant="secondary"
              onClick={() => navigate('/visitations')}
              className="bg-white/20 text-white border-white/30 hover:bg-white/30"
            >
              Back to List
            </Button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Basic Information */}
        <Card className="lg:col-span-2 shadow-lg">
          <div className="border-b border-gray-200 pb-4 mb-6">
            <h2 className="text-xl font-bold text-gray-900">Visit Information</h2>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-semibold text-gray-600 mb-1">Patient Name</label>
              <p className="text-gray-900 font-medium">
                {visit.patient_name || `Patient #${visit.patient_id}`}
              </p>
            </div>
            
            <div>
              <label className="block text-sm font-semibold text-gray-600 mb-1">Doctor Name</label>
              <p className="text-gray-900 font-medium">
                {visit.doctor_name || `Doctor #${visit.doctor_id}`}
              </p>
            </div>
            
            <div>
              <label className="block text-sm font-semibold text-gray-600 mb-1">Visit Date & Time</label>
              <p className="text-gray-900 font-medium">
                {formatDateTime(visit.date, visit.time)}
              </p>
            </div>
            
            {visit.appointment_id && (
              <div>
                <label className="block text-sm font-semibold text-gray-600 mb-1">Related Appointment</label>
                <p className="text-gray-900 font-medium">
                  Appointment #{visit.appointment_id}
                </p>
              </div>
            )}
          </div>
        </Card>

        {/* Quick Actions */}
        <Card className="shadow-lg">
          <div className="border-b border-gray-200 pb-4 mb-6">
            <h2 className="text-xl font-bold text-gray-900">Quick Actions</h2>
          </div>
          
          <div className="space-y-3">
            {canEditVisit(visit) && (
              <Button
                variant="primary"
                className="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700"
                onClick={() => navigate(`/visitations/${visit.ID}/edit`)}
              >
                Edit Visit
              </Button>
            )}
            
            <Button
              variant="secondary"
              className="w-full"
              onClick={() => navigate(`/patients/${visit.patient_id}`)}
            >
              View Patient Profile
            </Button>
            
            <Button
              variant="secondary"
              className="w-full"
              onClick={() => navigate(`/doctors/${visit.doctor_id}`)}
            >
              View Doctor Profile
            </Button>
            
            {visit.appointment_id && (
              <Button
                variant="secondary"
                className="w-full"
                onClick={() => navigate(`/appointments/${visit.appointment_id}`)}
              >
                View Related Appointment
              </Button>
            )}
          </div>
        </Card>
      </div>

      {/* Medical Details */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Patient Complaint */}
        <Card className="shadow-lg">
          <div className="border-b border-gray-200 pb-4 mb-6">
            <h2 className="text-xl font-bold text-gray-900">Patient Complaint</h2>
          </div>
          <div className="prose prose-sm max-w-none">
            {visit.complaint ? (
              <p className="text-gray-700 whitespace-pre-wrap">{visit.complaint}</p>
            ) : (
              <p className="text-gray-500 italic">No complaint recorded</p>
            )}
          </div>
        </Card>

        {/* Medical History */}
        <Card className="shadow-lg">
          <div className="border-b border-gray-200 pb-4 mb-6">
            <h2 className="text-xl font-bold text-gray-900">Medical History</h2>
          </div>
          <div className="prose prose-sm max-w-none">
            {visit.medical_history ? (
              <p className="text-gray-700 whitespace-pre-wrap">{visit.medical_history}</p>
            ) : (
              <p className="text-gray-500 italic">No medical history recorded</p>
            )}
          </div>
        </Card>

        {/* Diagnosis */}
        <Card className="shadow-lg">
          <div className="border-b border-gray-200 pb-4 mb-6">
            <h2 className="text-xl font-bold text-gray-900">Diagnosis</h2>
          </div>
          <div className="prose prose-sm max-w-none">
            {visit.diagnosis ? (
              <p className="text-gray-700 whitespace-pre-wrap">{visit.diagnosis}</p>
            ) : (
              <p className="text-gray-500 italic">No diagnosis recorded</p>
            )}
          </div>
        </Card>

        {/* Treatment Plan */}
        <Card className="shadow-lg">
          <div className="border-b border-gray-200 pb-4 mb-6">
            <h2 className="text-xl font-bold text-gray-900">Treatment Plan</h2>
          </div>
          <div className="prose prose-sm max-w-none">
            {visit.treatment ? (
              <p className="text-gray-700 whitespace-pre-wrap">{visit.treatment}</p>
            ) : (
              <p className="text-gray-500 italic">No treatment plan recorded</p>
            )}
          </div>
        </Card>
      </div>

      {/* Visit Metadata */}
      <Card className="shadow-lg">
        <div className="border-b border-gray-200 pb-4 mb-6">
          <h2 className="text-xl font-bold text-gray-900">Visit Metadata</h2>
        </div>
        
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
          <div>
            <label className="block text-sm font-semibold text-gray-600 mb-1">Created</label>
            <p className="text-gray-700">
              {visit.created_at ? new Date(visit.created_at).toLocaleString() : '-'}
            </p>
          </div>
          
          <div>
            <label className="block text-sm font-semibold text-gray-600 mb-1">Last Updated</label>
            <p className="text-gray-700">
              {visit.updated_at ? new Date(visit.updated_at).toLocaleString() : '-'}
            </p>
          </div>
          
          <div>
            <label className="block text-sm font-semibold text-gray-600 mb-1">Visit ID</label>
            <p className="text-gray-700 font-mono">#{visit.ID}</p>
          </div>
        </div>
      </Card>
    </div>
  );
};

export default ViewVisitDetails;

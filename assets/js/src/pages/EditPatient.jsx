import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import PatientForm from './PatientForm';
import { api } from '../services/apiService';
import Button from '../components/Button';

const EditPatient = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [patient, setPatient] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchPatient = async () => {
      try {
        setLoading(true);
        const response = await api.get(`/patients/${id}`);
        
        // Check for the structure of the response and extract the patient data properly
        if (response.data && response.data.data) {
          // If the API returns nested data structure
          setPatient(response.data.data);
        } else if (response.data) {
          // If the API returns flat data structure
          setPatient(response.data);
        }

        // For debugging
      } catch (err) {
        console.error('Error fetching patient details:', err);
        const errorMessage = err.response?.data?.message || 
                            err.response?.statusText || 
                            'Failed to load patient details. The patient may not exist or you may not have permission to edit it.';
        setError(errorMessage);
        
        // Log additional details for debugging
        if (err.response) {
          console.log('API Error Response:', {
            status: err.response.status,
            headers: err.response.headers,
            data: err.response.data
          });
        }
      } finally {
        setLoading(false);
      }
    };
    
    fetchPatient();
  }, [id]);

  if (loading) {
    return (
      <div className="flex justify-center p-12">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="mt-8">
        <div className="bg-red-50 p-4 rounded-md border border-red-200 text-red-700">
          {error}
        </div>
        <div className="mt-4">
          <Link to="/patients">
            <Button variant="secondary">Return to Patients</Button>
          </Link>
        </div>
      </div>
    );
  }

  if (!patient) {
    return (
      <div className="mt-8">
        <div className="text-center p-8">
          <p className="text-gray-600">Patient not found</p>
        </div>
        <div className="mt-4">
          <Link to="/patients">
            <Button variant="secondary">Return to Patients</Button>
          </Link>
        </div>
      </div>
    );
  }

  return <PatientForm patient={patient} isEditing={true} />;
};

export default EditPatient;

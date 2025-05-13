import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import Card from '../components/Card';
import Button from '../components/Button';
import { api } from '../services/apiClient';

const PatientDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [patient, setPatient] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('profile');

  useEffect(() => {
    const fetchPatient = async () => {
      try {
        setLoading(true);
        const response = await api.get(`/patients/${id}`);
        
        if (response.data) {
          setPatient(response.data);
        }
      } catch (err) {
        console.error('Error fetching patient details:', err);
        setError('Failed to load patient details. The patient may not exist or you may not have permission to view it.');
      } finally {
        setLoading(false);
      }
    };
    
    fetchPatient();
  }, [id]);

  const handleDelete = async () => {
    if (!window.confirm('Are you sure you want to delete this patient? This action cannot be undone.')) {
      return;
    }

    try {
      setLoading(true);
      await api.delete(`/patients/${id}`);
      navigate('/patients', { replace: true });
    } catch (err) {
      console.error('Error deleting patient:', err);
      setError('Failed to delete patient. Please try again.');
      setLoading(false);
    }
  };

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
        <Card>
          <div className="bg-red-50 p-4 rounded-md border border-red-200 text-red-700">
            {error}
          </div>
          <div className="mt-4">
            <Link to="/patients">
              <Button variant="secondary">Return to Patients</Button>
            </Link>
          </div>
        </Card>
      </div>
    );
  }

  if (!patient) {
    return (
      <div className="mt-8">
        <Card>
          <div className="text-center p-8">
            <p className="text-gray-600">Patient not found</p>
          </div>
          <div className="mt-4">
            <Link to="/patients">
              <Button variant="secondary">Return to Patients</Button>
            </Link>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Patient Details</h1>
          <p className="text-gray-600">
            Patient ID: {patient.patient_id || '-'}
          </p>
        </div>
        <div className="flex gap-2 mt-2 md:mt-0">
          <Link to={`/patients/${id}/edit`}>
            <Button variant="primary">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
              </svg>
              Edit Patient
            </Button>
          </Link>
          <Button variant="danger" onClick={handleDelete}>
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
            Delete
          </Button>
        </div>
      </div>

      {/* Tabs Navigation */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex">
          <button
            className={`py-2 px-4 border-b-2 font-medium text-sm ${
              activeTab === 'profile'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
            onClick={() => setActiveTab('profile')}
          >
            Profile
          </button>
          <button
            className={`py-2 px-4 border-b-2 font-medium text-sm ${
              activeTab === 'medical'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
            onClick={() => setActiveTab('medical')}
          >
            Medical History
          </button>
          <button
            className={`py-2 px-4 border-b-2 font-medium text-sm ${
              activeTab === 'visits'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
            onClick={() => setActiveTab('visits')}
          >
            Visits
          </button>
          <button
            className={`py-2 px-4 border-b-2 font-medium text-sm ${
              activeTab === 'billing'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
            onClick={() => setActiveTab('billing')}
          >
            Billing
          </button>
        </nav>
      </div>

      {/* Profile Tab */}
      {activeTab === 'profile' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card title="Personal Information">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Full Name</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.first_name} {patient.last_name}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Gender</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.gender || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Date of Birth</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.date_of_birth || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Age</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.age ? `${patient.age} years` : '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Blood Group</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.blood_group || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Marital Status</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.marital_status || '-'}</td>
                </tr>
              </tbody>
            </table>
          </Card>

          <Card title="Contact Information">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Phone</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.phone || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Email</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.email || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Address</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.address || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">City</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.city || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">State</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.state || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Postal Code</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.postal_code || '-'}</td>
                </tr>
              </tbody>
            </table>
          </Card>

          <Card title="Emergency Contact">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Name</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.emergency_contact_name || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Relationship</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.emergency_contact_relationship || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Phone</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.emergency_contact_phone || '-'}</td>
                </tr>
              </tbody>
            </table>
          </Card>

          <Card title="Registration Information">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Status</td>
                  <td className="px-4 py-2 text-sm">
                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                      patient.status === 'active' 
                        ? 'bg-green-100 text-green-800' 
                        : patient.status === 'inactive' 
                        ? 'bg-gray-100 text-gray-800'
                        : 'bg-yellow-100 text-yellow-800'
                    }`}>
                      {patient.status || 'unknown'}
                    </span>
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Registration Date</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.registration_date || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Last Visit</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.last_visit_date || '-'}</td>
                </tr>
              </tbody>
            </table>
          </Card>
        </div>
      )}

      {/* Medical History Tab */}
      {activeTab === 'medical' && (
        <Card title="Medical History">
          <div className="py-8 text-center text-gray-500">
            Medical history will be implemented in the next phase
          </div>
        </Card>
      )}

      {/* Visits Tab */}
      {activeTab === 'visits' && (
        <Card title="Visit History">
          <div className="py-8 text-center text-gray-500">
            Visit history will be implemented in the next phase
          </div>
        </Card>
      )}

      {/* Billing Tab */}
      {activeTab === 'billing' && (
        <Card title="Billing Information">
          <div className="py-8 text-center text-gray-500">
            Billing information will be implemented in the next phase
          </div>
        </Card>
      )}

      <div className="mt-4">
        <Link to="/patients">
          <Button variant="secondary">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clipRule="evenodd" />
            </svg>
            Back to Patients
          </Button>
        </Link>
      </div>
    </div>
  );
};

export default PatientDetails;

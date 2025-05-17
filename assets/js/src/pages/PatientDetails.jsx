import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import Card from '../components/Card';
import Button from '../components/Button';
import { api } from '../services/apiService';

// CSS utility for line clamping
const lineClampStyle = {
  display: '-webkit-box',
  WebkitLineClamp: '2',
  WebkitBoxOrient: 'vertical',
  overflow: 'hidden'
};

// CSS for popup animation
const popupOverlayStyle = {
  animation: 'fadeIn 0.3s ease-out',
};

const popupCardStyle = {
  animation: 'scaleIn 0.3s ease-out',
};

const PatientDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [patient, setPatient] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('profile');
  const [visitations, setVisitations] = useState([]);
  const [visitionsLoading, setVisitationsLoading] = useState(false);
  const [expandedHistory, setExpandedHistory] = useState({});
  const [popupContent, setPopupContent] = useState(null);
  const [timeRemaining, setTimeRemaining] = useState(20);
  const [timerId, setTimerId] = useState(null);
  const popupRef = useRef(null);

  // Function to open the popup with the full text
  const openPopup = useCallback((content, visitId) => {
    setPopupContent({ content, visitId });
    setTimeRemaining(20);
    
    // Start the countdown
    const timer = setInterval(() => {
      setTimeRemaining(prev => {
        if (prev <= 1) {
          clearInterval(timer);
          setPopupContent(null);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
    
    setTimerId(timer);
  }, []);
  
  // Function to close the popup
  const closePopup = useCallback(() => {
    if (timerId) {
      clearInterval(timerId);
    }
    setPopupContent(null);
  }, [timerId]);
  
  // Handle clicks outside the popup
  const handleOutsideClick = useCallback((e) => {
    if (popupRef.current && !popupRef.current.contains(e.target)) {
      closePopup();
    }
  }, [closePopup]);
  
  // Add event listener for clicks outside when popup is open
  useEffect(() => {
    if (popupContent) {
      document.addEventListener('mousedown', handleOutsideClick);
    }
    return () => {
      document.removeEventListener('mousedown', handleOutsideClick);
    };
  }, [popupContent, handleOutsideClick]);
  
  // Clean up timer on unmount
  useEffect(() => {
    return () => {
      if (timerId) {
        clearInterval(timerId);
      }
    };
  }, [timerId]);

  // Function to toggle expanded text with timeout (keeping this for compatibility)
  const toggleExpandText = useCallback((id) => {
    setExpandedHistory((prev) => {
      const newState = { ...prev, [id]: !prev[id] };
      return newState;
    });
  }, []);

  // Function to fetch patient visitations when medical tab is clicked
  const fetchVisitations = useCallback(async () => {
    if (!id) return;
    
    try {
      setVisitationsLoading(true);
      const response = await api.get(`/patients/${id}/visitations`);
      
      if (response.data && response.data.data) {
        setVisitations(response.data.data);
      } else if (response.data) {
        setVisitations(response.data);
      } else {
        setVisitations([]);
      }
    } catch (err) {
      console.error('Error fetching visitations:', err);
      setError('Failed to load visitation history');
    } finally {
      setVisitationsLoading(false);
    }
  }, [id]);

  // Effect to fetch visitations when tab changes to medical
  useEffect(() => {
    if (activeTab === 'medical' && visitations.length === 0) {
      fetchVisitations();
    }
  }, [activeTab, fetchVisitations, visitations.length]);

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
  
  // Parse bio_data if it's a JSON string
  if (patient && patient.bio_data && typeof patient.bio_data === 'string') {
    try {
      patient.bio_data = JSON.parse(patient.bio_data);
    } catch (e) {
      console.error('Failed to parse bio_data:', e);
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Patient Details</h1>
          <p className="text-gray-600">
            Patient ID: {patient.id || '-'}
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
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Age</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.age ? `${patient.age} years` : '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Blood Group</td>
                  <td className="px-4 py-2 text-sm text-gray-700">
                    {patient.bio_data?.blood_group || patient.blood_group || '-'}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Height</td>
                  <td className="px-4 py-2 text-sm text-gray-700">
                    {patient.bio_data?.height ? `${patient.bio_data.height} cm` : '-'}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Weight</td>
                  <td className="px-4 py-2 text-sm text-gray-700">
                    {patient.bio_data?.weight ? `${patient.bio_data.weight} kg` : '-'}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Marital Status</td>
                  <td className="px-4 py-2 text-sm text-gray-700">
                    <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${
                      patient.marital_status === 'Married' ? 'bg-green-100 text-green-800' : 
                      patient.marital_status === 'Divorced' ? 'bg-red-100 text-red-800' :
                      patient.marital_status === 'Widowed' ? 'bg-gray-100 text-gray-800' :
                      patient.marital_status === 'Separated' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-blue-100 text-blue-800'
                    }`}>
                      {patient.marital_status || '-'}
                    </span>
                  </td>
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
              </tbody>
            </table>
          </Card>

          <Card title="Emergency Contact">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Name</td>
                  <td className="px-4 py-2 text-sm text-gray-700">
                    {patient.bio_data?.emergency_contact?.name || patient.emergency_contact_name || '-'}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Relationship</td>
                  <td className="px-4 py-2 text-sm text-gray-700">
                    {patient.bio_data?.emergency_contact?.relationship || patient.emergency_contact_relationship || '-'}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Phone</td>
                  <td className="px-4 py-2 text-sm text-gray-700">
                    {patient.bio_data?.emergency_contact?.phone || patient.emergency_contact_phone || '-'}
                  </td>
                </tr>
              </tbody>
            </table>
          </Card>

          <Card title="Medical Information">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Allergies</td>
                  <td className="px-4 py-2 text-sm">
                    {patient.bio_data?.allergies ? (
                      <span className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                        {patient.bio_data.allergies}
                      </span>
                    ) : (
                      <span className="text-gray-700">None reported</span>
                    )}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Chronic Conditions</td>
                  <td className="px-4 py-2 text-sm">
                    {patient.bio_data?.chronic_conditions ? (
                      <span className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                        {patient.bio_data.chronic_conditions}
                      </span>
                    ) : (
                      <span className="text-gray-700">None reported</span>
                    )}
                  </td>
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
                      patient.created_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                    }`}>
                      {patient.created_at ? 'active' : 'unknown'}
                    </span>
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Registration Date</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{patient.created_at || '-'}</td>
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
          {visitionsLoading ? (
            <div className="py-8 text-center">
              <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mx-auto"></div>
              <p className="mt-2 text-gray-600">Loading medical history...</p>
            </div>
          ) : visitations.length === 0 ? (
            <div className="py-8 text-center text-gray-500">
              No medical history records found
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diagnosis</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Treatment</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medical History</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {visitations.map((visit) => (
                    <tr key={visit.id} className="hover:bg-gray-50">
                      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                        <div>{visit.date || '-'}</div>
                        <div className="text-gray-500 text-xs">{visit.time || ''}</div>
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-700">{visit.doctor || '-'}</td>
                      <td className="px-4 py-3 text-sm text-gray-700 max-w-[200px]">{visit.diagnosis || '-'}</td>
                      <td className="px-4 py-3 text-sm text-gray-700 max-w-[200px]">{visit.treatment || '-'}</td>
                      <td className="px-4 py-3 text-sm text-gray-700 max-w-[300px]">
                        {visit.medical_history ? (
                          <div>
                            <div style={lineClampStyle}>{visit.medical_history}</div>
                            <button 
                              onClick={() => openPopup(visit.medical_history, visit.id)}
                              className="text-blue-600 hover:text-blue-800 text-sm font-medium mt-1"
                            >
                              Show more
                            </button>
                          </div>
                        ) : (
                          '-'
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
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

      {/* Medical History Popup */}
      {popupContent && (
        <div 
          className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" 
          style={popupOverlayStyle}
          onClick={handleOutsideClick}
        >
          <div 
            ref={popupRef}
            className="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[80vh] overflow-auto focus:outline-none"
            style={popupCardStyle}
            tabIndex={-1}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between p-4 border-b">
              <h3 className="text-lg font-medium text-gray-900">Medical History Details</h3>
              <div className="flex items-center space-x-2">
                <div className="bg-gray-100 text-gray-700 px-2 py-1 rounded text-sm flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>{timeRemaining}s</span>
                </div>
                <button 
                  onClick={closePopup}
                  className="text-gray-500 hover:text-gray-700 focus:outline-none"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
            <div className="p-6">
              <div className="whitespace-pre-wrap text-gray-700">
                {popupContent.content}
              </div>
            </div>
            <div className="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end">
              <button
                type="button"
                className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                onClick={closePopup}
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default PatientDetails;

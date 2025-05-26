import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { useUserAccess } from '../../hooks/useUserAccess';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../services/apiService';

const Visitations = () => {
  const [visitations, setVisitations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(null);
  
  const { hasAccess, role } = useUserAccess();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  useEffect(() => {
    const fetchVisitations = async () => {
      try {
        setLoading(true);
        setError(null);
        
        // Build query parameters based on user role
        const params = new URLSearchParams();
        
        // For patients, only show their own visits
        if (role === 'patient' && user?.ID) {
          params.append('patient_id', user.ID);
        }
        
        // Add any search filters from URL
        const patientFilter = searchParams.get('patient_id');
        if (patientFilter && role !== 'patient') {
          params.append('patient_id', patientFilter);
        }
        
        const queryString = params.toString();
        const endpoint = queryString ? `/visitations?${queryString}` : '/visitations';
        
        const response = await api.get(endpoint);
        
        if (response.data && response.data.success) {
          setVisitations(response.data.data || []);
        } else {
          throw new Error(response.data?.message || 'Failed to fetch visitations');
        }
      } catch (err) {
        console.error('Error fetching visitations:', err);
        setError(err.response?.data?.message || err.message || 'Failed to load visitations');
      } finally {
        setLoading(false);
      }
    };
    
    if (hasAccess('visitations')) {
      fetchVisitations();
    } else {
      setLoading(false);
      setError('You do not have permission to view visitations');
    }
  }, [hasAccess, role, user?.ID, searchParams]);
  
  // Handle edit visit
  const handleEditVisit = (visitID) => {
    navigate(`/visitations/${visitID}/edit`);
  };
  
  // Handle delete visit (admin only)
  const handleDeleteVisit = async (visitID) => {
    if (!hasAccess('visitations') || role !== 'administrator') {
      alert('You do not have permission to delete visits');
      return;
    }
    
    if (!window.confirm('Are you sure you want to delete this visit? This action cannot be undone.')) {
      return;
    }
    
    try {
      setDeleteLoading(visitID);
      
      const response = await api.delete(`/visitations/${visitID}`);
      
      if (response.data && response.data.success) {
        // Remove the deleted visit from the list
        setVisitations(prev => prev.filter(visit => visit.ID !== visitID));
      } else {
        throw new Error(response.data?.message || 'Failed to delete visit');
      }
    } catch (err) {
      console.error('Error deleting visit:', err);
      alert(err.response?.data?.message || err.message || 'Failed to delete visit');
    } finally {
      setDeleteLoading(null);
    }
  };
  
  // Handle view visit details
  const handleViewVisit = (visitID) => {
    navigate(`/visitations/${visitID}`);
  };
  
  // Navigate to add new visit
  const handleAddNewVisit = () => {
    navigate('/visitations/new');
  };
  
  // Format date and time for display
  const formatDateTime = (date, time) => {
    if (!date) return '-';
    
    try {
      const dateObj = new Date(date);
      const formattedDate = dateObj.toLocaleDateString();
      
      if (time) {
        return `${formattedDate} at ${time}`;
      }
      
      return formattedDate;
    } catch (err) {
      return date;
    }
  };
  
  // Check if user can edit this specific visit
  const canEditVisit = (visit) => {
    if (!hasAccess('visitations')) return false;
    
    // Admins can edit any visit
    if (role === 'administrator') return true;
    
    // Doctors can edit visits they conducted
    if (role === 'doctor' && visit.doctor_id === user?.ID) return true;
    
    // Desk officers can edit any visit
    if (role === 'desk_officer') return true;
    
    return false;
  };
  
  // Check if user can delete this specific visit
  const canDeleteVisit = () => {
    return hasAccess('visitations') && role === 'administrator';
  };
  
  if (loading) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-4 mb-4">
        <p>Error: {error}</p>
        <Button 
          variant="primary" 
          className="mt-4"
          onClick={() => window.location.reload()}
        >
          Try Again
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white">
        <h1 className="text-3xl font-bold">Patient Visitations</h1>
        <p className="text-blue-100 mt-2">
          {role === 'patient' 
            ? 'View your visit history and medical records' 
            : 'Manage patient visitations and medical consultations'
          }
        </p>
      </div>
      
      <div className="flex justify-between items-center">
        <div className="text-sm text-gray-600">
          {visitations.length > 0 ? (
            <span>Showing {visitations.length} visit{visitations.length !== 1 ? 's' : ''}</span>
          ) : (
            <span>No visits found</span>
          )}
        </div>
        
        {(role === 'administrator' || role === 'doctor' || role === 'desk_officer') && (
          <Button 
            variant="primary"
            onClick={handleAddNewVisit}
            className="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700"
          >
            <span className="flex items-center gap-2">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              New Visit
            </span>
          </Button>
        )}
      </div>
      
      <Card className="shadow-lg">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Visit ID
                </th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Patient Name
                </th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Doctor Name
                </th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Complaint
                </th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Diagnosis
                </th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Visit Date & Time
                </th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {visitations.length > 0 ? (
                visitations.map((visitation) => (
                  <tr key={visitation.ID} className="hover:bg-gray-50 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      #{visitation.ID}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <div className="font-medium">{visitation.patient_name || `Patient #${visitation.patient_id}`}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <div className="font-medium">{visitation.doctor_name || `Doctor #${visitation.doctor_id}`}</div>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-900 max-w-xs">
                      <div className="truncate" title={visitation.complaint}>
                        {visitation.complaint || '-'}
                      </div>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-900 max-w-xs">
                      <div className="truncate" title={visitation.diagnosis}>
                        {visitation.diagnosis || '-'}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <div className="font-medium">
                        {formatDateTime(visitation.date, visitation.time)}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div className="flex items-center space-x-2">
                        <Button 
                          variant="secondary" 
                          size="sm" 
                          onClick={() => handleViewVisit(visitation.ID)}
                          className="bg-blue-100 text-blue-700 hover:bg-blue-200"
                        >
                          View
                        </Button>
                        {canEditVisit(visitation) && (
                          <Button 
                            variant="primary" 
                            size="sm" 
                            onClick={() => handleEditVisit(visitation.ID)}
                            className="bg-green-100 text-green-700 hover:bg-green-200"
                          >
                            Edit
                          </Button>
                        )}
                        {canDeleteVisit() && (
                          <Button 
                            variant="danger" 
                            size="sm" 
                            onClick={() => handleDeleteVisit(visitation.ID)} 
                            disabled={deleteLoading === visitation.ID}
                            className="bg-red-100 text-red-700 hover:bg-red-200 disabled:opacity-50"
                          >
                            {deleteLoading === visitation.ID ? 'Deleting...' : 'Delete'}
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={7} className="px-6 py-8 text-center text-sm text-gray-500">
                    <div className="flex flex-col items-center space-y-3">
                      <svg className="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <div>
                        <p className="font-medium text-gray-900">No visits found</p>
                        <p className="text-gray-500 mt-1">
                          {role === 'patient' 
                            ? 'You have no visit records yet.' 
                            : 'Start by adding a new patient visit.'
                          }
                        </p>
                      </div>
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
};

export default Visitations;
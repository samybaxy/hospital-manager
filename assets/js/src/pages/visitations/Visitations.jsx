import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
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
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState('');
  const [sortField, setSortField] = useState('date');
  const [sortOrder, setSortOrder] = useState('desc');
  
  const { hasAccess, role } = useUserAccess();
  const { user } = useAuth();
  const navigate = useNavigate();

  // Check access permission
  if (!hasAccess('visitations')) {
    return (
      <div className="p-6">
        <Card>
          <div className="text-center text-red-600">
            You do not have permission to view visitations.
          </div>
        </Card>
      </div>
    );
  }

  const fetchVisitations = useCallback(async () => {
    try {
        setLoading(true);
        setError(null);

        // Prepare the parameters for the API call
        const params = { 
            page: currentPage,
            search: searchTerm,
            per_page: perPage,
            sort_by: sortField,
            sort_order: sortOrder
        };
      
        // For patients, only show their own visits
        if (role === 'patient' && user?.ID) {
            params.append('patient_id', user.ID);
        }
        
        const response = await api.get('/visitations', { params }); // Corrected params format
        if (response.data) {
            // Check if data is inside the "data" property (common REST API pattern)
            const responseData = response.data.data || response.data;

            if (responseData.visitations && responseData.visitations.items) {
                // Extract patient items from the nested structure
                const visitItems = responseData.visitations.items || [];
                
                // Extract metadata for pagination
                setVisitations(visitItems);
                setTotalPages(responseData.visitations.lastPage || 1);
                setTotalRecords(responseData.visitations.total || 0);
                console.log(`Loaded ${visitItems.length} visits (page ${currentPage}/${responseData.visitations.lastPage}, total: ${responseData.visitations.total})`);
            } else if (Array.isArray(responseData.visitations)) {
                // Handle alternative API response format
                setVisitations(responseData.visitations);
                setTotalPages(responseData.total_pages || 1);
                setTotalRecords(responseData.total || 0);
                console.log(`Loaded ${responseData.visitations.length} visits (page ${currentPage}/${responseData.total_pages}, total: ${responseData.total})`);
            } else {
                console.error('Unexpected visitation data format:', responseData);
                setError('Data format error. Please contact support.');
            }
        } else {
            throw new Error(response.data?.message || 'Failed to fetch visitations');
        }
    } catch (err) {
      console.error('Error fetching visitations:', err);
      setError(err.response?.data?.message || err.message || 'Failed to load visitations');
    } finally {
      setLoading(false);
    }
  }, [currentPage, perPage, searchTerm, sortField, sortOrder]);

  useEffect(() => {
    fetchVisitations();  }, [fetchVisitations]);

  // Handle column sorting
  const handleSort = (field) => {
    if (sortField === field) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortOrder('asc');
    }
    setCurrentPage(1); // Reset to first page when sorting
  };

  // Handle pagination
  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  const handlePreviousPage = () => {
    setCurrentPage((prev) => Math.max(prev - 1, 1));
  };

  const handleNextPage = () => {
    setCurrentPage((prev) => Math.min(prev + 1, totalPages));
  };

  // Sorting indicator component
  const SortIndicator = ({ field }) => {
    if (sortField !== field) return null;
    return (
      <span className="ml-1 inline-block">
        {sortOrder === 'asc' ? '↑' : '↓'}
      </span>
    );
  };

  // Pagination component
  const renderPagination = () => {
    if (totalPages <= 1 && totalRecords <= perPage) return null;
    
    const pagesToShow = 5;
    const pages = [];
    let startPage = Math.max(1, currentPage - Math.floor(pagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + pagesToShow - 1);
    
    if (endPage - startPage + 1 < pagesToShow) {
      startPage = Math.max(1, endPage - pagesToShow + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
      pages.push(i);
    }
    
    return (
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-4 bg-white border-t border-gray-200 sm:px-6 mt-4">
        {/* Showing X to Y of Z */}
        <div className="mb-4 sm:mb-0 text-sm text-gray-700">
          <p>
            Showing <span className="font-bold">{((currentPage - 1) * perPage) + 1}</span>{' '}
            to <span className="font-bold">{Math.min(currentPage * perPage, totalRecords)}</span>{' '}
            of <span className="font-bold">{totalRecords}</span> visit{totalRecords !== 1 ? 's' : ''}
          </p>
        </div>
        
        <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0">
          {/* Items per page selector - moved to the right but before pagination */}
          <div className="flex items-center space-x-2 mb-4 mr-4 sm:mb-0">
            <label htmlFor="perPage" className="text-sm text-gray-600">Items per page:</label>
            <select
              id="perPage"
              value={perPage}
              onChange={(e) => {
                setPerPage(Number(e.target.value));
                setCurrentPage(1);
              }}
              className="border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value={10}>10</option>
              <option value={20}>20</option>
              <option value={50}>50</option>
              <option value={100}>100</option>
            </select>
          </div>
          
          <div className="flex items-center justify-center w-full sm:w-auto">
            <div className="flex-1 flex justify-between sm:hidden">
              <Button
                onClick={handlePreviousPage}
                disabled={currentPage === 1}
                variant="secondary"
                size="sm"
              >
                Previous
              </Button>
              <Button
                onClick={handleNextPage}
                disabled={currentPage === totalPages}
                variant="secondary"
                size="sm"
              >
                Next
              </Button>
            </div>
            
            <div className="hidden sm:flex">
              <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <button
                  onClick={handlePreviousPage}
                  disabled={currentPage === 1}
                  className={`relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium ${
                    currentPage === 1 
                      ? 'text-gray-300 cursor-not-allowed' 
                      : 'text-gray-500 hover:bg-gray-50'
                  }`}
                >
                  <span className="sr-only">Previous</span>
                  <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                </button>
                
                {/* First page and ellipsis */}
                {startPage > 1 && (
                  <>
                    <button 
                      onClick={() => handlePageChange(1)}
                      className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                    >
                      1
                    </button>
                    {startPage > 2 && <span className="px-2 relative inline-flex items-center border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>}
                  </>
                )}
                
                {/* Page numbers */}
                {pages.map(page => (
                  <button
                    key={page}
                    onClick={() => handlePageChange(page)}
                    className={`relative inline-flex items-center px-3 py-2 border ${
                      currentPage === page
                        ? 'z-10 bg-primary-50 border-primary-500 text-primary-600'
                        : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'
                    } text-sm font-medium`}
                  >
                    {page}
                  </button>
                ))}
                
                {/* Last page and ellipsis */}
                {endPage < totalPages && (
                  <>
                    {endPage < totalPages - 1 && <span className="px-2 relative inline-flex items-center border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>}
                    <button
                      onClick={() => handlePageChange(totalPages)}
                      className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                    >
                      {totalPages}
                    </button>
                  </>
                )}
                
                <button
                  onClick={handleNextPage}
                  disabled={currentPage === totalPages}
                  className={`relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium ${
                    currentPage === totalPages 
                      ? 'text-gray-300 cursor-not-allowed' 
                      : 'text-gray-500 hover:bg-gray-50'
                  }`}
                >
                  <span className="sr-only">Next</span>
                  <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
                  </svg>
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    );
  };
  
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
      
      <div className="flex flex-col md:flex-row md:items-center md:justify-start mb-6">
        <div className="w-full md:w-2/3">
          {/* Search input - automatic search like Patients page */}
          <input
            type="text"
            placeholder="Search visits..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
          />
        </div>
      </div>
      
      <Card className="shadow-lg">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th scope="col" className="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  S/N
                </th>
                <th 
                  scope="col" 
                  className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                  onClick={() => handleSort('patient_name')}
                >
                  Patient Name
                  <SortIndicator field="patient_name" />
                </th>
                <th 
                  scope="col" 
                  className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                  onClick={() => handleSort('doctor_name')}
                >
                  Doctor Name
                  <SortIndicator field="doctor_name" />
                </th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Diagnosis
                </th>
                <th 
                  scope="col" 
                  className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                  onClick={() => handleSort('date')}
                >
                  Visit Date & Time
                  <SortIndicator field="date" />
                </th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {visitations.length > 0 ? (
                visitations.map((visitation, index) => (
                  <tr key={visitation.ID} className="hover:bg-gray-50 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {((currentPage - 1) * perPage) + index + 1}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <div className="font-medium">{visitation.patient_name || `Patient #${visitation.patient_id}`}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <div className="font-medium">{visitation.doctor_name || `Doctor #${visitation.doctor_id}`}</div>
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
        
        {/* Pagination - now positioned at the bottom of the table */}
        {renderPagination()}
      </Card>
    </div>
  );
};

export default Visitations;
import React, { useState, useEffect, useCallback } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import Modal from '../components/Modal';
// Modified import to ensure it's using the latest version
import laboratoryService from '../services/laboratoryService';

// Helper function for status badge colors
const getStatusBadgeColor = (status) => {
  const colors = {
    requested: 'bg-blue-100 text-blue-800',
    sample_collected: 'bg-orange-100 text-orange-800',
    in_progress: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-green-100 text-green-800',
    verified: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-red-100 text-red-800'
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
};

const LabInvestigations = () => {
  const [investigations, setInvestigations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [selectedInvestigation, setSelectedInvestigation] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [modalType, setModalType] = useState('view'); // 'view', 'edit', 'results'
  const [updateLoading, setUpdateLoading] = useState(false);

  const fetchInvestigations = useCallback(async (page = 1) => {
    try {
      setLoading(true);
      const params = {
        page,
        per_page: perPage
      };
      
      // Only add search if it has a value
      if (searchTerm && searchTerm.trim()) {
        params.search = searchTerm.trim();
      }
      
      // Only add status if it's not 'all'
      if (statusFilter && statusFilter !== 'all') {
        params.status = statusFilter;
      }
      
      // Use URLSearchParams to construct proper query string
      const queryParams = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          queryParams.append(key, value);
        }
      });
      
      // Modify the service call to ensure search parameter is included
      // We'll directly fetch using fetch API to diagnose the issue
      const queryString = queryParams.toString();
      const apiUrl = `/wp-json/hospital-manager/v1/lab-investigations?${queryString}`;
      
      try {
        // Create a custom fetch implementation to log and ensure all params are sent
        const directResponse = await fetch(apiUrl);
        const directData = await directResponse.json();
        
        // Use the direct fetch result instead of the service call
        const response = {
          data: directData.data || [],
          pagination: directData.pagination || {}
        };
        
        setInvestigations(response.data || []);
        
        // Handle pagination data from API response
        if (response.pagination) {
          setTotalPages(response.pagination.total_pages || 1);
          setTotalRecords(response.pagination.total || 0);
          setCurrentPage(response.pagination.current_page || page);
        } else {
          // Fallback if pagination object is missing
          setTotalPages(Math.ceil((response.total || 0) / perPage));
          setTotalRecords(response.total || 0);
          setCurrentPage(page);
        }
        
        setError(null);
        setLoading(false);
        return; // Skip the original service call
      } catch (fetchErr) {
        console.error('Direct fetch error:', fetchErr);
        // Continue with original service call as fallback
      }
      
      const response = await laboratoryService.getInvestigations(params);
      
      setInvestigations(response.data || []);
      
      // Handle pagination data from API response
      if (response.pagination) {
        setTotalPages(response.pagination.total_pages || 1);
        setTotalRecords(response.pagination.total || 0);
        setCurrentPage(response.pagination.current_page || page);
      } else {
        // Fallback if pagination object is missing
        setTotalPages(Math.ceil((response.total || 0) / perPage));
        setTotalRecords(response.total || 0);
        setCurrentPage(page);
      }
      
      setError(null);
    } catch (err) {
      console.error('Error fetching lab investigations:', err);
      setError(err.message || 'Failed to fetch investigations');
      setInvestigations([]);
    } finally {
      setLoading(false);
    }
  }, [searchTerm, statusFilter, perPage]);

  useEffect(() => {
    fetchInvestigations(1);
  }, [fetchInvestigations]);

  // Trigger search when searchTerm or statusFilter changes
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      fetchInvestigations(1);
    }, 300); // Debounce search
    
    return () => clearTimeout(timeoutId);
  }, [searchTerm, statusFilter]);

  const handleSearch = (term) => {
    setSearchTerm(term);
    setCurrentPage(1);
  };

  const handleStatusFilter = (status) => {
    setStatusFilter(status);
    setCurrentPage(1);
  };

  // Handle pagination
  const handlePageChange = (page) => {
    setCurrentPage(page);
    fetchInvestigations(page);
  };

  const handlePreviousPage = () => {
    const newPage = Math.max(currentPage - 1, 1);
    handlePageChange(newPage);
  };

  const handleNextPage = () => {
    const newPage = Math.min(currentPage + 1, totalPages);
    handlePageChange(newPage);
  };

  const renderPagination = () => {
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
      <div className="flex items-center gap-1">
        {startPage > 1 && (
          <>
            <button 
              onClick={() => handlePageChange(1)}
              className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
            >
              1
            </button>
            {startPage > 2 && <span className="px-2">...</span>}
          </>
        )}
        
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
        
        {endPage < totalPages && (
          <>
            {endPage < totalPages - 1 && <span className="px-2">...</span>}
            <button
              onClick={() => handlePageChange(totalPages)}
              className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
            >
              {totalPages}
            </button>
          </>
        )}
      </div>
    );
  };

  const openModal = (investigation, type = 'view') => {
    setSelectedInvestigation(investigation);
    setModalType(type);
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setSelectedInvestigation(null);
    setModalType('view');
  };

  const handleUpdateStatus = async (investigationId, newStatus) => {
    try {
      setUpdateLoading(true);
      await laboratoryService.updateStatus(investigationId, newStatus);
      await fetchInvestigations(currentPage);
      closeModal();
    } catch (err) {
      console.error('Error updating status:', err);
      setError(err.message || 'Failed to update status');
    } finally {
      setUpdateLoading(false);
    }
  };

  const handleUpdateResults = async (investigationId, results, labNotes) => {
    try {
      setUpdateLoading(true);
      await laboratoryService.updateResults(investigationId, {
        test_results: results,
        lab_notes: labNotes,
        status: 'completed'
      });
      await fetchInvestigations(currentPage);
      closeModal();
    } catch (err) {
      console.error('Error updating results:', err);
      setError(err.message || 'Failed to update results');
    } finally {
      setUpdateLoading(false);
    }
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  if (loading && investigations.length === 0) {
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
          onClick={() => fetchInvestigations(currentPage)}
        >
          Try Again
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-3xl font-bold">Laboratory Investigations</h1>
            <p className="text-blue-100 mt-2">
              Manage laboratory tests, results, and medical investigations
            </p>
          </div>
          <Button 
            variant="secondary" 
            className="mt-4 md:mt-0 bg-white hover:bg-gray-100 text-blue-700"
            onClick={() => openModal(null, 'create')}
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
            </svg>
            New Investigation
          </Button>
        </div>
      </div>
      
      {/* Search and Filter Controls */}
      <Card className="p-4">
        <div className="flex flex-col md:flex-row gap-4">
          <div className="flex-1">
            <input
              type="text"
              placeholder="Search by patient name, test type..."
              className="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              value={searchTerm}
              onChange={(e) => handleSearch(e.target.value)}
            />
          </div>
          <div className="md:w-48">
            <select
              className="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              value={statusFilter}
              onChange={(e) => handleStatusFilter(e.target.value)}
            >
              <option value="all">All Status</option>
              <option value="requested">Requested</option>
              <option value="sample_collected">Sample Collected</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="verified">Verified</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div className="md:w-32">
            <select
              className="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              value={perPage}
              onChange={(e) => {
                setPerPage(Number(e.target.value));
                setCurrentPage(1);
                // Trigger refetch when per page changes
                fetchInvestigations(1);
              }}
            >
              <option value={5}>5 rows</option>
              <option value={10}>10 rows</option>
              <option value={25}>25 rows</option>
              <option value={50}>50 rows</option>
            </select>
          </div>
        </div>
      </Card>
      
      <Card>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th scope="col" className="py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16 min-w-16">
                  S/N
                </th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Patient
                </th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Test Type
                </th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Doctor
                </th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Date
                </th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Priority
                </th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {investigations.length > 0 ? (
                investigations.map((investigation, index) => (
                  <tr key={investigation.ID} className="hover:bg-gray-50">
                    <td className="py-4 text-center text-sm font-medium text-gray-900 whitespace-nowrap">
                      {(currentPage - 1) * perPage + index + 1}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      {investigation.patient_name || 'Unknown Patient'}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      {investigation.test_type || 'N/A'}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      {investigation.doctor_name || 'Unknown Doctor'}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      {formatDate(investigation.created_at)}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm">
                      <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusBadgeColor(investigation.status)}`}>
                        {investigation.status?.replace('_', ' ').toUpperCase()}
                      </span>
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm">
                      <div className="flex space-x-1">
                        {parseInt(investigation.is_critical) === 1 && (
                          <span className="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                            Critical
                          </span>
                        )}
                        {parseInt(investigation.is_abnormal) === 1 && (
                          <span className="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                            Abnormal
                          </span>
                        )}
                        {parseInt(investigation.is_critical) === 0 && parseInt(investigation.is_abnormal) === 0 && (
                          <span className="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                            Normal
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div className="flex space-x-2">
                        <Button 
                          variant="secondary" 
                          size="sm" 
                          onClick={() => openModal(investigation, 'view')}
                        >
                          View
                        </Button>
                        {investigation.status !== 'completed' && investigation.status !== 'verified' && (
                          <Button 
                            variant="primary" 
                            size="sm"
                            onClick={() => openModal(investigation, 'results')}
                          >
                            Results
                          </Button>
                        )}
                        <Button 
                          variant="outline" 
                          size="sm"
                          onClick={() => openModal(investigation, 'edit')}
                        >
                          Edit
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={8} className="px-4 py-4 text-center text-sm text-gray-500">
                    {loading ? 'Loading...' : 'No investigations found'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {!loading && investigations.length > 0 && totalPages > 1 && (
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 bg-white border-t border-gray-200">
            <div className="mb-4 sm:mb-0 text-sm text-gray-700">
              <p>
                Showing <span className="font-semibold">{((currentPage - 1) * perPage) + 1}</span>{' '}
                to <span className="font-semibold">{Math.min(currentPage * perPage, totalRecords)}</span>{' '}
                of <span className="font-semibold">{totalRecords}</span> investigations
              </p>
            </div>
            
            <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0">
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
                    
                    {/* Page numbers */}
                    {renderPagination()}
                    
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
        )}
      </Card>

      {/* Modal for viewing/editing investigations and results */}
      {showModal && (
        <InvestigationModal
          investigation={selectedInvestigation}
          type={modalType}
          onClose={closeModal}
          onUpdateStatus={handleUpdateStatus}
          onUpdateResults={handleUpdateResults}
          loading={updateLoading}
        />
      )}
    </div>
  );
};

// Lab Investigation View Component
const LabInvestigationView = ({ investigation }) => {
  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return (
    <div className="space-y-6">
      {/* Patient and Doctor Information */}
      <div className="bg-gray-50 p-4 rounded-lg">
        <h3 className="text-lg font-semibold text-gray-900 mb-3">Patient & Doctor Information</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">Patient Name</label>
            <p className="mt-1 text-sm text-gray-900 font-semibold">{investigation.patient_name || 'Unknown Patient'}</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Requesting Doctor</label>
            <p className="mt-1 text-sm text-gray-900 font-semibold">{investigation.doctor_name || 'Unknown Doctor'}</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Lab Technician</label>
            <p className="mt-1 text-sm text-gray-900">{investigation.lab_tech_name || 'Not assigned'}</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Visitation ID</label>
            <p className="mt-1 text-sm text-gray-900">#{investigation.visitation_id}</p>
          </div>
        </div>
      </div>

      {/* Test Information */}
      <div className="bg-blue-50 p-4 rounded-lg">
        <h3 className="text-lg font-semibold text-gray-900 mb-3">Test Information</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">Test Type</label>
            <p className="mt-1 text-sm text-gray-900 font-semibold">{investigation.test_type || 'N/A'}</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Sample Type</label>
            <p className="mt-1 text-sm text-gray-900">{investigation.sample_type || 'N/A'}</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Status</label>
            <span className={`mt-1 px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusBadgeColor(investigation.status)}`}>
              {investigation.status?.replace('_', ' ').toUpperCase()}
            </span>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Priority</label>
            <div className="mt-1 flex space-x-1">
              {parseInt(investigation.is_critical) === 1 && (
                <span className="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                  Critical
                </span>
              )}
              {parseInt(investigation.is_abnormal) === 1 && (
                <span className="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                  Abnormal
                </span>
              )}
              {parseInt(investigation.is_critical) === 0 && parseInt(investigation.is_abnormal) === 0 && (
                <span className="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                  Normal
                </span>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Request Notes */}
      {investigation.request_notes && (
        <div className="bg-yellow-50 p-4 rounded-lg">
          <h3 className="text-lg font-semibold text-gray-900 mb-3">Request Notes</h3>
          <p className="text-sm text-gray-700 whitespace-pre-wrap">{investigation.request_notes}</p>
        </div>
      )}

      {/* Lab Notes */}
      {investigation.lab_notes && (
        <div className="bg-green-50 p-4 rounded-lg">
          <h3 className="text-lg font-semibold text-gray-900 mb-3">Lab Notes</h3>
          <p className="text-sm text-gray-700 whitespace-pre-wrap">{investigation.lab_notes}</p>
        </div>
      )}

      {/* Timestamps */}
      <div className="bg-gray-50 p-4 rounded-lg">
        <h3 className="text-lg font-semibold text-gray-900 mb-3">Timeline</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">Created</label>
            <p className="mt-1 text-sm text-gray-900">{formatDate(investigation.created_at)}</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Last Updated</label>
            <p className="mt-1 text-sm text-gray-900">{formatDate(investigation.updated_at)}</p>
          </div>
        </div>
      </div>
    </div>
  );
};

// Lab Results View Component
const LabResultsView = ({ investigation }) => {
  const renderTestResults = () => {
    if (!investigation.test_results) {
      return (
        <div className="text-center py-8">
          <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h3 className="mt-2 text-sm font-medium text-gray-900">No Results Available</h3>
          <p className="mt-1 text-sm text-gray-500">Test results have not been uploaded yet.</p>
        </div>
      );
    }

    let results;
    try {
      results = typeof investigation.test_results === 'string' 
        ? JSON.parse(investigation.test_results) 
        : investigation.test_results;
    } catch (e) {
      results = investigation.test_results;
    }

    if (results.parameters && Array.isArray(results.parameters)) {
      // CBC-style results with parameters
      return (
        <div className="space-y-4">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parameter</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference Range</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {results.parameters.map((param, index) => (
                  <tr key={index} className={param.is_abnormal ? 'bg-red-50' : param.is_critical ? 'bg-orange-50' : ''}>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {param.name}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                      {param.value}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {param.unit}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {param.reference_range ? 
                        `${param.reference_range.min} - ${param.reference_range.max}` : 
                        'N/A'
                      }
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      {param.is_critical ? (
                        <span className="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                          Critical
                        </span>
                      ) : param.is_abnormal ? (
                        <span className="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                          Abnormal
                        </span>
                      ) : (
                        <span className="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                          Normal
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      );
    } else {
      // Simple result display
      return (
        <div className="space-y-4">
          {results.result && (
            <div className="bg-blue-50 p-4 rounded-lg">
              <label className="block text-sm font-medium text-gray-700">Result</label>
              <p className="mt-1 text-lg font-semibold text-gray-900">{results.result}</p>
            </div>
          )}
          {results.interpretation && (
            <div className="bg-green-50 p-4 rounded-lg">
              <label className="block text-sm font-medium text-gray-700">Interpretation</label>
              <p className="mt-1 text-sm text-gray-900">{results.interpretation}</p>
            </div>
          )}
          {typeof results === 'object' && !results.result && !results.interpretation && (
            <div className="bg-gray-50 p-4 rounded-lg">
              <label className="block text-sm font-medium text-gray-700">Raw Results</label>
              <pre className="mt-1 text-xs text-gray-600 whitespace-pre-wrap bg-white p-3 rounded border">
                {JSON.stringify(results, null, 2)}
              </pre>
            </div>
          )}
        </div>
      );
    }
  };

  return (
    <div className="space-y-6">
      {/* Test Information Header */}
      <div className="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-4 rounded-lg">
        <h3 className="text-lg font-semibold">{investigation.test_type}</h3>
        <p className="text-blue-100">Patient: {investigation.patient_name}</p>
        <p className="text-blue-100">Sample: {investigation.sample_type}</p>
      </div>

      {/* Results Display */}
      <div className="bg-white border border-gray-200 rounded-lg p-4">
        <h4 className="text-md font-semibold text-gray-900 mb-4">Test Results</h4>
        {renderTestResults()}
      </div>

      {/* Lab Notes */}
      {investigation.lab_notes && (
        <div className="bg-green-50 p-4 rounded-lg">
          <h4 className="text-md font-semibold text-gray-900 mb-2">Lab Notes</h4>
          <p className="text-sm text-gray-700 whitespace-pre-wrap">{investigation.lab_notes}</p>
        </div>
      )}

      {/* Flags and Alerts */}
      {investigation.flags && (
        <div className="bg-yellow-50 p-4 rounded-lg">
          <h4 className="text-md font-semibold text-gray-900 mb-2">Flags & Alerts</h4>
          <p className="text-sm text-gray-700">{investigation.flags}</p>
        </div>
      )}
    </div>
  );
};

// Modal component for investigation details, editing, and results
const InvestigationModal = ({ investigation, type, onClose, onUpdateStatus, onUpdateResults, loading }) => {
  const [formData, setFormData] = useState({
    test_type: investigation?.test_type || '',
    test_results: investigation?.test_results || '',
    lab_notes: investigation?.lab_notes || '',
    request_notes: investigation?.request_notes || '',
    status: investigation?.status || 'requested'
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    if (type === 'results') {
      onUpdateResults(investigation.ID, formData.test_results, formData.lab_notes);
    } else if (type === 'edit') {
      onUpdateStatus(investigation.ID, formData.status);
    }
  };

  const getModalTitle = () => {
    switch (type) {
      case 'view': return 'Investigation Details';
      case 'results': return 'Test Results';
      case 'edit': return 'Edit Investigation';
      case 'create': return 'New Investigation';
      default: return 'Investigation';
    }
  };

  return (
    <Modal onClose={onClose} title={getModalTitle()}>
      <div className="space-y-4">
        {investigation && type === 'view' && (
          <>
            <LabInvestigationView investigation={investigation} />
            <div className="flex justify-end pt-4 border-t">
              <Button variant="secondary" onClick={onClose}>
                Close
              </Button>
            </div>
          </>
        )}

        {investigation && type === 'results' && (
          <>
            <LabResultsView investigation={investigation} />
            <div className="flex justify-end pt-4 border-t">
              <Button variant="secondary" onClick={onClose}>
                Close
              </Button>
            </div>
          </>
        )}

        {investigation && type === 'edit' && (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">Patient</label>
                <p className="mt-1 text-sm text-gray-900">{investigation.patient_name || 'Unknown'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Doctor</label>
                <p className="mt-1 text-sm text-gray-900">{investigation.doctor_name || 'Unknown'}</p>
              </div>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700">Test Type</label>
              <input
                type="text"
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                value={formData.test_type || ''}
                onChange={(e) => setFormData({ ...formData, test_type: e.target.value })}
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700">Status</label>
              <select
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                value={formData.status}
                onChange={(e) => setFormData({ ...formData, status: e.target.value })}
              >
                <option value="requested">Requested</option>
                <option value="sample_collected">Sample Collected</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="verified">Verified</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            
            <div className="flex justify-end space-x-3 pt-4 border-t">
              <Button variant="secondary" onClick={onClose} disabled={loading}>
                Cancel
              </Button>
              <Button type="submit" variant="primary" disabled={loading}>
                {loading ? 'Updating...' : 'Update Status'}
              </Button>
            </div>
          </form>
        )}

        {type === 'create' && (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">Test Type<span className="text-red-500">*</span></label>
              <input
                type="text"
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                value={formData.test_type || ''}
                onChange={(e) => setFormData({ ...formData, test_type: e.target.value })}
                placeholder="Enter test type..."
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Request Notes</label>
              <textarea
                rows={3}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                value={formData.request_notes || ''}
                onChange={(e) => setFormData({ ...formData, request_notes: e.target.value })}
                placeholder="Enter request notes..."
              />
            </div>
            <div className="flex justify-end space-x-3 pt-4 border-t">
              <Button variant="secondary" onClick={onClose} disabled={loading}>
                Cancel
              </Button>
              <Button type="submit" variant="primary" disabled={loading}>
                {loading ? 'Creating...' : 'Create Investigation'}
              </Button>
            </div>
          </form>
        )}
      </div>
    </Modal>
  );
};

export default LabInvestigations;

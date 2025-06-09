import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import StatusMessage from '../../components/StatusMessage';
import { api } from '../../services/apiService';

// Removed unused imports
import { useAuth } from '../../context/AuthContext';
import { useUserAccess } from '../../hooks/useUserAccess';

const Appointments = () => {
  const { user, loading: authLoading } = useAuth();
  const { role } = useUserAccess();
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');
  const [sortBy, setSortBy] = useState('date');
  const [sortDirection, setSortDirection] = useState('desc');
  const [searchTerm, setSearchTerm] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  
  // Pagination state
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalAppointments, setTotalAppointments] = useState(0);
  const [perPage, setPerPage] = useState(10);

  const [cancellationState, setCancellationState] = useState({
    showConfirmation: false,
    appointmentId: null,
    isLoading: false,
    error: '',
    reason: '',
  });

  const fetchAppointments = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      // Prepare the parameters for the API call
      const params = { 
        page: currentPage,
        per_page: perPage,
        sort_by: sortBy,
        sort_order: sortDirection
      };
      
      // Only add status filter if not "all"
      if (filterStatus !== 'all') {
        params.status = filterStatus;
      }
      
      // Only add search if it has value
      if (searchTerm && searchTerm.trim()) {
        params.search = searchTerm.trim();
      }
      
      const response = await api.get('/appointments', { params });
      
      if (response.data) {
        // Check if data is inside the "data" property (common REST API pattern)
        const responseData = response.data.data || response.data;
        
        if (responseData.appointments && responseData.appointments.items) {
          // Extract appointment items from the nested structure
          const appointmentItems = responseData.appointments.items || [];
          
          // Extract metadata for pagination
          setAppointments(appointmentItems);
          setTotalPages(responseData.appointments.lastPage || 1);
          setTotalAppointments(responseData.appointments.total || 0);
          console.log(`Loaded ${appointmentItems.length} appointments (page ${currentPage}/${responseData.appointments.lastPage}, total: ${responseData.appointments.total})`);
        } else if (Array.isArray(responseData.appointments)) {
          // Handle alternative API response format
          setAppointments(responseData.appointments);
          setTotalPages(responseData.total_pages || 1);
          setTotalAppointments(responseData.total || 0);
          console.log(`Loaded ${responseData.appointments.length} appointments (page ${currentPage}/${responseData.total_pages}, total: ${responseData.total})`);
        } else {
          console.error('Unexpected appointment data format:', responseData);
          setError('Data format error. Please contact support.');
        }
      }
    } catch (err) {
      console.error('Error fetching appointments:', err);
      setError('Failed to load appointments. Please try again later.');
    } finally {
      setLoading(false);
    }
  }, [currentPage, searchTerm, perPage, sortBy, sortDirection, filterStatus]);

  // Keep track of manual fetch requests to prevent duplicate calls
  const [manualFetchRequested, setManualFetchRequested] = useState(false);

  useEffect(() => {
    // Wait for auth to complete before proceeding
    if (!authLoading) {
        // Only fetch automatically if a manual fetch wasn't requested
        if (!manualFetchRequested) {
            const loadAppointments = async () => {
                try {
                    await fetchAppointments();
                    
                    // Success message will be set in the fetchAppointments function
                    // or can be handled outside this effect to avoid dependencies
                    setSuccessMessage('Appointments data loaded successfully');
                } catch (error) {
                    console.error('Error in appointments data loading effect:', error);
                }
            };

            loadAppointments();
        }
      
      // Reset the flag after the effect runs
      setManualFetchRequested(false);
    }
  }, [fetchAppointments, manualFetchRequested, authLoading]);

  const handleCancelClick = (appointmentId) => {
    setCancellationState({
      showConfirmation: true,
      appointmentId,
      isLoading: false,
      error: '',
      reason: '',
    });
  };

  const handleCancelConfirm = async () => {
    setCancellationState(prev => ({
      ...prev,
      isLoading: true,
      error: '',
    }));

    try {
      // Include cancellation reason in the update - use api directly
      await api.put(`/appointments/${cancellationState.appointmentId}`, { 
        status: 'cancelled',
        notes: cancellationState.reason || 'No reason provided'
      });
      
      // Refresh the appointments list
      fetchAppointments();
      
      // Reset cancellation state
      setCancellationState({
        showConfirmation: false,
        appointmentId: null,
        isLoading: false,
        error: '',
        reason: '',
      });
    } catch (err) {
      console.error('Error cancelling appointment:', err);
      setCancellationState(prev => ({
        ...prev,
        isLoading: false,
        error: err.response?.data?.message || 'Failed to cancel appointment. Please try again.',
      }));
    }
  };

  const handleCancelDismiss = () => {
    setCancellationState({
      showConfirmation: false,
      appointmentId: null,
      isLoading: false,
      error: '',
      reason: '',
    });
  };

  const handleReasonChange = (e) => {
    setCancellationState(prev => ({
      ...prev,
      reason: e.target.value
    }));
  };

  const formatDateTime = (date, time) => {
    const dateObj = new Date(`${date}T${time}`);
    return dateObj.toLocaleString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      hour12: true
    });
  };

  const getStatusBadgeClass = (status) => {
    switch (status) {
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'confirmed':
        return 'bg-green-100 text-green-800';
      case 'completed':
        return 'bg-blue-100 text-blue-800';
      case 'cancelled':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
    };

    // Handle page navigation
    const handlePageChange = (page) => {
        // Only change the page if it's different from the current page and within range
        if (page !== currentPage && page >= 1 && page <= totalPages) {
            setCurrentPage(page);
            setManualFetchRequested(true);
        }
    };

    // Handle filter status changes
    const handleFilterChange = (newStatus) => {
        // Update the filter status state
        setFilterStatus(newStatus);
        
        // Always reset to page 1 when changing filters
        setCurrentPage(1); 
        
        // Set manual fetch flag to prevent duplicate calls
        setManualFetchRequested(true);
    };

    // Toggle sort direction and set the sort field - note this is only used in UI but sorting is done server-side
    const handleSort = (field) => {
    if (sortBy === field) {
        // Toggle direction if same field
        setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
        // Set new field and reset direction to ascending
        setSortBy(field);
        setSortDirection('asc');
    }

    // Note: Currently we're not sending sort parameters to the API - this would be implemented here
    // fetchAppointments({
    //   page: currentPage,
    //   per_page: perPage,
    //   status: filterStatus !== 'all' ? filterStatus : null,
    //   sort_by: field,
    //   sort_dir: sortBy === field && sortDirection === 'asc' ? 'desc' : 'asc'
    // });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        setCurrentPage(1); // Reset to first page on new search
        setManualFetchRequested(true); // Prevent duplicate fetch
        fetchAppointments(); // Immediately fetch with new search term
    };
    const renderPagination = () => {
    // Don't render pagination if there's only one page or no pages
    if (!totalPages || totalPages <= 1) return null;
    
    const pagesToShow = 5;
    let startPage = Math.max(1, currentPage - Math.floor(pagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + pagesToShow - 1);
    
    // Adjust startPage if we can't show enough pages
    if (endPage - startPage + 1 < pagesToShow) {
        startPage = Math.max(1, endPage - pagesToShow + 1);
    }
    
    // Generate array of page numbers to show
    const pages = [];
    for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
    }
    
    return (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-4 bg-white border-t border-gray-200 sm:px-6 mt-4">
        {/* Showing X to Y of Z */}
        <div className="mb-4 sm:mb-0 text-sm text-gray-700">
            <p>
            Showing <span className="font-bold">{totalAppointments > 0 ? (currentPage - 1) * perPage + 1 : 0}</span>{' '}
            to <span className="font-bold">{Math.min(currentPage * perPage, totalAppointments)}</span>{' '}
            of <span className="font-bold">{totalAppointments}</span> appointment{totalAppointments !== 1 ? 's' : ''}
            </p>
        </div>
        
        <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0">            
            <div className="flex items-center justify-center w-full sm:w-auto">
            <div className="flex-1 flex justify-between sm:hidden">
                <Button
                onClick={() => handlePageChange(currentPage - 1)}
                disabled={currentPage === 1}
                variant="secondary"
                size="sm"
                >
                Previous
                </Button>
                <Button
                onClick={() => handlePageChange(currentPage + 1)}
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
                    onClick={() => handlePageChange(currentPage - 1)}
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
                    onClick={() => handlePageChange(currentPage + 1)}
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

  const renderCancellationConfirmation = () => {
    if (!cancellationState.showConfirmation) return null;
    
    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-white rounded-lg p-6 max-w-md w-full">
          <h3 className="text-lg font-semibold text-red-700 mb-2">Cancel Appointment</h3>
          
          <p className="mb-4 text-gray-700">
            Are you sure you want to cancel this appointment? This action cannot be undone.
          </p>
          
          <div className="mb-4">
            <label htmlFor="cancel-reason" className="block text-sm font-medium text-gray-700 mb-1">
              Reason for cancellation
            </label>
            <textarea
              id="cancel-reason"
              rows={3}
              className="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Please provide a reason for cancelling this appointment"
              value={cancellationState.reason}
              onChange={handleReasonChange}
            ></textarea>
          </div>
          
          {cancellationState.error && (
            <div className="bg-red-50 p-3 rounded-md border border-red-200 mb-4 text-red-700 text-sm">
              {cancellationState.error}
            </div>
          )}
          
          <div className="flex space-x-3 justify-end">
            <Button
              onClick={handleCancelDismiss}
              disabled={cancellationState.isLoading}
              className="bg-gray-200 hover:bg-gray-300 text-gray-800"
            >
              Keep Appointment
            </Button>
            
            <Button
              onClick={handleCancelConfirm}
              disabled={cancellationState.isLoading}
              className="bg-red-600 hover:bg-red-700 text-white"
            >
              {cancellationState.isLoading ? (
                <>
                  <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Processing...
                </>
              ) : "Yes, Cancel Appointment"}
            </Button>
          </div>
        </div>
      </div>
    );
  };

// Helper function to check if data is valid for rendering
const hasValidAppointmentData = () => {
  // Check if we have a non-empty array
  if (!Array.isArray(appointments) || appointments.length === 0) {
    return false;
  }
  
  // Even if we have empty objects, we should try to display them
  // The rendering code has fallbacks for missing properties
  return true;
};

// Remove unused effect that doesn't perform any actions

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-3xl font-bold">Appointments</h1>
            <p className="text-blue-100 mt-2">
              Manage patient appointments and scheduling
            </p>
          </div>
          <div className="relative group">
            <Link to={role === 'patient' ? "/doctors" : "#"}>
              <Button 
                variant="secondary"
                className={`mt-4 md:mt-0 bg-white hover:bg-gray-100 text-blue-700 ${
                  role !== 'patient' ? 'cursor-not-allowed opacity-50' : ''
                }`}
                disabled={role !== 'patient'}
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Book Appointment
              </Button>
            </Link>
            {role !== 'patient' && (
              <div className="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg shadow-lg whitespace-nowrap z-10">
                Only patients can book appointments with doctors
                <div className="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
              </div>
            )}
          </div>
        </div>
      </div>
      
      <Card>
        {/* Success message */}
        {successMessage && (
          <StatusMessage 
            type="success"
            message={successMessage}
            duration={5000}
            onDismiss={() => setSuccessMessage('')}
          />
        )}

        {/* Search and filters */}
        <div className="mb-6">
          <form onSubmit={handleSearch} className="flex flex-col space-y-4 mb-4">
            <div className="flex flex-col md:flex-row gap-3">
              <div className="flex-grow">
                <input
                  type="text"
                  placeholder="Search by patient or doctor name..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                />
              </div>
              <Button type="submit" variant="secondary" className="whitespace-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
                </svg>
                Search
              </Button>
            </div>
          </form>

          <div className="flex flex-col md:flex-row md:items-center gap-3 mb-4">
            <div className="md:w-1/4">
              <label htmlFor="statusFilter" className="flex items-center space-x-2 text-sm font-medium text-gray-700 mb-1">
                <span>Filter by Status</span>
                {filterStatus !== 'all' && (
                  <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">Active</span>
                )}
              </label>
              <div className="relative">
                <select
                  id="statusFilter"
                  value={filterStatus}
                  onChange={(e) => handleFilterChange(e.target.value)}
                  className={`w-full pl-3 pr-10 py-2 border rounded-md leading-5 bg-white focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm ${
                    filterStatus !== 'all' 
                      ? 'border-blue-500 bg-blue-50' 
                      : 'border-gray-300'
                  }`}
                >
                  <option value="all">All Appointments</option>
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
            </div>
            
            <div className="md:w-1/4">
              <label htmlFor="perPage" className="block text-sm font-medium text-gray-700 mb-1">
                Rows Per Page
              </label>
              <select
                  id="perPage"
                  value={perPage}
                  onChange={(e) => {
                      setPerPage(Number(e.target.value));
                      setCurrentPage(1);
                  }}
                className="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
              >
                <option value={5}>5</option>
                <option value={10}>10</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>
          </div>
        </div>

        {/* Error message */}
        {error && (
          <div className="bg-red-50 p-4 mb-6 rounded-md border border-red-200 text-red-700">
            <div className="flex">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
              </svg>
              {error}
            </div>
          </div>
        )}

        {/* Loading state */}
        {loading ? (
          <div className="flex justify-center p-8">
            <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary-500"></div>
          </div>
        ) : hasValidAppointmentData() ? (
          <div className="overflow-x-auto rounded-md border border-gray-200">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th 
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                    onClick={() => handleSort('date')}
                  >
                    <div className="flex items-center">
                      Date & Time
                      {sortBy === 'date' && (
                        <svg 
                          xmlns="http://www.w3.org/2000/svg" 
                          className={`ml-1 h-4 w-4 ${sortDirection === 'desc' ? 'transform rotate-180' : ''}`} 
                          fill="none" 
                          viewBox="0 0 24 24" 
                          stroke="currentColor"
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                        </svg>
                      )}
                    </div>
                  </th>
                  {role !== 'patient' && (
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Patient
                    </th>
                  )}
                  {role !== 'doctor' && (
                    <th 
                      className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('doctor')}
                    >
                      <div className="flex items-center">
                        Doctor
                        {sortBy === 'doctor' && (
                          <svg 
                            xmlns="http://www.w3.org/2000/svg" 
                            className={`ml-1 h-4 w-4 ${sortDirection === 'desc' ? 'transform rotate-180' : ''}`} 
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke="currentColor"
                          >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                          </svg>
                        )}
                      </div>
                    </th>
                  )}
                  <th 
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                    onClick={() => handleSort('status')}
                  >
                    <div className="flex items-center">
                      Status
                      {sortBy === 'status' && (
                        <svg 
                          xmlns="http://www.w3.org/2000/svg" 
                          className={`ml-1 h-4 w-4 ${sortDirection === 'desc' ? 'transform rotate-180' : ''}`} 
                          fill="none" 
                          viewBox="0 0 24 24" 
                          stroke="currentColor"
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                        </svg>
                      )}
                    </div>
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Reason
                  </th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {appointments.map((appointment) => (
                  <tr key={appointment.ID} className="hover:bg-gray-50">
                    <td className="px-4 py-3 whitespace-nowrap text-sm">
                      {formatDateTime(appointment.appointment_date, appointment.appointment_time)}
                    </td>
                    {role !== 'patient' && (
                      <td className="px-4 py-3 whitespace-nowrap text-sm">
                        {appointment.patient_name || "Unknown Patient"}
                      </td>
                    )}
                    {role !== 'doctor' && (
                      <td className="px-4 py-3 whitespace-nowrap text-sm">
                        {appointment.doctor_name ? `Dr. ${appointment.doctor_name}` : "Unknown Doctor"}
                      </td>
                    )}
                    <td className="px-4 py-3 whitespace-nowrap text-sm">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusBadgeClass(appointment.status)}`}>
                        {appointment.status ? appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1) : 'Pending'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm">
                      <div className="max-w-xs truncate">{appointment.reason || 'No reason provided'}</div>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap text-right text-sm">
                      <div className="flex justify-end space-x-2">
                        <Link 
                          to={`/appointments/${appointment.ID}`} 
                          state={{ returnTo: 'appointments', returnPath: '/appointments' }}
                          className="inline-flex items-center px-2 5 py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                          Details
                        </Link>
                      
                        {appointment.status === 'pending' && (
                          <>
                            <div className="relative group">
                              <button
                                onClick={() => role !== 'patient' ? api.put(`/appointments/${appointment.ID}`, { status: 'confirmed' }).then(fetchAppointments) : null}
                                disabled={role === 'patient'}
                                className={`inline-flex items-center px-2.5 py-1.5 border text-xs font-medium rounded focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                                  role === 'patient'
                                    ? 'border-gray-300 text-gray-400 bg-gray-100 cursor-not-allowed'
                                    : 'border-green-300 text-green-700 bg-green-50 hover:bg-green-100 focus:ring-green-500'
                                }`}
                              >
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Confirm
                              </button>
                              {role === 'patient' && (
                                <div className="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg shadow-lg whitespace-nowrap z-10">
                                  The doctor will need to confirm your appointment
                                  <div className="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                </div>
                              )}
                            </div>
                            <button
                              onClick={() => handleCancelClick(appointment.ID)}
                              className="inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                              </svg>
                              Cancel
                            </button>
                          </>
                        )}
                        {appointment.status === 'confirmed' && (
                          <>
                            <button
                              onClick={() => api.put(`/appointments/${appointment.ID}`, { status: 'completed' }).then(fetchAppointments)}
                              className="inline-flex items-center px-2.5 py-1.5 border border-purple-300 text-xs font-medium rounded text-purple-700 bg-purple-50 hover:bg-purple-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                              Mark Completed
                            </button>
                            <button
                              onClick={() => handleCancelClick(appointment.ID)}
                              className="inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                              </svg>
                              Cancel
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="py-10 text-center text-gray-500 bg-gray-50 rounded-md border border-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-10 w-10 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p className="text-lg font-medium mb-1">No appointments found</p>
            <p className="text-sm">
              {filterStatus !== 'all' 
                ? `You don't have any ${filterStatus} appointments.` 
                : "You don't have any appointments yet."}
            </p>
            <div className="relative group inline-block">
              <Link to={role === 'patient' ? "/doctors" : "#"}>
                <Button 
                  variant="primary"
                  className={`inline-flex items-center px-4 py-2 mt-4 border border-transparent text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                    role === 'patient'
                      ? 'text-white bg-primary-600 hover:bg-primary-700 focus:ring-primary-500'
                      : 'text-gray-400 bg-gray-300 cursor-not-allowed'
                  }`}
                  disabled={role !== 'patient'}
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                  Book an Appointment
                </Button>
              </Link>
              {role !== 'patient' && (
                <div className="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg shadow-lg whitespace-nowrap z-10">
                  Only patients can book appointments with doctors
                  <div className="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Pagination */}
        {!loading && hasValidAppointmentData() && renderPagination()}
      </Card>
      
      {renderCancellationConfirmation()}
    </div>
  );
};

export default Appointments;

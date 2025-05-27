import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
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
  const [sortDirection, setSortDirection] = useState('asc');
  
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

// Update the useEffect to initialize from URL parameters
  useEffect(() => {
    // Wait for auth to complete before proceeding
    if (!authLoading) {
      // Get initial parameters from URL if available
      const urlParams = getUrlParams();
      
      // Set initial state from URL parameters
      if (urlParams.page) {
        setCurrentPage(parseInt(urlParams.page, 10));
      }
      
      if (urlParams.per_page) {
        setPerPage(parseInt(urlParams.per_page, 10));
      }
      
      if (urlParams.status) {
        setFilterStatus(urlParams.status);
      }
      
      // Fetch appointments with these parameters, ensuring we get all appointments if no status specified
      fetchAppointments(urlParams.status ? urlParams : {...urlParams, status: null});
    }
  }, [user, authLoading]);

  const fetchAppointments = async (paramsOverride = null) => {
    setLoading(true);
    setError('');
    
    try {
      // Use provided parameters or build from state
      const params = paramsOverride || {
        page: currentPage,
        per_page: perPage,
        status: filterStatus !== 'all' ? filterStatus : null
      };
      
      // Make sure we have reasonable values
      const apiParams = {
        page: params.page || 1,
        per_page: params.per_page || 10
      };
      
      // Only add status filter if explicitly set and not 'all'
      if (params.status && params.status !== 'all') {
        apiParams.status = params.status;
      }
      
      // Update URL parameters but don't include status=all
      const urlParams = {...apiParams};
      if (urlParams.status === 'all') {
        delete urlParams.status;
      }
      updateUrlParams(urlParams);
      
      // Use api directly instead of appointmentService
      const response = await api.get('/appointments', { params: apiParams });
      
      // Handle the response format with pagination metadata
      if (response.data) {
        if (response.data.data) {
          // We have a paginated response
          setAppointments(response.data.data);
          
          // Set pagination data
          const meta = response.data.meta || {};
          setTotalPages(meta.last_page || 1);
          setTotalAppointments(meta.total || 0);
          
          // Update current page if it's provided in the response and different
          if (meta.current_page && Number(meta.current_page) !== currentPage) {
            setCurrentPage(Number(meta.current_page));
          }
          
          // Update per_page if it's provided and different
          if (meta.per_page && Number(meta.per_page) !== perPage) {
            setPerPage(Number(meta.per_page));
          }
        } else {
          // Legacy response format without pagination
          setAppointments(response.data);
          setTotalPages(1);
          setTotalAppointments(response.data.length);
        }
      }
    } catch (err) {
      console.error('Error fetching appointments:', err);
      setError('Failed to load appointments. Please try again later.');
    } finally {
      setLoading(false);
    }
  };

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

  // These methods are no longer needed since filtering and sorting are done on the backend
  // Keeping them commented for reference in case we need to implement client-side filtering again
  /*
  // Filter appointments based on selected status
  const getFilteredAppointments = () => {
    if (filterStatus === 'all') {
      return appointments;
    }
    return appointments.filter(app => app.status === filterStatus);
  };

  // Sort appointments
  const getSortedAppointments = () => {
    // We don't need filtering or sorting logic here anymore
    // as we get paginated data from the backend
    return appointments;
  };
  */

// Handle page navigation
const handlePageChange = (page) => {
  // Only change the page if it's different from the current page and within range
  if (page !== currentPage && page >= 1 && page <= totalPages) {
    setCurrentPage(page);
    
    // Show loading spinner
    setLoading(true);
    
    // Create parameters for the API call
    const params = {
      page: page,
      per_page: perPage
    };
    
    // Only add status filter if not 'all'
    if (filterStatus !== 'all') {
      params.status = filterStatus;
    }
    
    // Fetch appointments with the new page
    fetchAppointments(params);
    
    // Scroll to top of the table for better UX
    window.scrollTo({
      top: document.querySelector('table')?.getBoundingClientRect().top + window.pageYOffset - 100,
      behavior: 'smooth'
    });
  }
};

// Handle filter status changes
const handleFilterChange = (newStatus) => {
  // Update the filter status state
  setFilterStatus(newStatus);
  
  // Always reset to page 1 when changing filters
  setCurrentPage(1); 
  
  // Create new parameters for the API call
  const params = {
    page: 1,
    per_page: perPage
  };
  
  // Only add status if not 'all'
  if (newStatus !== 'all') {
    params.status = newStatus;
  }
  
  // Fetch appointments with the new filter
  fetchAppointments(params);
};

// Handle per page selection changes
const handlePerPageChange = (newPerPage) => {
  // Convert to number and update state
  const perPageValue = Number(newPerPage);
  setPerPage(perPageValue);
  
  // Always reset to page 1 when changing items per page
  setCurrentPage(1); 
  
  // Create new parameters for the API call
  const params = {
    page: 1,
    per_page: perPageValue
  };
  
  // Only add status filter if not 'all'
  if (filterStatus !== 'all') {
    params.status = filterStatus;
  }
  
  // Fetch appointments with the new per_page
  fetchAppointments(params);
};

// Function to update URL parameters without page reload
const updateUrlParams = (params) => {
  const url = new URL(window.location.href);
  const previousParams = new URLSearchParams(url.search).toString();
  
  // Update or add each parameter
  Object.keys(params).forEach(key => {
    if (params[key] !== null && params[key] !== undefined) {
      url.searchParams.set(key, params[key]);
    } else {
      url.searchParams.delete(key);
    }
  });
  
  const newParams = url.searchParams.toString();
  
  // Replace current URL without reloading the page
  window.history.replaceState({}, '', url.toString());
};

// Function to read URL parameters
const getUrlParams = () => {
  const searchParams = new URLSearchParams(window.location.search);
  const params = {};
  
  // Get pagination parameters from URL if they exist
  if (searchParams.has('page')) {
    params.page = parseInt(searchParams.get('page'), 10);
  }
  
  if (searchParams.has('per_page')) {
    params.per_page = parseInt(searchParams.get('per_page'), 10);
  }
  
  if (searchParams.has('status')) {
    params.status = searchParams.get('status');
  }
  
  return params;
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

  // Fix the renderPagination method for better visibility and clarity
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
    <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
      <div className="flex sm:flex-1 sm:items-center sm:justify-between">
        <div>
          <p className="text-sm text-gray-700">
            Showing <span className="font-medium">{totalAppointments > 0 ? (currentPage - 1) * perPage + 1 : 0}</span> to{' '}
            <span className="font-medium">{Math.min(currentPage * perPage, totalAppointments)}</span> of{' '}
            <span className="font-medium">{totalAppointments}</span> appointments
          </p>
        </div>
        
        <div className="flex items-center space-x-1 mt-3 sm:mt-0">
          {/* Per page selector */}
          <div className="mr-4">
            <select
              value={perPage}
              onChange={(e) => {
                handlePerPageChange(e.target.value);
              }}
              className="border border-gray-300 rounded-md text-sm p-1"
            >
              <option value={5}>5 per page</option>
              <option value={10}>10 per page</option>
              <option value={25}>25 per page</option>
              <option value={50}>50 per page</option>
            </select>
          </div>
          
          {/* Previous page button */}
          {currentPage > 1 && (
            <button
              onClick={() => handlePageChange(currentPage - 1)}
              className="relative inline-flex items-center px-2 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
            >
              <span className="sr-only">Previous</span>
              <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fillRule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clipRule="evenodd" />
              </svg>
            </button>
          )}
          
          {/* First page + ellipsis */}
          {startPage > 1 && (
            <>
              <button
                onClick={() => handlePageChange(1)}
                className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                1
              </button>
              {startPage > 2 && <span className="px-2 text-gray-500">...</span>}
            </>
          )}
          
          {/* Page numbers */}
          {pages.map(page => (
            <button
              key={page}
              onClick={() => handlePageChange(page)}
              className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                page === currentPage
                  ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                  : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
              }`}
            >
              {page}
            </button>
          ))}
          
          {/* Last page + ellipsis */}
          {endPage < totalPages && (
            <>
              {endPage < totalPages - 1 && <span className="px-2 text-gray-500">...</span>}
              <button
                onClick={() => handlePageChange(totalPages)}
                className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                {totalPages}
              </button>
            </>
          )}
          
          {/* Next page button */}
          {currentPage < totalPages && (
            <button
              onClick={() => handlePageChange(currentPage + 1)}
              className="relative inline-flex items-center px-2 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
            >
              <span className="sr-only">Next</span>
              <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
              </svg>
            </button>
          )}
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

// Make the appointment list component simpler and ensure the pagination is visible
// Make the appointment list component simpler and ensure the pagination is visible
const renderAppointmentList = () => {
  if (loading) {
    return (
      <div className="flex justify-center py-8">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 p-4 rounded-md border border-red-200 text-red-700">
        <div className="flex">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
          {error}
        </div>
      </div>
    );
  }

  return (
    <>
      <div className="flex flex-wrap gap-4 mb-4">
        <div className="flex flex-wrap gap-4">
          <div>
            <label htmlFor="statusFilter" className="block text-sm font-medium text-gray-700 mb-1">Filter by Status</label>
            <select
              id="statusFilter"
              value={filterStatus}
              onChange={(e) => handleFilterChange(e.target.value)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              <option value="all">All Appointments</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          
          <div>
            <label htmlFor="perPage" className="block text-sm font-medium text-gray-700 mb-1">Items per page</label>
            <select
              id="perPage"
              value={perPage}
              onChange={(e) => handlePerPageChange(e.target.value)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              <option value={5}>5</option>
              <option value={10}>10</option>
              <option value={25}>25</option>
              <option value={50}>50</option>
            </select>
          </div>
        </div>
      </div>

      {appointments.length === 0 ? (
        <div className="text-center py-8">
          <p className="text-gray-500 mb-4">
            {filterStatus !== 'all' 
              ? `You don't have any ${filterStatus} appointments.` 
              : "You don't have any appointments yet."}
          </p>
          <Link to="/doctors">
            <Button
              variant="primary" 
              className="px-4 py-2"
            >
              Book an Appointment
            </Button>
          </Link>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            {/* ... table header and content remain unchanged ... */}
            <thead className="bg-gray-50">
              <tr>
                <th 
                  className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
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
                {/* ...rest of table header remains the same... */}
                {role !== 'patient' && (
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Patient
                  </th>
                )}
                {role !== 'doctor' && (
                  <th 
                    className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
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
                  className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
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
                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Reason
                </th>
                <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
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
                        className="inline-flex items-center px-2 py-1 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Details
                      </Link>
                    
                      {appointment.status === 'pending' && (
                        <>
                          <button
                            onClick={() => api.put(`/appointments/${appointment.ID}`, { status: 'confirmed' }).then(fetchAppointments)}
                            className="inline-flex items-center px-2 py-1 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            Confirm
                          </button>
                          <button
                            onClick={() => handleCancelClick(appointment.ID)}
                            className="inline-flex items-center px-2 py-1 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
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
                            className="inline-flex items-center px-2 py-1 border border-purple-300 text-xs font-medium rounded text-purple-700 bg-purple-50 hover:bg-purple-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Mark Completed
                          </button>
                          <button
                            onClick={() => handleCancelClick(appointment.ID)}
                            className="inline-flex items-center px-2 py-1 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
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
      )}
      
      {/* Always render pagination component for consistency */}
      {renderPagination()}
    </>
  );
};

// Remove unused effect that doesn't perform any actions

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold">Appointments</h1>
        <Link to="/doctors">
          <Button 
            variant="primary"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Book Appointment
          </Button>
        </Link>
      </div>
      <Card>
        {renderAppointmentList()}
      </Card>
      {renderCancellationConfirmation()}
    </div>
  );
};

export default Appointments;

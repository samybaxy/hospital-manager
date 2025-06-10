import React, { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import StatusMessage from '../../components/StatusMessage';
import ResponsiveTable from '../../components/ResponsiveTable';

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
  const [successMessage, setSuccessMessage] = useState('');
  const [manualFetchRequested, setManualFetchRequested] = useState(false);
  
  // Use a ref to track if this is the first render
  const isFirstRender = useRef(true);
  
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
            per_page: perPage,
            sort_by: sortField,
            sort_order: sortOrder
        };
        
        // Only add search parameter if it's not empty
        if (searchTerm && searchTerm.trim() !== '') {
            params.search = searchTerm.trim();
        } else {
            // Make sure to clear any search parameter when empty
            params.search = '';
        }
        
        const response = await api.get('/visitations', { params });
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
  }, [currentPage, perPage, searchTerm, sortField, sortOrder, role, user?.ID]);

  // Extract URL parameters for messages - this needs to run to catch navigation changes
  // like coming back from EditVisit
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const successParam = params.get('success');
    
    if (successParam) {
      console.log('Found success message in URL:', decodeURIComponent(successParam));
      setSuccessMessage(decodeURIComponent(successParam));
      
      // Clear success parameter from URL without page reload
      const newUrl = window.location.pathname + 
        window.location.search.replace(/[&?]success=[^&]+/, '').replace(/\?$/, '');
      window.history.replaceState({}, document.title, newUrl);
    }
  }, []);

  useEffect(() => {
    // Only fetch on first render or if manual fetch was requested
    if (isFirstRender.current || manualFetchRequested) {
      const isFirstLoad = isFirstRender.current;
      isFirstRender.current = false;
      
      const loadVisitations = async () => {
        try {
          await fetchVisitations();
          
          // Set "data loaded" message only on initial page load and if no other success message exists
          if (!successMessage && isFirstLoad) {
            setSuccessMessage('Visitations loaded successfully');
          } else if (!successMessage && manualFetchRequested) {
            setSuccessMessage('Visitation data refreshed');
          }
        } catch (error) {
          console.error('Error in visitation data loading effect:', error);
        }
      };
      
      loadVisitations();
      
      // Reset the flag after the effect runs
      if (manualFetchRequested) {
        setManualFetchRequested(false);
      }
    }
  }, [fetchVisitations, manualFetchRequested, successMessage]);

  // Handle column sorting
  const handleSort = (field) => {
    if (sortField === field) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortOrder('asc');
    }
    setCurrentPage(1); // Reset to first page when sorting
    setManualFetchRequested(true); // Set flag to trigger fetch on next render
  };

  // Handle pagination
  const handlePageChange = (page) => {
    setCurrentPage(page);
    setManualFetchRequested(true); // Set flag to trigger fetch after page change
  };

  const handlePreviousPage = () => {
    setCurrentPage((prev) => Math.max(prev - 1, 1));
    setManualFetchRequested(true); // Set flag to trigger fetch after page change
  };

  const handleNextPage = () => {
    setCurrentPage((prev) => Math.min(prev + 1, totalPages));
    setManualFetchRequested(true); // Set flag to trigger fetch after page change
  };

  // Sorting indicator component
  const SortIndicator = ({ field }) => {
    if (sortField !== field) return null;
    return (
      <span className="ml-1 inline-block">
        {sortOrder === 'asc' ? 
          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clipRule="evenodd" />
          </svg> : 
          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
          </svg>
        }
      </span>
    );
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
           // Find the visit details to include in the success message
      const deletedVisit = visitations.find(v => v.ID === visitID);
      const patientName = deletedVisit?.patient_name || `Patient #${deletedVisit?.patient_id || visitID}`;
      
      // Show success message with patient details
      setSuccessMessage(`Visit #${visitID} for ${patientName} has been successfully deleted`);
      
      // If we're on a paginated view and this was the last item on the page,
      // we might need to go to the previous page
      if (visitations.length === 1 && currentPage > 1) {
        setCurrentPage(currentPage - 1);
      } else {
        // Otherwise just refresh the current page
        fetchVisitations();
      }
    } else {
      throw new Error(response.data?.message || 'Failed to delete visit');
    }
  } catch (err) {
    console.error('Error deleting visit:', err);
    setError(err.response?.data?.message || err.message || 'Failed to delete visit');        // Don't set the error message twice
    // Only need the first setError call above
    
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

  // Check if user can add lab investigations
  const canAddLabInvestigation = () => {
    return role === 'lab_tech' || role === 'administrator';
  };

  // Handle redirecting to lab investigations page with visit data
  const handleAddLabInvestigation = (visitation) => {
    // Navigate to LabInvestigations page with query parameters
    const params = new URLSearchParams({
      action: 'add',
      visitation_id: visitation.ID,
      patient_id: visitation.patient_id,
      doctor_id: visitation.doctor_id,
      lab_tech_id: user?.ID || '',
      patient_name: visitation.patient_name || '',
      doctor_name: visitation.doctor_name || ''
    });
    
    navigate(`/lab-investigations?${params.toString()}`);
  };
  
  // Function to check if data is valid for rendering
  const hasValidVisitationData = () => {
    // Check if we have a non-empty array
    if (!Array.isArray(visitations) || visitations.length === 0) {
      console.log('No valid visitation data found:', visitations);
      return false;
    }
    
    // Even if we have empty objects, we should try to display them
    // The rendering code has fallbacks for missing properties
    return true;
  };

  // Function to render success messages if not already defined
  const renderSuccessMessage = () => {
    if (!successMessage) return null;
    
    return (
      <div className="mb-6">
        <StatusMessage 
          type="success"
          message={successMessage}
          duration={5000}
          onDismiss={() => setSuccessMessage('')}
        />
      </div>
    );
  };

  // Table columns configuration
  const columns = useMemo(() => [
    {
      id: 'serial',
      header: 'S/N',
      accessorFn: (row, index) => ((currentPage - 1) * perPage) + index + 1,
      cell: ({ getValue }) => (
        <span className="text-sm text-gray-500 font-medium">
          {getValue()}
        </span>
      ),
      meta: { hideOnMobile: true, hideOnTablet: false },
      size: 60,
    },
    {
      id: 'patient',
      header: 'Patient',
      accessorKey: 'patient_name',
      cell: ({ row }) => {
        const visitation = row.original;
        const patientName = visitation.patient_name || `Patient #${visitation.patient_id}`;
        
        return (
          <div className="flex items-center min-w-0">
            <div className="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center mr-3 text-gray-600 font-medium text-xs flex-shrink-0">
              {patientName.charAt(0).toUpperCase()}
            </div>
            <div className="min-w-0">
              <div className="text-sm font-medium text-gray-900 truncate">
                {patientName}
              </div>
            </div>
          </div>
        );
      },
      meta: { hideOnMobile: false },
      size: 200,
    },
    {
      id: 'doctor',
      header: 'Doctor',
      accessorKey: 'doctor_name',
      cell: ({ row }) => {
        const visitation = row.original;
        return (
          <div className="text-sm font-medium text-gray-900 truncate">
            {visitation.doctor_name || `Doctor #${visitation.doctor_id}`}
          </div>
        );
      },
      meta: { hideOnMobile: true, hideOnTablet: false },
      size: 150,
    },
    {
      id: 'diagnosis',
      header: 'Diagnosis',
      accessorKey: 'diagnosis',
      cell: ({ getValue }) => {
        const diagnosis = getValue();
        return (
          <div className="text-sm text-gray-900 max-w-xs" title={diagnosis}>
            {diagnosis 
              ? (diagnosis.length > 21 
                  ? `${diagnosis.substring(0, 21)}...` 
                  : diagnosis)
              : '-'
            }
          </div>
        );
      },
      meta: { hideOnMobile: true, hideOnTablet: true },
      size: 200,
    },
    {
      id: 'visitDate',
      header: 'Visit Date',
      accessorFn: row => formatDateTime(row.date, row.time),
      cell: ({ row }) => {
        const visitation = row.original;
        return (
          <div className="text-sm font-medium text-gray-900">
            {formatDateTime(visitation.date, visitation.time)}
          </div>
        );
      },
      meta: { hideOnMobile: false },
      size: 150,
    },
    {
      id: 'actions',
      header: 'Actions',
      cell: ({ row }) => {
        const visitation = row.original;
        
        return (
          <div className="flex space-x-1 justify-end">
            <Button 
              variant="secondary" 
              size="sm" 
              onClick={() => handleViewVisit(visitation.ID)}
              className="inline-flex items-center px-2 py-1 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              View
            </Button>
            {canEditVisit(visitation) && (
              <Button 
                variant="primary" 
                size="sm" 
                onClick={() => handleEditVisit(visitation.ID)}
                className="inline-flex items-center px-2 py-1 border border-indigo-300 text-xs font-medium rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
              </Button>
            )}
            {canAddLabInvestigation() && (
              <Button 
                variant="success" 
                size="sm" 
                onClick={() => handleAddLabInvestigation(visitation)}
                className="inline-flex items-center px-2 py-1 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Lab
              </Button>
            )}
            {canDeleteVisit() && (
              <Button 
                variant="danger" 
                size="sm" 
                onClick={() => handleDeleteVisit(visitation.ID)} 
                disabled={deleteLoading === visitation.ID}
                className="inline-flex items-center px-2 py-1 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                {deleteLoading === visitation.ID ? '...' : 'Del'}
              </Button>
            )}
          </div>
        );
      },
      meta: { hideOnMobile: false },
      size: 300,
    },
  ], [currentPage, perPage, canEditVisit, canAddLabInvestigation, canDeleteVisit, handleViewVisit, handleEditVisit, handleAddLabInvestigation, handleDeleteVisit, deleteLoading, formatDateTime]);

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mx-4 md:mx-6">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-3xl font-bold">Patient Visitations</h1>
            <p className="text-blue-100 mt-2">
              {role === 'patient' 
                ? 'View your visit history and medical records' 
                : 'Manage patient visitations and medical consultations'
              }
            </p>
          </div>
          {hasAccess('visitations', 'create') && (
            <Button 
              variant="secondary" 
              className="mt-4 md:mt-0 bg-white hover:bg-gray-100 text-blue-700"
              onClick={() => navigate('/visitations/new')}
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
              </svg>
              Add New Visit
            </Button>
          )}
        </div>
      </div>

      <Card>
        {/* Success message */}
        {renderSuccessMessage()}
        
        {/* Search and filters */}
        <div className="mb-6">
          <form onSubmit={(e) => { 
              e.preventDefault(); 
              setCurrentPage(1);
              // Reset search timeout to prevent race conditions
              if (window.searchTimeout) {
                clearTimeout(window.searchTimeout);
              } 
              setManualFetchRequested(true);
              // Immediate search on form submission
              fetchVisitations(); 
              console.log('Form submitted, searching with term:', searchTerm);
            }} className="flex flex-col space-y-4">
            <div className="flex flex-col md:flex-row gap-3">
              <div className="flex-grow">
                <input
                  type="text"
                  placeholder="Search by patient name, doctor name..."
                  className="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                  value={searchTerm}
                  onChange={(e) => {
                    const newSearchTerm = e.target.value;
                    setSearchTerm(newSearchTerm);
                    // Reset to page 1 whenever search changes
                    setCurrentPage(1);
                    // Set manual fetch request flag
                    setManualFetchRequested(true);
                    
                    // Clear any existing timeout
                    if (window.searchTimeout) {
                      clearTimeout(window.searchTimeout);
                    }
                    
                    // Set a timer to perform search automatically after user stops typing
                    window.searchTimeout = setTimeout(() => {
                      // Always fetch when the search field is empty (to show all results)
                      // or when there are at least 3 characters (for actual searching)
                      fetchVisitations();
                      console.log('Search triggered with term:', newSearchTerm);
                    }, 500);
                  }}
                />
              </div>
              <div className="flex space-x-2">
                <Button type="submit" variant="secondary" className="whitespace-nowrap px-2.5 py-1.5 text-xs">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
                  </svg>
                  Search
                </Button>
                <Button 
                  type="button" 
                  variant="secondary" 
                  className="whitespace-nowrap px-2.5 py-1.5 text-xs"
                  onClick={() => {
                    setSearchTerm('');
                    setCurrentPage(1);
                    setManualFetchRequested(true);
                    // Clear any search timeout
                    if (window.searchTimeout) {
                      clearTimeout(window.searchTimeout);
                    }
                    // Immediately fetch all records
                    fetchVisitations();
                  }}
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clipRule="evenodd" />
                  </svg>
                </Button>
              </div>
            </div>
            
            <div className="flex flex-col md:flex-row md:items-center gap-3">
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
                  className="block w-full pl-3 pr-10 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                >
                  <option value={5}>5</option>
                  <option value={10}>10</option>
                  <option value={25}>25</option>
                  <option value={50}>50</option>
                </select>
              </div>
            </div>
          </form>
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
            <Button 
              variant="primary" 
              className="mt-4"
              onClick={() => {
                setError(null);
                fetchVisitations();
              }}
            >
              Try Again
            </Button>
          </div>
        )}

        {/* Loading state */}
        {loading ? (
          <div className="flex justify-center p-8">
            <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary-500"></div>
          </div>
        ) : hasValidVisitationData() ? (
          <div className="overflow-x-auto rounded-md border border-gray-200">
            <ResponsiveTable
              data={visitations}
              columns={columns}
              rowKey="ID"
              manualPagination
              onPageChange={setCurrentPage}
              onPageSizeChange={setPerPage}
              pageSize={perPage}
              currentPage={currentPage}
              totalCount={totalRecords}
              loading={loading}
              renderRowActions={(row) => {
                const visitation = row.original;
                
                return (
                  <div className="flex space-x-1 justify-end">
                    <Button 
                      variant="secondary" 
                      size="sm" 
                      onClick={() => handleViewVisit(visitation.ID)}
                      className="inline-flex items-center px-2 py-1 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      View
                    </Button>
                    {canEditVisit(visitation) && (
                      <Button 
                        variant="primary" 
                        size="sm" 
                        onClick={() => handleEditVisit(visitation.ID)}
                        className="inline-flex items-center px-2 py-1 border border-indigo-300 text-xs font-medium rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                      </Button>
                    )}
                    {canAddLabInvestigation() && (
                      <Button 
                        variant="success" 
                        size="sm" 
                        onClick={() => handleAddLabInvestigation(visitation)}
                        className="inline-flex items-center px-2 py-1 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Lab
                      </Button>
                    )}
                    {canDeleteVisit() && (
                      <Button 
                        variant="danger" 
                        size="sm" 
                        onClick={() => handleDeleteVisit(visitation.ID)} 
                        disabled={deleteLoading === visitation.ID}
                        className="inline-flex items-center px-2 py-1 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        {deleteLoading === visitation.ID ? '...' : 'Del'}
                      </Button>
                    )}
                  </div>
                );
              }}
            />
          </div>
        ) : (
          <div className="py-10 text-center text-gray-500 bg-gray-50 rounded-md border border-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-10 w-10 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p className="text-lg font-medium mb-1">No visits found</p>
            <p className="text-sm">
              {searchTerm && searchTerm.trim() !== '' ? 
                'No visits match your search criteria. Try a different search term.' : 
                'There are no visit records in the system yet.'}
            </p>
            {hasAccess('visitations', 'create') && (
              <Button
                variant="primary"
                className="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                onClick={() => navigate('/visitations/new')}
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add a visit
              </Button>
            )}
          </div>
        )}

        {/* Pagination */}
        {!loading && hasValidVisitationData() && (
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-4 bg-white border-t border-gray-200 sm:px-6 mt-4">
            <div className="mb-4 sm:mb-0 text-sm text-gray-700">
              <p>
                Showing <span className="font-semibold">{((currentPage - 1) * perPage) + 1}</span>{' '}
                to <span className="font-semibold">{Math.min(currentPage * perPage, totalRecords)}</span>{' '}
                of <span className="font-semibold">{totalRecords}</span> visits
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
        
        {/* Export options */}
        {!loading && hasValidVisitationData() && (
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
            <div className="flex space-x-3">
              <Button 
                variant="secondary" 
                size="sm" 
                className="flex items-center"
                onClick={() => {
                  alert('Export to CSV feature will be implemented');
                }}
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export to CSV
              </Button>
              <Button 
                variant="secondary" 
                size="sm"
                className="flex items-center"
                onClick={() => {
                  alert('Print report feature will be implemented');
                }}
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-1 1v3M4 7h16" />
                </svg>
                Print Report
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
};

export default Visitations;
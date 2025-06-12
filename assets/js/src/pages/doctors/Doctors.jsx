import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { Link, useLocation } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import StatusMessage from '../../components/StatusMessage';
import ResponsiveTable from '../../components/ResponsiveTable';
import { api } from '../../services/apiService';
import { useUserAccess } from '../../hooks/useUserAccess';

const Doctors = () => {
  const location = useLocation();
  const { role, isAdministrator, isDeskOfficer } = useUserAccess();
  
  // Check if user can manage doctors (add/edit)
  const canManageDoctors = isAdministrator() || isDeskOfficer();
  
  // State management
  const [doctors, setDoctors] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalDoctors, setTotalDoctors] = useState(0);
  const [sortField, setSortField] = useState('last_name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [specialtyFilter, setSpecialtyFilter] = useState('all');
  const [specialtyOptions, setSpecialtyOptions] = useState([]);
  const [perPage, setPerPage] = useState(10);
  const [successMessage, setSuccessMessage] = useState('');
  const [showBookingTooltip, setShowBookingTooltip] = useState(false);
  const [showButtonHighlight, setShowButtonHighlight] = useState(false);

  // Table columns configuration
  const columns = useMemo(() => [
    {
      id: 'name',
      header: 'Name',
      accessorFn: row => `${row.first_name || ''} ${row.last_name || ''}`,
      cell: ({ row }) => {
        const doctor = row.original;
        const fullName = doctor.fullName || `${doctor.first_name} ${doctor.last_name}`;
        
        return (
          <div className="flex items-center min-w-0">
            <div className="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-blue-100 flex items-center justify-center mr-2 sm:mr-3 text-blue-600 font-medium text-xs sm:text-sm flex-shrink-0">
              {fullName.charAt(0).toUpperCase()}
            </div>
            <div className="min-w-0 flex-1">
              <div className="text-sm font-medium text-gray-900 truncate">
                {fullName}
              </div>
              <div className="text-xs sm:text-sm text-gray-500 truncate">
                ID: {doctor.ID}
              </div>
            </div>
          </div>
        );
      },
      meta: { hideOnMobile: false, hideOnTablet: false },
      size: 200,
    },
    {
      id: 'specialty',
      header: 'Specialty',
      accessorKey: 'specialty',
      cell: ({ getValue }) => (
        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 truncate">
          {getValue() || 'General'}
        </span>
      ),
      meta: { hideOnMobile: true, hideOnTablet: false },
      size: 150,
    },
    {
      id: 'phone',
      header: 'Phone',
      accessorKey: 'phone',
      cell: ({ getValue }) => (
        <span className="text-sm text-gray-900 truncate">
          {getValue() || '-'}
        </span>
      ),
      meta: { hideOnMobile: true, hideOnTablet: true },
      size: 120,
    },
    {
      id: 'actions',
      header: 'Actions',
      cell: ({ row }) => {
        const doctor = row.original;
        const doctorId = doctor.ID || doctor.id;
        
        return (
          <div className="flex justify-end space-x-1 sm:justify-center sm:space-x-2">
            <Link
              to={`/doctors/${doctor.ID}`}
              className="inline-flex items-center px-2 py-1 sm:px-2.5 sm:py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 sm:h-4 sm:w-4 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <span className="hidden sm:inline">View</span>
            </Link>
            {canManageDoctors && (
              <Link
                to={`/doctors/${doctor.ID}/edit`}
                className="inline-flex items-center px-2 py-1 sm:px-2.5 sm:py-1.5 border border-indigo-300 text-xs font-medium rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 sm:h-4 sm:w-4 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span className="hidden sm:inline">Edit</span>
              </Link>
            )}
            <Link
              to={`/appointments/book/${doctor.ID}`}
              className={`inline-flex items-center px-2 py-1 sm:px-2.5 sm:py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 ${showButtonHighlight ? 'ring-2 ring-green-500 ring-offset-2 animate-pulse' : ''}`}
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 sm:h-4 sm:w-4 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span className="hidden sm:inline">Book</span>
            </Link>
          </div>
        );
      },
      meta: { hideOnMobile: false, hideOnTablet: false, headerAlign: 'text-center' },
      size: 200,
    },
  ], [canManageDoctors, showButtonHighlight]);

  // Fetch doctors from API
  const fetchDoctors = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
        const params = {
            page: currentPage,
            per_page: perPage,
            search: searchTerm,
            orderby: sortField,
            order: sortOrder,
            specialty: specialtyFilter,
        }
        
        const response = await api.get('/doctors', { params });
        // Extract data and metadata from response
        // Extract data and metadata from response
        const responseData = response.data;
        // Handle both API response formats (nested or flat)
        if (responseData.data && responseData.data.doctors) {
            // New format with nested structure
            const { doctors } = responseData.data;
            setDoctors(doctors.items || []);
            setTotalDoctors(doctors.total || 0);
            setTotalPages(doctors.lastPage || 1);
            setCurrentPage(doctors.currentPage || 1);
        } else if (responseData.data && Array.isArray(responseData.data)) {
            // Original format with flat array and separate meta
            const { data, meta } = responseData;
            setDoctors(data || []);
            setTotalDoctors(meta?.total || 0);
            setTotalPages(meta?.last_page || 1);
            setCurrentPage(meta?.current_page || 1);
        } else {
            console.error('Unexpected API response format:', responseData);
            setDoctors([]);
            setTotalDoctors(0);
            setTotalPages(1);
            setCurrentPage(1);
        }
    } catch (err) {
      console.error('Error fetching doctors:', err);
      setError('Failed to fetch doctors. Please try again.');
    } finally {
      setLoading(false);
    }
  }, [currentPage, searchTerm, perPage, sortField, sortOrder, specialtyFilter]);

  // Keep track of manual fetch requests to prevent duplicate calls
  const [manualFetchRequested, setManualFetchRequested] = useState(false);

  // Initial load and refetch on dependency changes
  useEffect(() => {
    const loadDoctors = async () => {
      try {
        await fetchDoctors();
        
        // Only show success message once after data is loaded
        if (doctors.length > 0) {
          setSuccessMessage('Doctors data loaded successfully');
        }
      } catch (error) {
        console.error('Error in doctor data loading effect:', error);
      }
    };
    
    // Only fetch if not manual request
    if (!manualFetchRequested) {
      loadDoctors();
    }
    
    // Reset the flag after the effect runs
    setManualFetchRequested(false);
    
    // We don't need a return function here as we're not setting up any timers or subscriptions
  }, [fetchDoctors, manualFetchRequested, doctors.length]);
  
  // Check if user came from dashboard and show the booking tooltip and button highlights
  useEffect(() => {
    // Check if referrer is the dashboard
    const fromDashboard = location.state?.from === 'dashboard' || 
                          location.search.includes('from=dashboard');
    
    // Show tooltip and button highlight if coming from dashboard
    if (fromDashboard) {
      setShowBookingTooltip(true);
      setShowButtonHighlight(true);
      
      // Hide tooltip after 10 seconds but keep button highlight longer
      const tooltipTimer = setTimeout(() => {
        setShowBookingTooltip(false);
      }, 10000);
      
      // Keep button highlight effect for 20 seconds
      const buttonHighlightTimer = setTimeout(() => {
        setShowButtonHighlight(false);
      }, 20000);
      
      return () => {
        clearTimeout(tooltipTimer);
        clearTimeout(buttonHighlightTimer);
      };
    }
  }, [location]);

  // Fetch specialty options from API
  useEffect(() => {
    const fetchSpecialties = async () => {
      try {
        const response = await api.get('/doctors/specialties');
        setSpecialtyOptions(response.data || []);
      } catch (err) {
        console.error('Error fetching specialties:', err);
      }
    };
    
    fetchSpecialties();
  }, []);

  // Handle column sorting
  const handleSort = (field) => {
    if (field === sortField) {
      // Toggle order if same field is clicked
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      // Set new field and default to ascending
      setSortField(field);
      setSortOrder('asc');
    }
    
    // Reset to first page when sorting
    setCurrentPage(1);
  };

  // Handle search input
  const handleSearch = (e) => {
    const value = e.target.value;
    setSearchTerm(value);
    setCurrentPage(1); // Reset to first page when searching
    
    // If we have a debounce timer already, clear it
    if (window.searchTimer) {
      clearTimeout(window.searchTimer);
    }
    
    // Set a new debounce timer to trigger fetch after user stops typing
    window.searchTimer = setTimeout(() => {
      console.log('Search triggered for term:', value);
      setManualFetchRequested(prev => !prev); // Toggle to trigger refetch
    }, 500); // 500ms debounce
  };

  // Handle specialty filter
  const handleSpecialtyFilter = (e) => {
    setSpecialtyFilter(e.target.value);
    setCurrentPage(1); // Reset to first page
  };

  // Function to check if data is valid for rendering
  const hasValidDoctorData = () => {
    return Array.isArray(doctors) && doctors.length > 0;
  };

  // Pagination controls
  const handlePreviousPage = () => {
    if (currentPage > 1) {
      setCurrentPage(currentPage - 1);
    }
  };

  const handleNextPage = () => {
    if (currentPage < totalPages) {
      setCurrentPage(currentPage + 1);
    }
  };
  
  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  // Sorting indicator component
  const SortIndicator = ({ field }) => {
    if (field !== sortField) return null;
    
    return (
      <span className="ml-1">
        {sortOrder === 'asc' ? '↑' : '↓'}
      </span>
    );
  };

  // Pagination component
  const renderPagination = () => {
    if (totalPages <= 1) return null;
    
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
        <div className="mb-4 sm:mb-0 text-sm text-gray-700">
          <p>
            Showing <span className="font-semibold">{((currentPage - 1) * perPage) + 1}</span>{' '}
            to <span className="font-semibold">{Math.min(currentPage * perPage, totalDoctors)}</span>{' '}
            of <span className="font-semibold">{totalDoctors}</span> doctors
          </p>
        </div>
        
        <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-4">
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

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-4 md:p-6 text-white mobile-header-margin md:mx-0">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold">Doctors</h1>
            <p className="text-blue-100 mt-2">
              Manage doctor profiles, specialties, and scheduling
            </p>
          </div>
          {canManageDoctors && (
            <Link to="/doctors/new">
              <Button variant="secondary" className="mt-4 md:mt-0 bg-white hover:bg-gray-100 text-blue-700">
                <span className="flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
                  </svg>
                  Add New Doctor
                </span>
              </Button>
            </Link>
          )}
        </div>
      </div>

      {successMessage && (
        <StatusMessage 
          type="success"
          message={successMessage}
          duration={5000}
          onDismiss={() => setSuccessMessage('')}
        />
      )}

      <Card>
        <div className="mb-6">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between space-y-3 md:space-y-0 md:space-x-4">
            <div className="w-full">
              <label htmlFor="search" className="sr-only">
                Search Doctors
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg className="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
                  </svg>
                </div>
                <input
                  id="search"
                  name="search"
                  className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                  placeholder="Search doctors..."
                  type="search"
                  value={searchTerm}
                  onChange={handleSearch}
                />
              </div>
            </div>

            <div className="w-full md:w-1/4">
              <label htmlFor="specialty-filter" className="sr-only">
                Filter by Specialty
              </label>
              <select
                id="specialty-filter"
                name="specialty-filter"
                className="block w-full pl-3 pr-10 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                value={specialtyFilter}
                onChange={handleSpecialtyFilter}
              >
                <option value="all">All Specialties</option>
                {specialtyOptions.map((specialty, index) => (
                  <option key={index} value={specialty}>
                    {specialty}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {loading ? (
          <div className="flex justify-center items-center py-10">
            <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600"></div>
          </div>
        ) : error ? (
          <div className="bg-red-50 text-red-800 p-4 rounded-lg">
            {error}
          </div>
        ) : (
          <ResponsiveTable
            data={doctors}
            columns={columns}
            loading={loading}
            emptyMessage="No doctors found"
            showPagination={false} // We'll use custom pagination
          />
        )}
        
        {/* Booking tooltip */}
        {showBookingTooltip && (
          <div className="fixed inset-x-0 top-1/4 flex justify-center items-center pointer-events-none z-50">
            <div className="animate-bounce-slow w-64">
              <div className="relative bg-green-600 text-white p-3 rounded-lg shadow-lg">
                <div className="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-4 h-4 bg-green-600 rotate-45"></div>
                <p className="text-sm font-medium whitespace-normal text-center">
                  Click "Book Appointment" with any doctor
                </p>
              </div>
            </div>
          </div>
        )}
        
        {/* Pagination */}
        {renderPagination()}
      </Card>
    </div>
  );
};

export default Doctors;

import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import Card from '../../components/Card';
import Button from '../../components/Button';
import StatusMessage from '../../components/StatusMessage';
import ResponsiveTable from '../../components/ResponsiveTable';
import { Link } from 'react-router-dom';
import { api } from '../../services/apiService';

const Patients = () => {
  const [patients, setPatients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalPatients, setTotalPatients] = useState(0);
  const [sortField, setSortField] = useState('last_name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [hmoFilter, setHmoFilter] = useState('all');
  const [hmoOptions, setHmoOptions] = useState([]);
  const [perPage, setPerPage] = useState(10);
  const [successMessage, setSuccessMessage] = useState('');
  const isInitializedRef = useRef(false);

  // Table columns configuration
  const columns = useMemo(() => [
    {
      id: 'serialNumber',
      header: 'S/N',
      accessorFn: (row, index) => index + 1,
      cell: ({ getValue }) => (
        <span className="text-sm font-medium text-gray-900">
          {((currentPage - 1) * perPage) + getValue()}
        </span>
      ),
      meta: { hideOnMobile: true, hideOnTablet: true },
      size: 80,
      minSize: 80,
    },
    {
      id: 'patient',
      header: 'Patient',
      accessorFn: row => `${row.first_name || 'Unknown'} ${row.last_name || ''}`,
      cell: ({ row }) => {
        const patient = row.original;
        
        // Create initials
        let firstInitial = '?';
        let lastInitial = '';
        
        if (patient.first_name && typeof patient.first_name === 'string') {
          firstInitial = patient.first_name[0];
        }
        
        if (patient.last_name && typeof patient.last_name === 'string') {
          lastInitial = patient.last_name[0];
        }

        return (
          <div className="flex items-center min-w-0">
            <div className="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-gray-200 flex items-center justify-center mr-2 sm:mr-3 text-gray-600 font-medium text-xs sm:text-sm flex-shrink-0">
              {firstInitial}{lastInitial}
            </div>
            <div className="min-w-0 flex-1">
              <div className="text-sm font-medium text-gray-900 truncate">
                {patient.first_name || 'Unknown'} {patient.last_name || ''}
              </div>
              <div className="text-xs sm:text-sm text-gray-500 truncate">
                {patient.gender || 'Unknown'}, {patient.age || '-'} years
              </div>
            </div>
          </div>
        );
      },
      meta: { hideOnMobile: false, hideOnTablet: false },
      size: 200,
    },
    {
      id: 'contact',
      header: 'Contact',
      accessorFn: row => row.phone || row.email,
      cell: ({ row }) => {
        const patient = row.original;
        return (
          <div className="min-w-0">
            <div className="text-sm text-gray-900 truncate">{patient.phone || '-'}</div>
            <div className="text-xs text-gray-500 truncate">{patient.email || '-'}</div>
          </div>
        );
      },
      meta: { hideOnMobile: true, hideOnTablet: false },
      size: 150,
    },
    {
      id: 'hmo',
      header: 'HMO',
      accessorKey: 'hmo_name',
      cell: ({ getValue }) => {
        const hmoName = getValue();
        return (
          <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${
            hmoName
              ? 'bg-green-100 text-green-800'
              : 'bg-gray-100 text-gray-800'
          }`}>
            {hmoName || 'None'}
          </span>
        );
      },
      meta: { hideOnMobile: true, hideOnTablet: true },
      size: 120,
    },
    {
      id: 'lastVisit',
      header: 'Last Visit',
      accessorKey: 'last_visit_date',
      cell: ({ getValue }) => {
        const date = getValue();
        return (
          <span className="text-xs sm:text-sm text-gray-500 truncate">
            {date || '-'}
          </span>
        );
      },
      meta: { hideOnMobile: true, hideOnTablet: true },
      size: 100,
    },
    {
      id: 'actions',
      header: 'Actions',
      cell: ({ row }) => {
        const patient = row.original;
        const patientId = patient.ID || patient.id;
        
        return (
          <div className="flex justify-end space-x-1 sm:justify-center sm:space-x-2">
            <Link 
              to={`/patients/${patientId}`} 
              className="inline-flex items-center px-2 py-1 sm:px-2.5 sm:py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 sm:h-4 sm:w-4 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <span className="hidden sm:inline">View</span>
            </Link>
            <Link 
              to={`/patients/${patientId}/edit`} 
              className="inline-flex items-center px-2 py-1 sm:px-2.5 sm:py-1.5 border border-indigo-300 text-xs font-medium rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 sm:h-4 sm:w-4 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              <span className="hidden sm:inline">Edit</span>
            </Link>
            <Link 
              to={`/visitations/new/${patientId}`} 
              className="inline-flex items-center px-2 py-1 sm:px-2.5 sm:py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 sm:h-4 sm:w-4 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              <span className="hidden lg:inline">Add Visit</span>
            </Link>
          </div>
        );
      },
      meta: { hideOnMobile: false, hideOnTablet: false, headerAlign: 'text-center' },
      size: 150,
    },
  ], [currentPage, perPage]);

  const fetchPatients = useCallback(async () => {
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
      
      // Only add hmo_id if not "all"
      if (hmoFilter !== 'all') {
        params.hmo_id = Number(hmoFilter);
      }
      
      const response = await api.get('/patients', { params });
      if (response.data) {
        // Check if data is inside the "data" property (common REST API pattern)
        const responseData = response.data.data || response.data;
        
        if (responseData.patients && responseData.patients.items) {
          // Extract patient items from the nested structure
          const patientItems = responseData.patients.items || [];
          
          // Extract metadata for pagination
          setPatients(patientItems);
          setTotalPages(responseData.patients.lastPage || 1);
          setTotalPatients(responseData.patients.total || 0);
          console.log('Fetched patients:', responseData.patients.total);
        } else if (Array.isArray(responseData.patients)) {
          // Handle alternative API response format
          setPatients(responseData.patients);
          setTotalPages(responseData.total_pages || 1);
          setTotalPatients(responseData.total || 0);
        } else {
          console.error('Unexpected patient data format:', responseData);
          setError('Data format error. Please contact support.');
        }
      }
      
      // Show success message only on initial load
      if (!isInitializedRef.current) {
        setSuccessMessage('Patients data loaded successfully');
        isInitializedRef.current = true;
      }
    } catch (err) {
      console.error('Error fetching patients:', err);
      setError('Failed to load patients');
    } finally {
      setLoading(false);
    }
  }, [currentPage, searchTerm, perPage, sortField, sortOrder, hmoFilter]);

  // Data fetch when component mounts or when filters/pagination change
  useEffect(() => {
    fetchPatients();
  }, [fetchPatients]);
  
  // Fetch HMO options from the API
  useEffect(() => {
    const fetchHMOs = async () => {
      try {
        const response = await api.get('/hmos');
        if (response.data && response.data.data && response.data.data.hmos) {
          // Make sure we have proper numeric IDs for filtering
          const formattedHMOs = response.data.data.hmos.map(hmo => ({
            ID: Number(hmo.ID),
            name: hmo.name
          }));
          setHmoOptions(formattedHMOs);
        } else {
          console.error('Unexpected HMO data format:', response.data);
        }
      } catch (error) {
        console.error('Error fetching HMO options:', error);
      }
    };
    
    fetchHMOs();
  }, []);

  const handleSort = useCallback((field) => {
    setSortOrder(sortField === field && sortOrder === 'asc' ? 'desc' : 'asc');
    setSortField(field);
  }, [sortField, sortOrder]);

  const handleSearch = useCallback((e) => {
    e.preventDefault();
    setCurrentPage(1); // Reset to first page on new search
  }, []);

  const handleHmoFilter = useCallback((e) => {
    const value = e.target.value;
    
    // Convert to number if it's not 'all', otherwise keep as string 'all'
    const hmoValue = value === 'all' ? 'all' : Number(value);
    
    setHmoFilter(hmoValue);
    setCurrentPage(1); // Reset to first page when filtering
  }, []);
  
  // Function to check if data is valid for rendering
  const hasValidPatientData = useMemo(() => {
    // Check if we have a non-empty array
    if (!Array.isArray(patients) || patients.length === 0) {
      return false;
    }
    
    // Even if we have empty objects, we should try to display them
    // The rendering code has fallbacks for missing properties
    return true;
  }, [patients]);

  const handlePreviousPage = useCallback(() => {
    setCurrentPage((prev) => Math.max(prev - 1, 1));
  }, []);

  const handleNextPage = useCallback(() => {
    setCurrentPage((prev) => Math.min(prev + 1, totalPages));
  }, [totalPages]);
  
  const handlePageChange = useCallback((page) => {
    setCurrentPage(page);
  }, []);

  const handlePerPageChange = useCallback((e) => {
    setPerPage(Number(e.target.value));
    setCurrentPage(1);
  }, []);

  // Sorting indicator component
  const SortIndicator = React.memo(({ field }) => {
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
  });

  const renderPagination = useMemo(() => {
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
  }, [currentPage, totalPages, handlePageChange]);

  // Handle row click for navigation
  const handleRowClick = useCallback((patient) => {
    const patientId = patient.ID || patient.id;
    // You can navigate to patient details here if needed
    console.log('Patient clicked:', patient);
  }, []);

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-4 md:p-6 text-white md:mx-0">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold">Patients</h1>
            <p className="text-blue-100 mt-2">
              Manage patient records, personal information and medical history
            </p>
          </div>
          <Link to="/patients/new">
            <Button variant="secondary" className="mt-4 md:mt-0 bg-white hover:bg-gray-100 text-blue-700">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
              </svg>
              Add New Patient
            </Button>
          </Link>
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
          <form onSubmit={handleSearch} className="flex flex-col space-y-4">
            <div className="flex flex-col md:flex-row gap-3">
              <div className="flex-grow">
                <input
                  type="text"
                  placeholder="Search patients by name, ID, or phone number"
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
              <Button type="submit" variant="secondary" className="whitespace-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
                </svg>
                Search
              </Button>
            </div>
            
            <div className="flex flex-col md:flex-row md:items-center gap-3">
              <div className="md:w-1/4">
                <label htmlFor="hmoFilter" className="flex items-center space-x-2 text-sm font-medium text-gray-700 mb-1">
                  <span>HMO Filter</span>
                  {hmoFilter !== 'all' && (
                    <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">Active</span>
                  )}
                </label>
                <div className="relative">
                  <select
                    id="hmoFilter"
                    value={hmoFilter}
                    onChange={handleHmoFilter}
                    className={`w-full pl-3 pr-10 py-2 border rounded-md leading-5 bg-white focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm ${
                      hmoFilter !== 'all' 
                        ? 'border-blue-500 bg-blue-50' 
                        : 'border-gray-300'
                    }`}
                    disabled={!hmoOptions || hmoOptions.length === 0 || loading}
                  >
                    <option value="all">All HMOs</option>
                    {hmoOptions && hmoOptions.length > 0 ? (
                      hmoOptions.map(hmo => (
                        <option key={hmo.ID} value={hmo.ID}>{hmo.name}</option>
                      ))
                    ) : (
                      <option value="" disabled>Loading HMO options...</option>
                    )}
                  </select>
                  {(!hmoOptions || hmoOptions.length === 0 || loading) && (
                    <div className="absolute right-2 top-2">
                      <div className="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-primary-500"></div>
                    </div>
                  )}
                </div>
              </div>
              
              <div className="md:w-1/4">
                <label htmlFor="perPage" className="block text-sm font-medium text-gray-700 mb-1">
                  Rows Per Page
                </label>
                <select
                  id="perPage"
                  value={perPage}
                  onChange={handlePerPageChange}
                  className="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
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
            {error}
          </div>
        )}

        {/* Patients Table */}
        {hasValidPatientData ? (
          <ResponsiveTable
            data={patients}
            columns={columns}
            loading={loading}
            emptyMessage={searchTerm ? 'No patients match your search criteria. Try a different search term.' : 'There are no patients in the system yet.'}
            onRowClick={handleRowClick}
            enableSorting={true}
            enableFiltering={false}
            enablePagination={false}
            showPagination={false}
            pageSize={perPage}
          />
        ) : (
          <div className="py-10 text-center text-gray-500 bg-gray-50 rounded-md border border-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-10 w-10 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.5 12h5m-5 4h5m2-12H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2z" />
            </svg>
            <p className="text-lg font-medium mb-1">No patients found</p>
            <p className="text-sm">
              {searchTerm ? 'No patients match your search criteria. Try a different search term.' : 'There are no patients in the system yet.'}
            </p>
            <Link to="/patients/new" className="inline-flex items-center px-4 py-2 mt-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Add a patient
            </Link>
          </div>
        )}

        {/* Pagination */}
        {!loading && hasValidPatientData && (
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-4 bg-white border-t border-gray-200 sm:px-6 mt-4">
            <div className="mb-4 sm:mb-0 text-sm text-gray-700">
              <p>
                Showing <span className="font-semibold">{((currentPage - 1) * perPage) + 1}</span>{' '}
                to <span className="font-semibold">{Math.min(currentPage * perPage, totalPatients)}</span>{' '}
                of <span className="font-semibold">{totalPatients}</span> results
              </p>
            </div>
            
            <div className="flex-1 flex justify-between sm:hidden">
              <button
                onClick={handlePreviousPage}
                disabled={currentPage === 1}
                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md ${
                  currentPage === 1
                    ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed'
                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                }`}
              >
                Previous
              </button>
              <button
                onClick={handleNextPage}
                disabled={currentPage === totalPages}
                className={`ml-3 relative inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md ${
                  currentPage === totalPages
                    ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed'
                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                }`}
              >
                Next
              </button>
            </div>
            
            <div className="hidden sm:flex">
              <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <button
                  onClick={handlePreviousPage}
                  disabled={currentPage === 1}
                  className={`relative inline-flex items-center px-2 py-2 rounded-l-md border text-sm font-medium ${
                    currentPage === 1
                      ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed'
                      : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                  }`}
                >
                  <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                </button>
                
                {/* Page numbers */}
                {renderPagination}
                
                <button
                  onClick={handleNextPage}
                  disabled={currentPage === totalPages}
                  className={`relative inline-flex items-center px-2 py-2 rounded-r-md border text-sm font-medium ${
                    currentPage === totalPages
                      ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed'
                      : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                  }`}
                >
                  <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
                  </svg>
                </button>
              </nav>
            </div>
          </div>
        )}
        
        {/* Download and export options */}
        {!loading && hasValidPatientData && (
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
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
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

export default Patients;

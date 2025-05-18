import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import Card from '../components/Card';
import Button from '../components/Button';
import { api } from '../services/apiService';

const Doctors = () => {
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
        const { data, meta } = response.data;
        setDoctors(data || []);
        setTotalDoctors(meta?.total || 0);
        setTotalPages(meta?.last_page || 1);
        setCurrentPage(meta?.current_page || 1);
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
    fetchDoctors();
    
    // Clear any success message after 3 seconds
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(''), 3000);
      return () => clearTimeout(timer);
    }
  }, [fetchDoctors, manualFetchRequested, successMessage]);

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
    
    const pageNumbers = [];
    const maxPagesToShow = 5;
    
    let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
    
    if (endPage - startPage + 1 < maxPagesToShow) {
      startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
      pageNumbers.push(i);
    }
    
    return (
      <div className="flex justify-between items-center mt-6">
        <div className="text-sm text-gray-600">
          Showing {doctors.length} of {totalDoctors} doctors
        </div>
        <div className="flex space-x-1">
          <button
            onClick={handlePreviousPage}
            disabled={currentPage === 1}
            className={`px-3 py-1 rounded ${
              currentPage === 1
                ? 'bg-gray-200 text-gray-500 cursor-not-allowed'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            }`}
          >
            Previous
          </button>
          
          {pageNumbers.map(number => (
            <button
              key={number}
              onClick={() => handlePageChange(number)}
              className={`px-3 py-1 rounded ${
                currentPage === number
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
              }`}
            >
              {number}
            </button>
          ))}
          
          <button
            onClick={handleNextPage}
            disabled={currentPage === totalPages}
            className={`px-3 py-1 rounded ${
              currentPage === totalPages
                ? 'bg-gray-200 text-gray-500 cursor-not-allowed'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            }`}
          >
            Next
          </button>
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold">Doctors</h1>
        <Link to="/doctors/new">
          <Button variant="primary">
            <span className="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
              </svg>
              Add New Doctor
            </span>
          </Button>
        </Link>
      </div>

      {successMessage && (
        <div className="bg-green-50 text-green-800 p-4 rounded-lg border border-green-200">
          {successMessage}
        </div>
      )}

      <Card>
        <div className="mb-6">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between space-y-3 md:space-y-0 md:space-x-4">
            <div className="w-full md:w-1/3">
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
        ) : !hasValidDoctorData() ? (
          <div className="text-center py-10">
            <p className="text-gray-500">No doctors found.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                    onClick={() => handleSort('last_name')}
                  >
                    <span className="flex items-center">
                      Name
                      <SortIndicator field="last_name" />
                    </span>
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                    onClick={() => handleSort('specialty')}
                  >
                    <span className="flex items-center">
                      Specialty
                      <SortIndicator field="specialty" />
                    </span>
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Phone
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {doctors.map((doctor) => (
                  <tr key={doctor.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div>
                          <div className="text-sm font-medium text-gray-900">
                            {doctor.fullName || `${doctor.first_name} ${doctor.last_name}`}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                        {doctor.specialty}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {doctor.phone}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <div className="flex space-x-2">
                        <Link
                          to={`/doctors/${doctor.id}`}
                          className="text-primary-600 hover:text-primary-900"
                        >
                          View
                        </Link>
                        <Link
                          to={`/doctors/${doctor.id}/edit`}
                          className="text-indigo-600 hover:text-indigo-900"
                        >
                          Edit
                        </Link>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        
        {/* Pagination */}
        {renderPagination()}
      </Card>
    </div>
  );
};

export default Doctors;

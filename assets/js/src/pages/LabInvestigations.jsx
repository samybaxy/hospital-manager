import React, { useState, useEffect, useCallback } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import Modal from '../components/Modal';
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
  const [filteredInvestigations, setFilteredInvestigations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [selectedInvestigation, setSelectedInvestigation] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [modalType, setModalType] = useState('view'); // 'view', 'edit', 'results'
  const [updateLoading, setUpdateLoading] = useState(false);

  const itemsPerPage = 10;

  const fetchInvestigations = useCallback(async (page = 1) => {
    try {
      setLoading(true);
      const response = await laboratoryService.getInvestigations({
        page,
        per_page: itemsPerPage,
        search: searchTerm || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined
      });
      
      setInvestigations(response.data || []);
      setFilteredInvestigations(response.data || []);
      setTotalPages(Math.ceil((response.total || 0) / itemsPerPage));
      setCurrentPage(page);
      setError(null);
    } catch (err) {
      console.error('Error fetching lab investigations:', err);
      setError(err.message || 'Failed to fetch investigations');
      setInvestigations([]);
      setFilteredInvestigations([]);
    } finally {
      setLoading(false);
    }
  }, [searchTerm, statusFilter]);

  useEffect(() => {
    fetchInvestigations(1);
  }, [fetchInvestigations]);

  const handleSearch = (term) => {
    setSearchTerm(term);
    setCurrentPage(1);
    // Search across patient name, test_type, and sample_type
    if (term && investigations.length > 0) {
      const filtered = investigations.filter(inv => 
        (inv.patient_name && inv.patient_name.toLowerCase().includes(term.toLowerCase())) ||
        (inv.test_type && inv.test_type.toLowerCase().includes(term.toLowerCase())) ||
        (inv.sample_type && inv.sample_type.toLowerCase().includes(term.toLowerCase()))
      );
      setFilteredInvestigations(filtered);
    } else {
      setFilteredInvestigations(investigations);
    }
  };

  const handleStatusFilter = (status) => {
    setStatusFilter(status);
    setCurrentPage(1);
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
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold">Laboratory Investigations</h1>
        <Button variant="primary" onClick={() => openModal(null, 'create')}>
          New Investigation
        </Button>
      </div>
      
      {/* Search and Filter Controls */}
      <Card className="p-4">
        <div className="flex flex-col md:flex-row gap-4">
          <div className="flex-1">
            <input
              type="text"
              placeholder="Search by patient name, sample type..."
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
        </div>
      </Card>
      
      <Card>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ID
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Patient
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Test Type
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Sample Type
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Doctor
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Date
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Priority
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredInvestigations.length > 0 ? (
                filteredInvestigations.map((investigation) => (
                  <tr key={investigation.ID} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      #{investigation.ID}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {investigation.patient_name || 'Unknown Patient'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {investigation.test_type || 'N/A'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {investigation.sample_type || 'N/A'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {investigation.doctor_name || 'Unknown Doctor'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {formatDate(investigation.created_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusBadgeColor(investigation.status)}`}>
                        {investigation.status?.replace('_', ' ').toUpperCase()}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      <div className="flex space-x-1">
                        {investigation.is_critical && (
                          <span className="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                            Critical
                          </span>
                        )}
                        {investigation.is_abnormal && (
                          <span className="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                            Abnormal
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
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
                  <td colSpan={8} className="px-6 py-4 text-center text-sm text-gray-500">
                    {loading ? 'Loading...' : 'No investigations found'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="flex items-center justify-between px-6 py-3 border-t border-gray-200">
            <div className="flex-1 flex justify-between sm:hidden">
              <Button
                variant="secondary"
                disabled={currentPage === 1}
                onClick={() => fetchInvestigations(currentPage - 1)}
              >
                Previous
              </Button>
              <Button
                variant="secondary"
                disabled={currentPage === totalPages}
                onClick={() => fetchInvestigations(currentPage + 1)}
              >
                Next
              </Button>
            </div>
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-sm text-gray-700">
                  Page <span className="font-medium">{currentPage}</span> of{' '}
                  <span className="font-medium">{totalPages}</span>
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={currentPage === 1}
                    onClick={() => fetchInvestigations(currentPage - 1)}
                    className="rounded-l-md"
                  >
                    Previous
                  </Button>
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={currentPage === totalPages}
                    onClick={() => fetchInvestigations(currentPage + 1)}
                    className="rounded-r-md"
                  >
                    Next
                  </Button>
                </nav>
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

// Modal component for investigation details, editing, and results
const InvestigationModal = ({ investigation, type, onClose, onUpdateStatus, onUpdateResults, loading }) => {
  const [formData, setFormData] = useState({
    test_type: investigation?.test_type || '',
    test_results: investigation?.test_results || [],
    lab_notes: investigation?.lab_notes || '',
    sample_type: investigation?.sample_type || '',
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
      case 'results': return 'Update Test Results';
      case 'edit': return 'Edit Investigation';
      case 'create': return 'New Investigation';
      default: return 'Investigation';
    }
  };

  return (
    <Modal onClose={onClose} title={getModalTitle()}>
      <div className="space-y-4">
        {investigation && (
          <>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">Patient</label>
                <p className="mt-1 text-sm text-gray-900">{investigation.patient_name || 'Unknown'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Doctor</label>
                <p className="mt-1 text-sm text-gray-900">{investigation.doctor_name || 'Unknown'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Test Type</label>
                <p className="mt-1 text-sm text-gray-900">{investigation.test_type || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Sample Type</label>
                <p className="mt-1 text-sm text-gray-900">{investigation.sample_type || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Status</label>
                <span className={`mt-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusBadgeColor(investigation.status)}`}>
                  {investigation.status?.replace('_', ' ').toUpperCase()}
                </span>
              </div>
            </div>

            {investigation.request_notes && (
              <div>
                <label className="block text-sm font-medium text-gray-700">Request Notes</label>
                <p className="mt-1 text-sm text-gray-900">{investigation.request_notes}</p>
              </div>
            )}

            {type === 'results' && (
              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700">Lab Notes</label>
                  <textarea
                    rows={4}
                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                    value={formData.lab_notes}
                    onChange={(e) => setFormData({ ...formData, lab_notes: e.target.value })}
                    placeholder="Enter laboratory notes..."
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Test Results (JSON)</label>
                  <textarea
                    rows={6}
                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
                    value={JSON.stringify(formData.test_results, null, 2)}
                    onChange={(e) => {
                      try {
                        const parsed = JSON.parse(e.target.value);
                        setFormData({ ...formData, test_results: parsed });
                      } catch (err) {
                        // Handle invalid JSON gracefully
                      }
                    }}
                    placeholder='{"parameter": "value", "normal_range": "range", "is_abnormal": false}'
                  />
                </div>
                <div className="flex justify-end space-x-3">
                  <Button variant="secondary" onClick={onClose} disabled={loading}>
                    Cancel
                  </Button>
                  <Button type="submit" variant="primary" disabled={loading}>
                    {loading ? 'Updating...' : 'Update Results'}
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
                  <label className="block text-sm font-medium text-gray-700">Sample Type</label>
                  <input
                    type="text"
                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                    value={formData.sample_type || ''}
                    onChange={(e) => setFormData({ ...formData, sample_type: e.target.value })}
                    placeholder="Enter sample type..."
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
                <div className="flex justify-end space-x-3">
                  <Button variant="secondary" onClick={onClose} disabled={loading}>
                    Cancel
                  </Button>
                  <Button type="submit" variant="primary" disabled={loading}>
                    {loading ? 'Creating...' : 'Create Investigation'}
                  </Button>
                </div>
              </form>
            )}

            {type === 'edit' && (
              <form onSubmit={handleSubmit} className="space-y-4">
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
                <div className="flex justify-end space-x-3">
                  <Button variant="secondary" onClick={onClose} disabled={loading}>
                    Cancel
                  </Button>
                  <Button type="submit" variant="primary" disabled={loading}>
                    {loading ? 'Updating...' : 'Update Status'}
                  </Button>
                </div>
              </form>
            )}

            {type === 'view' && (
              <div className="flex justify-end">
                <Button variant="secondary" onClick={onClose}>
                  Close
                </Button>
              </div>
            )}
          </>
        )}
      </div>
    </Modal>
  );
};

export default LabInvestigations;

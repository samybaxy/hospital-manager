import React, { useState, useEffect } from 'react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';
import Alert from './Alert';
import LoadingState from './LoadingState';
import Table from './Table';
import Badge from './Badge';
import inventoryService from '../services/inventoryService';
import { usePermissions } from '../hooks/usePermissions.jsx';

const InventoryReorders = ({ onRefresh }) => {
  const permissions = usePermissions();
  const [reorders, setReorders] = useState([]);
  const [suggestions, setSuggestions] = useState([]);
  const [inventoryItems, setInventoryItems] = useState([]);
  const [suppliers, setSuppliers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingSuggestions, setLoadingSuggestions] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  
  // Modal states
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showApproveModal, setShowApproveModal] = useState(false);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [selectedReorder, setSelectedReorder] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  // Filter states
  const [filters, setFilters] = useState({
    status: '',
    priority: '',
    item_id: '',
    supplier_id: ''
  });

  // Tab state
  const [activeTab, setActiveTab] = useState('reorders');

  // Form state for new reorder
  const [reorderForm, setReorderForm] = useState({
    inventory_id: '',
    supplier_id: '',
    suggested_quantity: '',
    priority: 'medium',
    estimated_cost: '',
    notes: '',
    expected_delivery_date: ''
  });

  // Completion form state
  const [completionForm, setCompletionForm] = useState({
    actual_quantity: '',
    actual_cost: '',
    notes: ''
  });

  useEffect(() => {
    if (activeTab === 'reorders') {
      loadReorders();
      loadInventoryItems();
      loadSuppliers();
    } else if (activeTab === 'suggestions') {
      loadSuggestions();
    }
  }, [filters, activeTab]);

  const loadInventoryItems = async () => {
    try {
      const response = await inventoryService.getItems();
      // Handle nested response structure: response.data.data
      const items = Array.isArray(response) ? response : 
                   Array.isArray(response.data) ? response.data :
                   Array.isArray(response.data?.data) ? response.data.data : [];
      
      // Extra safety check to ensure items is always an array
      setInventoryItems(Array.isArray(items) ? items : []);
    } catch (err) {
      console.error('Error loading inventory items:', err);
      setInventoryItems([]); // Ensure it's always an array
    }
  };

  const loadSuppliers = async () => {
    try {
      const response = await inventoryService.getSuppliers();
      // Handle nested response structure: response.data.data
      const suppliers = Array.isArray(response) ? response : 
                       Array.isArray(response.data) ? response.data :
                       Array.isArray(response.data?.data) ? response.data.data : [];
      
      // Extra safety check to ensure suppliers is always an array
      setSuppliers(Array.isArray(suppliers) ? suppliers : []);
    } catch (err) {
      console.error('Error loading suppliers:', err);
      setSuppliers([]); // Ensure it's always an array
    }
  };

  const loadReorders = async () => {
    setLoading(true);
    try {
      const response = await inventoryService.getReorders(filters);
      const data = Array.isArray(response) ? response : response.data || [];
      setReorders(data);
    } catch (err) {
      console.error('Error loading reorders:', err);
      setError('Failed to load reorders');
    } finally {
      setLoading(false);
    }
  };

  const loadSuggestions = async () => {
    setLoadingSuggestions(true);
    try {
      const response = await inventoryService.getReorderSuggestions();
      const data = Array.isArray(response) ? response : response.data || [];
      setSuggestions(data);
    } catch (err) {
      console.error('Error loading suggestions:', err);
      setError('Failed to load reorder suggestions');
    } finally {
      setLoadingSuggestions(false);
    }
  };

  const resetReorderForm = () => {
    setReorderForm({
      inventory_id: '',
      supplier_id: '',
      suggested_quantity: '',
      priority: 'medium',
      estimated_cost: '',
      notes: '',
      expected_delivery_date: ''
    });
  };

  const handleCreateReorder = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      await inventoryService.createReorder(reorderForm);
      setSuccess('Reorder created successfully');
      setShowCreateModal(false);
      resetReorderForm();
      loadReorders();
      if (onRefresh) onRefresh();
    } catch (err) {
      console.error('Error creating reorder:', err);
      setError(err.message || 'Failed to create reorder');
    } finally {
      setSubmitting(false);
    }
  };

  const handleApproveReorder = async () => {
    setSubmitting(true);
    setError(null);

    try {
      await inventoryService.approveReorder(selectedReorder.ID);
      setSuccess('Reorder approved successfully');
      setShowApproveModal(false);
      setSelectedReorder(null);
      loadReorders();
      if (onRefresh) onRefresh();
    } catch (err) {
      console.error('Error approving reorder:', err);
      setError(err.message || 'Failed to approve reorder');
    } finally {
      setSubmitting(false);
    }
  };

  const handleCompleteReorder = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      await inventoryService.completeReorder(selectedReorder.ID, completionForm);
      setSuccess('Reorder completed successfully');
      setShowCompleteModal(false);
      setSelectedReorder(null);
      setCompletionForm({
        actual_quantity: '',
        actual_cost: '',
        notes: ''
      });
      loadReorders();
      if (onRefresh) onRefresh();
    } catch (err) {
      console.error('Error completing reorder:', err);
      setError(err.message || 'Failed to complete reorder');
    } finally {
      setSubmitting(false);
    }
  };

  const handleCreateFromSuggestion = (suggestion) => {
    setReorderForm({
      inventory_id: suggestion.ID,
      supplier_id: suggestion.preferred_supplier_id || '',
      suggested_quantity: suggestion.suggested_quantity || '',
      priority: suggestion.priority || 'medium',
      estimated_cost: '',
      notes: `Auto-generated from suggestion. Current stock: ${suggestion.quantity}, Reorder level: ${suggestion.reorder_level}`,
      expected_delivery_date: ''
    });
    setShowCreateModal(true);
  };

  const getPriorityBadge = (priority) => {
    const priorities = {
      'low': { variant: 'blue', children: 'Low' },
      'medium': { variant: 'yellow', children: 'Medium' },
      'high': { variant: 'orange', children: 'High' },
      'urgent': { variant: 'red', children: 'Urgent' }
    };
    return priorities[priority] || { variant: 'gray', children: priority };
  };

  const getStatusBadge = (status) => {
    const statuses = {
      'pending': { variant: 'yellow', children: 'Pending' },
      'approved': { variant: 'blue', children: 'Approved' },
      'ordered': { variant: 'purple', children: 'Ordered' },
      'received': { variant: 'green', children: 'Received' },
      'completed': { variant: 'green', children: 'Completed' },
      'cancelled': { variant: 'red', children: 'Cancelled' }
    };
    return statuses[status] || { variant: 'gray', children: status };
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
  };

  const formatCurrency = (amount) => {
    if (!amount) return '-';
    return `₦${Number(amount).toLocaleString()}`;
  };

  const reorderColumns = [
    {
      key: 'item_info',
      header: 'Item',
      render: (reorder) => (
        <div>
          <div className="font-medium">{reorder.item_name}</div>
          <div className="text-sm text-gray-500">
            Current: {reorder.current_quantity} | Reorder Level: {reorder.reorder_level}
          </div>
        </div>
      )
    },
    {
      key: 'quantity_info',
      header: 'Quantities',
      render: (reorder) => (
        <div className="text-sm">
          <div>Suggested: {reorder.suggested_quantity}</div>
          {reorder.max_stock_level && (
            <div className="text-gray-500">Max: {reorder.max_stock_level}</div>
          )}
        </div>
      )
    },
    {
      key: 'priority',
      header: 'Priority',
      render: (reorder) => <Badge {...getPriorityBadge(reorder.priority)} />
    },
    {
      key: 'status',
      header: 'Status',
      render: (reorder) => <Badge {...getStatusBadge(reorder.status)} />
    },
    {
      key: 'cost_info',
      header: 'Cost Info',
      render: (reorder) => (
        <div className="text-sm">
          {reorder.estimated_cost && (
            <div>Est: {formatCurrency(reorder.estimated_cost)}</div>
          )}
        </div>
      )
    },
    {
      key: 'supplier_info',
      header: 'Supplier',
      render: (reorder) => (
        <div className="text-sm">
          {reorder.supplier_name || 'Not specified'}
        </div>
      )
    },
    {
      key: 'dates',
      header: 'Dates',
      render: (reorder) => (
        <div className="text-sm space-y-1">
          <div>Created: {formatDate(reorder.created_at)}</div>
          {reorder.approved_at && (
            <div className="text-green-600">Approved: {formatDate(reorder.approved_at)}</div>
          )}
          {reorder.expected_delivery_date && (
            <div>Expected: {formatDate(reorder.expected_delivery_date)}</div>
          )}
        </div>
      )
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (reorder) => (
        <div className="flex flex-col space-y-1">
          {reorder.status === 'pending' && permissions.canUpdate && (
            <Button
              size="sm"
              variant="outline"
              onClick={() => {
                setSelectedReorder(reorder);
                setShowApproveModal(true);
              }}
            >
              Approve
            </Button>
          )}
          {(reorder.status === 'approved' || reorder.status === 'ordered') && permissions.canUpdate && (
            <Button
              size="sm"
              variant="primary"
              onClick={() => {
                setSelectedReorder(reorder);
                setCompletionForm({
                  actual_quantity: reorder.suggested_quantity,
                  actual_cost: reorder.estimated_cost || '',
                  notes: ''
                });
                setShowCompleteModal(true);
              }}
            >
              Complete
            </Button>
          )}
        </div>
      )
    }
  ];

  const suggestionColumns = [
    {
      key: 'item_info',
      header: 'Item',
      render: (suggestion) => (
        <div>
          <div className="font-medium">{suggestion.item_name}</div>
          <div className="text-sm text-gray-500">{suggestion.category}</div>
        </div>
      )
    },
    {
      key: 'stock_info',
      header: 'Stock Information',
      render: (suggestion) => (
        <div className="text-sm">
          <div>Current: {suggestion.quantity}</div>
          <div>Reorder Level: {suggestion.reorder_level}</div>
          <div className="text-red-600">
            Shortage: {suggestion.reorder_level - suggestion.quantity}
          </div>
        </div>
      )
    },
    {
      key: 'suggested_quantity',
      header: 'Suggested Order',
      render: (suggestion) => (
        <div className="font-medium text-blue-600">
          {suggestion.suggested_quantity || (suggestion.max_stock_level - suggestion.quantity)}
        </div>
      )
    },
    {
      key: 'priority',
      header: 'Priority',
      render: (suggestion) => {
        const shortage = suggestion.reorder_level - suggestion.quantity;
        const priority = shortage >= suggestion.reorder_level ? 'urgent' : 
                        shortage >= suggestion.reorder_level * 0.5 ? 'high' : 'medium';
        return <Badge {...getPriorityBadge(priority)} />;
      }
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (suggestion) => (
        <Button
          size="sm"
          variant="primary"
          onClick={() => handleCreateFromSuggestion(suggestion)}
        >
          Create Reorder
        </Button>
      )
    }
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <h2 className="text-2xl font-bold text-gray-900">Inventory Reorders</h2>
        {permissions.canCreate && (
          <Button
            variant="primary"
            onClick={() => {
              resetReorderForm();
              setShowCreateModal(true);
            }}
          >
            Create Reorder
          </Button>
        )}
      </div>

      {error && (
        <Alert type="error" className="mb-4">
          {error}
        </Alert>
      )}

      {success && (
        <Alert type="success" className="mb-4">
          {success}
        </Alert>
      )}

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('reorders')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'reorders'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Reorders
          </button>
          <button
            onClick={() => setActiveTab('suggestions')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'suggestions'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Suggestions
          </button>
        </nav>
      </div>

      {/* Filters */}
      {activeTab === 'reorders' && (
        <Card title="Filters">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Status
                </label>
                <select
                  value={filters.status}
                  onChange={(e) => setFilters(prev => ({ ...prev, status: e.target.value }))}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                  <option value="">All Statuses</option>
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="ordered">Ordered</option>
                  <option value="received">Received</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Priority
                </label>
                <select
                  value={filters.priority}
                  onChange={(e) => setFilters(prev => ({ ...prev, priority: e.target.value }))}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                  <option value="">All Priorities</option>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Item
                </label>
                <select
                  value={filters.item_id}
                  onChange={(e) => setFilters(prev => ({ ...prev, item_id: e.target.value }))}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                  <option value="">All Items</option>
                  {Array.isArray(inventoryItems) && inventoryItems.map(item => (
                    <option key={item.ID} value={item.ID}>
                      {item.item_name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Supplier
                </label>
                <select
                  value={filters.supplier_id}
                  onChange={(e) => setFilters(prev => ({ ...prev, supplier_id: e.target.value }))}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                  <option value="">All Suppliers</option>
                  {Array.isArray(suppliers) && suppliers.map(supplier => (
                    <option key={supplier.ID} value={supplier.ID}>
                      {supplier.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
        </Card>
      )}

      {/* Content */}
      <Card title={activeTab === 'reorders' ? 'Reorder Requests' : 'Reorder Suggestions'}>
        {activeTab === 'reorders' && (
          <>
            {loading ? (
              <LoadingState message="Loading reorders..." />
            ) : reorders.length === 0 ? (
              <div className="text-center py-8">
                <p className="text-gray-500">No reorders found</p>
              </div>
            ) : (
              <Table
                columns={reorderColumns}
                data={reorders}
              />
            )}
          </>
        )}

        {activeTab === 'suggestions' && (
          <>
            {loadingSuggestions ? (
              <LoadingState message="Loading suggestions..." />
            ) : suggestions.length === 0 ? (
              <div className="text-center py-8">
                <p className="text-gray-500">No reorder suggestions found</p>
                <p className="text-sm text-gray-400 mt-2">All items are above their reorder levels</p>
              </div>
            ) : (
              <Table
                columns={suggestionColumns}
                data={suggestions}
              />
            )}
          </>
        )}
      </Card>

      {/* Create Reorder Modal */}
      <Modal
        isOpen={showCreateModal}
        onClose={() => {
          setShowCreateModal(false);
          resetReorderForm();
        }}
        title="Create New Reorder"
        size="lg"
      >
        <form onSubmit={handleCreateReorder} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Item *
              </label>
              <select
                value={reorderForm.inventory_id}
                onChange={(e) => setReorderForm(prev => ({ ...prev, inventory_id: e.target.value }))}
                required
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                <option value="">Select Item</option>
                {Array.isArray(inventoryItems) && inventoryItems.map(item => (
                  <option key={item.ID} value={item.ID}>
                    {item.item_name} (Current: {item.quantity})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Supplier
              </label>
              <select
                value={reorderForm.supplier_id}
                onChange={(e) => setReorderForm(prev => ({ ...prev, supplier_id: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                <option value="">Select Supplier</option>
                {Array.isArray(suppliers) && suppliers.map(supplier => (
                  <option key={supplier.ID} value={supplier.ID}>
                    {supplier.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Suggested Quantity *
              </label>
              <input
                type="number"
                value={reorderForm.suggested_quantity}
                onChange={(e) => setReorderForm(prev => ({ ...prev, suggested_quantity: e.target.value }))}
                required
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Enter quantity"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Priority
              </label>
              <select
                value={reorderForm.priority}
                onChange={(e) => setReorderForm(prev => ({ ...prev, priority: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Estimated Cost
              </label>
              <input
                type="number"
                step="0.01"
                value={reorderForm.estimated_cost}
                onChange={(e) => setReorderForm(prev => ({ ...prev, estimated_cost: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="0.00"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Expected Delivery Date
              </label>
              <input
                type="date"
                value={reorderForm.expected_delivery_date}
                onChange={(e) => setReorderForm(prev => ({ ...prev, expected_delivery_date: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Notes
            </label>
            <textarea
              value={reorderForm.notes}
              onChange={(e) => setReorderForm(prev => ({ ...prev, notes: e.target.value }))}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              rows="3"
              placeholder="Additional notes..."
            />
          </div>

          <div className="flex justify-end space-x-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setShowCreateModal(false);
                resetReorderForm();
              }}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={submitting}
            >
              {submitting ? 'Creating...' : 'Create Reorder'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Approve Reorder Modal */}
      <Modal
        isOpen={showApproveModal}
        onClose={() => {
          setShowApproveModal(false);
          setSelectedReorder(null);
        }}
        title="Approve Reorder"
      >
        <div className="space-y-4">
          <p>Are you sure you want to approve this reorder?</p>
          {selectedReorder && (
            <div className="bg-gray-50 p-4 rounded-md">
              <div className="text-sm space-y-2">
                <div><strong>Item:</strong> {selectedReorder.item_name}</div>
                <div><strong>Quantity:</strong> {selectedReorder.suggested_quantity}</div>
                <div><strong>Priority:</strong> {selectedReorder.priority}</div>
                {selectedReorder.estimated_cost && (
                  <div><strong>Estimated Cost:</strong> {formatCurrency(selectedReorder.estimated_cost)}</div>
                )}
              </div>
            </div>
          )}
          
          <div className="flex justify-end space-x-3">
            <Button
              variant="outline"
              onClick={() => {
                setShowApproveModal(false);
                setSelectedReorder(null);
              }}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button
              variant="primary"
              onClick={handleApproveReorder}
              disabled={submitting}
            >
              {submitting ? 'Approving...' : 'Approve'}
            </Button>
          </div>
        </div>
      </Modal>

      {/* Complete Reorder Modal */}
      <Modal
        isOpen={showCompleteModal}
        onClose={() => {
          setShowCompleteModal(false);
          setSelectedReorder(null);
          setCompletionForm({
            actual_quantity: '',
            actual_cost: '',
            notes: ''
          });
        }}
        title="Complete Reorder"
        size="lg"
      >
        <form onSubmit={handleCompleteReorder} className="space-y-4">
          <div className="bg-gray-50 p-4 rounded-md">
            <div className="text-sm space-y-2">
              <div><strong>Item:</strong> {selectedReorder?.item_name}</div>
              <div><strong>Ordered Quantity:</strong> {selectedReorder?.suggested_quantity}</div>
              <div><strong>Estimated Cost:</strong> {formatCurrency(selectedReorder?.estimated_cost)}</div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Actual Quantity Received *
              </label>
              <input
                type="number"
                value={completionForm.actual_quantity}
                onChange={(e) => setCompletionForm(prev => ({ ...prev, actual_quantity: e.target.value }))}
                required
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Enter actual quantity"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Actual Cost
              </label>
              <input
                type="number"
                step="0.01"
                value={completionForm.actual_cost}
                onChange={(e) => setCompletionForm(prev => ({ ...prev, actual_cost: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="0.00"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Completion Notes
            </label>
            <textarea
              value={completionForm.notes}
              onChange={(e) => setCompletionForm(prev => ({ ...prev, notes: e.target.value }))}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              rows="3"
              placeholder="Any notes about the delivery..."
            />
          </div>

          <div className="flex justify-end space-x-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setShowCompleteModal(false);
                setSelectedReorder(null);
                setCompletionForm({
                  actual_quantity: '',
                  actual_cost: '',
                  notes: ''
                });
              }}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={submitting}
            >
              {submitting ? 'Completing...' : 'Complete Reorder'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
};

export default InventoryReorders;

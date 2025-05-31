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
    loadReorders();
    loadInventoryItems();
    loadSuppliers();
    if (activeTab === 'suggestions') {
      loadSuggestions();
    }
  }, [filters, activeTab]);

  const loadInventoryItems = async () => {
    try {
      const response = await inventoryService.getItems({ per_page: 1000 });
      setInventoryItems(response.data || []);
    } catch (err) {
      console.error('Error loading inventory items:', err);
    }
  };

  const loadSuppliers = async () => {
    try {
      const response = await inventoryService.getSuppliers({ per_page: 1000 });
      setSuppliers(response.data || []);
    } catch (err) {
      console.error('Error loading suppliers:', err);
    }
  };

  const loadReorders = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await inventoryService.getReorders(filters);
      setReorders(response.data || []);
    } catch (err) {
      setError('Failed to load reorders');
      console.error('Error loading reorders:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadSuggestions = async () => {
    setLoadingSuggestions(true);
    try {
      const response = await inventoryService.getReorderSuggestions();
      setSuggestions(response.data || []);
    } catch (err) {
      console.error('Error loading suggestions:', err);
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
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError('Failed to create reorder');
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
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError('Failed to approve reorder');
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
      setCompletionForm({ actual_quantity: '', actual_cost: '', notes: '' });
      loadReorders();
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError('Failed to complete reorder');
    } finally {
      setSubmitting(false);
    }
  };

  const handleCreateFromSuggestion = (suggestion) => {
    setReorderForm({
      inventory_id: suggestion.inventory_id,
      supplier_id: '',
      suggested_quantity: suggestion.suggested_quantity.toString(),
      priority: suggestion.priority || 'medium',
      estimated_cost: '',
      notes: `Auto-generated from suggestion. Current stock: ${suggestion.current_quantity}, Reorder level: ${suggestion.reorder_level}`,
      expected_delivery_date: ''
    });
    setShowCreateModal(true);
  };

  const getPriorityBadge = (priority) => {
    const priorityInfo = inventoryService.getReorderPriorities().find(p => p.value === priority);
    return priorityInfo ? {
      variant: priorityInfo.color,
      children: priorityInfo.label
    } : { variant: 'gray', children: priority };
  };

  const getStatusBadge = (status) => {
    const statusInfo = inventoryService.getReorderStatuses().find(s => s.value === status);
    return statusInfo ? {
      variant: statusInfo.color,
      children: statusInfo.label
    } : { variant: 'gray', children: status };
  };

  const formatDate = (dateString) => {
    return dateString ? new Date(dateString).toLocaleDateString() : 'N/A';
  };

  const formatCurrency = (amount) => {
    return amount ? `₦${parseFloat(amount).toLocaleString()}` : 'N/A';
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
                  actual_quantity: reorder.suggested_quantity.toString(),
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
      key: 'item_name',
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
        <div className="text-sm space-y-1">
          <div>Current: <span className="font-medium text-red-600">{suggestion.current_quantity}</span></div>
          <div>Reorder Level: {suggestion.reorder_level}</div>
          <div>Suggested Order: <span className="font-medium text-green-600">{suggestion.suggested_quantity}</span></div>
        </div>
      )
    },
    {
      key: 'priority',
      header: 'Priority',
      render: (suggestion) => <Badge {...getPriorityBadge(suggestion.priority)} />
    },
    {
      key: 'reason',
      header: 'Reason',
      render: (suggestion) => (
        <div className="text-sm">
          {suggestion.reason || 'Below reorder level'}
        </div>
      )
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (suggestion) => (
        <div className="flex space-x-2">
          {permissions.canCreate && (
            <Button
              size="sm"
              variant="primary"
              onClick={() => handleCreateFromSuggestion(suggestion)}
            >
              Create Reorder
            </Button>
          )}
        </div>
      )
    }
  ];

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4">
        <div className="fixed inset-0 bg-black opacity-50" onClick={onClose}></div>
        <div className="relative bg-white rounded-lg shadow-xl max-w-7xl w-full max-h-screen overflow-y-auto">
          <div className="p-6">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-2xl font-bold text-gray-900">Inventory Reorders</h2>
              <div className="flex space-x-3">
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
                <Button variant="outline" onClick={onClose}>
                  Close
                </Button>
              </div>
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
            <div className="mb-6">
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
                    Reorders ({reorders.length})
                  </button>
                  <button
                    onClick={() => setActiveTab('suggestions')}
                    className={`py-2 px-1 border-b-2 font-medium text-sm ${
                      activeTab === 'suggestions'
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    }`}
                  >
                    Suggestions ({suggestions.length})
                  </button>
                </nav>
              </div>
            </div>

            {activeTab === 'reorders' && (
              <>
                {/* Filters for Reorders */}
                <Card className="mb-6">
                  <Card.Header>
                    <h3 className="text-lg font-semibold">Filters</h3>
                  </Card.Header>
                  <Card.Body>
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
                          {inventoryService.getReorderStatuses().map(status => (
                            <option key={status.value} value={status.value}>
                              {status.label}
                            </option>
                          ))}
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
                          {inventoryService.getReorderPriorities().map(priority => (
                            <option key={priority.value} value={priority.value}>
                              {priority.label}
                            </option>
                          ))}
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
                          {inventoryItems.map(item => (
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
                          {suppliers.map(supplier => (
                            <option key={supplier.ID} value={supplier.ID}>
                              {supplier.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </Card.Body>
                </Card>

                {/* Reorders Table */}
                <Card>
                  <Card.Header>
                    <h3 className="text-lg font-semibold">
                      Reorder Requests ({reorders.length})
                    </h3>
                  </Card.Header>
                  <Card.Body>
                    {loading ? (
                      <LoadingState />
                    ) : reorders.length === 0 ? (
                      <div className="text-center py-8 text-gray-500">
                        No reorders found matching the current filters.
                      </div>
                    ) : (
                      <Table
                        columns={reorderColumns}
                        data={reorders}
                        keyField="ID"
                      />
                    )}
                  </Card.Body>
                </Card>
              </>
            )}

            {activeTab === 'suggestions' && (
              <Card>
                <Card.Header>
                  <div className="flex justify-between items-center">
                    <h3 className="text-lg font-semibold">
                      Reorder Suggestions ({suggestions.length})
                    </h3>
                    <Button
                      variant="outline"
                      onClick={loadSuggestions}
                      disabled={loadingSuggestions}
                    >
                      {loadingSuggestions ? 'Refreshing...' : 'Refresh Suggestions'}
                    </Button>
                  </div>
                </Card.Header>
                <Card.Body>
                  {loadingSuggestions ? (
                    <LoadingState />
                  ) : suggestions.length === 0 ? (
                    <div className="text-center py-8 text-gray-500">
                      No reorder suggestions available. All items are above their reorder levels.
                    </div>
                  ) : (
                    <Table
                      columns={suggestionColumns}
                      data={suggestions}
                      keyField="inventory_id"
                    />
                  )}
                </Card.Body>
              </Card>
            )}
          </div>
        </div>
      </div>

      {/* Create Reorder Modal */}
      <Modal
        isOpen={showCreateModal}
        onClose={() => {
          setShowCreateModal(false);
          resetReorderForm();
        }}
        title="Create New Reorder"
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
                {inventoryItems.map(item => (
                  <option key={item.ID} value={item.ID}>
                    {item.item_name} (Current: {item.quantity}, Reorder: {item.reorder_level})
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
                {suppliers.filter(s => s.is_active === '1' || s.is_active === true).map(supplier => (
                  <option key={supplier.ID} value={supplier.ID}>
                    {supplier.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Suggested Quantity *
              </label>
              <input
                type="number"
                value={reorderForm.suggested_quantity}
                onChange={(e) => setReorderForm(prev => ({ ...prev, suggested_quantity: e.target.value }))}
                required
                min="1"
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Enter quantity to order"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Priority *
              </label>
              <select
                value={reorderForm.priority}
                onChange={(e) => setReorderForm(prev => ({ ...prev, priority: e.target.value }))}
                required
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                {inventoryService.getReorderPriorities().map(priority => (
                  <option key={priority.value} value={priority.value}>
                    {priority.label}
                  </option>
                ))}
              </select>
            </div>
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
              rows={3}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              placeholder="Additional notes or comments"
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
          <p>
            Are you sure you want to approve the reorder for "{selectedReorder?.item_name}"?
          </p>
          {selectedReorder && (
            <div className="bg-gray-50 p-4 rounded-md">
              <div className="grid grid-cols-2 gap-2 text-sm">
                <div>Item: {selectedReorder.item_name}</div>
                <div>Quantity: {selectedReorder.suggested_quantity}</div>
                <div>Priority: {selectedReorder.priority}</div>
                <div>Estimated Cost: {formatCurrency(selectedReorder.estimated_cost)}</div>
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
            >
              Cancel
            </Button>
            <Button
              variant="primary"
              onClick={handleApproveReorder}
              disabled={submitting}
            >
              {submitting ? 'Approving...' : 'Approve Reorder'}
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
          setCompletionForm({ actual_quantity: '', actual_cost: '', notes: '' });
        }}
        title="Complete Reorder"
      >
        <form onSubmit={handleCompleteReorder} className="space-y-4">
          <p className="text-sm text-gray-600">
            Mark the reorder for "{selectedReorder?.item_name}" as completed and update inventory levels.
          </p>
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
                min="0"
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Quantity actually received"
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
              rows={3}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              placeholder="Any notes about the delivery or completion"
            />
          </div>
          <div className="flex justify-end space-x-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setShowCompleteModal(false);
                setSelectedReorder(null);
                setCompletionForm({ actual_quantity: '', actual_cost: '', notes: '' });
              }}
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

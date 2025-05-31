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

const InventoryTransactions = ({ onRefresh }) => {
  const permissions = usePermissions();
  const [transactions, setTransactions] = useState([]);
  const [inventoryItems, setInventoryItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  
  // Modal states
  const [showAddModal, setShowAddModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Filter states
  const [filters, setFilters] = useState({
    item_id: '',
    type: '',
    date_from: '',
    date_to: '',
    user_id: ''
  });

  // Pagination
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [itemsPerPage] = useState(20);

  // Form state for new transaction
  const [transactionForm, setTransactionForm] = useState({
    inventory_id: '',
    transaction_type: 'stock_in',
    quantity_changed: '',
    reference_number: '',
    notes: '',
    location_from: '',
    location_to: '',
    supplier_id: '',
    batch_number: '',
    expiry_date: '',
    cost_per_unit: '',
    total_cost: ''
  });

  useEffect(() => {
    loadTransactions();
    loadInventoryItems();
  }, [filters, currentPage]);

  const loadInventoryItems = async () => {
    try {
      const response = await inventoryService.getItems({ per_page: 1000 });
      setInventoryItems(response.data || []);
    } catch (err) {
      console.error('Error loading inventory items:', err);
    }
  };

  const loadTransactions = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        ...filters,
        page: currentPage,
        per_page: itemsPerPage
      };
      const response = await inventoryService.getTransactions(params);
      setTransactions(response.data || []);
      setTotalPages(response.total_pages || 1);
    } catch (err) {
      setError('Failed to load transactions');
      console.error('Error loading transactions:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleAddTransaction = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    
    try {
      await inventoryService.recordTransaction(transactionForm);
      setSuccess('Transaction recorded successfully');
      setShowAddModal(false);
      setTransactionForm({
        inventory_id: '',
        transaction_type: 'stock_in',
        quantity_changed: '',
        reference_number: '',
        notes: '',
        location_from: '',
        location_to: '',
        supplier_id: '',
        batch_number: '',
        expiry_date: '',
        cost_per_unit: '',
        total_cost: ''
      });
      loadTransactions();
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError('Failed to record transaction');
    } finally {
      setSubmitting(false);
    }
  };

  const getTransactionTypeBadge = (type) => {
    const typeInfo = inventoryService.getTransactionTypes().find(t => t.value === type);
    return typeInfo ? {
      variant: typeInfo.color,
      children: typeInfo.label
    } : { variant: 'gray', children: type };
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString();
  };

  const formatCurrency = (amount) => {
    return amount ? `₦${parseFloat(amount).toLocaleString()}` : 'N/A';
  };

  const transactionColumns = [
    {
      key: 'item_name',
      header: 'Item',
      render: (transaction) => (
        <div>
          <div className="font-medium">{transaction.item_name}</div>
          {transaction.reference_number && (
            <div className="text-sm text-gray-500">Ref: {transaction.reference_number}</div>
          )}
        </div>
      )
    },
    {
      key: 'transaction_type',
      header: 'Type',
      render: (transaction) => <Badge {...getTransactionTypeBadge(transaction.transaction_type)} />
    },
    {
      key: 'quantity_changed',
      header: 'Quantity Changed',
      render: (transaction) => (
        <div className={`font-medium ${
          transaction.quantity_changed > 0 ? 'text-green-600' : 'text-red-600'
        }`}>
          {transaction.quantity_changed > 0 ? '+' : ''}{transaction.quantity_changed}
        </div>
      )
    },
    {
      key: 'stock_levels',
      header: 'Stock Levels',
      render: (transaction) => (
        <div className="text-sm">
          <div>Previous: {transaction.previous_quantity}</div>
          <div>New: {transaction.new_quantity}</div>
        </div>
      )
    },
    {
      key: 'cost_info',
      header: 'Cost Information',
      render: (transaction) => (
        <div className="text-sm">
          {transaction.cost_per_unit && (
            <div>Unit: {formatCurrency(transaction.cost_per_unit)}</div>
          )}
          {transaction.total_cost && (
            <div>Total: {formatCurrency(transaction.total_cost)}</div>
          )}
        </div>
      )
    },
    {
      key: 'user_name',
      header: 'User',
      render: (transaction) => (
        <div className="text-sm">{transaction.user_name || 'Unknown'}</div>
      )
    },
    {
      key: 'created_at',
      header: 'Date',
      render: (transaction) => (
        <div className="text-sm">{formatDate(transaction.created_at)}</div>
      )
    },
    {
      key: 'details',
      header: 'Details',
      render: (transaction) => (
        <div className="text-sm space-y-1">
          {transaction.batch_number && (
            <div>Batch: {transaction.batch_number}</div>
          )}
          {transaction.expiry_date && (
            <div>Expires: {new Date(transaction.expiry_date).toLocaleDateString()}</div>
          )}
          {transaction.location_from && (
            <div>From: {transaction.location_from}</div>
          )}
          {transaction.location_to && (
            <div>To: {transaction.location_to}</div>
          )}
          {transaction.notes && (
            <div className="text-gray-600 truncate" title={transaction.notes}>
              Notes: {transaction.notes}
            </div>
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
              <h2 className="text-2xl font-bold text-gray-900">Inventory Transactions</h2>
              <div className="flex space-x-3">
                {permissions.canCreate && (
                  <Button
                    variant="primary"
                    onClick={() => setShowAddModal(true)}
                  >
                    Record Transaction
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

            {/* Filters */}
            <Card className="mb-6">
              <Card.Header>
                <h3 className="text-lg font-semibold">Filters</h3>
              </Card.Header>
              <Card.Body>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                      Transaction Type
                    </label>
                    <select
                      value={filters.type}
                      onChange={(e) => setFilters(prev => ({ ...prev, type: e.target.value }))}
                      className="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                      <option value="">All Types</option>
                      {inventoryService.getTransactionTypes().map(type => (
                        <option key={type.value} value={type.value}>
                          {type.label}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Date From
                    </label>
                    <input
                      type="date"
                      value={filters.date_from}
                      onChange={(e) => setFilters(prev => ({ ...prev, date_from: e.target.value }))}
                      className="w-full border border-gray-300 rounded-md px-3 py-2"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Date To
                    </label>
                    <input
                      type="date"
                      value={filters.date_to}
                      onChange={(e) => setFilters(prev => ({ ...prev, date_to: e.target.value }))}
                      className="w-full border border-gray-300 rounded-md px-3 py-2"
                    />
                  </div>
                </div>
              </Card.Body>
            </Card>

            {/* Transactions Table */}
            <Card>
              <Card.Header>
                <h3 className="text-lg font-semibold">
                  Transactions ({transactions.length})
                </h3>
              </Card.Header>
              <Card.Body>
                {loading ? (
                  <LoadingState />
                ) : transactions.length === 0 ? (
                  <div className="text-center py-8 text-gray-500">
                    No transactions found matching the current filters.
                  </div>
                ) : (
                  <>
                    <Table
                      columns={transactionColumns}
                      data={transactions}
                      keyField="ID"
                    />
                    
                    {/* Pagination */}
                    {totalPages > 1 && (
                      <div className="flex justify-between items-center mt-4">
                        <Button
                          variant="outline"
                          onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                          disabled={currentPage === 1}
                        >
                          Previous
                        </Button>
                        <span className="text-sm text-gray-600">
                          Page {currentPage} of {totalPages}
                        </span>
                        <Button
                          variant="outline"
                          onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                          disabled={currentPage === totalPages}
                        >
                          Next
                        </Button>
                      </div>
                    )}
                  </>
                )}
              </Card.Body>
            </Card>
          </div>
        </div>
      </div>

      {/* Add Transaction Modal */}
      <Modal
        isOpen={showAddModal}
        onClose={() => setShowAddModal(false)}
        title="Record New Transaction"
      >
        <form onSubmit={handleAddTransaction} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Item *
              </label>
              <select
                value={transactionForm.inventory_id}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, inventory_id: e.target.value }))}
                required
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                <option value="">Select Item</option>
                {inventoryItems.map(item => (
                  <option key={item.ID} value={item.ID}>
                    {item.item_name} (Current: {item.quantity})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Transaction Type *
              </label>
              <select
                value={transactionForm.transaction_type}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, transaction_type: e.target.value }))}
                required
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                {inventoryService.getTransactionTypes().map(type => (
                  <option key={type.value} value={type.value}>
                    {type.label}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Quantity Changed *
              </label>
              <input
                type="number"
                value={transactionForm.quantity_changed}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, quantity_changed: e.target.value }))}
                required
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Enter quantity (positive for increase, negative for decrease)"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Reference Number
              </label>
              <input
                type="text"
                value={transactionForm.reference_number}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, reference_number: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="PO#, Invoice#, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Batch Number
              </label>
              <input
                type="text"
                value={transactionForm.batch_number}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, batch_number: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Expiry Date
              </label>
              <input
                type="date"
                value={transactionForm.expiry_date}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, expiry_date: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Cost Per Unit
              </label>
              <input
                type="number"
                step="0.01"
                value={transactionForm.cost_per_unit}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, cost_per_unit: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="0.00"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Total Cost
              </label>
              <input
                type="number"
                step="0.01"
                value={transactionForm.total_cost}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, total_cost: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="0.00"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Location From
              </label>
              <input
                type="text"
                value={transactionForm.location_from}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, location_from: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Source location"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Location To
              </label>
              <input
                type="text"
                value={transactionForm.location_to}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, location_to: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Destination location"
              />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Notes
            </label>
            <textarea
              value={transactionForm.notes}
              onChange={(e) => setTransactionForm(prev => ({ ...prev, notes: e.target.value }))}
              rows={3}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              placeholder="Additional notes or comments"
            />
          </div>
          <div className="flex justify-end space-x-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowAddModal(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={submitting}
            >
              {submitting ? 'Recording...' : 'Record Transaction'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
};

export default InventoryTransactions;

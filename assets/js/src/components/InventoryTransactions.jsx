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
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [totalItems, setTotalItems] = useState(0);

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
  }, [filters, currentPage, itemsPerPage]);

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

  const loadTransactions = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page: currentPage,
        per_page: itemsPerPage,
        ...filters
      };
      const response = await inventoryService.getTransactions(params);
      
      // Handle API response structure: { success: true, data: { data: [...], pagination: {...} } }
      if (response.success && response.data && Array.isArray(response.data.data)) {
        // Response with pagination metadata
        setTransactions(response.data.data);
        setTotalItems(response.data.pagination?.total || 0);
        setTotalPages(response.data.pagination?.pages || 1);
      } else if (Array.isArray(response)) {
        // Direct array response (fallback)
        setTransactions(response);
        setTotalItems(response.length);
        setTotalPages(1);
      } else {
        setTransactions([]);
        setTotalItems(0);
        setTotalPages(1);
      }
    } catch (err) {
      console.error('Error loading transactions:', err);
      setError('Failed to load transactions');
      setTransactions([]);
      setTotalItems(0);
      setTotalPages(1);
    } finally {
      setLoading(false);
    }
  };

  const handleAddTransaction = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      await inventoryService.addTransaction(transactionForm);
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
      if (onRefresh) onRefresh();
    } catch (err) {
      console.error('Error recording transaction:', err);
      setError(err.message || 'Failed to record transaction');
    } finally {
      setSubmitting(false);
    }
  };

  // Handle page change
  const handlePageChange = (newPage) => {
    setCurrentPage(newPage);
  };

  // Handle items per page change
  const handleItemsPerPageChange = (newItemsPerPage) => {
    setItemsPerPage(newItemsPerPage);
    setCurrentPage(1); // Reset to first page when changing items per page
  };

  // Handle filter changes with pagination reset
  const handleFilterChange = (key, value) => {
    setFilters(prev => ({ ...prev, [key]: value }));
    setCurrentPage(1); // Reset to first page when filtering
  };

  const getTransactionTypeBadge = (type) => {
    const types = {
      'stock_in': { variant: 'green', children: 'Stock In' },
      'stock_out': { variant: 'red', children: 'Stock Out' },
      'adjustment': { variant: 'blue', children: 'Adjustment' },
      'transfer': { variant: 'purple', children: 'Transfer' },
      'return': { variant: 'orange', children: 'Return' },
      'expired': { variant: 'red', children: 'Expired' },
      'damaged': { variant: 'red', children: 'Damaged' }
    };
    return types[type] || { variant: 'gray', children: type };
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
  };

  const formatCurrency = (amount) => {
    if (!amount) return '-';
    return `₦${Number(amount).toLocaleString()}`;
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

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <h2 className="text-2xl font-bold text-gray-900">Inventory Transactions</h2>
        {permissions.canCreate && (
          <Button
            variant="primary"
            onClick={() => setShowAddModal(true)}
          >
            Record Transaction
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

      {/* Filters */}
      <Card className="mb-6">
        <div className="py-3 px-4 border-b border-gray-200">
          <h3 className="text-lg font-semibold">Filters</h3>
        </div>
        <div className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Item
              </label>
              <select
                value={filters.item_id}
                onChange={(e) => handleFilterChange('item_id', e.target.value)}
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
                Transaction Type
              </label>
              <select
                value={filters.type}
                onChange={(e) => handleFilterChange('type', e.target.value)}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                <option value="">All Types</option>
                <option value="stock_in">Stock In</option>
                <option value="stock_out">Stock Out</option>
                <option value="adjustment">Adjustment</option>
                <option value="transfer">Transfer</option>
                <option value="return">Return</option>
                <option value="expired">Expired</option>
                <option value="damaged">Damaged</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Date From
              </label>
              <input
                type="date"
                value={filters.date_from}
                onChange={(e) => handleFilterChange('date_from', e.target.value)}
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
                onChange={(e) => handleFilterChange('date_to', e.target.value)}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Items per page
              </label>
              <select
                value={itemsPerPage}
                onChange={(e) => handleItemsPerPageChange(parseInt(e.target.value))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                <option value={5}>5</option>
                <option value={10}>10</option>
                <option value={20}>20</option>
                <option value={50}>50</option>
                <option value={100}>100</option>
              </select>
            </div>
          </div>
        </div>
      </Card>

      {/* Transactions Table */}
      <Card>
        <div className="py-3 px-4 border-b border-gray-200">
          <div className="flex justify-between items-center">
            <h3 className="text-lg font-semibold">
              Transaction History ({totalItems})
            </h3>
            {totalItems > 0 && (
              <div className="text-sm text-gray-500">
                Showing {((currentPage - 1) * itemsPerPage) + 1} to {Math.min(currentPage * itemsPerPage, totalItems)} of {totalItems} transactions
              </div>
            )}
          </div>
        </div>
        <div className="p-4">
          {loading ? (
            <LoadingState message="Loading transactions..." />
          ) : transactions.length === 0 ? (
            <div className="text-center py-8 text-gray-500">
              No transactions found matching the current filters.
            </div>
          ) : (
            <Table
              columns={transactionColumns}
              data={transactions}
              emptyMessage="No transactions found"
              pagination={true}
              currentPage={currentPage}
              totalPages={totalPages}
              itemsPerPage={itemsPerPage}
              totalItems={totalItems}
              onPageChange={handlePageChange}
              onItemsPerPageChange={handleItemsPerPageChange}
              showItemsPerPageSelector={false}
            />
          )}
        </div>
      </Card>

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
                {Array.isArray(inventoryItems) && inventoryItems.map(item => (
                  <option key={item.ID} value={item.ID}>
                    {item.item_name}
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
                <option value="stock_in">Stock In</option>
                <option value="stock_out">Stock Out</option>
                <option value="adjustment">Adjustment</option>
                <option value="transfer">Transfer</option>
                <option value="return">Return</option>
                <option value="expired">Expired</option>
                <option value="damaged">Damaged</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                placeholder="Enter quantity"
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
                placeholder="PO-123, INV-456, etc."
              />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Cost per Unit
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
                Batch Number
              </label>
              <input
                type="text"
                value={transactionForm.batch_number}
                onChange={(e) => setTransactionForm(prev => ({ ...prev, batch_number: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Batch number"
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
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              rows="3"
              placeholder="Additional notes..."
            />
          </div>

          <div className="flex justify-end space-x-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowAddModal(false)}
              disabled={submitting}
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

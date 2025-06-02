import React, { useState, useEffect, useCallback } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import Modal from '../components/Modal';
import Table from '../components/Table';
import InventoryForm from '../components/InventoryForm';
import LoadingState from '../components/LoadingState';
import Alert from '../components/Alert';
import InventoryQuickActions from '../components/InventoryQuickActions';
import InventoryReports from '../components/InventoryReports';
import InventoryAlerts from '../components/InventoryAlerts';
import InventoryTransactions from '../components/InventoryTransactions';
import InventorySuppliers from '../components/InventorySuppliers';
import InventoryReorders from '../components/InventoryReorders';
import inventoryService from '../services/inventoryService';
import { usePermissions, PermissionGate, useInventoryActions } from '../hooks/usePermissions.jsx';

const Inventory = () => {
  // Get permissions and actions
  const permissions = usePermissions();
  const inventoryActions = useInventoryActions();
  
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [summary, setSummary] = useState(null);
  const [alertCounts, setAlertCounts] = useState({ total: 0, unacknowledged: 0 });
  
  // Modal states
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  // Filter states
  const [filters, setFilters] = useState({
    search: '',
    category: '',
    status: '',
    low_stock: false,
    expiring: false
  });

  // Pagination states
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [itemsPerPage] = useState(20);

  // Tab states
  const [activeTab, setActiveTab] = useState('inventory');
  const [showReports, setShowReports] = useState(false);

  // Load inventory data
  const loadInventory = useCallback(async () => {
    setLoading(true);
    setError(null);
    
    try {
      const params = {
        page: currentPage,
        per_page: itemsPerPage,
        ...filters
      };
      
      const response = await inventoryService.getItems(params);
      setItems(response.data || []);
      setTotalPages(Math.ceil((response.total || 0) / itemsPerPage));
    } catch (err) {
      setError('Failed to load inventory items');
      console.error('Error loading inventory:', err);
    } finally {
      setLoading(false);
    }
  }, [currentPage, itemsPerPage, filters]);

  // Load summary data
  const loadSummary = useCallback(async () => {
    try {
      const summaryData = await inventoryService.getSummary();
      setSummary(summaryData);
    } catch (err) {
      console.error('Error loading summary:', err);
    }
  }, []);

  // Load alert counts
  const loadAlertCounts = useCallback(async () => {
    try {
      const alerts = await inventoryService.getAlerts({ page: 1, per_page: 1000 });
      const total = alerts.total || 0;
      const unacknowledged = alerts.data?.filter(alert => !alert.acknowledged_at).length || 0;
      setAlertCounts({ total, unacknowledged });
    } catch (err) {
      console.error('Error loading alert counts:', err);
    }
  }, []);

  // Global refresh function for cross-tab updates
  const refreshAllData = useCallback(() => {
    loadInventory();
    loadSummary();
    loadAlertCounts();
  }, [loadInventory, loadSummary, loadAlertCounts]);

  // Initial load
  useEffect(() => {
    loadInventory();
    loadSummary();
    loadAlertCounts();
  }, [loadInventory, loadSummary, loadAlertCounts]);

  // Keyboard shortcuts
  useEffect(() => {
    const handleKeyPress = (event) => {
      // Ctrl/Cmd + N for new item (only if user can create)
      if ((event.ctrlKey || event.metaKey) && event.key === 'n' && permissions.canCreate) {
        event.preventDefault();
        setShowAddModal(true);
      }
      
      // Escape to close modals
      if (event.key === 'Escape') {
        if (showAddModal) setShowAddModal(false);
        if (showEditModal) {
          setShowEditModal(false);
          setSelectedItem(null);
        }
        if (showDeleteModal) {
          setShowDeleteModal(false);
          setSelectedItem(null);
        }
      }
    };

    document.addEventListener('keydown', handleKeyPress);
    return () => document.removeEventListener('keydown', handleKeyPress);
  }, [showAddModal, showEditModal, showDeleteModal, permissions.canCreate]);

  // Handle filter changes
  const handleFilterChange = (key, value) => {
    setFilters(prev => ({
      ...prev,
      [key]: value
    }));
    setCurrentPage(1); // Reset to first page when filtering
  };

  // Handle quick actions filter application
  const handleQuickFilter = (quickFilters) => {
    setFilters(prev => ({
      ...prev,
      ...quickFilters
    }));
    setCurrentPage(1);
  };

  // Handle adding new item
  const handleAddItem = async (itemData) => {
    setSubmitting(true);
    setError(null);
    try {
      await inventoryService.createItem(itemData);
      setShowAddModal(false);
      setSuccess('Item added successfully!');
      loadInventory();
      loadSummary();
    } catch (err) {
      setError('Failed to add item. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  // Handle editing item
  const handleEditItem = async (itemData) => {
    setSubmitting(true);
    setError(null);
    try {
      await inventoryService.updateItem(selectedItem.id, itemData);
      setShowEditModal(false);
      setSelectedItem(null);
      setSuccess('Item updated successfully!');
      loadInventory();
      loadSummary();
    } catch (err) {
      setError('Failed to update item. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  // Handle deleting item
  const handleDeleteItem = async () => {
    setSubmitting(true);
    setError(null);
    try {
      await inventoryService.deleteItem(selectedItem.id);
      setShowDeleteModal(false);
      setSelectedItem(null);
      setSuccess('Item deleted successfully!');
      loadInventory();
      loadSummary();
    } catch (err) {
      setError('Failed to delete item. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  // Handle export
  const handleExport = async () => {
    setError(null);
    try {
      await inventoryService.exportCSV(filters);
      setSuccess('Data exported successfully!');
    } catch (err) {
      setError('Failed to export data. Please try again.');
    }
  };

  // Table columns configuration with permission-based filtering
  const getAllColumns = () => [
    {
      header: 'Item Name',
      accessor: 'item_name',
      render: (item) => (
        <div>
          <div className="font-medium text-gray-900">{item.item_name}</div>
          <div className="text-sm text-gray-500">{item.category}</div>
        </div>
      )
    },
    {
      header: 'Quantity',
      accessor: 'quantity',
      render: (item) => (
        <div className="text-right">
          <div className="font-medium">{item.quantity} {item.unit}</div>
          <div className="text-sm text-gray-500">Min: {item.reorder_level}</div>
        </div>
      )
    },
    {
      header: 'Status',
      accessor: 'status',
      render: (item) => {
        const { text, color } = inventoryService.getStatusInfo(item.status);
        const colorClasses = {
          green: 'bg-green-100 text-green-800',
          yellow: 'bg-yellow-100 text-yellow-800',
          red: 'bg-red-100 text-red-800',
          blue: 'bg-blue-100 text-blue-800',
          gray: 'bg-gray-100 text-gray-800'
        };
        
        return (
          <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${colorClasses[color]}`}>
            {text}
          </span>
        );
      }
    },
    {
      header: 'Location',
      accessor: 'location'
    },
    {
      header: 'Expiry',
      accessor: 'expiry_date',
      render: (item) => {
        if (!item.expiry_date) return '-';
        
        const expiryDate = new Date(item.expiry_date);
        const today = new Date();
        const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
        
        let className = 'text-gray-900';
        if (daysUntilExpiry < 0) {
          className = 'text-red-600 font-medium';
        } else if (daysUntilExpiry <= 30) {
          className = 'text-yellow-600 font-medium';
        }
        
        return (
          <div className={className}>
            {expiryDate.toLocaleDateString()}
            {daysUntilExpiry <= 30 && (
              <div className="text-xs">
                {daysUntilExpiry < 0 ? 'Expired' : `${daysUntilExpiry} days`}
              </div>
            )}
          </div>
        );
      }
    },
    // Cost column - only show if user has reports permission
    ...(permissions.canViewReports ? [{
      header: 'Cost',
      accessor: 'cost',
      render: (item) => item.cost ? `₦${Number(item.cost).toLocaleString()}` : '-'
    }] : []),
    // Actions column - only show if user can edit or delete
    ...((permissions.canEdit || permissions.canDelete) ? [{
      header: 'Actions',
      accessor: 'actions',
      render: (item) => (
        <div className="flex space-x-2">
          <PermissionGate permission="edit">
            <Button
              size="sm"
              variant="secondary"
              onClick={(e) => {
                e.stopPropagation();
                setSelectedItem(item);
                setShowEditModal(true);
              }}
            >
              Edit
            </Button>
          </PermissionGate>
          <PermissionGate permission="delete">
            <Button
              size="sm"
              variant="danger"
              onClick={(e) => {
                e.stopPropagation();
                setSelectedItem(item);
                setShowDeleteModal(true);
              }}
            >
              Delete
            </Button>
          </PermissionGate>
        </div>
      )
    }] : [])
  ];

  const columns = getAllColumns();

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      {/* Header Section */}
      <div className="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg border border-blue-300 p-6 text-white">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <h1 className="text-3xl font-bold flex items-center gap-3">
              🏥 Inventory Management
            </h1>
            <p className="text-blue-100 mt-1">
              Manage medical supplies, track stock levels, and monitor inventory health
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <PermissionGate permission="reports">
              <Button 
                variant={showReports ? "primary" : "secondary"} 
                size="sm"
                onClick={() => setShowReports(!showReports)}
                className={`whitespace-nowrap ${showReports ? 'bg-white text-blue-600 hover:bg-gray-100' : 'bg-blue-500 text-white hover:bg-blue-400'}`}
              >
                {showReports ? '📊 Hide Reports' : '📊 Show Reports'}
              </Button>
            </PermissionGate>
            <PermissionGate permission="export">
              <Button 
                variant="secondary" 
                size="sm"
                onClick={handleExport}
                className="whitespace-nowrap bg-blue-500 text-white hover:bg-blue-400 border-blue-400"
              >
                📄 Export CSV
              </Button>
            </PermissionGate>
            <PermissionGate permission="create">
              <Button 
                variant="primary" 
                onClick={() => setShowAddModal(true)}
                className="whitespace-nowrap bg-white text-blue-600 hover:bg-gray-100 font-medium"
              >
                ➕ Add New Item
                <span className="ml-2 text-xs opacity-75 hidden sm:inline">(Ctrl+N)</span>
              </Button>
            </PermissionGate>
          </div>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <nav className="flex space-x-0 overflow-x-auto scrollbar-hide">
          <PermissionGate key="nav-inventory" permission="view">
            <button
              onClick={() => setActiveTab('inventory')}
              className={`py-4 px-6 border-b-3 font-medium text-sm whitespace-nowrap transition-all duration-200 flex-shrink-0 ${
                activeTab === 'inventory'
                  ? 'border-blue-500 text-blue-600 bg-blue-50 shadow-sm'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'
              }`}
            >
              <span className="flex items-center gap-2">
                📦 Inventory Items
              </span>
            </button>
          </PermissionGate>
          <PermissionGate key="nav-alerts" permission="view">
            <button
              onClick={() => setActiveTab('alerts')}
              className={`py-4 px-6 border-b-3 font-medium text-sm whitespace-nowrap relative transition-all duration-200 flex-shrink-0 ${
                activeTab === 'alerts'
                  ? 'border-blue-500 text-blue-600 bg-blue-50 shadow-sm'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'
              }`}
            >
              <span className="flex items-center gap-2">
                🚨 Alerts & Notifications
                {alertCounts.unacknowledged > 0 && (
                  <span className="bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center ml-1 animate-pulse">
                    {alertCounts.unacknowledged}
                  </span>
                )}
              </span>
            </button>
          </PermissionGate>
          <PermissionGate key="nav-transactions" permission="view">
            <button
              onClick={() => setActiveTab('transactions')}
              className={`py-4 px-6 border-b-3 font-medium text-sm whitespace-nowrap transition-all duration-200 flex-shrink-0 ${
                activeTab === 'transactions'
                  ? 'border-blue-500 text-blue-600 bg-blue-50 shadow-sm'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'
              }`}
            >
              <span className="flex items-center gap-2">
                📋 Transaction History
              </span>
            </button>
          </PermissionGate>
          <PermissionGate key="nav-suppliers" permission="view">
            <button
              onClick={() => setActiveTab('suppliers')}
              className={`py-4 px-6 border-b-3 font-medium text-sm whitespace-nowrap transition-all duration-200 flex-shrink-0 ${
                activeTab === 'suppliers'
                  ? 'border-blue-500 text-blue-600 bg-blue-50 shadow-sm'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'
              }`}
            >
              <span className="flex items-center gap-2">
                🏢 Suppliers
              </span>
            </button>
          </PermissionGate>
          <PermissionGate key="nav-reorders" permission="view">
            <button
              onClick={() => setActiveTab('reorders')}
              className={`py-4 px-6 border-b-3 font-medium text-sm whitespace-nowrap transition-all duration-200 flex-shrink-0 ${
                activeTab === 'reorders'
                  ? 'border-blue-500 text-blue-600 bg-blue-50 shadow-sm'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'
              }`}
            >
              <span className="flex items-center gap-2">
                🔄 Reorders
              </span>
            </button>
          </PermissionGate>
          <PermissionGate key="nav-reports" permission="reports">
            <button
              onClick={() => setActiveTab('reports')}
              className={`py-4 px-6 border-b-3 font-medium text-sm whitespace-nowrap transition-all duration-200 flex-shrink-0 ${
                activeTab === 'reports'
                  ? 'border-blue-500 text-blue-600 bg-blue-50 shadow-sm'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'
              }`}
            >
              <span className="flex items-center gap-2">
                📊 Reports & Analytics
              </span>
            </button>
          </PermissionGate>
        </nav>
      </div>

      {/* Tab Content */}
      <PermissionGate permission="view">
        {activeTab === 'inventory' && (
          <div className="space-y-6">
          {/* Summary Cards */}
          {summary && (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <Card className="bg-gradient-to-r from-blue-50 to-blue-100 border-blue-200 hover:shadow-md transition-shadow">
                <div className="text-center">
                  <div className="flex items-center justify-center mb-2">
                    <span className="text-2xl">📦</span>
                  </div>
                  <div className="text-3xl font-bold text-blue-600 mb-1">{summary.total_items}</div>
                  <div className="text-sm font-medium text-blue-800">Total Items</div>
                </div>
              </Card>
              <Card className="bg-gradient-to-r from-green-50 to-green-100 border-green-200 hover:shadow-md transition-shadow">
                <div className="text-center">
                  <div className="flex items-center justify-center mb-2">
                    <span className="text-2xl">✅</span>
                  </div>
                  <div className="text-3xl font-bold text-green-600 mb-1">{summary.in_stock}</div>
                  <div className="text-sm font-medium text-green-800">In Stock</div>
                </div>
              </Card>
              <Card className="bg-gradient-to-r from-yellow-50 to-yellow-100 border-yellow-200 hover:shadow-md transition-shadow">
                <div className="text-center">
                  <div className="flex items-center justify-center mb-2">
                    <span className="text-2xl">⚠️</span>
                  </div>
                  <div className="text-3xl font-bold text-yellow-600 mb-1">{summary.low_stock}</div>
                  <div className="text-sm font-medium text-yellow-800">Low Stock</div>
                </div>
              </Card>
              <Card className="bg-gradient-to-r from-red-50 to-red-100 border-red-200 hover:shadow-md transition-shadow">
                <div className="text-center">
                  <div className="flex items-center justify-center mb-2">
                    <span className="text-2xl">🚫</span>
                  </div>
                  <div className="text-3xl font-bold text-red-600 mb-1">{summary.out_of_stock}</div>
                  <div className="text-sm font-medium text-red-800">Out of Stock</div>
                </div>
              </Card>
            </div>
          )}

          {/* Quick Actions */}
          <PermissionGate permission="critical_items">
            <InventoryQuickActions onReload={handleQuickFilter} />
          </PermissionGate>

          {/* Collapsible Reports Section */}
          <PermissionGate permission="reports">
            {showReports && (
              <Card title="📊 Quick Reports & Analytics" className="bg-gradient-to-r from-purple-50 to-indigo-50 border-purple-200">
                <InventoryReports />
              </Card>
            )}
          </PermissionGate>

          {/* Filters */}
          <Card title="🔍 Search & Filter Options">
            <div className="space-y-6">
              {/* Search and Main Filters Row */}
              <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div className="lg:col-span-2">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Search Items
                  </label>
                  <div className="relative">
                    <input
                      type="text"
                      placeholder="Search by name, description, or SKU..."
                      value={filters.search}
                      onChange={(e) => handleFilterChange('search', e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-4 py-2.5 pl-10 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 text-gray-900 placeholder-gray-500"
                    />
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span className="text-gray-400 text-sm">🔍</span>
                    </div>
                    {filters.search && (
                      <button
                        onClick={() => handleFilterChange('search', '')}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors"
                      >
                        <span className="text-sm">✖</span>
                      </button>
                    )}
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Category
                  </label>
                  <select
                    value={filters.category}
                    onChange={(e) => handleFilterChange('category', e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-4 py-2.5 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  >
                    <option key="all-categories" value="">All Categories</option>
                    {inventoryService.getCategories().map(category => (
                      <option key={category} value={category}>{category}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Status
                  </label>
                  <select
                    value={filters.status}
                    onChange={(e) => handleFilterChange('status', e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-4 py-2.5 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  >
                    <option key="all-statuses" value="">All Statuses</option>
                    {inventoryService.getStatuses().map(status => {
                      const { text } = inventoryService.getStatusInfo(status);
                      return (
                        <option key={status} value={status}>{text}</option>
                      );
                    })}
                  </select>
                </div>
              </div>
              
              {/* Checkbox Filters and Actions Row */}
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-4 border-t border-gray-200">
                <div className="flex flex-wrap items-center gap-6">
                  <div className="flex items-center bg-gray-50 px-3 py-2 rounded-lg">
                    <input
                      type="checkbox"
                      id="low_stock"
                      checked={filters.low_stock}
                      onChange={(e) => handleFilterChange('low_stock', e.target.checked)}
                      className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded transition-colors"
                    />
                    <label htmlFor="low_stock" className="ml-2 text-sm font-medium text-gray-700">
                      🔴 Low Stock Only
                    </label>
                  </div>
                  <div className="flex items-center bg-gray-50 px-3 py-2 rounded-lg">
                    <input
                      type="checkbox"
                      id="expiring"
                      checked={filters.expiring}
                      onChange={(e) => handleFilterChange('expiring', e.target.checked)}
                      className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded transition-colors"
                    />
                    <label htmlFor="expiring" className="ml-2 text-sm font-medium text-gray-700">
                      ⏰ Expiring Soon
                    </label>
                  </div>
                </div>
                
                <div className="flex items-center gap-3 bg-blue-50 px-4 py-2 rounded-lg border border-blue-200">
                  <span className="text-sm font-medium text-blue-700">
                    📊 {items.length} item{items.length !== 1 ? 's' : ''} found
                  </span>
                  {(filters.search || filters.category || filters.status || filters.low_stock || filters.expiring) && (
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={() => {
                        setFilters({
                          search: '',
                          category: '',
                          status: '',
                          low_stock: false,
                          expiring: false
                        });
                        setCurrentPage(1);
                      }}
                      className="whitespace-nowrap text-xs"
                    >
                      ✖ Clear Filters
                    </Button>
                  )}
                </div>
              </div>
            </div>
          </Card>

          {/* Error and Success Messages */}
          {error && (
            <Alert 
              type="error" 
              title="⚠️ Error"
              onClose={() => setError(null)}
              className="border-l-4 border-red-500"
            >
              {error}
            </Alert>
          )}

          {success && (
            <Alert 
              type="success" 
              title="✅ Success"
              onClose={() => setSuccess(null)}
              className="border-l-4 border-green-500"
            >
              {success}
            </Alert>
          )}

      {/* Items Table */}
      <Card title="📋 Inventory Items">
        {loading ? (
          <div className="flex items-center justify-center py-12">
            <LoadingState message="Loading inventory items..." />
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <Table
                columns={columns}
                data={items}
                emptyMessage="No inventory items found matching your criteria"
              />
            </div>
            
            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex flex-col sm:flex-row justify-between items-center mt-6 pt-6 border-t border-gray-200 gap-4">
                <div className="text-sm text-gray-700 font-medium">
                  Showing page {currentPage} of {totalPages} 
                  <span className="text-gray-500 ml-2">({items.length} items on this page)</span>
                </div>
                <div className="flex space-x-2">
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage(prev => prev - 1)}
                    className="px-4"
                  >
                    ← Previous
                  </Button>
                  <div className="flex items-center px-3 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded border">
                    {currentPage}
                  </div>
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={currentPage === totalPages}
                    onClick={() => setCurrentPage(prev => prev + 1)}
                    className="px-4"
                  >
                    Next →
                  </Button>
                </div>
              </div>
            )}
          </>
        )}
      </Card>
        </div>
        )}
      </PermissionGate>

      {/* Reports Tab */}
      <PermissionGate permission="reports">
        {activeTab === 'reports' && (
          <div className="space-y-6">
            <InventoryReports />
          </div>
        )}
      </PermissionGate>

      {/* Alerts Tab */}
      <PermissionGate permission="view">
        {activeTab === 'alerts' && (
          <div className="space-y-6">
            <InventoryAlerts onRefresh={refreshAllData} />
          </div>
        )}
      </PermissionGate>

      {/* Transactions Tab */}
      <PermissionGate permission="view">
        {activeTab === 'transactions' && (
          <div className="space-y-6">
            <InventoryTransactions onRefresh={refreshAllData} />
          </div>
        )}
      </PermissionGate>

      {/* Suppliers Tab */}
      <PermissionGate permission="view">
        {activeTab === 'suppliers' && (
          <div className="space-y-6">
            <InventorySuppliers onRefresh={refreshAllData} />
          </div>
        )}
      </PermissionGate>

      {/* Reorders Tab */}
      <PermissionGate permission="view">
        {activeTab === 'reorders' && (
          <div className="space-y-6">
            <InventoryReorders onRefresh={refreshAllData} />
          </div>
        )}
      </PermissionGate>

      {/* Modals (Always rendered regardless of active tab) */}
      {/* Add Item Modal */}
      <Modal
        isOpen={showAddModal}
        onClose={() => setShowAddModal(false)}
        title="Add New Item"
        size="lg"
      >
        <InventoryForm
          onSubmit={handleAddItem}
          onCancel={() => setShowAddModal(false)}
          isSubmitting={submitting}
        />
      </Modal>

      {/* Edit Item Modal */}
      <Modal
        isOpen={showEditModal}
        onClose={() => {
          setShowEditModal(false);
          setSelectedItem(null);
        }}
        title="Edit Item"
        size="lg"
      >
        <InventoryForm
          item={selectedItem}
          onSubmit={handleEditItem}
          onCancel={() => {
            setShowEditModal(false);
            setSelectedItem(null);
          }}
          isSubmitting={submitting}
        />
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteModal}
        onClose={() => {
          setShowDeleteModal(false);
          setSelectedItem(null);
        }}
        title="Confirm Deletion"
        size="sm"
      >
        <div className="space-y-4">
          <p className="text-gray-600">
            Are you sure you want to delete "{selectedItem?.item_name}"? This action cannot be undone.
          </p>
          <div className="flex justify-end space-x-3">
            <Button
              variant="secondary"
              onClick={() => {
                setShowDeleteModal(false);
                setSelectedItem(null);
              }}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button
              variant="danger"
              onClick={handleDeleteItem}
              disabled={submitting}
            >
              {submitting ? 'Deleting...' : 'Delete'}
            </Button>
          </div>
        </div>
      </Modal>
      </div>
    </div>
  );
};

export default Inventory;

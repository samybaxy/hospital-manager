import React, { useState, useEffect } from 'react';
import Card from './Card';
import Button from './Button';
import Alert from './Alert';
import LoadingState from './LoadingState';
import Table from './Table';
import Badge from './Badge';
import inventoryService from '../services/inventoryService';
import { usePermissions } from '../hooks/usePermissions.jsx';

const InventoryAlerts = ({ onRefresh }) => {
  const permissions = usePermissions();
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [processingAlert, setProcessingAlert] = useState(null);

  // Filter states
  const [filters, setFilters] = useState({
    type: '',
    severity: '',
    status: 'active'
  });

  // Pagination states
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [totalItems, setTotalItems] = useState(0);

  useEffect(() => {
    loadAlerts();
  }, [filters, currentPage, itemsPerPage]);

  const loadAlerts = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page: currentPage,
        per_page: itemsPerPage,
        ...filters
      };
      
      const response = await inventoryService.getAlerts(params);
      
      // Handle different response structures
      if (response.data && Array.isArray(response.data.data)) {
        // Response with pagination metadata
        setAlerts(response.data.data);
        setTotalItems(response.data.pagination?.total || 0);
        setTotalPages(response.data.pagination?.pages || 1);
      } else if (Array.isArray(response.data)) {
        // Simple array response
        setAlerts(response.data);
        setTotalItems(response.data.length);
        setTotalPages(1);
      } else {
        setAlerts([]);
        setTotalItems(0);
        setTotalPages(1);
      }
    } catch (err) {
      setError('Failed to load alerts');
      console.error('Error loading alerts:', err);
      setAlerts([]);
      setTotalItems(0);
      setTotalPages(1);
    } finally {
      setLoading(false);
    }
  };

  const handleAcknowledgeAlert = async (alertId) => {
    setProcessingAlert(alertId);
    try {
      await inventoryService.acknowledgeAlert(alertId);
      setSuccess('Alert acknowledged successfully');
      loadAlerts();
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError('Failed to acknowledge alert');
    } finally {
      setProcessingAlert(null);
    }
  };

  const handleResolveAlert = async (alertId) => {
    setProcessingAlert(alertId);
    try {
      await inventoryService.resolveAlert(alertId);
      setSuccess('Alert resolved successfully');
      loadAlerts();
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError('Failed to resolve alert');
    } finally {
      setProcessingAlert(null);
    }
  };

  const handleGenerateAlerts = async () => {
    setLoading(true);
    try {
      await inventoryService.generateAlerts();
      setSuccess('Alerts generated successfully');
      loadAlerts();
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError('Failed to generate alerts');
    } finally {
      setLoading(false);
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

  const getSeverityBadgeProps = (severity) => {
    const severityInfo = inventoryService.getAlertSeverityInfo(severity);
    return {
      variant: severityInfo.color,
      children: severityInfo.text
    };
  };

  const getTypeBadgeProps = (type) => {
    const typeMap = {
      'low_stock': { variant: 'yellow', text: 'Low Stock' },
      'out_of_stock': { variant: 'red', text: 'Out of Stock' },
      'expired': { variant: 'red', text: 'Expired' },
      'expiring_soon': { variant: 'orange', text: 'Expiring Soon' },
      'critical_level': { variant: 'red', text: 'Critical Level' },
      'reorder_point': { variant: 'blue', text: 'Reorder Point' }
    };
    
    const info = typeMap[type] || { variant: 'gray', text: type };
    return {
      variant: info.variant,
      children: info.text
    };
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString();
  };

  const alertColumns = [
    {
      header: 'Item Details',
      accessor: 'item_name',
      render: (alert) => (
        <div>
          <div className="font-medium text-gray-900">{alert.item_name || 'Unknown Item'}</div>
          <div className="text-sm text-gray-500">{alert.category}</div>
          {alert.location && (
            <div className="text-xs text-gray-400">Location: {alert.location}</div>
          )}
        </div>
      )
    },
    {
      header: 'Alert Type',
      accessor: 'alert_type',
      render: (alert) => (
        <div>
          <Badge {...getTypeBadgeProps(alert.alert_type)} />
          <div className="text-xs text-gray-500 mt-1">{alert.title}</div>
        </div>
      )
    },
    {
      header: 'Severity',
      accessor: 'severity',
      render: (alert) => <Badge {...getSeverityBadgeProps(alert.severity)} />
    },
    {
      header: 'Stock Info',
      accessor: 'current_value',
      render: (alert) => (
        <div className="text-sm">
          <div className="flex justify-between">
            <span>Current:</span>
            <span className="font-medium">
              {alert.current_value !== null ? `${alert.current_value} ${alert.unit || ''}` : 'N/A'}
            </span>
          </div>
          <div className="flex justify-between text-gray-500">
            <span>Threshold:</span>
            <span>
              {alert.threshold_value !== null ? `${alert.threshold_value} ${alert.unit || ''}` : 'N/A'}
            </span>
          </div>
          {alert.alert_type === 'reorder_point' && alert.reorder_level && (
            <div className="flex justify-between text-orange-600 text-xs">
              <span>Reorder Level:</span>
              <span>{alert.reorder_level} {alert.unit || ''}</span>
            </div>
          )}
        </div>
      )
    },
    {
      header: 'Expiry Info',
      accessor: 'expiry_date',
      render: (alert) => {
        if (!alert.expiry_date) return <span className="text-gray-400">No expiry</span>;
        
        const expiryDate = new Date(alert.expiry_date);
        const now = new Date();
        const daysUntilExpiry = Math.ceil((expiryDate - now) / (1000 * 60 * 60 * 24));
        
        let statusColor = 'text-gray-600';
        if (daysUntilExpiry <= 0) {
          statusColor = 'text-red-600';
        } else if (daysUntilExpiry <= 30) {
          statusColor = 'text-orange-600';
        } else if (daysUntilExpiry <= 90) {
          statusColor = 'text-yellow-600';
        }
        
        return (
          <div className="text-sm">
            <div className={`font-medium ${statusColor}`}>
              {expiryDate.toLocaleDateString()}
            </div>
            <div className={`text-xs ${statusColor}`}>
              {daysUntilExpiry <= 0 
                ? `Expired ${Math.abs(daysUntilExpiry)} days ago`
                : `${daysUntilExpiry} days remaining`
              }
            </div>
          </div>
        );
      }
    },
    {
      header: 'Cost Impact',
      accessor: 'cost',
      render: (alert) => {
        if (!alert.cost || !alert.current_value) {
          return <span className="text-gray-400">N/A</span>;
        }
        
        const totalValue = parseFloat(alert.cost) * parseInt(alert.current_value);
        return (
          <div className="text-sm">
            <div className="font-medium">₦{totalValue.toLocaleString()}</div>
            <div className="text-xs text-gray-500">
              ₦{parseFloat(alert.cost).toLocaleString()}/{alert.unit || 'unit'}
            </div>
          </div>
        );
      }
    },
    {
      header: 'Created',
      accessor: 'created_at',
      render: (alert) => (
        <div className="text-sm">{formatDate(alert.created_at)}</div>
      )
    },
    {
      header: 'Status',
      accessor: 'status',
      render: (alert) => (
        <div>
          {alert.acknowledged_at && (
            <div className="text-xs text-blue-600">
              Acknowledged: {formatDate(alert.acknowledged_at)}
            </div>
          )}
          {alert.resolved_at && (
            <div className="text-xs text-green-600">
              Resolved: {formatDate(alert.resolved_at)}
            </div>
          )}
          {!alert.acknowledged_at && !alert.resolved_at && (
            <Badge variant="yellow">Active</Badge>
          )}
        </div>
      )
    },
    {
      header: 'Actions',
      accessor: 'actions',
      render: (alert) => (
        <div className="flex space-x-2">
          {!alert.acknowledged_at && permissions.canUpdate && (
            <Button
              size="sm"
              variant="outline"
              onClick={() => handleAcknowledgeAlert(alert.ID)}
              disabled={processingAlert === alert.ID}
            >
              {processingAlert === alert.ID ? 'Processing...' : 'Acknowledge'}
            </Button>
          )}
          {alert.acknowledged_at && !alert.resolved_at && permissions.canUpdate && (
            <Button
              size="sm"
              variant="primary"
              onClick={() => handleResolveAlert(alert.ID)}
              disabled={processingAlert === alert.ID}
            >
              {processingAlert === alert.ID ? 'Processing...' : 'Resolve'}
            </Button>
          )}
        </div>
      )
    }
  ];

  return (
    <div className="space-y-6">
      <Card className="bg-white">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-2xl font-bold text-gray-900">Inventory Alerts</h2>
          <div className="flex space-x-3">
            {permissions.canUpdate && (
              <Button
                variant="primary"
                onClick={handleGenerateAlerts}
                disabled={loading}
                className="bg-blue-500 hover:bg-blue-600 text-white"
              >
                Generate Alerts
              </Button>
            )}
          </div>
        </div>

        {error && (
          <Alert 
            type="error" 
            className="mb-4"
            title="Error"
            onClose={() => setError(null)}
          >
            {error}
          </Alert>
        )}

        {success && (
          <Alert 
            type="success" 
            className="mb-4"
            title="Success"
            onClose={() => setSuccess(null)}
          >
            {success}
          </Alert>
        )}

        {/* Filters */}
        <Card className="mb-6">
          <div className="py-3 px-4 border-b border-gray-200">
            <h3 className="text-lg font-semibold">Filters</h3>
          </div>
          <div className="p-4">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Alert Type
                </label>
                <select
                  value={filters.type}
                  onChange={(e) => handleFilterChange('type', e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                  <option value="">All Types</option>
                  {inventoryService.getAlertTypes().map(type => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Severity
                </label>
                <select
                  value={filters.severity}
                  onChange={(e) => handleFilterChange('severity', e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                  <option value="">All Severities</option>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="critical">Critical</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Status
                </label>
                <select
                  value={filters.status}
                  onChange={(e) => handleFilterChange('status', e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                  <option value="active">Active Only</option>
                  <option value="acknowledged">Acknowledged</option>
                  <option value="resolved">Resolved</option>
                  <option value="">All Statuses</option>
                </select>
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

        {/* Alerts Table */}
        <Card>
          <div className="py-3 px-4 border-b border-gray-200">
            <div className="flex justify-between items-center">
              <h3 className="text-lg font-semibold">
                Alerts ({totalItems})
              </h3>
              {totalItems > 0 && (
                <div className="text-sm text-gray-500">
                  Showing {((currentPage - 1) * itemsPerPage) + 1} to {Math.min(currentPage * itemsPerPage, totalItems)} of {totalItems} alerts
                </div>
              )}
            </div>
          </div>
          <div className="p-4">
            {loading ? (
              <LoadingState message="Loading alerts..." />
            ) : alerts.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                No alerts found matching the current filters.
              </div>
            ) : (
              <Table
                columns={alertColumns}
                data={alerts}
                emptyMessage="No alerts found"
                pagination={true}
                currentPage={currentPage}
                totalPages={totalPages}
                itemsPerPage={itemsPerPage}
                totalItems={totalItems}
                onPageChange={handlePageChange}
                onItemsPerPageChange={handleItemsPerPageChange}
                showItemsPerPageSelector={false} // Already shown in filters
              />
            )}
          </div>
        </Card>
      </Card>
    </div>
  );
};

export default InventoryAlerts;

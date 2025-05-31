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

  useEffect(() => {
    if (isOpen) {
      loadAlerts();
    }
  }, [isOpen, filters]);

  const loadAlerts = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await inventoryService.getAlerts(filters);
      setAlerts(response.data || []);
    } catch (err) {
      setError('Failed to load alerts');
      console.error('Error loading alerts:', err);
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
      key: 'item_name',
      header: 'Item',
      render: (alert) => (
        <div>
          <div className="font-medium">{alert.item_name}</div>
          <div className="text-sm text-gray-500">{alert.title}</div>
        </div>
      )
    },
    {
      key: 'alert_type',
      header: 'Type',
      render: (alert) => <Badge {...getTypeBadgeProps(alert.alert_type)} />
    },
    {
      key: 'severity',
      header: 'Severity',
      render: (alert) => <Badge {...getSeverityBadgeProps(alert.severity)} />
    },
    {
      key: 'current_value',
      header: 'Current/Threshold',
      render: (alert) => (
        <div className="text-sm">
          <div>Current: {alert.current_value || 'N/A'}</div>
          <div className="text-gray-500">Threshold: {alert.threshold_value || 'N/A'}</div>
        </div>
      )
    },
    {
      key: 'created_at',
      header: 'Created',
      render: (alert) => (
        <div className="text-sm">
          {formatDate(alert.created_at)}
        </div>
      )
    },
    {
      key: 'status',
      header: 'Status',
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
      key: 'actions',
      header: 'Actions',
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

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4">
        <div className="fixed inset-0 bg-black opacity-50" onClick={onClose}></div>
        <div className="relative bg-white rounded-lg shadow-xl max-w-7xl w-full max-h-screen overflow-y-auto">
          <div className="p-6">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-2xl font-bold text-gray-900">Inventory Alerts</h2>
              <div className="flex space-x-3">
                {permissions.canUpdate && (
                  <Button
                    variant="outline"
                    onClick={handleGenerateAlerts}
                    disabled={loading}
                  >
                    Generate Alerts
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
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Alert Type
                    </label>
                    <select
                      value={filters.type}
                      onChange={(e) => setFilters(prev => ({ ...prev, type: e.target.value }))}
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
                      onChange={(e) => setFilters(prev => ({ ...prev, severity: e.target.value }))}
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
                      onChange={(e) => setFilters(prev => ({ ...prev, status: e.target.value }))}
                      className="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                      <option value="active">Active Only</option>
                      <option value="acknowledged">Acknowledged</option>
                      <option value="resolved">Resolved</option>
                      <option value="">All Statuses</option>
                    </select>
                  </div>
                </div>
              </Card.Body>
            </Card>

            {/* Alerts Table */}
            <Card>
              <Card.Header>
                <h3 className="text-lg font-semibold">
                  Alerts ({alerts.length})
                </h3>
              </Card.Header>
              <Card.Body>
                {loading ? (
                  <LoadingState />
                ) : alerts.length === 0 ? (
                  <div className="text-center py-8 text-gray-500">
                    No alerts found matching the current filters.
                  </div>
                ) : (
                  <Table
                    columns={alertColumns}
                    data={alerts}
                    keyField="ID"
                  />
                )}
              </Card.Body>
            </Card>
          </div>
        </div>
      </div>
    </div>
  );
};

export default InventoryAlerts;

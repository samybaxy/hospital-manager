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

const InventorySuppliers = ({ onRefresh }) => {
  const permissions = usePermissions();
  const [suppliers, setSuppliers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  
  // Modal states
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [selectedSupplier, setSelectedSupplier] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  // Filter states
  const [filters, setFilters] = useState({
    search: '',
    is_active: 'all'
  });

  // Form state
  const [supplierForm, setSupplierForm] = useState({
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    country: 'Nigeria',
    postal_code: '',
    tax_id: '',
    payment_terms: '',
    delivery_time_days: '',
    minimum_order_amount: '',
    is_active: true,
    notes: ''
  });

  useEffect(() => {
    if (isOpen) {
      loadSuppliers();
    }
  }, [isOpen, filters]);

  const loadSuppliers = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = filters.is_active !== 'all' ? { is_active: filters.is_active } : {};
      if (filters.search) {
        params.search = filters.search;
      }
      const response = await inventoryService.getSuppliers(params);
      setSuppliers(response.data || []);
    } catch (err) {
      setError('Failed to load suppliers');
      console.error('Error loading suppliers:', err);
    } finally {
      setLoading(false);
    }
  };

  const resetForm = () => {
    setSupplierForm({
      name: '',
      contact_person: '',
      email: '',
      phone: '',
      address: '',
      city: '',
      state: '',
      country: 'Nigeria',
      postal_code: '',
      tax_id: '',
      payment_terms: '',
      delivery_time_days: '',
      minimum_order_amount: '',
      is_active: true,
      notes: ''
    });
  };

  const handleAdd = () => {
    resetForm();
    setShowAddModal(true);
  };

  const handleEdit = (supplier) => {
    setSelectedSupplier(supplier);
    setSupplierForm({
      name: supplier.name || '',
      contact_person: supplier.contact_person || '',
      email: supplier.email || '',
      phone: supplier.phone || '',
      address: supplier.address || '',
      city: supplier.city || '',
      state: supplier.state || '',
      country: supplier.country || 'Nigeria',
      postal_code: supplier.postal_code || '',
      tax_id: supplier.tax_id || '',
      payment_terms: supplier.payment_terms || '',
      delivery_time_days: supplier.delivery_time_days || '',
      minimum_order_amount: supplier.minimum_order_amount || '',
      is_active: supplier.is_active === '1' || supplier.is_active === true,
      notes: supplier.notes || ''
    });
    setShowEditModal(true);
  };

  const handleDelete = (supplier) => {
    setSelectedSupplier(supplier);
    setShowDeleteModal(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    
    try {
      if (showEditModal && selectedSupplier) {
        await inventoryService.updateSupplier(selectedSupplier.ID, supplierForm);
        setSuccess('Supplier updated successfully');
        setShowEditModal(false);
      } else {
        await inventoryService.createSupplier(supplierForm);
        setSuccess('Supplier created successfully');
        setShowAddModal(false);
      }
      resetForm();
      setSelectedSupplier(null);
      loadSuppliers();
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError(`Failed to ${showEditModal ? 'update' : 'create'} supplier`);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDeleteConfirm = async () => {
    setSubmitting(true);
    setError(null);
    
    try {
      await inventoryService.deleteSupplier(selectedSupplier.ID);
      setSuccess('Supplier deleted successfully');
      setShowDeleteModal(false);
      setSelectedSupplier(null);
      loadSuppliers();
      onRefresh?.(); // Refresh parent component data
    } catch (err) {
      setError('Failed to delete supplier');
    } finally {
      setSubmitting(false);
    }
  };

  const formatCurrency = (amount) => {
    return amount ? `₦${parseFloat(amount).toLocaleString()}` : 'N/A';
  };

  const supplierColumns = [
    {
      key: 'name',
      header: 'Name',
      render: (supplier) => (
        <div>
          <div className="font-medium">{supplier.name}</div>
          {supplier.contact_person && (
            <div className="text-sm text-gray-500">Contact: {supplier.contact_person}</div>
          )}
        </div>
      )
    },
    {
      key: 'contact_info',
      header: 'Contact Information',
      render: (supplier) => (
        <div className="text-sm space-y-1">
          {supplier.email && (
            <div>📧 {supplier.email}</div>
          )}
          {supplier.phone && (
            <div>📞 {supplier.phone}</div>
          )}
        </div>
      )
    },
    {
      key: 'location',
      header: 'Location',
      render: (supplier) => (
        <div className="text-sm">
          {supplier.city && supplier.state ? (
            <div>{supplier.city}, {supplier.state}</div>
          ) : supplier.city || supplier.state ? (
            <div>{supplier.city || supplier.state}</div>
          ) : null}
          {supplier.country && supplier.country !== 'Nigeria' && (
            <div>{supplier.country}</div>
          )}
        </div>
      )
    },
    {
      key: 'business_info',
      header: 'Business Info',
      render: (supplier) => (
        <div className="text-sm space-y-1">
          {supplier.payment_terms && (
            <div>Payment: {supplier.payment_terms}</div>
          )}
          {supplier.delivery_time_days && (
            <div>Delivery: {supplier.delivery_time_days} days</div>
          )}
          {supplier.minimum_order_amount && (
            <div>Min Order: {formatCurrency(supplier.minimum_order_amount)}</div>
          )}
        </div>
      )
    },
    {
      key: 'status',
      header: 'Status',
      render: (supplier) => (
        <Badge 
          variant={supplier.is_active === '1' || supplier.is_active === true ? 'green' : 'red'}
        >
          {supplier.is_active === '1' || supplier.is_active === true ? 'Active' : 'Inactive'}
        </Badge>
      )
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (supplier) => (
        <div className="flex space-x-2">
          {permissions.canUpdate && (
            <Button
              size="sm"
              variant="outline"
              onClick={() => handleEdit(supplier)}
            >
              Edit
            </Button>
          )}
          {permissions.canDelete && (
            <Button
              size="sm"
              variant="danger"
              onClick={() => handleDelete(supplier)}
            >
              Delete
            </Button>
          )}
        </div>
      )
    }
  ];

  const filteredSuppliers = suppliers.filter(supplier => {
    if (filters.search) {
      const searchLower = filters.search.toLowerCase();
      return (
        supplier.name.toLowerCase().includes(searchLower) ||
        (supplier.contact_person && supplier.contact_person.toLowerCase().includes(searchLower)) ||
        (supplier.email && supplier.email.toLowerCase().includes(searchLower))
      );
    }
    return true;
  });

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4">
        <div className="fixed inset-0 bg-black opacity-50" onClick={onClose}></div>
        <div className="relative bg-white rounded-lg shadow-xl max-w-7xl w-full max-h-screen overflow-y-auto">
          <div className="p-6">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-2xl font-bold text-gray-900">Inventory Suppliers</h2>
              <div className="flex space-x-3">
                {permissions.canCreate && (
                  <Button
                    variant="primary"
                    onClick={handleAdd}
                  >
                    Add Supplier
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
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Search Suppliers
                    </label>
                    <input
                      type="text"
                      value={filters.search}
                      onChange={(e) => setFilters(prev => ({ ...prev, search: e.target.value }))}
                      placeholder="Search by name, contact person, or email..."
                      className="w-full border border-gray-300 rounded-md px-3 py-2"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Status
                    </label>
                    <select
                      value={filters.is_active}
                      onChange={(e) => setFilters(prev => ({ ...prev, is_active: e.target.value }))}
                      className="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                      <option value="all">All Suppliers</option>
                      <option value="1">Active Only</option>
                      <option value="0">Inactive Only</option>
                    </select>
                  </div>
                </div>
              </Card.Body>
            </Card>

            {/* Suppliers Table */}
            <Card>
              <Card.Header>
                <h3 className="text-lg font-semibold">
                  Suppliers ({filteredSuppliers.length})
                </h3>
              </Card.Header>
              <Card.Body>
                {loading ? (
                  <LoadingState />
                ) : filteredSuppliers.length === 0 ? (
                  <div className="text-center py-8 text-gray-500">
                    No suppliers found matching the current filters.
                  </div>
                ) : (
                  <Table
                    columns={supplierColumns}
                    data={filteredSuppliers}
                    keyField="ID"
                  />
                )}
              </Card.Body>
            </Card>
          </div>
        </div>
      </div>

      {/* Add/Edit Supplier Modal */}
      <Modal
        isOpen={showAddModal || showEditModal}
        onClose={() => {
          setShowAddModal(false);
          setShowEditModal(false);
          setSelectedSupplier(null);
          resetForm();
        }}
        title={showEditModal ? 'Edit Supplier' : 'Add New Supplier'}
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Supplier Name *
              </label>
              <input
                type="text"
                value={supplierForm.name}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, name: e.target.value }))}
                required
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Enter supplier name"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Contact Person
              </label>
              <input
                type="text"
                value={supplierForm.contact_person}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, contact_person: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Contact person name"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Email
              </label>
              <input
                type="email"
                value={supplierForm.email}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, email: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="supplier@email.com"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Phone
              </label>
              <input
                type="tel"
                value={supplierForm.phone}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, phone: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="+234..."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                City
              </label>
              <input
                type="text"
                value={supplierForm.city}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, city: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="City"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                State
              </label>
              <input
                type="text"
                value={supplierForm.state}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, state: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="State"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Country
              </label>
              <input
                type="text"
                value={supplierForm.country}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, country: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Country"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Postal Code
              </label>
              <input
                type="text"
                value={supplierForm.postal_code}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, postal_code: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Postal code"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Tax ID
              </label>
              <input
                type="text"
                value={supplierForm.tax_id}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, tax_id: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Tax identification number"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Payment Terms
              </label>
              <input
                type="text"
                value={supplierForm.payment_terms}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, payment_terms: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="e.g., Net 30, COD"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Delivery Time (Days)
              </label>
              <input
                type="number"
                value={supplierForm.delivery_time_days}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, delivery_time_days: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Typical delivery time in days"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Minimum Order Amount
              </label>
              <input
                type="number"
                step="0.01"
                value={supplierForm.minimum_order_amount}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, minimum_order_amount: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="0.00"
              />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Address
            </label>
            <textarea
              value={supplierForm.address}
              onChange={(e) => setSupplierForm(prev => ({ ...prev, address: e.target.value }))}
              rows={2}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              placeholder="Full address"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Notes
            </label>
            <textarea
              value={supplierForm.notes}
              onChange={(e) => setSupplierForm(prev => ({ ...prev, notes: e.target.value }))}
              rows={3}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              placeholder="Additional notes about the supplier"
            />
          </div>
          <div className="flex items-center">
            <input
              type="checkbox"
              id="is_active"
              checked={supplierForm.is_active}
              onChange={(e) => setSupplierForm(prev => ({ ...prev, is_active: e.target.checked }))}
              className="mr-2"
            />
            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">
              Active Supplier
            </label>
          </div>
          <div className="flex justify-end space-x-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setShowAddModal(false);
                setShowEditModal(false);
                setSelectedSupplier(null);
                resetForm();
              }}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={submitting}
            >
              {submitting ? 'Saving...' : (showEditModal ? 'Update Supplier' : 'Add Supplier')}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteModal}
        onClose={() => {
          setShowDeleteModal(false);
          setSelectedSupplier(null);
        }}
        title="Confirm Deletion"
      >
        <div className="space-y-4">
          <p>
            Are you sure you want to delete the supplier "{selectedSupplier?.name}"? 
            This action cannot be undone.
          </p>
          <div className="flex justify-end space-x-3">
            <Button
              variant="outline"
              onClick={() => {
                setShowDeleteModal(false);
                setSelectedSupplier(null);
              }}
            >
              Cancel
            </Button>
            <Button
              variant="danger"
              onClick={handleDeleteConfirm}
              disabled={submitting}
            >
              {submitting ? 'Deleting...' : 'Delete Supplier'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};

export default InventorySuppliers;

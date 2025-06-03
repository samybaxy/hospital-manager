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
    loadSuppliers();
  }, [filters]);

  const loadSuppliers = async () => {
    setLoading(true);
    try {
      const response = await inventoryService.getSuppliers(filters);
      const data = Array.isArray(response) ? response : response.data || [];
      setSuppliers(data);
    } catch (err) {
      console.error('Error loading suppliers:', err);
      setError('Failed to load suppliers');
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
    setSelectedSupplier(null);
    setShowAddModal(true);
  };

  const handleEdit = (supplier) => {
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
    setSelectedSupplier(supplier);
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
      if (selectedSupplier) {
        await inventoryService.updateSupplier(selectedSupplier.ID, supplierForm);
        setSuccess('Supplier updated successfully');
        setShowEditModal(false);
      } else {
        await inventoryService.addSupplier(supplierForm);
        setSuccess('Supplier added successfully');
        setShowAddModal(false);
      }
      
      resetForm();
      setSelectedSupplier(null);
      loadSuppliers();
      if (onRefresh) onRefresh();
    } catch (err) {
      console.error('Error saving supplier:', err);
      setError(err.message || 'Failed to save supplier');
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
      if (onRefresh) onRefresh();
    } catch (err) {
      console.error('Error deleting supplier:', err);
      setError(err.message || 'Failed to delete supplier');
    } finally {
      setSubmitting(false);
    }
  };

  const formatCurrency = (amount) => {
    if (!amount) return '-';
    return `₦${Number(amount).toLocaleString()}`;
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
    const matchesSearch = !filters.search || 
      supplier.name?.toLowerCase().includes(filters.search.toLowerCase()) ||
      supplier.contact_person?.toLowerCase().includes(filters.search.toLowerCase()) ||
      supplier.email?.toLowerCase().includes(filters.search.toLowerCase());
    
    const matchesStatus = filters.is_active === 'all' || 
      (filters.is_active === 'active' && (supplier.is_active === '1' || supplier.is_active === true)) ||
      (filters.is_active === 'inactive' && (supplier.is_active === '0' || supplier.is_active === false));
    
    return matchesSearch && matchesStatus;
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <h2 className="text-2xl font-bold text-gray-900">Suppliers</h2>
        {permissions.canCreate && (
          <Button
            variant="primary"
            onClick={handleAdd}
          >
            Add Supplier
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
      <Card title="Filters">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Search
              </label>
              <input
                type="text"
                value={filters.search}
                onChange={(e) => setFilters(prev => ({ ...prev, search: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Search by name, contact person, or email..."
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
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
              </select>
            </div>
          </div>
      </Card>

      {/* Suppliers Table */}
      <Card title="Suppliers">
        {loading ? (
            <LoadingState message="Loading suppliers..." />
          ) : filteredSuppliers.length === 0 ? (
            <div className="text-center py-8">
              <p className="text-gray-500">No suppliers found</p>
            </div>
          ) : (
            <Table
              columns={supplierColumns}
              data={filteredSuppliers}
            />
          )}
      </Card>

      {/* Add Supplier Modal */}
      <Modal
        isOpen={showAddModal}
        onClose={() => {
          setShowAddModal(false);
          resetForm();
        }}
        title="Add New Supplier"
        size="lg"
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
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Email
              </label>
              <input
                type="email"
                value={supplierForm.email}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, email: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Email address"
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
                placeholder="Phone number"
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
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              rows="2"
              placeholder="Street address"
            />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Payment Terms
              </label>
              <input
                type="text"
                value={supplierForm.payment_terms}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, payment_terms: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Net 30, COD, etc."
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
                placeholder="Days"
              />
            </div>
          </div>

          <div>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={supplierForm.is_active}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, is_active: e.target.checked }))}
                className="mr-2"
              />
              <span className="text-sm font-medium text-gray-700">Active Supplier</span>
            </label>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Notes
            </label>
            <textarea
              value={supplierForm.notes}
              onChange={(e) => setSupplierForm(prev => ({ ...prev, notes: e.target.value }))}
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
                setShowAddModal(false);
                resetForm();
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
              {submitting ? 'Adding...' : 'Add Supplier'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Edit Supplier Modal */}
      <Modal
        isOpen={showEditModal}
        onClose={() => {
          setShowEditModal(false);
          setSelectedSupplier(null);
          resetForm();
        }}
        title="Edit Supplier"
        size="lg"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Same form fields as Add Modal */}
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
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Email
              </label>
              <input
                type="email"
                value={supplierForm.email}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, email: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Email address"
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
                placeholder="Phone number"
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
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              rows="2"
              placeholder="Street address"
            />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Payment Terms
              </label>
              <input
                type="text"
                value={supplierForm.payment_terms}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, payment_terms: e.target.value }))}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Net 30, COD, etc."
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
                placeholder="Days"
              />
            </div>
          </div>

          <div>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={supplierForm.is_active}
                onChange={(e) => setSupplierForm(prev => ({ ...prev, is_active: e.target.checked }))}
                className="mr-2"
              />
              <span className="text-sm font-medium text-gray-700">Active Supplier</span>
            </label>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Notes
            </label>
            <textarea
              value={supplierForm.notes}
              onChange={(e) => setSupplierForm(prev => ({ ...prev, notes: e.target.value }))}
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
                setShowEditModal(false);
                setSelectedSupplier(null);
                resetForm();
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
              {submitting ? 'Updating...' : 'Update Supplier'}
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
        title="Delete Supplier"
      >
        <div className="space-y-4">
          <p>Are you sure you want to delete the supplier "{selectedSupplier?.name}"?</p>
          <p className="text-sm text-gray-600">This action cannot be undone.</p>
          
          <div className="flex justify-end space-x-3">
            <Button
              variant="outline"
              onClick={() => {
                setShowDeleteModal(false);
                setSelectedSupplier(null);
              }}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button
              variant="danger"
              onClick={handleDeleteConfirm}
              disabled={submitting}
            >
              {submitting ? 'Deleting...' : 'Delete'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};

export default InventorySuppliers;

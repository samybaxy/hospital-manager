import React, { useState, useEffect, useRef } from 'react';
import Button from './Button';
import inventoryService from '../services/inventoryService';

const InventoryForm = ({ 
  item = null, 
  onSubmit, 
  onCancel,
  isSubmitting = false 
}) => {
  const [formData, setFormData] = useState({
    item_name: '',
    category: '',
    quantity: '',
    unit: '',
    reorder_level: '',
    expiry_date: '',
    location: '',
    cost: '',
    status: 'in_stock',
    description: ''
  });

  const [errors, setErrors] = useState({});
  const itemNameRef = useRef(null);

  // Auto-focus on item name field when form opens
  useEffect(() => {
    if (itemNameRef.current) {
      itemNameRef.current.focus();
    }
  }, []);

  // Populate form with existing item data for editing
  useEffect(() => {
    if (item) {
      setFormData({
        item_name: item.item_name || '',
        category: item.category || '',
        quantity: item.quantity || '',
        unit: item.unit || '',
        reorder_level: item.reorder_level || '',
        expiry_date: item.expiry_date ? item.expiry_date.split(' ')[0] : '', // Extract date part
        location: item.location || '',
        cost: item.cost || '',
        status: item.status || 'in_stock',
        description: item.description || ''
      });
    }
  }, [item]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));

    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => ({
        ...prev,
        [name]: null
      }));
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.item_name.trim()) {
      newErrors.item_name = 'Item name is required';
    }

    if (!formData.category) {
      newErrors.category = 'Category is required';
    }

    if (!formData.quantity || formData.quantity < 0) {
      newErrors.quantity = 'Valid quantity is required';
    }

    if (!formData.unit.trim()) {
      newErrors.unit = 'Unit is required';
    }

    if (!formData.reorder_level || formData.reorder_level < 0) {
      newErrors.reorder_level = 'Valid reorder level is required';
    }

    if (formData.cost && formData.cost < 0) {
      newErrors.cost = 'Cost must be positive';
    }

    if (!formData.location.trim()) {
      newErrors.location = 'Location is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    // Convert numeric fields
    const submitData = {
      ...formData,
      quantity: parseInt(formData.quantity),
      reorder_level: parseInt(formData.reorder_level),
      cost: formData.cost ? parseFloat(formData.cost) : null
    };

    onSubmit(submitData);
  };

  const categories = inventoryService.getCategories();
  const statuses = inventoryService.getStatuses();

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Item Name */}
        <div>
          <label htmlFor="item_name" className="block text-sm font-medium text-gray-700">
            Item Name *
          </label>
          <input
            ref={itemNameRef}
            type="text"
            name="item_name"
            id="item_name"
            value={formData.item_name}
            onChange={handleChange}
            className={`mt-1 block w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 ${
              errors.item_name ? 'border-red-500' : 'border-gray-300'
            }`}
            placeholder="Enter item name"
          />
          {errors.item_name && (
            <p className="mt-1 text-sm text-red-600">{errors.item_name}</p>
          )}
        </div>

        {/* Category */}
        <div>
          <label htmlFor="category" className="block text-sm font-medium text-gray-700">
            Category *
          </label>
          <select
            name="category"
            id="category"
            value={formData.category}
            onChange={handleChange}
            className={`mt-1 block w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 ${
              errors.category ? 'border-red-500' : 'border-gray-300'
            }`}
          >
            <option value="">Select category</option>
            {categories.map(category => (
              <option key={category} value={category}>
                {category}
              </option>
            ))}
          </select>
          {errors.category && (
            <p className="mt-1 text-sm text-red-600">{errors.category}</p>
          )}
        </div>

        {/* Quantity */}
        <div>
          <label htmlFor="quantity" className="block text-sm font-medium text-gray-700">
            Quantity *
          </label>
          <input
            type="number"
            name="quantity"
            id="quantity"
            min="0"
            value={formData.quantity}
            onChange={handleChange}
            className={`mt-1 block w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 ${
              errors.quantity ? 'border-red-500' : 'border-gray-300'
            }`}
            placeholder="0"
          />
          {errors.quantity && (
            <p className="mt-1 text-sm text-red-600">{errors.quantity}</p>
          )}
        </div>

        {/* Unit */}
        <div>
          <label htmlFor="unit" className="block text-sm font-medium text-gray-700">
            Unit *
          </label>
          <input
            type="text"
            name="unit"
            id="unit"
            value={formData.unit}
            onChange={handleChange}
            className={`mt-1 block w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 ${
              errors.unit ? 'border-red-500' : 'border-gray-300'
            }`}
            placeholder="e.g., pieces, bottles, boxes"
          />
          {errors.unit && (
            <p className="mt-1 text-sm text-red-600">{errors.unit}</p>
          )}
        </div>

        {/* Reorder Level */}
        <div>
          <label htmlFor="reorder_level" className="block text-sm font-medium text-gray-700">
            Reorder Level *
          </label>
          <input
            type="number"
            name="reorder_level"
            id="reorder_level"
            min="0"
            value={formData.reorder_level}
            onChange={handleChange}
            className={`mt-1 block w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 ${
              errors.reorder_level ? 'border-red-500' : 'border-gray-300'
            }`}
            placeholder="Minimum quantity before reorder"
          />
          {errors.reorder_level && (
            <p className="mt-1 text-sm text-red-600">{errors.reorder_level}</p>
          )}
        </div>

        {/* Expiry Date */}
        <div>
          <label htmlFor="expiry_date" className="block text-sm font-medium text-gray-700">
            Expiry Date
          </label>
          <input
            type="date"
            name="expiry_date"
            id="expiry_date"
            value={formData.expiry_date}
            onChange={handleChange}
            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>

        {/* Location */}
        <div>
          <label htmlFor="location" className="block text-sm font-medium text-gray-700">
            Location *
          </label>
          <input
            type="text"
            name="location"
            id="location"
            value={formData.location}
            onChange={handleChange}
            className={`mt-1 block w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 ${
              errors.location ? 'border-red-500' : 'border-gray-300'
            }`}
            placeholder="Storage location"
          />
          {errors.location && (
            <p className="mt-1 text-sm text-red-600">{errors.location}</p>
          )}
        </div>

        {/* Cost */}
        <div>
          <label htmlFor="cost" className="block text-sm font-medium text-gray-700">
            Cost (₦)
          </label>
          <input
            type="number"
            name="cost"
            id="cost"
            min="0"
            step="0.01"
            value={formData.cost}
            onChange={handleChange}
            className={`mt-1 block w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 ${
              errors.cost ? 'border-red-500' : 'border-gray-300'
            }`}
            placeholder="0.00"
          />
          {errors.cost && (
            <p className="mt-1 text-sm text-red-600">{errors.cost}</p>
          )}
        </div>

        {/* Status */}
        <div>
          <label htmlFor="status" className="block text-sm font-medium text-gray-700">
            Status
          </label>
          <select
            name="status"
            id="status"
            value={formData.status}
            onChange={handleChange}
            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            {statuses.map(status => {
              const { text } = inventoryService.getStatusInfo(status);
              return (
                <option key={status} value={status}>
                  {text}
                </option>
              );
            })}
          </select>
        </div>
      </div>

      {/* Description */}
      <div>
        <label htmlFor="description" className="block text-sm font-medium text-gray-700">
          Description
        </label>
        <textarea
          name="description"
          id="description"
          rows={3}
          value={formData.description}
          onChange={handleChange}
          className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
          placeholder="Additional notes or description"
        />
      </div>

      {/* Form Actions */}
      <div className="flex justify-end space-x-3 pt-6 border-t">
        <Button
          type="button"
          variant="secondary"
          onClick={onCancel}
          disabled={isSubmitting}
        >
          Cancel
        </Button>
        <Button
          type="submit"
          variant="primary"
          disabled={isSubmitting}
        >
          {isSubmitting ? 'Saving...' : (item ? 'Update Item' : 'Add Item')}
        </Button>
      </div>
    </form>
  );
};

export default InventoryForm;

import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import Card from '../components/Card';
import Button from '../components/Button';
import { api } from '../services/apiService';

const PatientForm = ({ patient = {}, isEditing = false }) => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [formData, setFormData] = useState({
    first_name: patient.first_name || '',
    last_name: patient.last_name || '',
    gender: patient.gender || '',
    date_of_birth: patient.date_of_birth || '',
    blood_group: patient.blood_group || '',
    marital_status: patient.marital_status || '',
    phone: patient.phone || '',
    email: patient.email || '',
    address: patient.address || '',
    city: patient.city || '',
    state: patient.state || '',
    postal_code: patient.postal_code || '',
    emergency_contact_name: patient.emergency_contact_name || '',
    emergency_contact_relationship: patient.emergency_contact_relationship || '',
    emergency_contact_phone: patient.emergency_contact_phone || '',
    status: patient.status || 'active',
  });
  
  const [formErrors, setFormErrors] = useState({});
  const [touched, setTouched] = useState({});

  // Update form data if patient prop changes
  useEffect(() => {
    if (isEditing && patient) {
      setFormData({
        first_name: patient.first_name || '',
        last_name: patient.last_name || '',
        gender: patient.gender || '',
        date_of_birth: patient.date_of_birth || '',
        blood_group: patient.blood_group || '',
        marital_status: patient.marital_status || '',
        phone: patient.phone || '',
        email: patient.email || '',
        address: patient.address || '',
        city: patient.city || '',
        state: patient.state || '',
        postal_code: patient.postal_code || '',
        emergency_contact_name: patient.emergency_contact_name || '',
        emergency_contact_relationship: patient.emergency_contact_relationship || '',
        emergency_contact_phone: patient.emergency_contact_phone || '',
        status: patient.status || 'active',
      });
    }
  }, [patient, isEditing]);

  const validateField = (name, value) => {
    let error = '';
    
    switch (name) {
      case 'first_name':
      case 'last_name':
        if (!value.trim()) {
          error = `${name === 'first_name' ? 'First' : 'Last'} name is required`;
        } else if (value.trim().length < 2) {
          error = `${name === 'first_name' ? 'First' : 'Last'} name must be at least 2 characters`;
        }
        break;
      
      case 'email':
        if (value && !/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test(value)) {
          error = 'Invalid email address';
        }
        break;
        
      case 'phone':
        if (!value.trim()) {
          error = 'Phone number is required';
        } else if (!/^[0-9+() -]{10,15}$/.test(value.trim())) {
          error = 'Phone number must be between 10-15 digits';
        }
        break;
        
      case 'postal_code':
        if (value && !/^[0-9a-zA-Z -]{3,10}$/.test(value.trim())) {
          error = 'Invalid postal code format';
        }
        break;
        
      case 'emergency_contact_phone':
        if (value && !/^[0-9+() -]{10,15}$/.test(value.trim())) {
          error = 'Phone number must be between 10-15 digits';
        }
        break;
        
      default:
        // No validation for other fields
        break;
    }
    
    return error;
  };

  const validateForm = () => {
    const errors = {};
    
    // Validate each field
    Object.keys(formData).forEach(key => {
      const error = validateField(key, formData[key]);
      if (error) {
        errors[key] = error;
      }
    });
    
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    
    // Validate field on change if it's been touched
    if (touched[name]) {
      const error = validateField(name, value);
      setFormErrors(prev => ({
        ...prev,
        [name]: error
      }));
    }
  };
  
  const handleBlur = (e) => {
    const { name, value } = e.target;
    
    // Mark field as touched
    setTouched(prev => ({
      ...prev,
      [name]: true
    }));
    
    // Validate field
    const error = validateField(name, value);
    setFormErrors(prev => ({
      ...prev,
      [name]: error
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    
    // Mark all fields as touched for validation
    const allTouched = Object.keys(formData).reduce((acc, key) => {
      acc[key] = true;
      return acc;
    }, {});
    setTouched(allTouched);
    
    // Validate entire form
    if (!validateForm()) {
      setError('Please correct the errors in the form before submitting.');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    try {
      setLoading(true);
      
      if (isEditing) {
        await api.put(`/patients/${patient.id}`, formData);
        navigate(`/patients/${patient.id}`, { replace: true });
      } else {
        const response = await api.post('/patients', formData);
        navigate(`/patients/${response.data.id}`, { replace: true });
      }
    } catch (err) {
      console.error('Error saving patient:', err);
      setError(err.response?.data?.message || 'Failed to save patient information. Please try again.');
      setLoading(false);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">{isEditing ? 'Edit Patient' : 'Add New Patient'}</h1>
      </div>

      <Card>
        {error && (
          <div className="bg-red-50 p-4 mb-6 rounded-md border border-red-200 text-red-700">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="space-y-6">
            {/* Personal Information */}
            <div>
              <h2 className="text-lg font-medium border-b pb-2">Personal Information</h2>
              <div className="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div className="sm:col-span-3">
                  <label htmlFor="first_name" className="block text-sm font-medium text-gray-700">
                    First name <span className="text-red-500">*</span>
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      name="first_name"
                      id="first_name"
                      required
                      value={formData.first_name}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      className={`shadow-sm block w-full sm:text-sm rounded-md ${
                        formErrors.first_name 
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500' 
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                      }`}
                    />
                    {formErrors.first_name && (
                      <p className="mt-1 text-sm text-red-600">{formErrors.first_name}</p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="last_name" className="block text-sm font-medium text-gray-700">
                    Last name <span className="text-red-500">*</span>
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      name="last_name"
                      id="last_name"
                      required
                      value={formData.last_name}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      className={`shadow-sm block w-full sm:text-sm rounded-md ${
                        formErrors.last_name 
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500' 
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                      }`}
                    />
                    {formErrors.last_name && (
                      <p className="mt-1 text-sm text-red-600">{formErrors.last_name}</p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="gender" className="block text-sm font-medium text-gray-700">
                    Gender
                  </label>
                  <div className="mt-1">
                    <select
                      id="gender"
                      name="gender"
                      value={formData.gender}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    >
                      <option value="">Select gender</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="date_of_birth" className="block text-sm font-medium text-gray-700">
                    Date of Birth
                  </label>
                  <div className="mt-1">
                    <input
                      type="date"
                      name="date_of_birth"
                      id="date_of_birth"
                      value={formData.date_of_birth}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    />
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="blood_group" className="block text-sm font-medium text-gray-700">
                    Blood Group
                  </label>
                  <div className="mt-1">
                    <select
                      id="blood_group"
                      name="blood_group"
                      value={formData.blood_group}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    >
                      <option value="">Select blood group</option>
                      <option value="A+">A+</option>
                      <option value="A-">A-</option>
                      <option value="B+">B+</option>
                      <option value="B-">B-</option>
                      <option value="AB+">AB+</option>
                      <option value="AB-">AB-</option>
                      <option value="O+">O+</option>
                      <option value="O-">O-</option>
                    </select>
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="marital_status" className="block text-sm font-medium text-gray-700">
                    Marital Status
                  </label>
                  <div className="mt-1">
                    <select
                      id="marital_status"
                      name="marital_status"
                      value={formData.marital_status}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    >
                      <option value="">Select marital status</option>
                      <option value="single">Single</option>
                      <option value="married">Married</option>
                      <option value="divorced">Divorced</option>
                      <option value="widowed">Widowed</option>
                    </select>
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="status" className="block text-sm font-medium text-gray-700">
                    Patient Status
                  </label>
                  <div className="mt-1">
                    <select
                      id="status"
                      name="status"
                      value={formData.status}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    >
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                      <option value="pending">Pending</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            {/* Contact Information */}
            <div>
              <h2 className="text-lg font-medium border-b pb-2">Contact Information</h2>
              <div className="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div className="sm:col-span-3">
                  <label htmlFor="phone" className="block text-sm font-medium text-gray-700">
                    Phone <span className="text-red-500">*</span>
                  </label>
                  <div className="mt-1">
                    <input
                      type="tel"
                      name="phone"
                      id="phone"
                      required
                      value={formData.phone}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      className={`shadow-sm block w-full sm:text-sm rounded-md ${
                        formErrors.phone 
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500' 
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                      }`}
                    />
                    {formErrors.phone && (
                      <p className="mt-1 text-sm text-red-600">{formErrors.phone}</p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                    Email
                  </label>
                  <div className="mt-1">
                    <input
                      type="email"
                      name="email"
                      id="email"
                      value={formData.email}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      className={`shadow-sm block w-full sm:text-sm rounded-md ${
                        formErrors.email 
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500' 
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                      }`}
                    />
                    {formErrors.email && (
                      <p className="mt-1 text-sm text-red-600">{formErrors.email}</p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-6">
                  <label htmlFor="address" className="block text-sm font-medium text-gray-700">
                    Address
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      name="address"
                      id="address"
                      value={formData.address}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    />
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="city" className="block text-sm font-medium text-gray-700">
                    City
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      name="city"
                      id="city"
                      value={formData.city}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    />
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="state" className="block text-sm font-medium text-gray-700">
                    State
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      name="state"
                      id="state"
                      value={formData.state}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    />
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="postal_code" className="block text-sm font-medium text-gray-700">
                    Postal Code
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      name="postal_code"
                      id="postal_code"
                      value={formData.postal_code}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      className={`shadow-sm block w-full sm:text-sm rounded-md ${
                        formErrors.postal_code 
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500' 
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                      }`}
                    />
                    {formErrors.postal_code && (
                      <p className="mt-1 text-sm text-red-600">{formErrors.postal_code}</p>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Emergency Contact */}
            <div>
              <h2 className="text-lg font-medium border-b pb-2">Emergency Contact</h2>
              <div className="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div className="sm:col-span-3">
                  <label htmlFor="emergency_contact_name" className="block text-sm font-medium text-gray-700">
                    Name
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      name="emergency_contact_name"
                      id="emergency_contact_name"
                      value={formData.emergency_contact_name}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    />
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="emergency_contact_relationship" className="block text-sm font-medium text-gray-700">
                    Relationship
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      name="emergency_contact_relationship"
                      id="emergency_contact_relationship"
                      value={formData.emergency_contact_relationship}
                      onChange={handleChange}
                      className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    />
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="emergency_contact_phone" className="block text-sm font-medium text-gray-700">
                    Phone
                  </label>
                  <div className="mt-1">
                    <input
                      type="tel"
                      name="emergency_contact_phone"
                      id="emergency_contact_phone"
                      value={formData.emergency_contact_phone}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      className={`shadow-sm block w-full sm:text-sm rounded-md ${
                        formErrors.emergency_contact_phone 
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500' 
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                      }`}
                    />
                    {formErrors.emergency_contact_phone && (
                      <p className="mt-1 text-sm text-red-600">{formErrors.emergency_contact_phone}</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="pt-5 flex justify-between">
            <Link to="/patients">
              <Button type="button" variant="secondary">
                Cancel
              </Button>
            </Link>
            <Button 
              type="submit" 
              variant="primary" 
              disabled={loading}
              className={loading ? 'opacity-75 cursor-not-allowed' : ''}
            >
              {loading ? (
                <>
                  <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Saving...
                </>
              ) : (
                'Save Patient'
              )}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
};

export default PatientForm;

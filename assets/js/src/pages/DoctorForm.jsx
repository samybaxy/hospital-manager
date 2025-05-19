import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import Card from '../components/Card';
import Button from '../components/Button';
import { api } from '../services/apiService';

// CSS for enhanced form styling
const formStyles = {
  inputField: `px-3 py-2 shadow-sm block w-full sm:text-sm border-gray-300 rounded-md 
               transition-all duration-200 ease-in-out focus:ring-primary-500 focus:border-primary-500
               hover:border-gray-400`,
  errorField: `px-3 py-2 shadow-sm block w-full sm:text-sm border-red-300 rounded-md 
               text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500
               transition-all duration-200 ease-in-out`,
  label: 'block text-sm font-medium text-gray-700 mb-1',
  section: 'px-6 py-6 bg-white rounded-md shadow-md sm:overflow-hidden mb-6 border border-gray-100',
  sectionTitle: 'text-lg font-medium text-gray-900 pb-3 border-b border-gray-200 mb-5 flex items-center',
  sectionIcon: 'mr-2 h-5 w-5 text-primary-500',
  fieldGroup: 'mb-4',
  helpText: 'mt-1 text-xs text-gray-500',
};

const DoctorForm = ({ doctor = {}, isEditing = false, cancelUrl = '/doctors' }) => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [formData, setFormData] = useState({
    first_name: doctor.first_name || '',
    last_name: doctor.last_name || '',
    phone: doctor.phone || '',
    specialty: doctor.specialty || '',
    status: doctor.status || 'active',
  });
  
  const [formErrors, setFormErrors] = useState({});
  const [touched, setTouched] = useState({});

  // Update form data if doctor prop changes
  useEffect(() => {
    if (isEditing && doctor) {
      setFormData({
        first_name: doctor.first_name || '',
        last_name: doctor.last_name || '',
        phone: doctor.phone || '',
        specialty: doctor.specialty || '',
        status: doctor.status || 'active',
      });
    }
  }, [doctor, isEditing]);

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
        
      case 'phone':
        if (!value.trim()) {
          error = 'Phone number is required';
        } else if (!/^[0-9+() -]{10,20}$/.test(value.trim())) {
          error = 'Phone number must be between 10-15 digits';
        }
        break;
        
      case 'specialty':
        if (!value.trim()) {
          error = 'Specialty is required';
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
        await api.put(`/doctors/${doctor.id}`, formData);
        navigate(`/doctors/${doctor.id}`, { replace: true });
      } else {
        const response = await api.post('/doctors', formData);
        
        // Debug the response
        console.log('Create doctor response:', response);
        
        // Handle the response and redirect
        if (response && response.status === 201) {
          console.log('Doctor created successfully. Redirecting to doctor list.');
          navigate('/doctors', { replace: true });
        } else {
          // Something unexpected happened
          throw new Error('Unexpected response from server');
        }
      }
    } catch (err) {
      console.error('Error saving doctor:', err);
      setError(err.response?.data?.message || 'Failed to save doctor information. Please try again.');
      setLoading(false);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">{isEditing ? 'Edit Doctor' : 'Add New Doctor'}</h1>
      </div>

      <Card className="border border-gray-200 rounded-lg shadow-lg overflow-hidden">
        {error && (
          <div className="bg-red-50 p-4 mb-6 rounded-md border border-red-200 text-red-700 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="space-y-8">
            {/* Personal Information */}
            <div className={formStyles.section}>
              <h2 className={formStyles.sectionTitle}>
                <svg xmlns="http://www.w3.org/2000/svg" className={formStyles.sectionIcon} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Doctor Information
              </h2>
              <div className="mt-4 grid grid-cols-1 gap-y-6 gap-x-6 sm:grid-cols-6">
                <div className="sm:col-span-3">
                  <label htmlFor="first_name" className={formStyles.label}>
                    First name <span className="text-red-500">*</span>
                  </label>
                  <div>
                    <input
                      type="text"
                      name="first_name"
                      id="first_name"
                      required
                      value={formData.first_name}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      className={formErrors.first_name ? formStyles.errorField : formStyles.inputField}
                      placeholder="Enter first name"
                    />
                    {formErrors.first_name && (
                      <p className="mt-1 text-sm text-red-600 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {formErrors.first_name}
                      </p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="last_name" className={formStyles.label}>
                    Last name <span className="text-red-500">*</span>
                  </label>
                  <div>
                    <input
                      type="text"
                      name="last_name"
                      id="last_name"
                      required
                      value={formData.last_name}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      className={formErrors.last_name ? formStyles.errorField : formStyles.inputField}
                      placeholder="Enter last name"
                    />
                    {formErrors.last_name && (
                      <p className="mt-1 text-sm text-red-600 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {formErrors.last_name}
                      </p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="phone" className={formStyles.label}>
                    Phone <span className="text-red-500">*</span>
                  </label>
                  <div>
                    <input
                      type="tel"
                      name="phone"
                      id="phone"
                      required
                      value={formData.phone}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      placeholder="Enter phone number"
                      className={formErrors.phone ? formStyles.errorField : formStyles.inputField}
                    />
                    {formErrors.phone && (
                      <p className="mt-1 text-sm text-red-600 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {formErrors.phone}
                      </p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="specialty" className={formStyles.label}>
                    Specialty <span className="text-red-500">*</span>
                  </label>
                  <div>
                    <input
                      type="text"
                      name="specialty"
                      id="specialty"
                      required
                      value={formData.specialty}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      placeholder="e.g. Cardiology, Pediatrics"
                      className={formErrors.specialty ? formStyles.errorField : formStyles.inputField}
                    />
                    {formErrors.specialty && (
                      <p className="mt-1 text-sm text-red-600 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {formErrors.specialty}
                      </p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="status" className={formStyles.label}>
                    Status
                  </label>
                  <div>
                    <select
                      id="status"
                      name="status"
                      value={formData.status}
                      onChange={handleChange}
                      className={formStyles.inputField}
                    >
                      <option key="status-active" value="active">Active</option>
                      <option key="status-inactive" value="inactive">Inactive</option>
                      <option key="status-onleave" value="on_leave">On Leave</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="pt-6 mt-6 flex justify-between border-t border-gray-200">
            <Link to={cancelUrl}>
              <Button type="button" variant="secondary" className="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Cancel
              </Button>
            </Link>
            <Button 
              type="submit" 
              variant="primary" 
              disabled={loading}
              className={`flex items-center ${loading ? 'opacity-75 cursor-not-allowed' : ''}`}
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
                <>
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  Save Doctor
                </>
              )}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
};

export default DoctorForm;
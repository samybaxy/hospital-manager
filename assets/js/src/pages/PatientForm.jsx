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

const PatientForm = ({ patient = {}, isEditing = false }) => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [formData, setFormData] = useState({
    first_name: patient.first_name || '',
    last_name: patient.last_name || '',
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
    // bio_data fields
    height: '',
    weight: '',
    allergies: '',
    chronic_conditions: '',
  });
  
  const [formErrors, setFormErrors] = useState({});
  const [touched, setTouched] = useState({});

  // Update form data if patient prop changes
  useEffect(() => {
    if (isEditing && patient) {
      console.log("Setting form data from patient:", patient);
      // Handle bio_data parsing if it's a string
      let bioData = patient.bio_data || {};
      if (typeof bioData === 'string') {
        try {
          bioData = JSON.parse(bioData);
        } catch (e) {
          console.error('Failed to parse bio_data:', e);
          bioData = {};
        }
      }
      // Get emergency contact info from either nested bio_data or top-level properties
      const emergencyContactName = bioData?.emergency_contact?.name || patient.emergency_contact_name || '';
      const emergencyContactRelationship = bioData?.emergency_contact?.relationship || patient.emergency_contact_relationship || '';
      const emergencyContactPhone = bioData?.emergency_contact?.phone || patient.emergency_contact_phone || '';
      // Get blood group from either nested bio_data or top-level property
      const bloodGroup = bioData?.blood_group || patient.blood_group || '';
      setFormData({
        first_name: patient.first_name || '',
        last_name: patient.last_name || '',
        blood_group: bloodGroup,
        marital_status: patient.marital_status || '',
        phone: patient.phone || '',
        email: patient.email || '',
        address: patient.address || '',
        city: patient.city || '',
        state: patient.state || '',
        postal_code: patient.postal_code || '',
        emergency_contact_name: emergencyContactName,
        emergency_contact_relationship: emergencyContactRelationship,
        emergency_contact_phone: emergencyContactPhone,
        status: patient.status || 'active',
        // bio_data fields
        height: bioData?.height || '',
        weight: bioData?.weight || '',
        allergies: bioData?.allergies || '',
        chronic_conditions: bioData?.chronic_conditions || '',
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

    // Prepare bio_data
    const bio_data = {
      height: formData.height,
      weight: formData.weight,
      allergies: formData.allergies,
      chronic_conditions: formData.chronic_conditions,
      blood_group: formData.blood_group,
      emergency_contact: {
        name: formData.emergency_contact_name,
        relationship: formData.emergency_contact_relationship,
        phone: formData.emergency_contact_phone,
      },
    };

    // Prepare payload
    const payload = {
      ...formData,
      bio_data: JSON.stringify(bio_data),
    };
    // Remove direct fields that are now in bio_data
    delete payload.height;
    delete payload.weight;
    delete payload.allergies;
    delete payload.chronic_conditions;
    delete payload.blood_group;
    delete payload.emergency_contact_name;
    delete payload.emergency_contact_relationship;
    delete payload.emergency_contact_phone;

    try {
      setLoading(true);
      if (isEditing) {
        await api.put(`/patients/${patient.id}`, payload);
        navigate(`/patients/${patient.id}`, { replace: true });
      } else {
        const response = await api.post('/patients', payload);
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
                Personal Information
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
                    <p className={formStyles.helpText}>First name as it appears on official documents</p>
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
                    <p className={formStyles.helpText}>Last name as it appears on official documents</p>
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="blood_group" className={formStyles.label}>
                    Blood Group
                  </label>
                  <div>
                    <select
                      id="blood_group"
                      name="blood_group"
                      value={formData.blood_group}
                      onChange={handleChange}
                      className={formStyles.inputField}
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
                  <label htmlFor="marital_status" className={formStyles.label}>
                    Marital Status
                  </label>
                  <div>
                    <select
                      id="marital_status"
                      name="marital_status"
                      value={formData.marital_status}
                      onChange={handleChange}
                      className={formStyles.inputField}
                    >
                      <option value="">Select marital status</option>
                      <option value="Single">Single</option>
                      <option value="Married">Married</option>
                      <option value="Divorced">Divorced</option>
                      <option value="Widowed">Widowed</option>
                      <option value="Separated">Separated</option>
                    </select>
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="status" className={formStyles.label}>
                    Patient Status
                  </label>
                  <div>
                    <select
                      id="status"
                      name="status"
                      value={formData.status}
                      onChange={handleChange}
                      className={formStyles.inputField}
                    >
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                      <option value="pending">Pending</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            {/* Medical Information */}
            <div className={formStyles.section}>
              <h2 className={formStyles.sectionTitle}>
                <svg xmlns="http://www.w3.org/2000/svg" className={formStyles.sectionIcon} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-3-3v6m9 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Medical Information
              </h2>
              <div className="mt-4 grid grid-cols-1 gap-y-6 gap-x-6 sm:grid-cols-6">
                {/* Height */}
                <div className="sm:col-span-2">
                  <label htmlFor="height" className={formStyles.label}>
                    Height (cm)
                  </label>
                  <div>
                    <input
                      type="number"
                      name="height"
                      id="height"
                      value={formData.height}
                      onChange={handleChange}
                      placeholder="e.g. 170"
                      className={formStyles.inputField}
                      min="0"
                    />
                  </div>
                </div>
                {/* Weight */}
                <div className="sm:col-span-2">
                  <label htmlFor="weight" className={formStyles.label}>
                    Weight (kg)
                  </label>
                  <div>
                    <input
                      type="number"
                      name="weight"
                      id="weight"
                      value={formData.weight}
                      onChange={handleChange}
                      placeholder="e.g. 65"
                      className={formStyles.inputField}
                      min="0"
                    />
                  </div>
                </div>
                <div className="sm:col-span-3">
                  <label htmlFor="allergies" className={formStyles.label}>
                    Allergies
                  </label>
                  <div>
                    <input
                      type="text"
                      name="allergies"
                      id="allergies"
                      value={formData.allergies}
                      onChange={handleChange}
                      placeholder="e.g. Penicillin, Peanuts"
                      className={formStyles.inputField}
                    />
                  </div>
                </div>
                <div className="sm:col-span-3">
                  <label htmlFor="chronic_conditions" className={formStyles.label}>
                    Chronic Conditions
                  </label>
                  <div>
                    <input
                      type="text"
                      name="chronic_conditions"
                      id="chronic_conditions"
                      value={formData.chronic_conditions}
                      onChange={handleChange}
                      placeholder="e.g. Diabetes, Hypertension"
                      className={formStyles.inputField}
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Contact Information */}
            <div className={formStyles.section}>
              <h2 className={formStyles.sectionTitle}>
                <svg xmlns="http://www.w3.org/2000/svg" className={formStyles.sectionIcon} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                Contact Information
              </h2>
              <div className="mt-4 grid grid-cols-1 gap-y-6 gap-x-6 sm:grid-cols-6">
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
                    <p className={formStyles.helpText}>Include country code if international</p>
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="email" className={formStyles.label}>
                    Email
                  </label>
                  <div>
                    <input
                      type="email"
                      name="email"
                      id="email"
                      value={formData.email}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      placeholder="Enter email address"
                      className={formErrors.email ? formStyles.errorField : formStyles.inputField}
                    />
                    {formErrors.email && (
                      <p className="mt-1 text-sm text-red-600">{formErrors.email}</p>
                    )}
                  </div>
                </div>

                <div className="sm:col-span-6">
                  <label htmlFor="address" className={formStyles.label}>
                    Address
                  </label>
                  <div>
                    <input
                      type="text"
                      name="address"
                      id="address"
                      value={formData.address}
                      onChange={handleChange}
                      placeholder="Enter full address"
                      className={formStyles.inputField}
                    />
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="city" className={formStyles.label}>
                    City
                  </label>
                  <div>
                    <input
                      type="text"
                      name="city"
                      id="city"
                      value={formData.city}
                      onChange={handleChange}
                      placeholder="Enter city"
                      className={formStyles.inputField}
                    />
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <label htmlFor="state" className={formStyles.label}>
                    State
                  </label>
                  <div>
                    <input
                      type="text"
                      name="state"
                      id="state"
                      value={formData.state}
                      onChange={handleChange}
                      placeholder="Enter state"
                      className={formStyles.inputField}
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Emergency Contact */}
            <div className={formStyles.section}>
              <h2 className={formStyles.sectionTitle}>
                <svg xmlns="http://www.w3.org/2000/svg" className={formStyles.sectionIcon} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                Emergency Contact
              </h2>
              <div className="mt-4 grid grid-cols-1 gap-y-6 gap-x-6 sm:grid-cols-6">
                <div className="sm:col-span-3">
                  <label htmlFor="emergency_contact_name" className={formStyles.label}>
                    Name
                  </label>
                  <div>
                    <input
                      type="text"
                      name="emergency_contact_name"
                      id="emergency_contact_name"
                      value={formData.emergency_contact_name}
                      onChange={handleChange}
                      placeholder="Enter contact name"
                      className={formStyles.inputField}
                    />
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="emergency_contact_relationship" className={formStyles.label}>
                    Relationship
                  </label>
                  <div>
                    <input
                      type="text"
                      name="emergency_contact_relationship"
                      id="emergency_contact_relationship"
                      value={formData.emergency_contact_relationship}
                      onChange={handleChange}
                      placeholder="Enter relationship"
                      className={formStyles.inputField}
                    />
                  </div>
                </div>

                <div className="sm:col-span-3">
                  <label htmlFor="emergency_contact_phone" className={formStyles.label}>
                    Phone
                  </label>
                  <div>
                    <input
                      type="tel"
                      name="emergency_contact_phone"
                      id="emergency_contact_phone"
                      value={formData.emergency_contact_phone}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      placeholder="Enter emergency phone"
                      className={formErrors.emergency_contact_phone ? formStyles.errorField : formStyles.inputField}
                    />
                    {formErrors.emergency_contact_phone && (
                      <p className="mt-1 text-sm text-red-600">{formErrors.emergency_contact_phone}</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="pt-6 mt-6 flex justify-between border-t border-gray-200">
            <Link to="/patients">
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
                  Save Patient
                </>
              )}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
};

export default PatientForm;

import React, { useState, useEffect } from 'react';
import Button from './Button';
import Card from './Card';
import laboratoryService from '../services/laboratoryService';

const LabInvestigationForm = ({ 
  investigation = null, 
  patientId = null, 
  visitationId = null,
  doctorId = null,
  labTechId = null,
  onSubmit, 
  onCancel, 
  loading = false 
}) => {
  console.log('LabInvestigationForm rendered:', { investigation, patientId, visitationId, doctorId, labTechId, loading });
  const [formData, setFormData] = useState({
    visitation_id: visitationId || '',
    patient_id: patientId || '',
    doctor_id: doctorId || '',
    lab_tech_id: labTechId || '',
    sample_type: '',
    request_notes: '',
    status: 'requested',
    // Test-related fields
    selected_tests: [], // Array of selected test objects
    test_results: {}, // Object containing results for each test
    lab_notes: ''
  });

  const [categories, setCategories] = useState([]);
  const [testDefinitions, setTestDefinitions] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState('');
  const [availableTests, setAvailableTests] = useState([]);
  const [activeTestForms, setActiveTestForms] = useState({}); // Track which tests have active forms
  const [errors, setErrors] = useState({});
  const [loadingData, setLoadingData] = useState(false);

  // Initialize form data when investigation prop changes
  useEffect(() => {
    if (investigation) {
      setFormData({
        visitation_id: investigation.visitation_id || '',
        patient_id: investigation.patient_id || '',
        doctor_id: investigation.doctor_id || '',
        lab_tech_id: investigation.lab_tech_id || '',
        sample_type: investigation.sample_type || '',
        request_notes: investigation.request_notes || '',
        status: investigation.status || 'requested',
        selected_tests: investigation.selected_tests || [],
        test_results: investigation.test_results ? 
          (typeof investigation.test_results === 'string' ? 
            JSON.parse(investigation.test_results) : investigation.test_results) : {},
        lab_notes: investigation.lab_notes || ''
      });
    }
  }, [investigation]);

  // Load categories and test definitions on component mount
  useEffect(() => {
    loadLabData();
  }, []);

  // Load available tests when category is selected
  useEffect(() => {
    if (selectedCategory) {
      loadTestsForCategory(selectedCategory);
    }
  }, [selectedCategory]);

  const loadLabData = async () => {
    setLoadingData(true);
    try {
      const [categoriesResponse, testsResponse] = await Promise.all([
        laboratoryService.getLabCategories(),
        laboratoryService.getTestDefinitions()
      ]);
      
      console.log('Categories response:', categoriesResponse);
      console.log('Tests response:', testsResponse);
      
      setCategories(categoriesResponse.data || []);
      setTestDefinitions(testsResponse.data || []);
    } catch (error) {
      console.error('Error loading lab data:', error);
      // For now, use sample data
      setCategories([
        { ID: 1, name: 'Hematology', description: 'Blood-related tests' },
        { ID: 2, name: 'Chemistry', description: 'Blood chemistry tests' },
        { ID: 3, name: 'Microbiology', description: 'Infection and culture tests' },
        { ID: 4, name: 'Immunology', description: 'Immune system tests' }
      ]);
      
      setTestDefinitions([
        {
          ID: 1,
          category_id: 1,
          code: 'ESR',
          name: 'Erythrocyte Sedimentation Rate',
          sample_type: 'Blood',
          test_parameters: {
            parameters: [
              {
                name: 'ESR',
                unit: 'mm/hr',
                reference_range: { min: 0, max: 15 },
                type: 'numeric'
              }
            ]
          }
        },
        {
          ID: 2,
          category_id: 1,
          code: 'CBC',
          name: 'Complete Blood Count',
          sample_type: 'Blood',
          test_parameters: {
            parameters: [
              {
                name: 'Hemoglobin',
                unit: 'g/dL',
                reference_range: { min: 12.0, max: 16.0 },
                type: 'numeric'
              },
              {
                name: 'White Blood Cells',
                unit: '×10³/μL',
                reference_range: { min: 4.0, max: 11.0 },
                type: 'numeric'
              },
              {
                name: 'Platelets',
                unit: '×10³/μL',
                reference_range: { min: 150, max: 450 },
                type: 'numeric'
              }
            ]
          }
        },
        {
          ID: 3,
          category_id: 1,
          code: 'FERRITIN',
          name: 'Ferritin',
          sample_type: 'Blood',
          test_parameters: {
            parameters: [
              {
                name: 'Ferritin',
                unit: 'ng/mL',
                reference_range: { min: 15, max: 150 },
                type: 'numeric'
              }
            ]
          }
        },
        {
          ID: 4,
          category_id: 1,
          code: 'B12',
          name: 'Vitamin B12',
          sample_type: 'Blood',
          test_parameters: {
            parameters: [
              {
                name: 'Vitamin B12',
                unit: 'pg/mL',
                reference_range: { min: 200, max: 900 },
                type: 'numeric'
              }
            ]
          }
        },
        {
          ID: 5,
          category_id: 2,
          code: 'GLUCOSE',
          name: 'Blood Glucose',
          sample_type: 'Blood',
          test_parameters: {
            parameters: [
              {
                name: 'Glucose',
                unit: 'mg/dL',
                reference_range: { min: 70, max: 100 },
                type: 'numeric'
              }
            ]
          }
        }
      ]);
    } finally {
      setLoadingData(false);
    }
  };

  const loadTestsForCategory = (categoryId) => {
    const tests = testDefinitions.filter(test => test.category_id === parseInt(categoryId));
    setAvailableTests(tests);
  };

  const handleInputChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
    
    // Clear error for this field
    if (errors[field]) {
      setErrors(prev => ({
        ...prev,
        [field]: null
      }));
    }
  };

  const addTestToForm = (test) => {
    // Check if test is already added
    const isAlreadyAdded = formData.selected_tests.some(t => t.ID === test.ID);
    if (isAlreadyAdded) {
      return;
    }

    const updatedTests = [...formData.selected_tests, test];
    setFormData(prev => ({
      ...prev,
      selected_tests: updatedTests
    }));

    // Initialize test results for this test
    const initialResults = {};
    if (test.test_parameters && test.test_parameters.parameters) {
      test.test_parameters.parameters.forEach(param => {
        initialResults[param.name] = {
          value: '',
          unit: param.unit,
          reference_range: param.reference_range,
          flag: '',
          notes: ''
        };
      });
    }

    setFormData(prev => ({
      ...prev,
      test_results: {
        ...prev.test_results,
        [test.code]: initialResults
      }
    }));

    // Activate the test form
    setActiveTestForms(prev => ({
      ...prev,
      [test.ID]: true
    }));
  };

  const removeTestFromForm = (testId) => {
    const updatedTests = formData.selected_tests.filter(t => t.ID !== testId);
    const testToRemove = formData.selected_tests.find(t => t.ID === testId);
    
    setFormData(prev => {
      const updatedResults = { ...prev.test_results };
      if (testToRemove) {
        delete updatedResults[testToRemove.code];
      }
      
      return {
        ...prev,
        selected_tests: updatedTests,
        test_results: updatedResults
      };
    });

    // Remove from active forms
    setActiveTestForms(prev => {
      const updated = { ...prev };
      delete updated[testId];
      return updated;
    });
  };

  const updateTestResult = (testCode, paramName, field, value) => {
    setFormData(prev => ({
      ...prev,
      test_results: {
        ...prev.test_results,
        [testCode]: {
          ...prev.test_results[testCode],
          [paramName]: {
            ...prev.test_results[testCode][paramName],
            [field]: value
          }
        }
      }
    }));
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.visitation_id) newErrors.visitation_id = 'Visitation ID is required';
    if (!formData.patient_id) newErrors.patient_id = 'Patient ID is required';
    if (!formData.doctor_id) newErrors.doctor_id = 'Doctor is required';
    if (formData.selected_tests.length === 0) newErrors.selected_tests = 'At least one test must be selected';

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    // Prepare data for submission
    const submissionData = {
      ...formData,
      test_results: JSON.stringify(formData.test_results)
    };

    onSubmit(submissionData);
  };

  const renderTestParameterForm = (test) => {
    if (!test.test_parameters || !test.test_parameters.parameters) {
      return null;
    }

    return (
      <div className="mt-4 p-4 bg-gray-50 rounded-lg">
        <h4 className="font-medium text-gray-900 mb-3">Test Parameters for {test.name}</h4>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {test.test_parameters.parameters.map((param, index) => (
            <div key={index} className="space-y-2">
              <label className="block text-sm font-medium text-gray-700">
                {param.name}
                {param.unit && <span className="text-gray-500"> ({param.unit})</span>}
              </label>
              <input
                type={param.type === 'numeric' ? 'number' : 'text'}
                step={param.type === 'numeric' ? '0.01' : undefined}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                value={formData.test_results[test.code]?.[param.name]?.value || ''}
                onChange={(e) => updateTestResult(test.code, param.name, 'value', e.target.value)}
                placeholder={`Enter ${param.name.toLowerCase()}`}
              />
              {param.reference_range && (
                <p className="text-xs text-gray-500">
                  Reference: {param.reference_range.min} - {param.reference_range.max} {param.unit}
                </p>
              )}
              <textarea
                rows={2}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                value={formData.test_results[test.code]?.[param.name]?.notes || ''}
                onChange={(e) => updateTestResult(test.code, param.name, 'notes', e.target.value)}
                placeholder="Notes (optional)"
              />
            </div>
          ))}
        </div>
      </div>
    );
  };

  if (loadingData) {
    return (
      <div className="space-y-4">
        <div className="flex justify-center items-center py-8">
          <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary-600"></div>
          <span className="ml-3 text-gray-600">Loading form data...</span>
        </div>
        {/* Temporary bypass for testing */}
        <div className="text-center">
          <button 
            type="button"
            onClick={() => setLoadingData(false)}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm"
          >
            Skip Loading (Debug)
          </button>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Basic Investigation Information */}
      <Card className="p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Investigation Details</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Visitation ID <span className="text-red-500">*</span>
            </label>
            <input
              type="number"
              className={`w-full px-3 py-2 border rounded-md bg-gray-100 text-gray-600 cursor-not-allowed ${
                errors.visitation_id ? 'border-red-300' : 'border-gray-300'
              }`}
              value={formData.visitation_id}
              onChange={(e) => handleInputChange('visitation_id', e.target.value)}
              placeholder="Enter visitation ID"
              disabled
              title="This field is automatically filled and cannot be edited"
            />
            {errors.visitation_id && <p className="mt-1 text-sm text-red-600">{errors.visitation_id}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Patient ID <span className="text-red-500">*</span>
            </label>
            <input
              type="number"
              className={`w-full px-3 py-2 border rounded-md bg-gray-100 text-gray-600 cursor-not-allowed ${
                errors.patient_id ? 'border-red-300' : 'border-gray-300'
              }`}
              value={formData.patient_id}
              onChange={(e) => handleInputChange('patient_id', e.target.value)}
              placeholder="Enter patient ID"
              disabled
              title="This field is automatically filled and cannot be edited"
            />
            {errors.patient_id && <p className="mt-1 text-sm text-red-600">{errors.patient_id}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Doctor ID <span className="text-red-500">*</span>
            </label>
            <input
              type="number"
              className={`w-full px-3 py-2 border rounded-md bg-gray-100 text-gray-600 cursor-not-allowed ${
                errors.doctor_id ? 'border-red-300' : 'border-gray-300'
              }`}
              value={formData.doctor_id}
              onChange={(e) => handleInputChange('doctor_id', e.target.value)}
              placeholder="Enter doctor ID"
              disabled
              title="This field is automatically filled and cannot be edited"
            />
            {errors.doctor_id && <p className="mt-1 text-sm text-red-600">{errors.doctor_id}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Lab Technician ID</label>
            <input
              type="number"
              className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed"
              value={formData.lab_tech_id}
              onChange={(e) => handleInputChange('lab_tech_id', e.target.value)}
              placeholder="Enter lab tech ID"
              disabled
              title="This field is automatically filled and cannot be edited"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Sample Type</label>
            <select
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              value={formData.sample_type}
              onChange={(e) => handleInputChange('sample_type', e.target.value)}
            >
              <option value="">Select sample type</option>
              <option value="Blood">Blood</option>
              <option value="Urine">Urine</option>
              <option value="Stool">Stool</option>
              <option value="Sputum">Sputum</option>
              <option value="CSF">CSF</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              value={formData.status}
              onChange={(e) => handleInputChange('status', e.target.value)}
            >
              <option value="requested">Requested</option>
              <option value="sample_collected">Sample Collected</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="verified">Verified</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <div className="mt-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Request Notes</label>
          <textarea
            rows={3}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            value={formData.request_notes}
            onChange={(e) => handleInputChange('request_notes', e.target.value)}
            placeholder="Enter any special instructions or notes..."
          />
        </div>
      </Card>

      {/* Test Selection */}
      <Card className="p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Select Tests</h3>
        
        {/* Category Selection */}
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-2">Test Category</label>
          <select
            className="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
          >
            <option value="">Select a category</option>
            {categories.map(category => (
              <option key={category.ID} value={category.ID}>
                {category.name}
              </option>
            ))}
          </select>
        </div>

        {/* Available Tests Pills */}
        {availableTests.length > 0 && (
          <div className="mb-6">
            <h4 className="text-sm font-medium text-gray-700 mb-2">Available Tests</h4>
            <div className="flex flex-wrap gap-2">
              {availableTests.map(test => {
                const isSelected = formData.selected_tests.some(t => t.ID === test.ID);
                return (
                  <button
                    key={test.ID}
                    type="button"
                    onClick={() => addTestToForm(test)}
                    disabled={isSelected}
                    className={`inline-flex items-center px-3 py-2 rounded-full text-sm font-medium ${
                      isSelected
                        ? 'bg-gray-200 text-gray-500 cursor-not-allowed'
                        : 'bg-primary-100 text-primary-800 hover:bg-primary-200'
                    }`}
                    title={test.description}
                  >
                    <svg 
                      className="w-4 h-4 mr-1" 
                      fill="none" 
                      stroke="currentColor" 
                      viewBox="0 0 24 24"
                    >
                      <path 
                        strokeLinecap="round" 
                        strokeLinejoin="round" 
                        strokeWidth={2} 
                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" 
                      />
                    </svg>
                    {test.code} - {test.name}
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* Selected Tests */}
        {formData.selected_tests.length > 0 && (
          <div>
            <h4 className="text-sm font-medium text-gray-700 mb-2">Selected Tests</h4>
            {errors.selected_tests && <p className="mb-2 text-sm text-red-600">{errors.selected_tests}</p>}
            <div className="space-y-4">
              {formData.selected_tests.map(test => (
                <div key={test.ID} className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center justify-between mb-2">
                    <h5 className="font-medium text-gray-900">{test.code} - {test.name}</h5>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => removeTestFromForm(test.ID)}
                      className="text-red-600 border-red-300 hover:bg-red-50"
                    >
                      Remove
                    </Button>
                  </div>
                  <p className="text-sm text-gray-600 mb-2">Sample: {test.sample_type}</p>
                  
                  {/* Test Parameter Forms */}
                  {activeTestForms[test.ID] && renderTestParameterForm(test)}
                </div>
              ))}
            </div>
          </div>
        )}
      </Card>

      {/* Lab Notes */}
      <Card className="p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Lab Notes</h3>
        <textarea
          rows={4}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          value={formData.lab_notes}
          onChange={(e) => handleInputChange('lab_notes', e.target.value)}
          placeholder="Enter lab technician notes, observations, or comments..."
        />
      </Card>

      {/* Form Actions */}
      <div className="flex justify-end space-x-3">
        <Button
          type="button"
          variant="secondary"
          onClick={onCancel}
          disabled={loading}
        >
          Cancel
        </Button>
        <Button
          type="submit"
          variant="primary"
          disabled={loading}
        >
          {loading ? 'Saving...' : (investigation ? 'Update Investigation' : 'Create Investigation')}
        </Button>
      </div>
    </form>
  );
};

export default LabInvestigationForm;

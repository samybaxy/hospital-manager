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

  // Update form data when props change (important for when component is reused)
  useEffect(() => {
    if (!investigation) {
      setFormData(prev => ({
        ...prev,
        visitation_id: visitationId || prev.visitation_id,
        patient_id: patientId || prev.patient_id,
        doctor_id: doctorId || prev.doctor_id,
        lab_tech_id: labTechId || prev.lab_tech_id,
      }));
    }
  }, [patientId, visitationId, doctorId, labTechId, investigation]);

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
      console.log('Investigation data received:', investigation);
      
      // Convert test_type back to selected_tests format for editing
      let selectedTests = [];
      if (investigation.test_type) {
        selectedTests = [{
          ID: `existing_${Date.now()}`,
          name: investigation.test_type,
          code: investigation.test_type,
          sample_type: investigation.sample_type,
        }];
      }
      
      // Parse test results if it's a string
      let parsedResults = {};
      if (investigation.test_results) {
        try {
          parsedResults = typeof investigation.test_results === 'string' 
            ? JSON.parse(investigation.test_results) 
            : investigation.test_results;
        } catch (e) {
          console.error('Error parsing test results:', e);
          parsedResults = {};
        }
      }
      
      setFormData({
        visitation_id: investigation.visitation_id || '',
        patient_id: investigation.patient_id || '',
        doctor_id: investigation.doctor_id || '',
        lab_tech_id: investigation.lab_tech_id || '',
        sample_type: investigation.sample_type || '',
        request_notes: investigation.request_notes || '',
        status: investigation.status || 'requested',
        selected_tests: selectedTests,
        test_results: parsedResults,
        lab_notes: investigation.lab_notes || ''
      });

      // Activate test forms for existing tests in edit mode
      if (selectedTests.length > 0) {
        const activeForms = {};
        selectedTests.forEach(test => {
          activeForms[test.ID] = true;
        });
        setActiveTestForms(activeForms);
      }
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
      
      const categoriesData = categoriesResponse.data || [];
      const testsData = testsResponse.data || [];
      
      setCategories(categoriesData);
      setTestDefinitions(testsData);
      
      // If we're in edit mode and have an investigation, try to find the category
      if (investigation && investigation.test_type && testsData.length > 0) {
        const matchingTest = testsData.find(test => 
          test.name === investigation.test_type || 
          test.code === investigation.test_type
        );
        
        if (matchingTest && matchingTest.category_id) {
          setSelectedCategory(matchingTest.category_id.toString());
          
          // Also update the selected test with the full test definition
          setFormData(prev => ({
            ...prev,
            selected_tests: [{
              ...matchingTest,
              ID: `existing_${Date.now()}` // Keep unique ID for form tracking
            }]
          }));
          
          // Activate the test form
          setActiveTestForms({ [`existing_${Date.now()}`]: true });
        }
      }
    } catch (error) {
      console.error('Error loading lab data:', error);
      // For now, use sample data
      const sampleCategories = [
        { ID: 1, name: 'Hematology', description: 'Blood-related tests' },
        { ID: 2, name: 'Chemistry', description: 'Blood chemistry tests' },
        { ID: 3, name: 'Microbiology', description: 'Infection and culture tests' },
        { ID: 4, name: 'Immunology', description: 'Immune system tests' }
      ];
      
      const sampleTestDefinitions = [
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
      ];
      
      setCategories(sampleCategories);
      setTestDefinitions(sampleTestDefinitions);
      
      // If we're in edit mode and have an investigation, try to find the category from sample data
      if (investigation && investigation.test_type) {
        const matchingTest = sampleTestDefinitions.find(test => 
          test.name === investigation.test_type || 
          test.code === investigation.test_type
        );
        
        if (matchingTest && matchingTest.category_id) {
          setSelectedCategory(matchingTest.category_id.toString());
          
          // Also update the selected test with the full test definition
          const testId = `existing_${Date.now()}`;
          setFormData(prev => ({
            ...prev,
            selected_tests: [{
              ...matchingTest,
              ID: testId
            }]
          }));
          
          // Activate the test form
          setActiveTestForms({ [testId]: true });
        }
      }
    } finally {
      setLoadingData(false);
    }
  };

  const loadTestsForCategory = (categoryId) => {
    
    // Debug: Let's see the structure of the first few test definitions
    if (testDefinitions.length > 0) {
      console.log('Sample test definition structure:', testDefinitions[0]);
      console.log('All category_ids in test definitions:', testDefinitions.map(test => test.category_id));
    }
    
    const tests = testDefinitions.filter(test => {
      const testCategoryId = parseInt(test.category_id);
      const selectedCategoryId = parseInt(categoryId);
      return testCategoryId === selectedCategoryId;
    });
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

    // For editing mode, only allow one test (replace existing)
    // For add mode, allow multiple tests
    const isEditMode = !!investigation;
    const updatedTests = isEditMode ? [test] : [test, ...formData.selected_tests];
    
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
    
    setFormData(prev => {
      const currentResults = prev.test_results || {};
      
      // Check if we have array-based format: { parameters: [{ name: "...", value: "..." }] }
      if (currentResults.parameters && Array.isArray(currentResults.parameters)) {
        const updatedParameters = currentResults.parameters.map(param => {
          if (param.name === paramName) {
            return { ...param, [field]: value };
          }
          return param;
        });
        
        const updatedResults = {
          ...currentResults,
          parameters: updatedParameters
        };
        
        return {
          ...prev,
          test_results: updatedResults
        };
      }
      
      // Use object-based format: { [testCode]: { [paramName]: { value: "..." } } }
      const updatedResults = {
        ...currentResults,
        [testCode]: {
          ...currentResults[testCode],
          [paramName]: {
            ...currentResults[testCode]?.[paramName],
            [field]: value
          }
        }
      };
      
      return {
        ...prev,
        test_results: updatedResults
      };
    });
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
    console.log('=== renderTestParameterForm ===');
    console.log('Test:', test);
    console.log('Test parameters:', test.test_parameters);
    console.log('Current test results:', formData.test_results);
    console.log('Is investigation (edit mode):', !!investigation);
    
    const testCode = test.code || test.name;
    
    // Helper function to get parameter value from existing results
    const getExistingParameterValue = (paramName, field = 'value') => {
      const existingResults = formData.test_results;
      
      // Check array-based format first: { parameters: [{ name: "...", value: "..." }] }
      if (existingResults && existingResults.parameters && Array.isArray(existingResults.parameters)) {
        const paramData = existingResults.parameters.find(p => p.name === paramName);
        return paramData?.[field] || '';
      }
      
      // Check nested test results: { [testCode]: { [paramName]: { value: "..." } } }
      if (existingResults && existingResults[testCode] && existingResults[testCode][paramName]) {
        return existingResults[testCode][paramName][field] || '';
      }
      
      // Check direct parameter mapping: { [paramName]: { value: "..." } }
      if (existingResults && existingResults[paramName] && typeof existingResults[paramName] === 'object') {
        return existingResults[paramName][field] || '';
      }
      
      return '';
    };
    
    // Check if we have test parameters defined
    if (test.test_parameters && test.test_parameters.parameters) {
      // Normal case with defined test parameters
      return (
        <div className="mt-4 p-4 bg-gray-50 rounded-lg">
          <h4 className="font-medium text-gray-900 mb-3">Test Parameters for {test.name}</h4>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {test.test_parameters.parameters.map((param, index) => {
              const currentValue = getExistingParameterValue(param.name, 'value');
              const currentFlag = getExistingParameterValue(param.name, 'flag');
              const currentNotes = getExistingParameterValue(param.name, 'notes');
              
            //   console.log(`Parameter ${param.name}: value="${currentValue}", flag="${currentFlag}", notes="${currentNotes}"`);
              
              return (
                <div key={index} className="space-y-2">
                  <label className="block text-sm font-medium text-gray-700">
                    {param.name}
                    {param.unit && <span className="text-gray-500"> ({param.unit})</span>}
                  </label>
                  <input
                    type={param.type === 'numeric' ? 'number' : 'text'}
                    step={param.type === 'numeric' ? '0.01' : undefined}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    value={currentValue}
                    onChange={(e) => updateTestResult(testCode, param.name, 'value', e.target.value)}
                    placeholder={`Enter ${param.name.toLowerCase()}`}
                  />
                  {param.reference_range && (
                    <p className="text-xs text-gray-500">
                      Reference: {param.reference_range.min} - {param.reference_range.max} {param.unit}
                    </p>
                  )}
                  <div className="grid grid-cols-2 gap-2">
                    <input
                      type="text"
                      className="px-3 py-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                      value={currentFlag}
                      onChange={(e) => updateTestResult(testCode, param.name, 'flag', e.target.value)}
                      placeholder="Flag (H/L/N)"
                    />
                    <input
                      type="text"
                      className="px-3 py-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                      value={currentNotes}
                      onChange={(e) => updateTestResult(testCode, param.name, 'notes', e.target.value)}
                      placeholder="Notes"
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      );
    }
    
    // Handle case where we're editing an existing investigation without defined test parameters
    if (investigation) {
      const existingResults = formData.test_results;
      console.log('Editing mode - existing results:', existingResults);
      
      // Handle array-based format: { parameters: [{ name: "...", value: "..." }] }
      if (existingResults && existingResults.parameters && Array.isArray(existingResults.parameters)) {
        console.log('Found array-based parameters:', existingResults.parameters);
        return (
          <div className="mt-4 p-4 bg-gray-50 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Test Results for {test.name}</h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {existingResults.parameters.map((paramData, index) => {
                const paramName = paramData.name;
                return (
                  <div key={index} className="space-y-2">
                    <label className="block text-sm font-medium text-gray-700">
                      {paramName}
                      {paramData.unit && <span className="text-gray-500"> ({paramData.unit})</span>}
                    </label>
                    <input
                      type="text"
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      value={paramData.value || ''}
                      onChange={(e) => updateTestResult(testCode, paramName, 'value', e.target.value)}
                      placeholder={`Enter ${paramName.toLowerCase()}`}
                    />
                    {paramData.reference_range && (
                      <p className="text-xs text-gray-500">
                        Reference: {paramData.reference_range.min} - {paramData.reference_range.max} {paramData.unit}
                      </p>
                    )}
                    <div className="grid grid-cols-2 gap-2">
                      <input
                        type="text"
                        className="px-3 py-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                        value={paramData.flag || ''}
                        onChange={(e) => updateTestResult(testCode, paramName, 'flag', e.target.value)}
                        placeholder="Flag (H/L/N)"
                      />
                      <input
                        type="text"
                        className="px-3 py-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                        value={paramData.notes || ''}
                        onChange={(e) => updateTestResult(testCode, paramName, 'notes', e.target.value)}
                        placeholder="Notes"
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        );
      }
      
      // Try to find parameters from existing results (object-based format)
      if (existingResults && typeof existingResults === 'object') {
        // Check for nested test results structure
        const nestedResults = existingResults[testCode] || existingResults;
        
        if (nestedResults && typeof nestedResults === 'object') {
          // Find parameter keys (exclude metadata fields)
          const parameterKeys = Object.keys(nestedResults).filter(key => 
            key !== 'parameters' && 
            typeof nestedResults[key] === 'object' &&
            nestedResults[key] !== null
          );
          
          if (parameterKeys.length > 0) {
            return (
              <div className="mt-4 p-4 bg-gray-50 rounded-lg">
                <h4 className="font-medium text-gray-900 mb-3">Test Results for {test.name}</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {parameterKeys.map((paramName) => {
                    const paramData = nestedResults[paramName];
                    return (
                      <div key={paramName} className="space-y-2">
                        <label className="block text-sm font-medium text-gray-700">
                          {paramName}
                          {paramData.unit && <span className="text-gray-500"> ({paramData.unit})</span>}
                        </label>
                        <input
                          type="text"
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                          value={paramData.value || ''}
                          onChange={(e) => updateTestResult(testCode, paramName, 'value', e.target.value)}
                          placeholder={`Enter ${paramName.toLowerCase()}`}
                        />
                        {paramData.reference_range && (
                          <p className="text-xs text-gray-500">
                            Reference: {paramData.reference_range.min} - {paramData.reference_range.max} {paramData.unit}
                          </p>
                        )}
                        <div className="grid grid-cols-2 gap-2">
                          <input
                            type="text"
                            className="px-3 py-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                            value={paramData.flag || ''}
                            onChange={(e) => updateTestResult(testCode, paramName, 'flag', e.target.value)}
                            placeholder="Flag (H/L/N)"
                          />
                          <input
                            type="text"
                            className="px-3 py-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                            value={paramData.notes || ''}
                            onChange={(e) => updateTestResult(testCode, paramName, 'notes', e.target.value)}
                            placeholder="Notes"
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          }
        }
      }
      
      // Fallback for editing mode - JSON editor
      return (
        <div className="mt-4 p-4 bg-gray-50 rounded-lg">
          <h4 className="font-medium text-gray-900 mb-3">Test Results for {test.name}</h4>
          <p className="text-sm text-gray-600 mb-3">Raw test results data (JSON format):</p>
          <textarea
            rows={6}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
            value={typeof formData.test_results === 'string' ? formData.test_results : JSON.stringify(formData.test_results, null, 2)}
            onChange={(e) => {
              try {
                const parsed = JSON.parse(e.target.value);
                setFormData(prev => ({ ...prev, test_results: parsed }));
              } catch (err) {
                // If it's not valid JSON, store as string for now
                setFormData(prev => ({ ...prev, test_results: e.target.value }));
              }
            }}
            placeholder="Enter test results as JSON"
          />
        </div>
      );
    }
    
    // No test parameters and not in edit mode
    return (
      <div className="mt-4 p-4 bg-yellow-50 rounded-lg">
        <p className="text-sm text-yellow-800">
          No test parameters defined for this test. Parameters will be available when you add results.
        </p>
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
            onClick={() => {
              console.log('Bypassing loading - setting sample data manually');
              setLoadingData(false);
              // Ensure sample data is set when bypassing
              const sampleCategories = [
                { ID: 1, name: 'Hematology', description: 'Blood-related tests' },
                { ID: 2, name: 'Chemistry', description: 'Blood chemistry tests' },
                { ID: 3, name: 'Microbiology', description: 'Infection and culture tests' },
                { ID: 4, name: 'Immunology', description: 'Immune system tests' }
              ];
              
              const sampleTestDefinitions = [
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
              ];
              
              console.log('Setting bypass categories:', sampleCategories);
              console.log('Setting bypass test definitions:', sampleTestDefinitions);
              
              setCategories(sampleCategories);
              setTestDefinitions(sampleTestDefinitions);
            }}
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
              {/* Additional status options only available in edit mode */}
              {investigation && (
                <>
                  <option value="completed">Completed</option>
                  <option value="verified">Verified</option>
                  <option value="cancelled">Cancelled</option>
                </>
              )}
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
        <h3 className="text-lg font-semibold text-gray-900 mb-4">
          Select Tests
          {investigation && (
            <span className="text-sm font-normal text-gray-600 ml-2">
              (Edit mode: You can change the test for this investigation)
            </span>
          )}
        </h3>
        
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
                  
                  {/* Test Parameter Forms - Always show in edit mode, conditional in add mode */}
                  {(investigation || activeTestForms[test.ID]) && renderTestParameterForm(test)}
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

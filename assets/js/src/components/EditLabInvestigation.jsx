import React, { useState } from 'react';
import Modal from './Modal';
import LabInvestigationForm from './LabInvestigationForm';
import laboratoryService from '../services/laboratoryService';

const EditLabInvestigation = ({ 
  isOpen, 
  onClose, 
  onSuccess, 
  investigation = null 
}) => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (formData) => {
    if (!investigation?.ID) {
      setError('Investigation ID is required for update');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      // For editing, we only allow one test, so take the first selected test
      const selectedTests = formData.selected_tests || [];
      
      if (selectedTests.length === 0) {
        setError('At least one test must be selected');
        setLoading(false);
        return;
      }
      
      // Convert selected_tests back to test_type for backend
      const test = selectedTests[0]; // Only take the first test for editing
      const updateData = {
        visitation_id: formData.visitation_id,
        patient_id: formData.patient_id,
        doctor_id: formData.doctor_id,
        lab_tech_id: formData.lab_tech_id,
        test_type: test.name || test.code, // Use name or code
        sample_type: test.sample_type || formData.sample_type,
        request_notes: formData.request_notes || '',
        lab_notes: formData.lab_notes || '',
        status: formData.status || 'requested',
        test_results: formData.test_results || ''
      };
      
      // Remove any undefined/null values
      Object.keys(updateData).forEach(key => {
        if (updateData[key] === undefined || updateData[key] === null) {
          delete updateData[key];
        }
      });
      
      console.log('Updating investigation with data:', updateData);
      
      const response = await laboratoryService.updateInvestigation(investigation.ID, updateData);
      
      if (response.success) {
        onSuccess?.(response.data);
        onClose();
      } else {
        setError(response.message || 'Failed to update investigation');
      }
    } catch (err) {
      console.error('Error updating investigation:', err);
      setError(err.response?.data?.message || err.message || 'Failed to update investigation');
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    setError(null);
    onClose();
  };

  if (!investigation) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={`Edit Lab Investigation #${investigation.ID}`}
      size="xl"
    >
      <div className="space-y-4">
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-4">
            <div className="flex">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                </svg>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium">Error</h3>
                <p className="mt-1 text-sm">{error}</p>
              </div>
            </div>
          </div>
        )}

        <LabInvestigationForm
          investigation={investigation}
          onSubmit={handleSubmit}
          onCancel={handleCancel}
          loading={loading}
        />
      </div>
    </Modal>
  );
};

export default EditLabInvestigation;

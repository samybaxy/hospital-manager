import React, { useState } from 'react';
import { 
  Box, 
  Typography, 
  TextField, 
  Button, 
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Grid,
  Paper,
  FormControlLabel,
  Checkbox,
  Divider
} from '@mui/material';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';

const LabResultsForm = () => {
  const [labResult, setLabResult] = useState({
    patientId: '',
    sampleCollectionDate: null,
    resultDate: null,
    requestedBy: '',
    testType: '',
    sampleType: '',
    results: '',
    normalRange: '',
    isAbnormal: false,
    interpretation: '',
    comments: '',
    status: 'pending'
  });

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setLabResult(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handleDateChange = (name) => (date) => {
    setLabResult(prev => ({
      ...prev,
      [name]: date
    }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    // TODO: Implement API call to save lab results
    console.log('Lab results submitted:', labResult);
    alert('Lab results saved successfully!');
  };

  return (
    <Paper elevation={3} sx={{ p: 3, maxWidth: 800, mx: 'auto', my: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Laboratory Results Form
      </Typography>
      
      <Box component="form" onSubmit={handleSubmit} noValidate sx={{ mt: 3 }}>
        <Grid container spacing={3}>
          <Grid item xs={12} sm={6}>
            <TextField
              required
              fullWidth
              id="patientId"
              label="Patient ID"
              name="patientId"
              value={labResult.patientId}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="requestedBy"
              label="Requested By (Doctor)"
              name="requestedBy"
              value={labResult.requestedBy}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <LocalizationProvider dateAdapter={AdapterDateFns}>
              <DatePicker
                label="Sample Collection Date"
                value={labResult.sampleCollectionDate}
                onChange={handleDateChange('sampleCollectionDate')}
                renderInput={(params) => <TextField {...params} fullWidth required />}
              />
            </LocalizationProvider>
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <LocalizationProvider dateAdapter={AdapterDateFns}>
              <DatePicker
                label="Result Date"
                value={labResult.resultDate}
                onChange={handleDateChange('resultDate')}
                renderInput={(params) => <TextField {...params} fullWidth required />}
              />
            </LocalizationProvider>
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <FormControl fullWidth>
              <InputLabel id="test-type-label">Test Type</InputLabel>
              <Select
                labelId="test-type-label"
                id="testType"
                name="testType"
                value={labResult.testType}
                label="Test Type"
                onChange={handleChange}
              >
                <MenuItem value="blood">Blood Test</MenuItem>
                <MenuItem value="urine">Urine Analysis</MenuItem>
                <MenuItem value="stool">Stool Analysis</MenuItem>
                <MenuItem value="imaging">Imaging</MenuItem>
                <MenuItem value="microbiology">Microbiology</MenuItem>
                <MenuItem value="pathology">Pathology</MenuItem>
                <MenuItem value="other">Other</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="sampleType"
              label="Sample Type"
              name="sampleType"
              placeholder="e.g., Venous Blood, Spot Urine"
              value={labResult.sampleType}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12}>
            <Divider sx={{ my: 2 }} />
            <Typography variant="h6" gutterBottom>
              Result Details
            </Typography>
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              required
              fullWidth
              id="results"
              label="Results"
              name="results"
              multiline
              rows={3}
              value={labResult.results}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="normalRange"
              label="Normal Range"
              name="normalRange"
              value={labResult.normalRange}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <Box sx={{ display: 'flex', alignItems: 'center', height: '100%' }}>
              <FormControlLabel
                control={
                  <Checkbox
                    checked={labResult.isAbnormal}
                    onChange={handleChange}
                    name="isAbnormal"
                  />
                }
                label="Abnormal Result"
              />
            </Box>
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              fullWidth
              id="interpretation"
              label="Interpretation"
              name="interpretation"
              multiline
              rows={2}
              value={labResult.interpretation}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              fullWidth
              id="comments"
              label="Additional Comments"
              name="comments"
              multiline
              rows={2}
              value={labResult.comments}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12}>
            <FormControl fullWidth>
              <InputLabel id="status-label">Result Status</InputLabel>
              <Select
                labelId="status-label"
                id="status"
                name="status"
                value={labResult.status}
                label="Result Status"
                onChange={handleChange}
              >
                <MenuItem value="pending">Pending</MenuItem>
                <MenuItem value="completed">Completed</MenuItem>
                <MenuItem value="verified">Verified</MenuItem>
                <MenuItem value="cancelled">Cancelled</MenuItem>
              </Select>
            </FormControl>
          </Grid>
        </Grid>
        
        <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end' }}>
          <Button
            type="button"
            variant="outlined"
            sx={{ mr: 2 }}
            onClick={() => window.history.back()}
          >
            Cancel
          </Button>
          <Button
            type="submit"
            variant="contained"
            color="primary"
          >
            Save Lab Results
          </Button>
        </Box>
      </Box>
    </Paper>
  );
};

export default LabResultsForm;

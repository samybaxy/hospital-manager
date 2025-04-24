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
  Paper
} from '@mui/material';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';

const VisitationForm = () => {
  const [visitation, setVisitation] = useState({
    patientId: '',
    visitDate: null,
    purpose: '',
    symptoms: '',
    temperature: '',
    bloodPressure: '',
    weight: '',
    height: '',
    doctorNotes: '',
    visitStatus: 'waiting'
  });

  const handleChange = (e) => {
    const { name, value } = e.target;
    setVisitation(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleDateChange = (date) => {
    setVisitation(prev => ({
      ...prev,
      visitDate: date
    }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    // TODO: Implement API call to save visitation data
    console.log('Visitation data submitted:', visitation);
    alert('Visitation record saved successfully!');
  };

  return (
    <Paper elevation={3} sx={{ p: 3, maxWidth: 800, mx: 'auto', my: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Patient Visitation Form
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
              value={visitation.patientId}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <LocalizationProvider dateAdapter={AdapterDateFns}>
              <DatePicker
                label="Visit Date"
                value={visitation.visitDate}
                onChange={handleDateChange}
                renderInput={(params) => <TextField {...params} fullWidth required />}
              />
            </LocalizationProvider>
          </Grid>
          
          <Grid item xs={12}>
            <FormControl fullWidth>
              <InputLabel id="purpose-label">Purpose of Visit</InputLabel>
              <Select
                labelId="purpose-label"
                id="purpose"
                name="purpose"
                value={visitation.purpose}
                label="Purpose of Visit"
                onChange={handleChange}
              >
                <MenuItem value="checkup">Regular Check-up</MenuItem>
                <MenuItem value="emergency">Emergency</MenuItem>
                <MenuItem value="followup">Follow-up</MenuItem>
                <MenuItem value="vaccination">Vaccination</MenuItem>
                <MenuItem value="consultation">Consultation</MenuItem>
                <MenuItem value="other">Other</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              fullWidth
              id="symptoms"
              label="Symptoms"
              name="symptoms"
              multiline
              rows={3}
              value={visitation.symptoms}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="temperature"
              label="Temperature (°C)"
              name="temperature"
              type="number"
              InputProps={{ inputProps: { step: 0.1 } }}
              value={visitation.temperature}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="bloodPressure"
              label="Blood Pressure (mmHg)"
              name="bloodPressure"
              placeholder="e.g., 120/80"
              value={visitation.bloodPressure}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="weight"
              label="Weight (kg)"
              name="weight"
              type="number"
              InputProps={{ inputProps: { step: 0.1 } }}
              value={visitation.weight}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="height"
              label="Height (cm)"
              name="height"
              type="number"
              value={visitation.height}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              fullWidth
              id="doctorNotes"
              label="Doctor's Notes"
              name="doctorNotes"
              multiline
              rows={4}
              value={visitation.doctorNotes}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12}>
            <FormControl fullWidth>
              <InputLabel id="status-label">Visit Status</InputLabel>
              <Select
                labelId="status-label"
                id="visitStatus"
                name="visitStatus"
                value={visitation.visitStatus}
                label="Visit Status"
                onChange={handleChange}
              >
                <MenuItem value="waiting">Waiting</MenuItem>
                <MenuItem value="in-progress">In Progress</MenuItem>
                <MenuItem value="completed">Completed</MenuItem>
                <MenuItem value="no-show">No Show</MenuItem>
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
            Save Visitation
          </Button>
        </Box>
      </Box>
    </Paper>
  );
};

export default VisitationForm;

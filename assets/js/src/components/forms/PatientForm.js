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
  Divider,
  FormLabel,
  RadioGroup,
  FormControlLabel,
  Radio
} from '@mui/material';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';

const PatientForm = ({ editMode = false, patientData = null }) => {
  const [patient, setPatient] = useState(
    patientData || {
      firstName: '',
      lastName: '',
      dateOfBirth: null,
      gender: '',
      email: '',
      phone: '',
      address: '',
      city: '',
      state: '',
      emergencyContactName: '',
      emergencyContactPhone: '',
      bloodType: '',
      allergies: '',
      medicalHistory: '',
      insuranceProvider: '',
      insurancePolicyNumber: '',
      occupation: '',
      maritalStatus: ''
    }
  );

  const handleChange = (e) => {
    const { name, value } = e.target;
    setPatient(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleDateChange = (date) => {
    setPatient(prev => ({
      ...prev,
      dateOfBirth: date
    }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    // TODO: Implement API call to save patient data
    console.log('Patient data submitted:', patient);
    alert(`Patient ${editMode ? 'updated' : 'registered'} successfully!`);
  };

  return (
    <Paper elevation={3} sx={{ p: 3, maxWidth: 900, mx: 'auto', my: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        {editMode ? 'Edit Patient Information' : 'New Patient Registration'}
      </Typography>
      
      <Box component="form" onSubmit={handleSubmit} noValidate sx={{ mt: 3 }}>
        <Typography variant="h6" sx={{ mb: 2 }}>
          Personal Information
        </Typography>
        
        <Grid container spacing={3}>
          <Grid item xs={12} sm={6}>
            <TextField
              required
              fullWidth
              id="firstName"
              label="First Name"
              name="firstName"
              value={patient.firstName}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              required
              fullWidth
              id="lastName"
              label="Last Name"
              name="lastName"
              value={patient.lastName}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <LocalizationProvider dateAdapter={AdapterDateFns}>
              <DatePicker
                label="Date of Birth"
                value={patient.dateOfBirth}
                onChange={handleDateChange}
                renderInput={(params) => <TextField {...params} fullWidth required />}
              />
            </LocalizationProvider>
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <FormControl component="fieldset" sx={{ mt: 1 }}>
              <FormLabel component="legend">Gender</FormLabel>
              <RadioGroup
                row
                name="gender"
                value={patient.gender}
                onChange={handleChange}
              >
                <FormControlLabel value="female" control={<Radio />} label="Female" />
                <FormControlLabel value="male" control={<Radio />} label="Male" />
                <FormControlLabel value="other" control={<Radio />} label="Other" />
              </RadioGroup>
            </FormControl>
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="email"
              label="Email Address"
              name="email"
              type="email"
              value={patient.email}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              required
              fullWidth
              id="phone"
              label="Phone Number"
              name="phone"
              value={patient.phone}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              fullWidth
              id="address"
              label="Address"
              name="address"
              multiline
              rows={2}
              value={patient.address}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="city"
              label="City"
              name="city"
              value={patient.city}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="state"
              label="State/Province"
              name="state"
              value={patient.state}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <FormControl fullWidth>
              <InputLabel id="marital-status-label">Marital Status</InputLabel>
              <Select
                labelId="marital-status-label"
                id="maritalStatus"
                name="maritalStatus"
                value={patient.maritalStatus}
                label="Marital Status"
                onChange={handleChange}
              >
                <MenuItem value="single">Single</MenuItem>
                <MenuItem value="married">Married</MenuItem>
                <MenuItem value="divorced">Divorced</MenuItem>
                <MenuItem value="widowed">Widowed</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="occupation"
              label="Occupation"
              name="occupation"
              value={patient.occupation}
              onChange={handleChange}
            />
          </Grid>
        </Grid>
        
        <Divider sx={{ my: 3 }} />
        
        <Typography variant="h6" sx={{ mb: 2 }}>
          Emergency Contact
        </Typography>
        
        <Grid container spacing={3}>
          <Grid item xs={12} sm={6}>
            <TextField
              required
              fullWidth
              id="emergencyContactName"
              label="Emergency Contact Name"
              name="emergencyContactName"
              value={patient.emergencyContactName}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              required
              fullWidth
              id="emergencyContactPhone"
              label="Emergency Contact Phone"
              name="emergencyContactPhone"
              value={patient.emergencyContactPhone}
              onChange={handleChange}
            />
          </Grid>
        </Grid>
        
        <Divider sx={{ my: 3 }} />
        
        <Typography variant="h6" sx={{ mb: 2 }}>
          Medical Information
        </Typography>
        
        <Grid container spacing={3}>
          <Grid item xs={12} sm={6}>
            <FormControl fullWidth>
              <InputLabel id="blood-type-label">Blood Type</InputLabel>
              <Select
                labelId="blood-type-label"
                id="bloodType"
                name="bloodType"
                value={patient.bloodType}
                label="Blood Type"
                onChange={handleChange}
              >
                <MenuItem value="A+">A+</MenuItem>
                <MenuItem value="A-">A-</MenuItem>
                <MenuItem value="B+">B+</MenuItem>
                <MenuItem value="B-">B-</MenuItem>
                <MenuItem value="AB+">AB+</MenuItem>
                <MenuItem value="AB-">AB-</MenuItem>
                <MenuItem value="O+">O+</MenuItem>
                <MenuItem value="O-">O-</MenuItem>
                <MenuItem value="unknown">Unknown</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="allergies"
              label="Allergies"
              name="allergies"
              placeholder="List any allergies, or type 'None' if none"
              value={patient.allergies}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              fullWidth
              id="medicalHistory"
              label="Medical History"
              name="medicalHistory"
              multiline
              rows={4}
              placeholder="List any pre-existing conditions, previous surgeries, etc."
              value={patient.medicalHistory}
              onChange={handleChange}
            />
          </Grid>
        </Grid>
        
        <Divider sx={{ my: 3 }} />
        
        <Typography variant="h6" sx={{ mb: 2 }}>
          Insurance Information
        </Typography>
        
        <Grid container spacing={3}>
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="insuranceProvider"
              label="Insurance Provider"
              name="insuranceProvider"
              value={patient.insuranceProvider}
              onChange={handleChange}
            />
          </Grid>
          
          <Grid item xs={12} sm={6}>
            <TextField
              fullWidth
              id="insurancePolicyNumber"
              label="Policy Number"
              name="insurancePolicyNumber"
              value={patient.insurancePolicyNumber}
              onChange={handleChange}
            />
          </Grid>
        </Grid>
        
        <Box sx={{ mt: 4, display: 'flex', justifyContent: 'flex-end' }}>
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
            {editMode ? 'Update Patient' : 'Register Patient'}
          </Button>
        </Box>
      </Box>
    </Paper>
  );
};

export default PatientForm;

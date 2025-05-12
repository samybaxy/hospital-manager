import React from 'react';
import { Formik, Form } from 'formik';
import {
    Box,
    Button,
    Grid,
    TextField,
    Typography
} from '@mui/material';
import * as Yup from 'yup';
import { TimePicker } from '@mui/x-date-pickers';
import { format, isValid } from 'date-fns';

const validationSchema = Yup.object({
    date: Yup.date().required('Date is required'),
    time: Yup.date().required('Time is required'),
    diagnosis: Yup.string().required('Diagnosis is required'),
    treatment: Yup.string().required('Treatment is required'),
    medical_history: Yup.string()
});

const VisitationForm = ({ patient, onSubmit, onCancel }) => {
    // Enhanced function to ensure we have valid date objects with detailed error checking
    const ensureValidDate = (dateValue) => {
        // Create a safe current date for fallback
        const currentDate = new Date();
        
        // Log the incoming value for debugging
        console.log('ensureValidDate received:', dateValue, 'type:', typeof dateValue);
        
        // Handle null/undefined case
        if (dateValue === null || dateValue === undefined) {
            console.log('No date value provided, using current date');
            return currentDate;
        }
        
        // Handle Date object case
        if (dateValue instanceof Date) {
            if (isValid(dateValue) && !isNaN(dateValue.getTime())) {
                return dateValue;
            } else {
                console.log('Invalid Date object provided, using current date');
                return currentDate;
            }
        }
        
        // Handle string case
        if (typeof dateValue === 'string') {
            // Try to detect common patterns first
            
            // ISO format: "2023-05-11T14:30:00.000Z"
            if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(dateValue)) {
                try {
                    const parsedDate = new Date(dateValue);
                    if (isValid(parsedDate) && !isNaN(parsedDate.getTime())) {
                        return parsedDate;
                    }
                } catch (e) {
                    console.warn('Failed to parse ISO date:', dateValue);
                }
            }
            
            // Date-only format: "2023-05-11"
            if (/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
                try {
                    const parsedDate = new Date(dateValue);
                    if (isValid(parsedDate) && !isNaN(parsedDate.getTime())) {
                        return parsedDate;
                    }
                } catch (e) {
                    console.warn('Failed to parse date-only format:', dateValue);
                }
            }
            
            // Time-only format: "14:30" or "14:30:00"
            if (/^([0-1]?[0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/.test(dateValue)) {
                try {
                    // For time-only strings, use current date with the specified time
                    const timeParts = dateValue.split(':');
                    const hours = parseInt(timeParts[0], 10);
                    const minutes = parseInt(timeParts[1], 10);
                    const seconds = timeParts[2] ? parseInt(timeParts[2], 10) : 0;
                    
                    const timeDate = new Date();
                    timeDate.setHours(hours, minutes, seconds, 0);
                    
                    if (isValid(timeDate) && !isNaN(timeDate.getTime())) {
                        return timeDate;
                    }
                } catch (e) {
                    console.warn('Failed to parse time-only format:', dateValue);
                }
            }
            
            // Last resort: try generic parsing
            try {
                const parsedDate = new Date(dateValue);
                if (isValid(parsedDate) && !isNaN(parsedDate.getTime())) {
                    return parsedDate;
                }
            } catch (e) {
                console.warn('Failed to parse date string with generic parsing:', dateValue);
            }
        }
        
        // For numbers, try timestamp
        if (typeof dateValue === 'number') {
            try {
                const dateFromTimestamp = new Date(dateValue);
                if (isValid(dateFromTimestamp) && !isNaN(dateFromTimestamp.getTime())) {
                    return dateFromTimestamp;
                }
            } catch (e) {
                console.warn('Failed to parse numeric timestamp:', dateValue);
            }
        }
        
        // Default to current date if all parsing failed
        console.warn('All date parsing attempts failed, using current date');
        return currentDate;
    };
    
    // Set initial values with safe date handling
    const today = new Date();
    
    const initialValues = {
        patient_id: patient?.id || 0,
        date: today,
        time: today,
        diagnosis: '',
        treatment: '',
        medical_history: ''
    };

    const handleSubmit = (values) => {
        try {
            // Create a safe current date for fallbacks
            const currentDate = new Date();
            
            // Carefully validate and format the date
            let formattedDate;
            try {
                const dateValue = values.date instanceof Date ? values.date : new Date(values.date);
                formattedDate = isValid(dateValue) ? format(dateValue, 'yyyy-MM-dd') : format(currentDate, 'yyyy-MM-dd');
            } catch (dateError) {
                console.error('Error formatting date:', dateError);
                formattedDate = format(currentDate, 'yyyy-MM-dd');
            }
            
            // Carefully validate and format the time
            let formattedTime;
            try {
                // Handle time specially to avoid time zone issues
                const timeValue = values.time instanceof Date ? values.time : new Date(values.time);
                
                if (!isValid(timeValue)) {
                    throw new Error('Invalid time value');
                }
                
                // Use hours and minutes only to avoid timezone issues
                const hours = timeValue.getHours().toString().padStart(2, '0');
                const minutes = timeValue.getMinutes().toString().padStart(2, '0');
                formattedTime = `${hours}:${minutes}:00`;
            } catch (timeError) {
                console.error('Error formatting time:', timeError);
                // Default to current time
                const hours = currentDate.getHours().toString().padStart(2, '0');
                const minutes = currentDate.getMinutes().toString().padStart(2, '0');
                formattedTime = `${hours}:${minutes}:00`;
            }
            
            console.log('Submitting with formatted time:', formattedTime);
            
            onSubmit({
                ...values,
                date: formattedDate,
                time: formattedTime
            });
        } catch (error) {
            console.error('Error during form submission:', error);
            
            // Ultimate fallback values if there's an error
            const now = new Date();
            const fallbackDate = now.toISOString().split('T')[0];
            const fallbackTime = now.getHours().toString().padStart(2, '0') + ':' + 
                               now.getMinutes().toString().padStart(2, '0') + ':00';
                               
            console.log('Using fallback time:', fallbackTime);
            
            onSubmit({
                ...values,
                date: fallbackDate,
                time: fallbackTime
            });
        }
    };

    return (
        <Formik
            initialValues={initialValues}
            validationSchema={validationSchema}
            onSubmit={handleSubmit}
        >
            {({ values, errors, touched, handleChange, handleBlur, setFieldValue }) => (
                <Form>
                    <Grid container spacing={3}>
                        <Grid item xs={12}>
                            <Typography variant="subtitle1" gutterBottom>
                                Patient: {patient?.fullName}
                            </Typography>
                        </Grid>

                        <Grid item xs={12} md={6}>
                            <TimePicker
                                label="Appointment Time"
                                value={values.time}
                                onChange={(newTime) => setFieldValue('time', newTime)}
                                renderInput={(params) => (
                                    <TextField 
                                        {...params}
                                        fullWidth
                                        error={touched.time && !!errors.time}
                                        helperText={touched.time && errors.time}
                                    />
                                )}
                            />
                        </Grid>

                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                multiline
                                rows={3}
                                name="diagnosis"
                                label="Diagnosis"
                                value={values.diagnosis}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.diagnosis && !!errors.diagnosis}
                                helperText={touched.diagnosis && errors.diagnosis}
                            />
                        </Grid>

                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                multiline
                                rows={3}
                                name="treatment"
                                label="Treatment"
                                value={values.treatment}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.treatment && !!errors.treatment}
                                helperText={touched.treatment && errors.treatment}
                            />
                        </Grid>

                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                multiline
                                rows={4}
                                name="medical_history"
                                label="Medical History Notes"
                                value={values.medical_history}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.medical_history && !!errors.medical_history}
                                helperText={touched.medical_history && errors.medical_history}
                            />
                        </Grid>

                        <Grid item xs={12}>
                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
                                <Button onClick={onCancel}>
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
                        </Grid>
                    </Grid>
                </Form>
            )}
        </Formik>
    );
};

export default VisitationForm;

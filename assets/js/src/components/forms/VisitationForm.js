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
import { format } from 'date-fns';

const validationSchema = Yup.object({
    date: Yup.date().required('Date is required'),
    time: Yup.date().required('Time is required'),
    diagnosis: Yup.string().required('Diagnosis is required'),
    treatment: Yup.string().required('Treatment is required'),
    medical_history: Yup.string()
});

const VisitationForm = ({ patient, onSubmit, onCancel }) => {
    const initialValues = {
        patient_id: patient?.id,
        date: new Date(),
        time: new Date(),
        diagnosis: '',
        treatment: '',
        medical_history: ''
    };

    const handleSubmit = (values) => {
        onSubmit({
            ...values,
            date: format(values.date, 'yyyy-MM-dd'),
            time: format(values.time, 'HH:mm:ss')
        });
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

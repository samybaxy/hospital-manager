import React from 'react';
import { Formik, Form } from 'formik';
import {
    Box,
    Button,
    Grid,
    TextField,
    MenuItem,
    Typography
} from '@mui/material';
import * as Yup from 'yup';

const validationSchema = Yup.object({
    blood_group: Yup.string().required('Blood group is required'),
    genotype: Yup.string().required('Genotype is required'),
    height: Yup.number().positive('Height must be positive').required('Height is required'),
    weight: Yup.number().positive('Weight must be positive').required('Weight is required'),
    allergies: Yup.string(),
    chronic_conditions: Yup.string(),
    current_medications: Yup.string()
});

const bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
const genotypes = ['AA', 'AS', 'SS', 'AC', 'SC'];

const BiodataForm = ({ patient, onSubmit, onCancel }) => {
    const initialValues = {
        blood_group: patient?.bio_data?.blood_group || '',
        genotype: patient?.bio_data?.genotype || '',
        height: patient?.bio_data?.height || '',
        weight: patient?.bio_data?.weight || '',
        allergies: patient?.bio_data?.allergies || '',
        chronic_conditions: patient?.bio_data?.chronic_conditions || '',
        current_medications: patient?.bio_data?.current_medications || ''
    };

    const handleSubmit = (values) => {
        onSubmit({
            ...patient?.bio_data,
            ...values
        });
    };

    return (
        <Formik
            initialValues={initialValues}
            validationSchema={validationSchema}
            onSubmit={handleSubmit}
        >
            {({ values, errors, touched, handleChange, handleBlur }) => (
                <Form>
                    <Grid container spacing={3}>
                        <Grid item xs={12}>
                            <Typography variant="subtitle1" gutterBottom>
                                Patient: {patient?.fullName}
                            </Typography>
                        </Grid>

                        <Grid item xs={12} md={6}>
                            <TextField
                                fullWidth
                                select
                                name="blood_group"
                                label="Blood Group"
                                value={values.blood_group}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.blood_group && !!errors.blood_group}
                                helperText={touched.blood_group && errors.blood_group}
                            >
                                {bloodGroups.map(group => (
                                    <MenuItem key={group} value={group}>
                                        {group}
                                    </MenuItem>
                                ))}
                            </TextField>
                        </Grid>

                        <Grid item xs={12} md={6}>
                            <TextField
                                fullWidth
                                select
                                name="genotype"
                                label="Genotype"
                                value={values.genotype}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.genotype && !!errors.genotype}
                                helperText={touched.genotype && errors.genotype}
                            >
                                {genotypes.map(type => (
                                    <MenuItem key={type} value={type}>
                                        {type}
                                    </MenuItem>
                                ))}
                            </TextField>
                        </Grid>

                        <Grid item xs={12} md={6}>
                            <TextField
                                fullWidth
                                name="height"
                                label="Height (cm)"
                                type="number"
                                value={values.height}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.height && !!errors.height}
                                helperText={touched.height && errors.height}
                            />
                        </Grid>

                        <Grid item xs={12} md={6}>
                            <TextField
                                fullWidth
                                name="weight"
                                label="Weight (kg)"
                                type="number"
                                value={values.weight}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.weight && !!errors.weight}
                                helperText={touched.weight && errors.weight}
                            />
                        </Grid>

                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                multiline
                                rows={2}
                                name="allergies"
                                label="Known Allergies"
                                value={values.allergies}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.allergies && !!errors.allergies}
                                helperText={touched.allergies && errors.allergies}
                            />
                        </Grid>

                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                multiline
                                rows={2}
                                name="chronic_conditions"
                                label="Chronic Conditions"
                                value={values.chronic_conditions}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.chronic_conditions && !!errors.chronic_conditions}
                                helperText={touched.chronic_conditions && errors.chronic_conditions}
                            />
                        </Grid>

                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                multiline
                                rows={2}
                                name="current_medications"
                                label="Current Medications"
                                value={values.current_medications}
                                onChange={handleChange}
                                onBlur={handleBlur}
                                error={touched.current_medications && !!errors.current_medications}
                                helperText={touched.current_medications && errors.current_medications}
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
                                    Save Biodata
                                </Button>
                            </Box>
                        </Grid>
                    </Grid>
                </Form>
            )}
        </Formik>
    );
};

export default BiodataForm;

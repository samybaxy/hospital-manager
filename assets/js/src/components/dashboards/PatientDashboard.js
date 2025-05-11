import React, { useState } from 'react';
import {
    Box,
    Grid,
    Paper,
    Typography,
    Tabs,
    Tab,
    List,
    ListItem,
    ListItemText,
    ListItemSecondary,
    Button,
    Chip,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    FormControl,
    InputLabel,
    Select,
    MenuItem,
    TextField
} from '@mui/material';
import {
    LocalHospital as HospitalIcon,
    Science as LabIcon,
    Chat as ChatIcon,
    Event as EventIcon
} from '@mui/icons-material';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { format, addDays } from 'date-fns';
import { DateTimePicker } from '@mui/x-date-pickers';
import ChatList from '../chat/ChatList';

const PatientDashboard = () => {
    const [activeTab, setActiveTab] = useState(0);
    const [selectedDoctor, setSelectedDoctor] = useState('');
    const [appointmentDate, setAppointmentDate] = useState(addDays(new Date(), 1));
    const [appointmentReason, setAppointmentReason] = useState('');
    const [openAppointmentDialog, setOpenAppointmentDialog] = useState(false);
    const queryClient = useQueryClient();

    // Fetch medical history
    const { data: medicalHistory, isLoading: loadingHistory } = useQuery(
        'medicalHistory',
        () => fetch('/wp-json/hospital-manager/v1/patients/my/history').then(res => res.json())
    );

    // Fetch lab results
    const { data: labResults, isLoading: loadingLab } = useQuery(
        'labResults',
        () => fetch('/wp-json/hospital-manager/v1/patients/my/lab-results').then(res => res.json())
    );

    // Fetch appointments
    const { data: appointments, isLoading: loadingAppointments } = useQuery(
        'appointments',
        () => fetch('/wp-json/hospital-manager/v1/appointments').then(res => res.json())
    );

    // Fetch available doctors
    const { data: doctors } = useQuery(
        'doctors',
        () => fetch('/wp-json/hospital-manager/v1/doctors').then(res => res.json())
    );

    // Book appointment mutation
    const bookAppointment = useMutation(
        (appointmentData) => fetch('/wp-json/hospital-manager/v1/appointments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(appointmentData)
        }).then(res => res.json()),
        {
            onSuccess: () => {
                queryClient.invalidateQueries('appointments');
                setOpenAppointmentDialog(false);
                setSelectedDoctor('');
                setAppointmentDate(addDays(new Date(), 1));
                setAppointmentReason('');
            }
        }
    );

    const handleAppointmentSubmit = () => {
        bookAppointment.mutate({
            doctor_id: selectedDoctor,
            date: format(appointmentDate, 'yyyy-MM-dd'),
            time: format(appointmentDate, 'HH:mm:ss'),
            reason: appointmentReason
        });
    };

    return (
        <Box sx={{ flexGrow: 1, p: 3 }}>
            <Grid container spacing={3}>
                {/* Welcome Card */}
                <Grid item xs={12}>
                    <Paper sx={{ p: 3, mb: 3 }}>
                        <Typography variant="h5" gutterBottom>
                            Welcome to Your Health Dashboard
                        </Typography>
                        <Button
                            variant="contained"
                            startIcon={<EventIcon />}
                            onClick={() => setOpenAppointmentDialog(true)}
                            sx={{ mt: 1 }}
                        >
                            Book Appointment
                        </Button>
                    </Paper>
                </Grid>

                {/* Main Content */}
                <Grid item xs={12}>
                    <Paper sx={{ width: '100%' }}>
                        <Tabs
                            value={activeTab}
                            onChange={(_, newValue) => setActiveTab(newValue)}
                            sx={{ borderBottom: 1, borderColor: 'divider' }}
                        >
                            <Tab icon={<HospitalIcon />} label="Medical History" />
                            <Tab icon={<LabIcon />} label="Lab Results" />
                            <Tab icon={<EventIcon />} label="Appointments" />
                            <Tab icon={<ChatIcon />} label="Messages" />
                        </Tabs>

                        {/* Medical History Tab */}
                        {activeTab === 0 && (
                            <Box sx={{ p: 3 }}>
                                {loadingHistory ? (
                                    <Typography>Loading medical history...</Typography>
                                ) : (
                                    <List>
                                        {medicalHistory?.map((visit) => (
                                            <ListItem key={visit.id} divider>
                                                <ListItemText
                                                    primary={format(new Date(visit.date), 'PP')}
                                                    secondary={
                                                        <>
                                                            <Typography component="span" variant="body2" color="text.primary">
                                                                Doctor: {visit.doctor_name}
                                                            </Typography>
                                                            <br />
                                                            <Typography component="span" variant="body2">
                                                                Diagnosis: {visit.diagnosis}
                                                            </Typography>
                                                            <br />
                                                            <Typography component="span" variant="body2">
                                                                Treatment: {visit.treatment}
                                                            </Typography>
                                                        </>
                                                    }
                                                />
                                            </ListItem>
                                        ))}
                                    </List>
                                )}
                            </Box>
                        )}

                        {/* Lab Results Tab */}
                        {activeTab === 1 && (
                            <Box sx={{ p: 3 }}>
                                {loadingLab ? (
                                    <Typography>Loading lab results...</Typography>
                                ) : (
                                    <List>
                                        {labResults?.map((result) => (
                                            <ListItem key={result.id} divider>
                                                <ListItemText
                                                    primary={
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                                            <Typography variant="subtitle1">
                                                                {result.test_type}
                                                            </Typography>
                                                            <Chip
                                                                size="small"
                                                                color={result.status === 'completed' ? 'success' : 'warning'}
                                                                label={result.status}
                                                            />
                                                        </Box>
                                                    }
                                                    secondary={
                                                        <>
                                                            <Typography component="span" variant="body2">
                                                                Date: {format(new Date(result.created_at), 'PP')}
                                                            </Typography>
                                                            {result.status === 'completed' && (
                                                                <>
                                                                    <br />
                                                                    <Typography component="span" variant="body2">
                                                                        Results: {result.results}
                                                                    </Typography>
                                                                </>
                                                            )}
                                                        </>
                                                    }
                                                />
                                                {result.status === 'completed' && (
                                                    <Button size="small" href={result.report_url}>
                                                        View Report
                                                    </Button>
                                                )}
                                            </ListItem>
                                        ))}
                                    </List>
                                )}
                            </Box>
                        )}

                        {/* Appointments Tab */}
                        {activeTab === 2 && (
                            <Box sx={{ p: 3 }}>
                                {loadingAppointments ? (
                                    <Typography>Loading appointments...</Typography>
                                ) : (
                                    <List>
                                        {appointments?.map((appointment) => (
                                            <ListItem key={appointment.id} divider>
                                                <ListItemText
                                                    primary={
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                                            <Typography variant="subtitle1">
                                                                {appointment.appointment_date ? 
                                                                  format(new Date(appointment.appointment_date), 'PP') :
                                                                  'Date not set'}
                                                                {' at '}
                                                                {appointment.appointment_time ? 
                                                                  (() => {
                                                                    try {
                                                                      return format(new Date(`2000-01-01T${appointment.appointment_time}`), 'p')
                                                                    } catch (e) {
                                                                      return appointment.appointment_time
                                                                    }
                                                                  })() :
                                                                  'Time not set'}
                                                            </Typography>
                                                            <Chip
                                                                size="small"
                                                                color={
                                                                    appointment.status === 'confirmed' ? 'success' :
                                                                    appointment.status === 'pending' ? 'warning' : 'error'
                                                                }
                                                                label={appointment.status}
                                                            />
                                                        </Box>
                                                    }
                                                    secondary={
                                                        <>
                                                            <Typography component="span" variant="body2" color="text.primary">
                                                                Doctor: {appointment.doctor_name}
                                                            </Typography>
                                                            <br />
                                                            <Typography component="span" variant="body2">
                                                                Reason: {appointment.reason}
                                                            </Typography>
                                                        </>
                                                    }
                                                />
                                            </ListItem>
                                        ))}
                                    </List>
                                )}
                            </Box>
                        )}

                        {/* Messages Tab */}
                        {activeTab === 3 && (
                            <Box sx={{ p: 3 }}>
                                <ChatList />
                            </Box>
                        )}
                    </Paper>
                </Grid>
            </Grid>

            {/* Book Appointment Dialog */}
            <Dialog
                open={openAppointmentDialog}
                onClose={() => setOpenAppointmentDialog(false)}
                maxWidth="sm"
                fullWidth
            >
                <DialogTitle>Book New Appointment</DialogTitle>
                <DialogContent>
                    <Box sx={{ mt: 2, display: 'flex', flexDirection: 'column', gap: 3 }}>
                        <FormControl fullWidth>
                            <InputLabel>Select Doctor</InputLabel>
                            <Select
                                value={selectedDoctor}
                                onChange={(e) => setSelectedDoctor(e.target.value)}
                                label="Select Doctor"
                            >
                                {doctors?.map((doctor) => (
                                    <MenuItem key={doctor.id} value={doctor.id}>
                                        {doctor.name} - {doctor.specialty}
                                    </MenuItem>
                                ))}
                            </Select>
                        </FormControl>

                        <DateTimePicker
                            label="Appointment Date & Time"
                            value={appointmentDate}
                            onChange={setAppointmentDate}
                            minDate={addDays(new Date(), 1)}
                            renderInput={(params) => <TextField {...params} fullWidth />}
                        />

                        <TextField
                            fullWidth
                            multiline
                            rows={3}
                            label="Reason for Visit"
                            value={appointmentReason}
                            onChange={(e) => setAppointmentReason(e.target.value)}
                        />
                    </Box>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setOpenAppointmentDialog(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        onClick={handleAppointmentSubmit}
                        disabled={!selectedDoctor || !appointmentDate || !appointmentReason}
                    >
                        Book Appointment
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default PatientDashboard;

import React from 'react';
import {
    Box,
    Paper,
    Typography,
    Grid,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Tabs,
    Tab,
    Button,
    Chip
} from '@mui/material';
import { useParams } from 'react-router-dom';
import { useQuery } from 'react-query';
import { format } from 'date-fns';
import HistoryIcon from '@mui/icons-material/History';
import BiotechIcon from '@mui/icons-material/Biotech';
import LocalHospitalIcon from '@mui/icons-material/LocalHospital';

const TabPanel = ({ children, value, index }) => (
    <div hidden={value !== index} role="tabpanel">
        {value === index && <Box sx={{ p: 3 }}>{children}</Box>}
    </div>
);

const PatientProfile = () => {
    const { id } = useParams();
    const [tabValue, setTabValue] = React.useState(0);

    // Fetch patient data
    const { data: patient, isLoading: loadingPatient } = useQuery(
        ['patient', id],
        () => fetch(`/wp-json/hospital-manager/v1/patients/${id}`).then(res => res.json())
    );

    // Fetch visitation history
    const { data: visitations, isLoading: loadingVisitations } = useQuery(
        ['visitations', id],
        () => fetch(`/wp-json/hospital-manager/v1/patients/${id}/visitations`).then(res => res.json())
    );

    // Fetch lab results
    const { data: labResults, isLoading: loadingLab } = useQuery(
        ['labResults', id],
        () => fetch(`/wp-json/hospital-manager/v1/patients/${id}/lab-results`).then(res => res.json())
    );

    // Fetch audit logs
    const { data: auditLogs, isLoading: loadingAudit } = useQuery(
        ['auditLogs', id],
        () => fetch(`/wp-json/hospital-manager/v1/audit-logs/patient/${id}`).then(res => res.json())
    );

    if (loadingPatient) return <div>Loading...</div>;

    return (
        <Box sx={{ flexGrow: 1, p: 3 }}>
            <Grid container spacing={3}>
                {/* Patient Overview */}
                <Grid item xs={12}>
                    <Paper sx={{ p: 3 }}>
                        <Grid container spacing={3}>
                            <Grid item xs={12} md={6}>
                                <Typography variant="h5">{patient?.fullName}</Typography>
                                <Typography color="textSecondary" gutterBottom>
                                    HMO: {patient?.hmoName} (ID: {patient?.hmoDesignatedId})
                                </Typography>
                                <Box sx={{ mt: 2 }}>
                                    <Typography variant="body1">
                                        Phone: {patient?.phoneNumber}
                                    </Typography>
                                    <Typography variant="body1">
                                        Email: {patient?.email}
                                    </Typography>
                                </Box>
                            </Grid>
                            <Grid item xs={12} md={6}>
                                <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
                                    <Chip 
                                        label={`Blood Group: ${patient?.bio_data?.blood_group || 'N/A'}`}
                                        color="primary"
                                    />
                                    <Chip 
                                        label={`Genotype: ${patient?.bio_data?.genotype || 'N/A'}`}
                                        color="primary"
                                    />
                                    <Chip 
                                        label={`Height: ${patient?.bio_data?.height || 'N/A'} cm`}
                                        color="secondary"
                                    />
                                    <Chip 
                                        label={`Weight: ${patient?.bio_data?.weight || 'N/A'} kg`}
                                        color="secondary"
                                    />
                                </Box>
                            </Grid>
                        </Grid>
                    </Paper>
                </Grid>

                {/* Tabs Section */}
                <Grid item xs={12}>
                    <Paper sx={{ width: '100%' }}>
                        <Tabs
                            value={tabValue}
                            onChange={(_, newValue) => setTabValue(newValue)}
                            sx={{ borderBottom: 1, borderColor: 'divider' }}
                        >
                            <Tab icon={<LocalHospitalIcon />} label="Visitation History" />
                            <Tab icon={<BiotechIcon />} label="Lab Results" />
                            <Tab icon={<HistoryIcon />} label="Audit Log" />
                        </Tabs>

                        {/* Visitation History */}
                        <TabPanel value={tabValue} index={0}>
                            {loadingVisitations ? (
                                <div>Loading visitations...</div>
                            ) : (
                                <TableContainer>
                                    <Table>
                                        <TableHead>
                                            <TableRow>
                                                <TableCell>Date</TableCell>
                                                <TableCell>Doctor</TableCell>
                                                <TableCell>Diagnosis</TableCell>
                                                <TableCell>Treatment</TableCell>
                                                <TableCell>Actions</TableCell>
                                            </TableRow>
                                        </TableHead>
                                        <TableBody>
                                            {visitations?.map((visit) => (
                                                <TableRow key={visit.id}>
                                                    <TableCell>
                                                        {format(new Date(visit.date), 'PP')}
                                                    </TableCell>
                                                    <TableCell>{visit.doctor_name}</TableCell>
                                                    <TableCell>{visit.diagnosis}</TableCell>
                                                    <TableCell>{visit.treatment}</TableCell>
                                                    <TableCell>
                                                        <Button
                                                            size="small"
                                                            onClick={() => window.location.href = `/visitations/${visit.id}`}
                                                        >
                                                            View Details
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </TableContainer>
                            )}
                        </TabPanel>

                        {/* Lab Results */}
                        <TabPanel value={tabValue} index={1}>
                            {loadingLab ? (
                                <div>Loading lab results...</div>
                            ) : (
                                <TableContainer>
                                    <Table>
                                        <TableHead>
                                            <TableRow>
                                                <TableCell>Date</TableCell>
                                                <TableCell>Test Type</TableCell>
                                                <TableCell>Status</TableCell>
                                                <TableCell>Results</TableCell>
                                                <TableCell>Actions</TableCell>
                                            </TableRow>
                                        </TableHead>
                                        <TableBody>
                                            {labResults?.map((result) => (
                                                <TableRow key={result.id}>
                                                    <TableCell>
                                                        {format(new Date(result.created_at), 'PP')}
                                                    </TableCell>
                                                    <TableCell>{result.test_type}</TableCell>
                                                    <TableCell>
                                                        <Chip
                                                            label={result.status}
                                                            color={result.status === 'completed' ? 'success' : 'warning'}
                                                            size="small"
                                                        />
                                                    </TableCell>
                                                    <TableCell>{result.results || 'Pending'}</TableCell>
                                                    <TableCell>
                                                        <Button
                                                            size="small"
                                                            onClick={() => window.location.href = `/lab-results/${result.id}`}
                                                        >
                                                            View Details
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </TableContainer>
                            )}
                        </TabPanel>

                        {/* Audit Log */}
                        <TabPanel value={tabValue} index={2}>
                            {loadingAudit ? (
                                <div>Loading audit log...</div>
                            ) : (
                                <TableContainer>
                                    <Table>
                                        <TableHead>
                                            <TableRow>
                                                <TableCell>Date & Time</TableCell>
                                                <TableCell>Action</TableCell>
                                                <TableCell>User</TableCell>
                                                <TableCell>Details</TableCell>
                                            </TableRow>
                                        </TableHead>
                                        <TableBody>
                                            {auditLogs?.map((log) => (
                                                <TableRow key={log.id}>
                                                    <TableCell>
                                                        {format(new Date(log.created_at), 'PPp')}
                                                    </TableCell>
                                                    <TableCell>{log.action}</TableCell>
                                                    <TableCell>{log.user_name}</TableCell>
                                                    <TableCell>{log.details}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </TableContainer>
                            )}
                        </TabPanel>
                    </Paper>
                </Grid>
            </Grid>
        </Box>
    );
};

export default PatientProfile;

import React, { useState } from 'react';
import { 
    Grid, 
    Paper, 
    Typography,
    Box,
    Button,
    Stack,
    Tabs,
    Tab,
    Dialog,
    DialogTitle,
    DialogContent,
} from '@mui/material';
import { DataGrid } from '@mui/x-data-grid';
import { useQuery } from 'react-query';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import HistoryIcon from '@mui/icons-material/History';
import PatientForm from '../forms/PatientForm';

const DeskOfficerDashboard = () => {
    const [tab, setTab] = useState(0);
    const [openDialog, setOpenDialog] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState(null);

    const { data: patients, isLoading: loadingPatients } = useQuery('patients', () =>
        fetch('/wp-json/hospital-manager/v1/patients').then(res => res.json())
    );

    const { data: auditLogs, isLoading: loadingAudit } = useQuery('auditLogs', () =>
        fetch('/wp-json/hospital-manager/v1/audit-logs').then(res => res.json())
    );

    const patientColumns = [
        { field: 'id', headerName: 'ID', width: 90 },
        { field: 'fullName', headerName: 'Full Name', width: 200 },
        { field: 'hmoName', headerName: 'HMO', width: 150 },
        { field: 'hmoDesignatedId', headerName: 'HMO ID', width: 130 },
        { field: 'phoneNumber', headerName: 'Phone', width: 130 },
        {
            field: 'actions',
            headerName: 'Actions',
            width: 200,
            renderCell: (params) => (
                <Stack direction="row" spacing={1}>
                    <Button
                        size="small"
                        startIcon={<EditIcon />}
                        onClick={() => {
                            setSelectedPatient(params.row);
                            setOpenDialog(true);
                        }}
                    >
                        Edit
                    </Button>
                    <Button
                        size="small"
                        startIcon={<HistoryIcon />}
                        onClick={() => setTab(1)}
                    >
                        History
                    </Button>
                </Stack>
            )
        }
    ];

    const auditColumns = [
        { field: 'timestamp', headerName: 'Time', width: 180 },
        { field: 'user', headerName: 'User', width: 150 },
        { field: 'action', headerName: 'Action', width: 120 },
        { field: 'entityType', headerName: 'Type', width: 100 },
        { field: 'details', headerName: 'Details', width: 400 }
    ];

    if (loadingPatients || loadingAudit) return <div>Loading...</div>;

    return (
        <Box sx={{ flexGrow: 1, p: 3 }}>
            <Grid container spacing={3}>
                <Grid item xs={12}>
                    <Paper sx={{ p: 2 }}>
                        <Stack direction="row" spacing={2} alignItems="center" mb={3}>
                            <Typography variant="h6">Desk Officer Dashboard</Typography>
                            {tab === 0 && (
                                <Button
                                    variant="contained"
                                    startIcon={<AddIcon />}
                                    onClick={() => {
                                        setSelectedPatient(null);
                                        setOpenDialog(true);
                                    }}
                                >
                                    New Patient
                                </Button>
                            )}
                        </Stack>

                        <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 2 }}>
                            <Tabs value={tab} onChange={(_, newValue) => setTab(newValue)}>
                                <Tab label="Patients" />
                                <Tab label="Audit Log" />
                            </Tabs>
                        </Box>

                        {tab === 0 ? (
                            <DataGrid
                                rows={patients || []}
                                columns={patientColumns}
                                pageSize={10}
                                autoHeight
                                disableSelectionOnClick
                            />
                        ) : (
                            <DataGrid
                                rows={auditLogs || []}
                                columns={auditColumns}
                                pageSize={10}
                                autoHeight
                                disableSelectionOnClick
                            />
                        )}
                    </Paper>
                </Grid>
            </Grid>

            <Dialog 
                open={openDialog} 
                onClose={() => setOpenDialog(false)}
                maxWidth="md"
                fullWidth
            >
                <DialogTitle>
                    {selectedPatient ? 'Edit Patient' : 'New Patient'}
                </DialogTitle>
                <DialogContent>
                    <PatientForm 
                        patient={selectedPatient}
                        onSubmit={() => setOpenDialog(false)}
                    />
                </DialogContent>
            </Dialog>
        </Box>
    );
};

export default DeskOfficerDashboard;

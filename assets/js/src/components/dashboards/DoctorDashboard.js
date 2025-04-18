import React, { useState } from 'react';
import {
    Box,
    Grid,
    Paper,
    Typography,
    TextField,
    Button,
    Dialog,
    DialogTitle,
    DialogContent,
    Tab,
    Tabs,
    IconButton
} from '@mui/material';
import { DataGrid } from '@mui/x-data-grid';
import { DatePicker } from '@mui/x-date-pickers';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { format } from 'date-fns';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import VisibilityIcon from '@mui/icons-material/Visibility';
import VisitationForm from '../forms/VisitationForm';
import BiodataForm from '../forms/BiodataForm';
import ChatList from '../chat/ChatList';

const DoctorDashboard = () => {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedDate, setSelectedDate] = useState(new Date());
    const [activeTab, setActiveTab] = useState(0);
    const [openVisitationDialog, setOpenVisitationDialog] = useState(false);
    const [openBiodataDialog, setOpenBiodataDialog] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState(null);
    const queryClient = useQueryClient();

    // Fetch patients
    const { data: patientsData, isLoading: loadingPatients } = useQuery(
        ['patients', searchTerm],
        () => fetch(`/wp-json/hospital-manager/v1/doctor/patients?search=${searchTerm}`)
            .then(res => res.json())
    );

    // Fetch visitations for selected date
    const { data: visitationsData, isLoading: loadingVisitations } = useQuery(
        ['visitations', format(selectedDate, 'yyyy-MM-dd')],
        () => fetch(`/wp-json/hospital-manager/v1/doctor/visitations?date=${format(selectedDate, 'yyyy-MM-dd')}`)
            .then(res => res.json())
    );

    // Create visitation mutation
    const createVisitation = useMutation(
        (data) => fetch('/wp-json/hospital-manager/v1/doctor/visitations', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(res => res.json()),
        {
            onSuccess: () => {
                queryClient.invalidateQueries('visitations');
                setOpenVisitationDialog(false);
            }
        }
    );

    // Update patient biodata mutation
    const updateBiodata = useMutation(
        ({ patientId, biodata }) => fetch(`/wp-json/hospital-manager/v1/doctor/patients/${patientId}/biodata`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ biodata })
        }).then(res => res.json()),
        {
            onSuccess: () => {
                queryClient.invalidateQueries('patients');
                setOpenBiodataDialog(false);
            }
        }
    );

    const patientColumns = [
        { field: 'id', headerName: 'ID', width: 90 },
        { field: 'fullName', headerName: 'Patient Name', width: 200 },
        { field: 'hmoName', headerName: 'HMO', width: 150 },
        { field: 'hmoDesignatedId', headerName: 'HMO ID', width: 130 },
        {
            field: 'actions',
            headerName: 'Actions',
            width: 200,
            renderCell: (params) => (
                <Box>
                    <IconButton 
                        onClick={() => {
                            setSelectedPatient(params.row);
                            setOpenVisitationDialog(true);
                        }}
                    >
                        <AddIcon />
                    </IconButton>
                    <IconButton
                        onClick={() => {
                            setSelectedPatient(params.row);
                            setOpenBiodataDialog(true);
                        }}
                    >
                        <EditIcon />
                    </IconButton>
                    <IconButton onClick={() => window.location.href = `/patients/${params.row.id}`}>
                        <VisibilityIcon />
                    </IconButton>
                </Box>
            )
        }
    ];

    const visitationColumns = [
        { field: 'time', headerName: 'Time', width: 100 },
        { field: 'patientName', headerName: 'Patient', width: 200 },
        { field: 'diagnosis', headerName: 'Diagnosis', width: 300 },
        { field: 'treatment', headerName: 'Treatment', width: 300 },
        {
            field: 'actions',
            headerName: 'Actions',
            width: 100,
            renderCell: (params) => (
                <IconButton onClick={() => window.location.href = `/visitations/${params.row.id}`}>
                    <VisibilityIcon />
                </IconButton>
            )
        }
    ];

    return (
        <Box sx={{ flexGrow: 1, p: 3 }}>
            <Grid container spacing={3}>
                <Grid item xs={12}>
                    <Paper sx={{ p: 2 }}>
                        <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 2 }}>
                            <Tabs value={activeTab} onChange={(_, newValue) => setActiveTab(newValue)}>
                                <Tab label="Patients" />
                                <Tab label="Visitations" />
                                <Tab label="Messages" />
                            </Tabs>
                        </Box>

                        {activeTab === 0 ? (
                            <>
                                <Box sx={{ mb: 2 }}>
                                    <TextField
                                        label="Search Patients"
                                        variant="outlined"
                                        size="small"
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        sx={{ width: 300 }}
                                    />
                                </Box>

                                <DataGrid
                                    rows={patientsData?.data || []}
                                    columns={patientColumns}
                                    pageSize={10}
                                    autoHeight
                                    loading={loadingPatients}
                                    disableSelectionOnClick
                                />
                            </>
                        ) : activeTab === 1 ? (
                            <>
                                <Box sx={{ mb: 2 }}>
                                    <DatePicker
                                        label="Select Date"
                                        value={selectedDate}
                                        onChange={setSelectedDate}
                                        renderInput={(params) => <TextField {...params} />}
                                    />
                                </Box>

                                <DataGrid
                                    rows={visitationsData || []}
                                    columns={visitationColumns}
                                    pageSize={10}
                                    autoHeight
                                    loading={loadingVisitations}
                                    disableSelectionOnClick
                                />
                            </>
                        ) : (
                            <Box sx={{ mt: 2 }}>
                                <ChatList />
                            </Box>
                        )}
                    </Paper>
                </Grid>
            </Grid>

            {/* New Visitation Dialog */}
            <Dialog 
                open={openVisitationDialog} 
                onClose={() => setOpenVisitationDialog(false)}
                maxWidth="md"
                fullWidth
            >
                <DialogTitle>New Visitation</DialogTitle>
                <DialogContent>
                    <VisitationForm
                        patient={selectedPatient}
                        onSubmit={createVisitation.mutate}
                        onCancel={() => setOpenVisitationDialog(false)}
                    />
                </DialogContent>
            </Dialog>

            {/* Biodata Update Dialog */}
            <Dialog
                open={openBiodataDialog}
                onClose={() => setOpenBiodataDialog(false)}
                maxWidth="md"
                fullWidth
            >
                <DialogTitle>Update Patient Biodata</DialogTitle>
                <DialogContent>
                    <BiodataForm
                        patient={selectedPatient}
                        onSubmit={(biodata) => updateBiodata.mutate({
                            patientId: selectedPatient.id,
                            biodata
                        })}
                        onCancel={() => setOpenBiodataDialog(false)}
                    />
                </DialogContent>
            </Dialog>
        </Box>
    );
};

export default DoctorDashboard;

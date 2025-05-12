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
import { format, isValid, parseISO } from 'date-fns';
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
            .catch(error => {
                console.error('Error fetching patients:', error);
                return { data: [] }; // Return empty data structure to prevent errors
            })
    );

    // Safely format date for API request
    const getFormattedDate = (date) => {
        try {
            if (!date || !(date instanceof Date) || isNaN(date.getTime())) {
                console.warn('Invalid date provided, using current date');
                return format(new Date(), 'yyyy-MM-dd');
            }
            return format(date, 'yyyy-MM-dd');
        } catch (error) {
            console.error('Error formatting date:', error);
            return format(new Date(), 'yyyy-MM-dd');
        }
    };
    
    // Fetch visitations for selected date
    const { data: visitationsData, isLoading: loadingVisitations } = useQuery(
        ['visitations', getFormattedDate(selectedDate)],
        () => fetch(`/wp-json/hospital-manager/v1/doctor/visitations?date=${getFormattedDate(selectedDate)}`)
            .then(res => res.json())
            .then(data => {
                // Process response data to ensure valid dates
                if (Array.isArray(data)) {
                    return data.map(item => {
                        try {
                            // Ensure each item has an id
                            if (!item.id) {
                                item.id = `temp-${Math.random().toString(36).substr(2, 9)}`;
                            }
                            
                            // Completely sanitize time field to prevent invalid time errors
                            if (typeof item.time === 'string') {
                                // Strict format checking for time string (HH:MM or H:MM format)
                                const timeRegex = /^([0-1]?[0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/;
                                if (timeRegex.test(item.time)) {
                                    const timeParts = item.time.split(':');
                                    item.time = timeParts[0] + ':' + timeParts[1];
                                } else {
                                    console.warn('Invalid time format, using default:', item.time);
                                    item.time = '00:00'; // Use default for invalid format
                                }
                            } else if (item.time instanceof Date && !isNaN(item.time.getTime())) {
                                // If it's a valid Date object, format it correctly
                                item.time = format(item.time, 'HH:mm');
                            } else {
                                // For any other invalid type, use default
                                console.warn('Invalid time value type, using default');
                                item.time = '00:00';
                            }
                            
                            // Handle any date fields that might be present
                            if (item.date) {
                                try {
                                    if (typeof item.date === 'string') {
                                        const parsedDate = parseISO(item.date);
                                        if (isValid(parsedDate)) {
                                            item.date = parsedDate;
                                        } else {
                                            item.date = new Date();
                                        }
                                    } else if (!(item.date instanceof Date) || isNaN(item.date.getTime())) {
                                        item.date = new Date();
                                    }
                                } catch (dateError) {
                                    console.error('Error parsing date:', dateError);
                                    item.date = new Date();
                                }
                            }
                            
                            return item;
                        } catch (e) {
                            console.error('Error processing visitation item:', e, item);
                            return {
                                ...item,
                                id: item.id || `temp-${Math.random().toString(36).substr(2, 9)}`,
                                time: '00:00' // Fallback time
                            };
                        }
                    });
                }
                return [];
            })
            .catch(error => {
                console.error('Error fetching visitations:', error);
                return [];
            })
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
        { 
            field: 'time', 
            headerName: 'Time', 
            width: 100,
            // Enhanced time rendering with comprehensive error handling
            valueGetter: (params) => {
                try {
                    // If no value, return empty string
                    if (!params.value) return '';
                    
                    // Handle string values - most common case
                    if (typeof params.value === 'string') {
                        // Check if it's a valid time string format (HH:MM or H:MM)
                        const timeRegex = /^([0-1]?[0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/;
                        if (timeRegex.test(params.value)) {
                            return params.value;
                        }
                        
                        // Try to parse ISO string to date and format
                        try {
                            const date = new Date(params.value);
                            if (!isNaN(date.getTime())) {
                                return format(date, 'HH:mm');
                            }
                        } catch (e) {
                            console.warn('Failed to parse time string:', params.value);
                        }
                        
                        // Return as-is if nothing else works
                        return params.value;
                    }
                    
                    // Handle Date objects
                    if (params.value instanceof Date && !isNaN(params.value.getTime())) {
                        return format(params.value, 'HH:mm');
                    }
                    
                    // For any other type, convert to string
                    return String(params.value);
                } catch (error) {
                    console.error('Error formatting time value:', error);
                    return '00:00'; // Safe fallback
                }
            }
        },
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
                                        onChange={(newDate) => {
                                            try {
                                                // Validate the date before setting it
                                                if (newDate && !isNaN(new Date(newDate).getTime())) {
                                                    setSelectedDate(newDate);
                                                } else {
                                                    console.warn('Invalid date selected, keeping current date');
                                                }
                                            } catch (error) {
                                                console.error('Error setting date:', error);
                                            }
                                        }}
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

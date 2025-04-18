import React from 'react';
import { 
    Grid, 
    Paper, 
    Typography,
    Box,
    Button,
    Stack
} from '@mui/material';
import { DataGrid } from '@mui/x-data-grid';
import { useQuery } from 'react-query';

const LabTechDashboard = () => {
    const { data: labRequests, isLoading } = useQuery('labRequests', () =>
        fetch('/wp-json/hospital-manager/v1/lab-investigations/pending').then(res => res.json())
    );

    const columns = [
        { field: 'id', headerName: 'ID', width: 90 },
        { field: 'patientName', headerName: 'Patient', width: 200 },
        { field: 'requestedBy', headerName: 'Doctor', width: 200 },
        { field: 'requestDate', headerName: 'Date Requested', width: 150 },
        { field: 'status', headerName: 'Status', width: 120 },
        {
            field: 'actions',
            headerName: 'Actions',
            width: 200,
            renderCell: (params) => (
                <Button 
                    variant="contained"
                    size="small"
                    onClick={() => window.location.href = `/lab-results/${params.row.id}`}
                >
                    {params.row.status === 'PENDING' ? 'Add Results' : 'View Results'}
                </Button>
            )
        }
    ];

    if (isLoading) return <div>Loading...</div>;

    return (
        <Box sx={{ flexGrow: 1, p: 3 }}>
            <Grid container spacing={3}>
                <Grid item xs={12}>
                    <Paper sx={{ p: 2 }}>
                        <Typography variant="h6" gutterBottom>
                            Laboratory Investigations
                        </Typography>
                        <DataGrid
                            rows={labRequests || []}
                            columns={columns}
                            pageSize={10}
                            autoHeight
                            disableSelectionOnClick
                        />
                    </Paper>
                </Grid>
            </Grid>
        </Box>
    );
};

export default LabTechDashboard;

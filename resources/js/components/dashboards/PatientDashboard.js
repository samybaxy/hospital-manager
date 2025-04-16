import React from 'react';
import { useQuery } from 'react-query';
import { 
  Grid, 
  Paper, 
  Typography,
  List,
  ListItem,
  ListItemText 
} from '@mui/material';

const PatientDashboard = () => {
  const { data, isLoading } = useQuery('patientData', () => 
    fetch('/wp-json/hospital-manager/v1/dashboard')
      .then(res => res.json())
  );

  if (isLoading) return <div>Loading...</div>;

  return (
    <Grid container spacing={3}>
      <Grid item xs={12} md={6}>
        <Paper sx={{ p: 2 }}>
          <Typography variant="h6">Upcoming Appointments</Typography>
          <List>
            {data?.appointments?.map(appointment => (
              <ListItem key={appointment.id}>
                <ListItemText 
                  primary={appointment.doctor}
                  secondary={appointment.date}
                />
              </ListItem>
            ))}
          </List>
        </Paper>
      </Grid>
      {/* Add more dashboard widgets */}
    </Grid>
  );
};

export default PatientDashboard;
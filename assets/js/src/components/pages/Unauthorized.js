import React from 'react';
import { 
  Box, 
  Typography, 
  Paper, 
  Button,
  Container
} from '@mui/material';
import LockIcon from '@mui/icons-material/Lock';

const Unauthorized = () => {
  return (
    <Container maxWidth="sm">
      <Paper 
        elevation={3} 
        sx={{ 
          p: 4, 
          mt: 8, 
          display: 'flex', 
          flexDirection: 'column', 
          alignItems: 'center',
          textAlign: 'center'
        }}
      >
        <LockIcon color="error" sx={{ fontSize: 64, mb: 2 }} />
        
        <Typography variant="h4" component="h1" gutterBottom>
          Access Denied
        </Typography>
        
        <Typography variant="body1" paragraph color="text.secondary">
          You do not have permission to access this page. 
          Please contact your system administrator if you believe this is an error.
        </Typography>
        
        <Box sx={{ mt: 3 }}>
          <Button 
            variant="contained" 
            color="primary" 
            onClick={() => window.location.href = '/'}
          >
            Return to Dashboard
          </Button>
        </Box>
      </Paper>
    </Container>
  );
};

export default Unauthorized;

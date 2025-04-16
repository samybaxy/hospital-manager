import React from 'react';
import { List, ListItem, ListItemIcon, ListItemText } from '@mui/material';
import {
    People as PeopleIcon,
    LocalHospital as DoctorIcon,
    Event as AppointmentIcon,
    Dashboard as DashboardIcon
} from '@mui/icons-material';

const Navigation = ({ onNavigate }) => {
    const menuItems = [
        { text: 'Dashboard', icon: <DashboardIcon />, path: '/' },
        { text: 'Patients', icon: <PeopleIcon />, path: '/patients' },
        { text: 'Doctors', icon: <DoctorIcon />, path: '/doctors' },
        { text: 'Appointments', icon: <AppointmentIcon />, path: '/appointments' }
    ];

    return (
        <List>
            {menuItems.map((item) => (
                <ListItem
                    button
                    key={item.text}
                    onClick={() => onNavigate(item.path)}
                >
                    <ListItemIcon>{item.icon}</ListItemIcon>
                    <ListItemText primary={item.text} />
                </ListItem>
            ))}
        </List>
    );
};

export default Navigation;

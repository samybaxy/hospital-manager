import React, { useState } from 'react';
import { 
    Box,
    Drawer,
    AppBar,
    Toolbar,
    Typography,
    List,
    ListItem,
    ListItemIcon,
    ListItemText,
    IconButton,
    Divider,
    useTheme,
    useMediaQuery,
    Button,
    Badge
} from '@mui/material';
import NotificationCenter from '../notifications/NotificationCenter';
import {
    Menu as MenuIcon,
    Dashboard as DashboardIcon,
    Person as PersonIcon,
    LocalHospital as HospitalIcon,
    Science as ScienceIcon,
    History as HistoryIcon,
    ExitToApp as LogoutIcon,
    Assessment as AssessmentIcon
} from '@mui/icons-material';
import { useNavigate, useLocation, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

const drawerWidth = 240;

const getMenuItems = (role) => {
    const baseItems = [
        { text: 'Dashboard', icon: <DashboardIcon />, path: '/' }
    ];

    switch (role) {
        case 'doctor':
            return [
                ...baseItems,
                { text: 'Patients', icon: <PersonIcon />, path: '/patients' },
                { text: 'Visitations', icon: <HospitalIcon />, path: '/visitations' }
            ];
        case 'lab_tech':
            return [
                ...baseItems,
                { text: 'Lab Tests', icon: <ScienceIcon />, path: '/lab-tests' }
            ];
        case 'desk_officer':
            return [
                ...baseItems,
                { text: 'Patients', icon: <PersonIcon />, path: '/patients' },
                { text: 'Audit Log', icon: <HistoryIcon />, path: '/audit-log' }
            ];
        case 'administrator':
            return [
                ...baseItems,
                { text: 'Statistics', icon: <AssessmentIcon />, path: '/statistics' },
                { text: 'System Log', icon: <HistoryIcon />, path: '/system-log' }
            ];
        case 'patient':
            return [
                ...baseItems,
                { text: 'My Profile', icon: <PersonIcon />, path: '/profile' },
                { text: 'Medical History', icon: <HistoryIcon />, path: '/history' }
            ];
        default:
            return baseItems;
    }
};

const MainLayout = () => {
    const [mobileOpen, setMobileOpen] = useState(false);
    const theme = useTheme();
    const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
    const navigate = useNavigate();
    const location = useLocation();
    const { auth, logout } = useAuth();

    const handleDrawerToggle = () => {
        setMobileOpen(!mobileOpen);
    };

    const menuItems = getMenuItems(auth.role);

    const drawer = (
        <div>
            <Toolbar>
                <Typography variant="h6" noWrap>
                    Hospital Manager
                </Typography>
            </Toolbar>
            <Divider />
            <List>
                {menuItems.map((item) => (
                    <ListItem
                        button
                        key={item.text}
                        onClick={() => navigate(item.path)}
                        selected={location.pathname === item.path}
                    >
                        <ListItemIcon>{item.icon}</ListItemIcon>
                        <ListItemText primary={item.text} />
                    </ListItem>
                ))}
            </List>
            <Divider />
            <List>
                <ListItem button onClick={logout}>
                    <ListItemIcon><LogoutIcon /></ListItemIcon>
                    <ListItemText primary="Logout" />
                </ListItem>
            </List>
        </div>
    );

    return (
        <>
            <AppBar
                position="fixed"
                sx={{
                    width: { sm: `calc(100% - ${drawerWidth}px)` },
                    ml: { sm: `${drawerWidth}px` },
                }}
            >
                <Toolbar>
                    <IconButton
                        color="inherit"
                        aria-label="open drawer"
                        edge="start"
                        onClick={handleDrawerToggle}
                        sx={{ mr: 2, display: { sm: 'none' } }}
                    >
                        <MenuIcon />
                    </IconButton>
                    <Box sx={{ flexGrow: 1 }}>
                        <Typography variant="h6" noWrap component="div">
                            {auth.user?.name} - {auth.role?.replace('_', ' ').toUpperCase()}
                        </Typography>
                    </Box>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        <NotificationCenter />
                        <Button color="inherit" onClick={logout}>
                            Logout
                        </Button>
                    </Box>
                </Toolbar>
            </AppBar>

            <Box
                component="nav"
                sx={{ width: { sm: drawerWidth }, flexShrink: { sm: 0 } }}
            >
                <Drawer
                    variant={isMobile ? 'temporary' : 'permanent'}
                    open={isMobile ? mobileOpen : true}
                    onClose={handleDrawerToggle}
                    ModalProps={{
                        keepMounted: true // Better open performance on mobile.
                    }}
                    sx={{
                        '& .MuiDrawer-paper': {
                            boxSizing: 'border-box',
                            width: drawerWidth,
                        },
                    }}
                >
                    {drawer}
                </Drawer>
            </Box>

            <Box
                component="main"
                sx={{
                    flexGrow: 1,
                    p: 3,
                    width: { sm: `calc(100% - ${drawerWidth}px)` },
                    ml: { sm: `${drawerWidth}px` },
                    mt: ['48px', '56px', '64px']
                }}
            >
                <Outlet />
            </Box>
        </>
    );
};

export default MainLayout;

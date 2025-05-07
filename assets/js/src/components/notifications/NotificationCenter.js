import React, { useState, useEffect } from 'react';
import {
    Box,
    Badge,
    IconButton,
    Popover,
    List,
    ListItem,
    ListItemText,
    ListItemIcon,
    Typography,
    Divider,
    Button,
    CircularProgress
} from '@mui/material';
import {
    Notifications as NotificationsIcon,
    Science as ScienceIcon,
    Event as EventIcon,
    Message as MessageIcon,
    Check as CheckIcon
} from '@mui/icons-material';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { format, formatDistance } from 'date-fns';

const NotificationCenter = () => {
    const [anchorEl, setAnchorEl] = useState(null);
    const [eventSource, setEventSource] = useState(null);
    const queryClient = useQueryClient();

    // Get authentication status
    const isAuthenticated = localStorage.getItem('isAuthenticated') === 'true';

    // Fetch notifications only if authenticated
    const { data: notifications, isLoading } = useQuery(
        'notifications',
        () => fetch('/wp-json/hospital-manager/v1/notifications').then(res => {
            if (!res.ok) {
                throw new Error('Failed to fetch notifications');
            }
            return res.json();
        }),
        {
            enabled: isAuthenticated, // Only run query if authenticated
            retry: false // Don't retry if it fails
        }
    );

    // Get unread count only if authenticated
    const { data: unreadCount } = useQuery(
        'unreadNotifications',
        () => fetch('/wp-json/hospital-manager/v1/notifications/unread').then(res => {
            if (!res.ok) {
                throw new Error('Failed to fetch unread count');
            }
            return res.json();
        }),
        {
            enabled: isAuthenticated, // Only run query if authenticated
            retry: false // Don't retry if it fails
        }
    );

    // Mark as read mutation
    const markAsRead = useMutation(
        (notificationId) => fetch(`/wp-json/hospital-manager/v1/notifications/${notificationId}/read`, {
            method: 'PUT'
        }).then(res => res.json()),
        {
            onSuccess: () => {
                queryClient.invalidateQueries('notifications');
                queryClient.invalidateQueries('unreadNotifications');
            }
        }
    );

    // Setup SSE connection for real-time notifications - only when authenticated
    useEffect(() => {
        const isAuthenticated = localStorage.getItem('isAuthenticated') === 'true';
        
        // Only connect if authenticated
        if (!isAuthenticated) {
            return;
        }
        
        let sse;
        try {
            sse = new EventSource('/wp-json/hospital-manager/v1/ws/events');

            sse.onopen = () => {
                // Use debug level logging in production
                console.debug('NotificationCenter: SSE connection established');
            };
            
            sse.onerror = () => {
                // Use debug level logging in production
                console.debug('NotificationCenter: SSE connection error');
                sse.close();
            };

            sse.addEventListener('message', (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    // Handle different types of notifications
                    if (data.channel === 'lab_results') {
                        // Show lab results notification
                        queryClient.invalidateQueries('notifications');
                        queryClient.invalidateQueries('unreadNotifications');
                    } else if (data.channel === 'chat') {
                        // Handle new chat message
                        queryClient.invalidateQueries(['chatMessages', data.data.chat_id]);
                        queryClient.invalidateQueries('notifications');
                    } else if (data.channel === 'appointment') {
                        // Handle appointment updates
                        queryClient.invalidateQueries('appointments');
                        queryClient.invalidateQueries('notifications');
                    }
                } catch (error) {
                    // Use debug level logging in production
                    console.debug('NotificationCenter: Error parsing SSE message');
                }
            });

            setEventSource(sse);
            
            return () => {
                sse.close();
            };
        } catch (error) {
            // Use debug level logging in production
            console.debug('NotificationCenter: Error setting up EventSource');
        }
    }, [queryClient]);

    const getNotificationIcon = (type) => {
        switch (type) {
            case 'lab_results':
                return <ScienceIcon color="primary" />;
            case 'appointment':
                return <EventIcon color="secondary" />;
            case 'chat':
                return <MessageIcon color="info" />;
            default:
                return <NotificationsIcon />;
        }
    };

    const handleNotificationClick = (notification) => {
        markAsRead.mutate(notification.id);

        // Navigate based on notification type
        switch (notification.type) {
            case 'lab_results':
                window.location.href = `/lab-results/${notification.data.lab_result_id}`;
                break;
            case 'appointment':
                window.location.href = `/appointments/${notification.data.appointment_id}`;
                break;
            case 'chat':
                window.location.href = `/chats/${notification.data.chat_id}`;
                break;
        }

        setAnchorEl(null);
    };

    return (
        <>
            <IconButton 
                color="inherit" 
                onClick={(e) => setAnchorEl(e.currentTarget)}
            >
                <Badge 
                    badgeContent={unreadCount?.unread_count || 0} 
                    color="error"
                >
                    <NotificationsIcon />
                </Badge>
            </IconButton>

            <Popover
                open={Boolean(anchorEl)}
                anchorEl={anchorEl}
                onClose={() => setAnchorEl(null)}
                anchorOrigin={{
                    vertical: 'bottom',
                    horizontal: 'right',
                }}
                transformOrigin={{
                    vertical: 'top',
                    horizontal: 'right',
                }}
                PaperProps={{
                    sx: { width: 360, maxHeight: 400 }
                }}
            >
                <Box sx={{ p: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Typography variant="h6">Notifications</Typography>
                    {unreadCount?.unread_count > 0 && (
                        <Button 
                            size="small"
                            startIcon={<CheckIcon />}
                            onClick={() => markAsRead.mutate('all')}
                        >
                            Mark all as read
                        </Button>
                    )}
                </Box>
                <Divider />

                {isLoading ? (
                    <Box sx={{ p: 3, display: 'flex', justifyContent: 'center' }}>
                        <CircularProgress />
                    </Box>
                ) : (
                    <List sx={{ p: 0 }}>
                        {notifications && Array.isArray(notifications.data) ? (
                            notifications.data.map((notification) => (
                                <React.Fragment key={notification.id}>
                                    <ListItem 
                                        button
                                        onClick={() => handleNotificationClick(notification)}
                                        sx={{
                                            backgroundColor: notification.read ? 'inherit' : 'action.hover'
                                        }}
                                    >
                                        <ListItemIcon>
                                            {getNotificationIcon(notification.type)}
                                        </ListItemIcon>
                                    <ListItemText
                                        primary={notification.title}
                                        secondary={
                                            <>
                                                <Typography
                                                    component="span"
                                                    variant="body2"
                                                    color="textSecondary"
                                                    display="block"
                                                >
                                                    {notification.message}
                                                </Typography>
                                                <Typography
                                                    component="span"
                                                    variant="caption"
                                                    color="textSecondary"
                                                >
                                                    {formatDistance(
                                                        new Date(notification.created_at),
                                                        new Date(),
                                                        { addSuffix: true }
                                                    )}
                                                </Typography>
                                            </>
                                        }
                                    />
                                </ListItem>
                                <Divider />
                            </React.Fragment>
                        ))
                        ) : (
                            <ListItem>
                                <ListItemText
                                    secondary="No notifications available"
                                    sx={{ textAlign: 'center' }}
                                />
                            </ListItem>
                        )}
                    </List>
                )}
            </Popover>
        </>
    );
};

export default NotificationCenter;

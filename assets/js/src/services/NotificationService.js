import React from 'react';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { useWebSocket } from './WebSocketService';

export const NotificationProvider = ({ children }) => {
    const handleNotification = (data) => {
        switch (data.channel) {
            case 'lab_results':
                toast.info('New lab results are available', {
                    onClick: () => window.location.href = `/lab-results/${data.data.lab_result_id}`
                });
                break;
            case 'chat':
                const sender = data.data.sender_name;
                toast.info(`New message from ${sender}`, {
                    onClick: () => window.location.href = `/chats/${data.data.chat_id}`
                });
                break;
            case 'appointment':
                toast.info(`Appointment ${data.data.status}: ${data.data.message}`, {
                    onClick: () => window.location.href = `/appointments/${data.data.appointment_id}`
                });
                break;
            default:
                if (data.title && data.message) {
                    toast.info(data.message, { autoClose: 5000 });
                }
        }
    };

    // Subscribe to all notification channels
    useWebSocket('notification', handleNotification);

    return (
        <>
            {children}
            <ToastContainer
                position="top-right"
                autoClose={5000}
                hideProgressBar={false}
                newestOnTop
                closeOnClick
                rtl={false}
                pauseOnFocusLoss
                draggable
                pauseOnHover
                theme="light"
            />
        </>
    );
};

export const showNotification = (type, message, options = {}) => {
    switch (type) {
        case 'success':
            toast.success(message, options);
            break;
        case 'error':
            toast.error(message, options);
            break;
        case 'warning':
            toast.warning(message, options);
            break;
        default:
            toast.info(message, options);
    }
};

export default NotificationProvider;

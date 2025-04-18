import React, { useState, useEffect, useRef } from 'react';
import {
    Box,
    Paper,
    Typography,
    TextField,
    IconButton,
    List,
    ListItem,
    ListItemText,
    ListItemAvatar,
    Avatar,
    Badge,
    Divider,
    Button,
    CircularProgress
} from '@mui/material';
import {
    Send as SendIcon,
    Person as PersonIcon
} from '@mui/icons-material';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { format } from 'date-fns';

const ChatWindow = ({ chatId, onClose }) => {
    const [message, setMessage] = useState('');
    const messagesEndRef = useRef(null);
    const queryClient = useQueryClient();

    // Fetch messages
    const { data: messages, isLoading } = useQuery(
        ['chatMessages', chatId],
        () => fetch(`/wp-json/hospital-manager/v1/chats/${chatId}/messages`).then(res => res.json()),
        {
            refetchInterval: 3000 // Poll every 3 seconds for new messages
        }
    );

    // Send message mutation
    const sendMessage = useMutation(
        (newMessage) => fetch(`/wp-json/hospital-manager/v1/chats/${chatId}/messages`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: newMessage })
        }).then(res => res.json()),
        {
            onSuccess: () => {
                queryClient.invalidateQueries(['chatMessages', chatId]);
                setMessage('');
            }
        }
    );

    // Mark messages as read
    useEffect(() => {
        if (chatId) {
            fetch(`/wp-json/hospital-manager/v1/chats/${chatId}/read`, {
                method: 'PUT'
            });
        }
    }, [chatId, messages]);

    // Auto scroll to bottom on new messages
    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(scrollToBottom, [messages]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (message.trim()) {
            sendMessage.mutate(message);
        }
    };

    if (isLoading) {
        return <CircularProgress />;
    }

    return (
        <Paper sx={{ height: '500px', display: 'flex', flexDirection: 'column' }}>
            {/* Messages Area */}
            <Box sx={{ flexGrow: 1, overflow: 'auto', p: 2 }}>
                {messages?.data.map((msg) => (
                    <Box
                        key={msg.id}
                        sx={{
                            display: 'flex',
                            justifyContent: msg.isSender ? 'flex-end' : 'flex-start',
                            mb: 2
                        }}
                    >
                        <Paper
                            sx={{
                                p: 1,
                                backgroundColor: msg.isSender ? 'primary.main' : 'grey.100',
                                color: msg.isSender ? 'white' : 'text.primary',
                                maxWidth: '70%'
                            }}
                        >
                            <Typography variant="body1">{msg.message}</Typography>
                            <Typography variant="caption" display="block" sx={{ mt: 0.5 }}>
                                {format(new Date(msg.created_at), 'p')}
                            </Typography>
                        </Paper>
                    </Box>
                ))}
                <div ref={messagesEndRef} />
            </Box>

            {/* Message Input */}
            <Box sx={{ p: 2, backgroundColor: 'background.paper' }}>
                <form onSubmit={handleSubmit} style={{ display: 'flex', gap: 8 }}>
                    <TextField
                        fullWidth
                        size="small"
                        placeholder="Type a message..."
                        value={message}
                        onChange={(e) => setMessage(e.target.value)}
                    />
                    <IconButton type="submit" color="primary">
                        <SendIcon />
                    </IconButton>
                </form>
            </Box>
        </Paper>
    );
};

const ChatList = () => {
    const [selectedChat, setSelectedChat] = useState(null);

    // Fetch chats
    const { data: chats, isLoading } = useQuery(
        'chats',
        () => fetch('/wp-json/hospital-manager/v1/chats').then(res => res.json()),
        {
            refetchInterval: 5000 // Poll every 5 seconds for updates
        }
    );

    if (isLoading) {
        return <CircularProgress />;
    }

    return (
        <Box sx={{ display: 'flex', gap: 2 }}>
            {/* Chat List */}
            <Paper sx={{ width: 300, height: '500px', overflow: 'auto' }}>
                <List>
                    {chats?.map((chat) => (
                        <React.Fragment key={chat.id}>
                            <ListItem
                                button
                                selected={selectedChat?.id === chat.id}
                                onClick={() => setSelectedChat(chat)}
                            >
                                <ListItemAvatar>
                                    <Badge
                                        badgeContent={chat.unread_count}
                                        color="error"
                                        invisible={!chat.unread_count}
                                    >
                                        <Avatar>
                                            <PersonIcon />
                                        </Avatar>
                                    </Badge>
                                </ListItemAvatar>
                                <ListItemText
                                    primary={chat.doctor_name || chat.patient_name}
                                    secondary={format(new Date(chat.last_message_at), 'PP p')}
                                />
                            </ListItem>
                            <Divider />
                        </React.Fragment>
                    ))}
                </List>
            </Paper>

            {/* Chat Window */}
            {selectedChat ? (
                <ChatWindow
                    chatId={selectedChat.id}
                    onClose={() => setSelectedChat(null)}
                />
            ) : (
                <Paper
                    sx={{
                        flexGrow: 1,
                        height: '500px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center'
                    }}
                >
                    <Typography color="textSecondary">
                        Select a chat to start messaging
                    </Typography>
                </Paper>
            )}
        </Box>
    );
};

export default ChatList;

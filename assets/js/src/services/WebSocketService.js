import { useEffect, useCallback } from 'react';
import { useQueryClient } from 'react-query';

class WebSocketService {
    static instance = null;
    static eventSource = null;
    static listeners = new Map();
    static reconnectTimeout = null;
    static isConnecting = false;

    static getInstance() {
        if (!WebSocketService.instance) {
            WebSocketService.instance = new WebSocketService();
        }
        return WebSocketService.instance;
    }

    connect() {
        // Check if user is authenticated before trying to connect
        if (localStorage.getItem('isAuthenticated') !== 'true') {
            return; // Don't attempt to connect if not authenticated
        }
        
        if (WebSocketService.eventSource || WebSocketService.isConnecting) {
            return;
        }

        WebSocketService.isConnecting = true;
        try {
            WebSocketService.eventSource = new EventSource('/wp-json/hospital-manager/v1/ws/events');

            WebSocketService.eventSource.onopen = () => {
                // Use debug level logging in production
                console.debug('SSE connection established');
                WebSocketService.isConnecting = false;
                if (WebSocketService.reconnectTimeout) {
                    clearTimeout(WebSocketService.reconnectTimeout);
                    WebSocketService.reconnectTimeout = null;
                }
            };

            WebSocketService.eventSource.onerror = (event) => {
                // Use debug level logging in production
                console.debug('SSE connection error');
                this.disconnect();
                
                // Only reconnect if user is still authenticated
                if (localStorage.getItem('isAuthenticated') === 'true' && !WebSocketService.reconnectTimeout) {
                    WebSocketService.reconnectTimeout = setTimeout(() => {
                        this.connect();
                    }, 5000); // Reconnect after 5 seconds
                }
            };
        } catch (error) {
            // Use debug level logging in production
            console.debug('Failed to establish SSE connection');
            WebSocketService.isConnecting = false;
            
            // Only reconnect if user is still authenticated
            if (localStorage.getItem('isAuthenticated') === 'true' && !WebSocketService.reconnectTimeout) {
                WebSocketService.reconnectTimeout = setTimeout(() => {
                    this.connect();
                }, 5000);
            }
        }

        WebSocketService.eventSource.addEventListener('message', (event) => {
            try {
                const data = JSON.parse(event.data);
                this.notifyListeners(data);
            } catch (error) {
                console.error('Error parsing SSE message:', error);
            }
        });
    }

    disconnect() {
        if (WebSocketService.eventSource) {
            WebSocketService.eventSource.close();
            WebSocketService.eventSource = null;
        }
        WebSocketService.isConnecting = false;
    }

    subscribe(channel, callback) {
        if (!WebSocketService.listeners.has(channel)) {
            WebSocketService.listeners.set(channel, new Set());
        }
        WebSocketService.listeners.get(channel).add(callback);

        // Connect if not already connected
        this.connect();

        // Return unsubscribe function
        return () => {
            const listeners = WebSocketService.listeners.get(channel);
            if (listeners) {
                listeners.delete(callback);
                if (listeners.size === 0) {
                    WebSocketService.listeners.delete(channel);
                }
            }
        };
    }

    notifyListeners(data) {
        const { channel } = data;
        const listeners = WebSocketService.listeners.get(channel);
        if (listeners) {
            listeners.forEach(callback => callback(data));
        }
    }
}

export const useWebSocket = (channel, callback) => {
    const ws = WebSocketService.getInstance();
    
    // Get authentication status from wherever it's stored
    const isAuthenticated = localStorage.getItem('isAuthenticated') === 'true';
    
    useEffect(() => {
        // Only subscribe if authenticated
        if (isAuthenticated) {
            const unsubscribe = ws.subscribe(channel, callback);
            return () => unsubscribe();
        }
    }, [channel, callback, isAuthenticated]);

    return ws;
};

export const useWebSocketWithQueryInvalidation = (channel, queryKeys) => {
    const queryClient = useQueryClient();

    const handleMessage = useCallback((data) => {
        // Invalidate relevant queries based on the message
        if (Array.isArray(queryKeys)) {
            queryKeys.forEach(key => queryClient.invalidateQueries(key));
        } else {
            queryClient.invalidateQueries(queryKeys);
        }
    }, [queryClient, queryKeys]);

    useWebSocket(channel, handleMessage);
};

export default WebSocketService;

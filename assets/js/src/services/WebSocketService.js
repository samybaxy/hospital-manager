import { useEffect, useCallback } from 'react';
import { useQueryClient } from 'react-query';
import ApiService from './ApiService';

class WebSocketService {
    static instance = null;
    static eventSource = null;
    static listeners = new Map();
    static reconnectTimeout = null;
    static isConnecting = false;
    static usePolling = false;
    static pollingInterval = null;
    
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

        // First try to use EventSource (Server-Sent Events)
        if (!WebSocketService.usePolling && typeof EventSource !== 'undefined') {
            try {
                // Create the URL with the nonce as a query parameter for authentication
                const nonce = window.hospitalManagerData?.nonce || '';
                const apiUrl = window.hospitalManagerData?.apiUrl || '/wp-json/hospital-manager/v1';
                const url = new URL(`${apiUrl}/ws/events`, window.location.origin);
                url.searchParams.append('_wpnonce', nonce);
                
                // Create the EventSource with withCredentials to include cookies
                WebSocketService.eventSource = new EventSource(url.toString(), { 
                    withCredentials: true
                });

                WebSocketService.eventSource.onopen = () => {
                    console.debug('SSE connection established');
                    WebSocketService.isConnecting = false;
                    if (WebSocketService.reconnectTimeout) {
                        clearTimeout(WebSocketService.reconnectTimeout);
                        WebSocketService.reconnectTimeout = null;
                    }
                };

                WebSocketService.eventSource.onerror = (event) => {
                    console.debug('SSE connection error');
                    this.disconnect();
                    
                    // Fall back to polling after SSE failure
                    WebSocketService.usePolling = true;
                    
                    // Only reconnect if user is still authenticated
                    if (localStorage.getItem('isAuthenticated') === 'true' && !WebSocketService.reconnectTimeout) {
                        WebSocketService.reconnectTimeout = setTimeout(() => {
                            this.connect(); // This will now use polling
                        }, 5000); // Reconnect after 5 seconds
                    }
                };

                WebSocketService.eventSource.addEventListener('message', (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        this.notifyListeners(data);
                    } catch (error) {
                        console.error('Error parsing SSE message:', error);
                    }
                });
            } catch (error) {
                // Use debug level logging in production
                console.debug('Failed to establish SSE connection');
                WebSocketService.isConnecting = false;
                
                // Fall back to polling
                WebSocketService.usePolling = true;
                this.startPolling();
            }
        } else {
            // Use polling as fallback
            this.startPolling();
        }
    }
    
    /**
     * Start polling for events as a fallback when SSE is not available
     */
    startPolling() {
        console.debug('Starting polling for WebSocket events');
        WebSocketService.isConnecting = false;
        
        // Clear any existing polling interval
        if (WebSocketService.pollingInterval) {
            clearInterval(WebSocketService.pollingInterval);
        }
        
        // Start polling immediately and then at regular intervals
        this.pollEvents();
        WebSocketService.pollingInterval = setInterval(() => {
            this.pollEvents();
        }, 5000); // Poll every 5 seconds
    }
    
    /**
     * Poll the server for new events
     */
    async pollEvents() {
        if (localStorage.getItem('isAuthenticated') !== 'true') {
            this.stopPolling();
            return;
        }
        
        try {
            const response = await ApiService.get('/ws/poll');
            
            if (response && response.messages && Array.isArray(response.messages)) {
                response.messages.forEach(message => {
                    this.notifyListeners(message);
                });
            }
        } catch (error) {
            console.debug('Error polling for events:', error);
        }
    }
    
    /**
     * Stop the polling interval
     */
    stopPolling() {
        if (WebSocketService.pollingInterval) {
            clearInterval(WebSocketService.pollingInterval);
            WebSocketService.pollingInterval = null;
        }
    }

    disconnect() {
        if (WebSocketService.eventSource) {
            WebSocketService.eventSource.close();
            WebSocketService.eventSource = null;
        }
        
        this.stopPolling();
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

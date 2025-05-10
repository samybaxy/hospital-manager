/**
 * WebSocket fallback handler for browsers that don't support Server-Sent Events
 */

export class WebSocketFallback {
  constructor() {
    this.baseUrl = '/wp-json/hospital-manager/v1';
    this.eventHandlers = {};
    this.connected = false;
    this.pollingInterval = 5000; // 5 seconds
    this.pollingIntervalId = null;
    this.sseSupported = typeof EventSource !== 'undefined';
    this.eventSource = null;
  }

  /**
   * Connect to the real-time events
   */
  connect() {
    if (this.sseSupported) {
      try {
        this.connectSSE();
      } catch (error) {
        console.warn('Failed to connect using SSE, falling back to polling', error);
        this.startPolling();
      }
    } else {
      console.log('SSE not supported by browser, using polling fallback');
      this.startPolling();
    }
  }

  /**
   * Connect using Server-Sent Events
   */
  connectSSE() {
    // Close existing connection if any
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }

    // Create a new EventSource connection
    const url = `${this.baseUrl}/ws/events`;
    this.eventSource = new EventSource(url);
    
    this.eventSource.onopen = () => {
      this.connected = true;
      this.triggerEvent('connection', { status: 'connected' });
    };
    
    this.eventSource.onerror = (error) => {
      console.error('SSE connection error:', error);
      this.eventSource.close();
      this.eventSource = null;
      this.connected = false;
      this.triggerEvent('error', { error });
      
      // Fallback to polling when SSE connection fails
      this.startPolling();
    };
    
    this.eventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        this.triggerEvent('message', data);
      } catch (error) {
        console.error('Error parsing SSE message:', error);
      }
    };
  }

  /**
   * Start polling as a fallback when SSE is not available
   */
  startPolling() {
    // Clear any existing polling
    if (this.pollingIntervalId) {
      clearInterval(this.pollingIntervalId);
    }
    
    // Set connected status and trigger connection event
    this.connected = true;
    this.triggerEvent('connection', { status: 'connected_polling' });
    
    // Immediately do first poll
    this.poll();
    
    // Set up regular polling
    this.pollingIntervalId = setInterval(() => this.poll(), this.pollingInterval);
  }

  /**
   * Poll the server for new messages
   */
  async poll() {
    try {
      const url = `${this.baseUrl}/ws/poll`;
      const response = await fetch(url);
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.messages && Array.isArray(data.messages)) {
        // Process each message
        data.messages.forEach(message => {
          this.triggerEvent('message', message);
        });
      }
    } catch (error) {
      console.error('Polling error:', error);
      this.triggerEvent('error', { error });
    }
  }

  /**
   * Register an event handler
   * @param {string} event - The event name (connection, message, error)
   * @param {Function} callback - The callback function
   */
  on(event, callback) {
    if (!this.eventHandlers[event]) {
      this.eventHandlers[event] = [];
    }
    this.eventHandlers[event].push(callback);
  }

  /**
   * Trigger an event
   * @param {string} event - The event name
   * @param {Object} data - The event data
   */
  triggerEvent(event, data) {
    if (this.eventHandlers[event]) {
      this.eventHandlers[event].forEach(callback => callback(data));
    }
  }

  /**
   * Disconnect from the real-time events
   */
  disconnect() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
    
    if (this.pollingIntervalId) {
      clearInterval(this.pollingIntervalId);
      this.pollingIntervalId = null;
    }
    
    this.connected = false;
  }
}

// Create a singleton instance
const wsClient = new WebSocketFallback();
export default wsClient;

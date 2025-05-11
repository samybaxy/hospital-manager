// ApiService.js - Centralized API communication with proper authentication handling

/**
 * ApiService handles all API requests with proper authentication
 */
class ApiService {
    constructor() {
        // Get the API URL and nonce from the localized script data
        this.apiUrl = window.hospitalManagerData?.apiUrl || '/wp-json/hospital-manager/v1';
        this.nonce = window.hospitalManagerData?.nonce || '';
        
        // Always try to ensure we have a valid nonce when the service is initialized
        if (!this.nonce) {
            console.log('No nonce found in window.hospitalManagerData, attempting to get one');
            // Don't await this - let it run in the background
            this.refreshNonce().catch(err => {
                console.warn('Initial nonce refresh failed:', err);
            });
        } else {
            console.log('Nonce found in window.hospitalManagerData');
        }
    }

    /**
     * Get the current WordPress nonce for API authentication
     * 
     * @returns {string} The current nonce
     */
    getNonce() {
        // First try to get it from the localized data provided by WordPress
        if (window.hospitalManagerData?.nonce) {
            // Store the nonce in memory for later use
            this.nonce = window.hospitalManagerData.nonce;
            localStorage.setItem('hm_nonce', this.nonce);
            return this.nonce;
        }
        
        // If no nonce is available in localized data, try localStorage
        const storedNonce = localStorage.getItem('hm_nonce');
        if (storedNonce) {
            this.nonce = storedNonce;
            return storedNonce;
        }
        
        // If no nonce is available, attempt to fetch a fresh nonce from the server
        this.refreshNonce();
        
        // Return the current nonce (might be empty, but will be updated async)
        return this.nonce;
    }
    
    /**
     * Refresh the WordPress nonce by making a request to the debug endpoint
     * This is a fallback for when the nonce is missing or expired
     * 
     * @returns {Promise<string>} A promise that resolves with the new nonce
     */
    async refreshNonce() {
        try {
            // Make a request to the debug endpoint without a nonce
            // This endpoint should return a fresh nonce in the header
            const response = await fetch(`${this.apiUrl}/auth/debug`, {
                method: 'GET',
                credentials: 'include', // Important for keeping cookies
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            // Check if we received a refreshed nonce in the headers
            const refreshedNonce = response.headers.get('X-WP-Nonce');
            if (refreshedNonce) {
                console.log('Received new nonce from headers');
                this.storeNonce(refreshedNonce);
                return refreshedNonce;
            }
            
            // If not in headers, try to get from response body
            try {
                const data = await response.json();
                if (data && data.fresh_nonce) {
                    console.log('Received new nonce from response body');
                    this.storeNonce(data.fresh_nonce);
                    return data.fresh_nonce;
                }
                
                // If we have a debug endpoint response but no nonce, log it
                console.debug('Debug endpoint response:', data);
            } catch (e) {
                console.warn('Failed to parse debug response:', e);
            }
        } catch (error) {
            console.warn('Failed to refresh nonce:', error);
        }
        
        // If we still don't have a nonce, try to create one with a regular WP admin-ajax request
        try {
            const ajaxResponse = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await ajaxResponse.json();
            if (data && data.nonce) {
                console.log('Received new nonce from admin-ajax');
                this.storeNonce(data.nonce);
                return data.nonce;
            }
        } catch (error) {
            console.warn('Failed to get nonce from admin-ajax:', error);
        }
        
        return this.nonce;
    }
    
    /**
     * Store a new nonce received from the server
     * 
     * @param {string} nonce The new nonce to store
     */
    storeNonce(nonce) {
        if (nonce) {
            this.nonce = nonce;
            localStorage.setItem('hm_nonce', nonce);
        }
    }

    /**
     * Make an authenticated API request
     * 
     * @param {string} endpoint - The API endpoint (without the base URL)
     * @param {Object} options - Request options (method, body, etc.)
     * @param {boolean} isRetry - Whether this is a retry attempt after refreshing nonce
     * @returns {Promise} - The fetch promise
     */
    async request(endpoint, options = {}, isRetry = false) {
        const url = endpoint.startsWith('http') ? endpoint : `${this.apiUrl}${endpoint}`;
        
        // Get the nonce from hospitalManagerData or from localStorage if available
        const nonce = this.getNonce();
        
        // Default headers with nonce authentication
        const headers = {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        
        // Merge with any custom headers
        if (options.headers) {
            Object.assign(headers, options.headers);
        }
        
        // Prepare the request options
        const requestOptions = {
            ...options,
            headers,
            credentials: 'include' // Important! Include cookies for WordPress authentication
        };
        
        // If body is an object, stringify it
        if (options.body && typeof options.body === 'object') {
            requestOptions.body = JSON.stringify(options.body);
        }
        
        try {
            const response = await fetch(url, requestOptions);
            
            // Check if we received a refreshed nonce in the headers
            const refreshedNonce = response.headers.get('X-WP-Nonce');
            if (refreshedNonce) {
                this.storeNonce(refreshedNonce);
            }
            
            // For 204 No Content, just return success true
            if (response.status === 204) {
                return { success: true };
            }
            
            // Try to parse JSON response
            let data;
            try {
                data = await response.json();
            } catch (e) {
                // If response is not JSON, return the raw response
                return { 
                    success: response.ok,
                    status: response.status,
                    statusText: response.statusText
                };
            }
            
            // If the response indicates a nonce or cookie error, try to refresh and retry
            if (!isRetry && (response.status === 401 || response.status === 403) && 
                (data?.code === 'rest_cookie_invalid_nonce' || data?.message === 'Cookie check failed')) {
                console.log('Cookie check failed. Refreshing nonce and retrying request...');
                
                // Refresh the nonce
                const newNonce = await this.refreshNonce();
                console.log('New nonce obtained:', newNonce ? 'yes' : 'no');
                
                // Short delay to ensure nonce is registered on server
                await new Promise(resolve => setTimeout(resolve, 300));
                
                // Retry the request once with the new nonce
                return this.request(endpoint, options, true);
            }
            
            // Check if there's a fresh nonce in the response body
            if (data && data.fresh_nonce) {
                this.storeNonce(data.fresh_nonce);
            }
            
            // If the response is not OK, throw an error
            if (!response.ok) {
                throw new Error(data.message || `API request failed: ${response.status} ${response.statusText}`);
            }
            
            return data;
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            
            // If there's a network error and this isn't a retry, try refreshing the nonce
            if (!isRetry && error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                console.log('Network error. Refreshing nonce and retrying request...');
                
                // Refresh the nonce
                await this.refreshNonce();
                
                // Retry the request once with the new nonce
                return this.request(endpoint, options, true);
            }
            
            throw error;
        }
    }
    
    // Convenience methods for different HTTP methods
    
    /**
     * Make a GET request
     * 
     * @param {string} endpoint - The API endpoint
     * @param {Object} queryParams - Query parameters
     * @returns {Promise} - The fetch promise
     */
    async get(endpoint, queryParams = {}) {
        const url = new URL(endpoint.startsWith('http') ? endpoint : `${this.apiUrl}${endpoint}`, window.location.origin);
        
        // Add query parameters
        Object.keys(queryParams).forEach(key => {
            if (queryParams[key] !== undefined && queryParams[key] !== null) {
                url.searchParams.append(key, queryParams[key]);
            }
        });
        
        return this.request(url.toString(), { method: 'GET' });
    }
    
    /**
     * Make a POST request
     * 
     * @param {string} endpoint - The API endpoint
     * @param {Object} data - The data to send
     * @returns {Promise} - The fetch promise
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, { 
            method: 'POST',
            body: data
        });
    }
    
    /**
     * Make a PUT request
     * 
     * @param {string} endpoint - The API endpoint
     * @param {Object} data - The data to send
     * @returns {Promise} - The fetch promise
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: data
        });
    }
    
    /**
     * Make a DELETE request
     * 
     * @param {string} endpoint - The API endpoint
     * @returns {Promise} - The fetch promise
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
}

// Export a singleton instance of the API service
export default new ApiService();

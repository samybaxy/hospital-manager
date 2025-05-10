// ApiService.js - Centralized API communication with proper authentication handling

/**
 * ApiService handles all API requests with proper authentication
 */
class ApiService {
    constructor() {
        // Get the API URL and nonce from the localized script data
        this.apiUrl = window.hospitalManagerData?.apiUrl || '/wp-json/hospital-manager/v1';
        this.nonce = window.hospitalManagerData?.nonce || '';
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
            return this.nonce;
        }
        
        // If no nonce is available in localized data, try localStorage
        const storedNonce = localStorage.getItem('hm_nonce');
        if (storedNonce) {
            this.nonce = storedNonce;
            return storedNonce;
        }
        
        // If no nonce is available, return the current one (might be empty)
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
     * @returns {Promise} - The fetch promise
     */
    async request(endpoint, options = {}) {
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
            
            // If the response is not OK, throw an error
            if (!response.ok) {
                throw new Error(data.message || `API request failed: ${response.status} ${response.statusText}`);
            }
            
            return data;
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
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

import React, { createContext, useContext, useState, useEffect } from 'react';

export const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [auth, setAuth] = useState({
        isAuthenticated: false,
        user: null,
        role: null,
        loading: true
    });

    useEffect(() => {
        // Check if user is likely authenticated using localStorage first
        const wasAuthenticated = localStorage.getItem('isAuthenticated') === 'true';
        
        // Set initial state based on localStorage to avoid flash of login screen
        if (wasAuthenticated) {
            setAuth(prevAuth => ({ 
                ...prevAuth, 
                isAuthenticated: true,
                // Keep loading true until we verify with server
            }));
        }
        
        // Import our API service dynamically to avoid circular dependency
        import('../services/ApiService').then(module => {
            const ApiService = module.default;
            
            // Set up function to check authentication
            const checkAuth = async () => {
                try {
                    console.log('Checking authentication status...');
                    // Create a controller to potentially abort the request if it takes too long
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 8000); // 8 second timeout
                    
                    // Use the ApiService which ensures proper authentication handling
                    const response = await ApiService.get('/auth/me');
                    
                    clearTimeout(timeoutId);
                    console.log('Auth check response:', response);
                    
                    if (response) {
                        if (response.authenticated) {
                            console.log('User is authenticated:', response.user);
                            // Store authentication state in localStorage for WebSocketService
                            localStorage.setItem('isAuthenticated', 'true');
                            // Store user info in localStorage for quick access during page loads
                            localStorage.setItem('user', JSON.stringify(response.user));
                            localStorage.setItem('role', response.role);
                            
                            setAuth({
                                isAuthenticated: true,
                                user: response.user,
                                role: response.role,
                                loading: false
                            });
                        } else {
                            console.log('User is not authenticated');
                            // User is not authenticated
                            localStorage.removeItem('isAuthenticated');
                            localStorage.removeItem('user');
                            localStorage.removeItem('role');
                            
                            setAuth({
                                isAuthenticated: false,
                                user: null,
                                role: null,
                                loading: false
                            });
                        }
                    } else {
                        // Handle unexpected error status codes
                        localStorage.removeItem('isAuthenticated');
                        
                        setAuth({
                            isAuthenticated: false,
                            user: null,
                            role: null,
                            loading: false
                        });
                    }
                } catch (error) {
                    // Only log unexpected errors
                    if (error.name !== 'AbortError') {
                        // Keep this silent in production
                        console.debug('Auth check error:', error);
                    }
                    
                    // Set not authenticated state on error
                    localStorage.removeItem('isAuthenticated');
                    setAuth({
                        isAuthenticated: false,
                        user: null,
                        role: null,
                        loading: false
                    });
                }
            };
            
            // Execute the auth check function
            checkAuth()
                .catch(error => {
                    console.error('Unexpected error during auth check:', error);
                    // Ensure we handle any top-level async errors
                    localStorage.removeItem('isAuthenticated');
                    setAuth({
                        isAuthenticated: false,
                        user: null,
                        role: null,
                        loading: false
                    });
                });
        }).catch(error => {
            console.error('Failed to import ApiService:', error);
            // Set not authenticated state on error
            localStorage.removeItem('isAuthenticated');
            setAuth({
                isAuthenticated: false,
                user: null,
                role: null,
                loading: false
            });
        });
        
        // Don't call checkAuth here, it's already called inside the promise above
    }, []);

    const login = async (credentials) => {
        try {
            // Import ApiService dynamically to avoid circular dependency
            const { default: ApiService } = await import('../services/ApiService');
            
            console.log('Logging in with credentials:', credentials.username);
            const data = await ApiService.post('/auth/login', credentials);
            console.log('Login response:', data);
            
            if (data && data.user) {
                // Store authentication state and user info in localStorage
                localStorage.setItem('isAuthenticated', 'true');
                localStorage.setItem('user', JSON.stringify(data.user));
                localStorage.setItem('role', data.role || '');
                
                // Update auth context state
                setAuth({
                    isAuthenticated: true,
                    user: data.user,
                    role: data.role,
                    loading: false
                });
                
                // Store the fresh nonce if available
                if (data.fresh_nonce) {
                    ApiService.storeNonce(data.fresh_nonce);
                }
                
                return data;
            } else {
                throw new Error('Invalid login response format');
            }
        } catch (error) {
            console.error('Login failed:', error);
            // Clear any partial authentication data
            localStorage.removeItem('isAuthenticated');
            localStorage.removeItem('user');
            localStorage.removeItem('role');
            
            throw error;
        }
    };

    const logout = async () => {
        try {
            // Import ApiService dynamically to avoid circular dependency
            const { default: ApiService } = await import('../services/ApiService');
            
            await ApiService.post('/auth/logout');
            
            // Remove authentication state from localStorage
            localStorage.removeItem('isAuthenticated');
            
            setAuth({
                isAuthenticated: false,
                user: null,
                role: null,
                loading: false
            });
        } catch (error) {
            console.error('Logout error:', error);
            // Still clear auth state even if logout API fails
            localStorage.removeItem('isAuthenticated');
            setAuth({
                isAuthenticated: false,
                user: null,
                role: null,
                loading: false
            });
        }
    };

    return (
        <AuthContext.Provider value={{ auth, login, logout }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};

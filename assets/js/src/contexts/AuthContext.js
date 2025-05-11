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
        
        // Import our API service dynamically to avoid circular dependency
        import('../services/ApiService').then(module => {
            const ApiService = module.default;
            
            // Set up function to check authentication
            const checkAuth = async () => {
                try {
                    // Create a controller to potentially abort the request if it takes too long
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
                    
                    // Use the ApiService which ensures proper authentication handling
                    const response = await ApiService.get('/auth/me');
                    
                    clearTimeout(timeoutId);
                    
                    if (response.status === 200) {
                        const data = response;
                        
                        if (data.authenticated) {
                            // Store authentication state in localStorage for WebSocketService
                            localStorage.setItem('isAuthenticated', 'true');
                            
                            setAuth({
                                isAuthenticated: true,
                                user: data.user,
                                role: data.role,
                                loading: false
                            });
                        } else {
                            // User is not authenticated but we got a 200 response
                            localStorage.removeItem('isAuthenticated');
                            
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
            
            const data = await ApiService.post('/auth/login', credentials);
            
            // Store authentication state in localStorage for WebSocketService
            localStorage.setItem('isAuthenticated', 'true');
            
            setAuth({
                isAuthenticated: true,
                user: data.user,
                role: data.role,
                loading: false
            });
            
            return data;
        } catch (error) {
            console.error('Login failed:', error);
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

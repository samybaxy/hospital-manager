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
        
        // Set up custom fetch options to avoid console errors for 401 responses
        const checkAuth = async () => {
            try {
                // Create a controller to potentially abort the request
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
                
                // Check WordPress user session with credentials included to handle cookies
                const response = await fetch('/wp-json/hospital-manager/v1/auth/me', {
                    credentials: 'include', // Include cookies
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Help identify AJAX requests
                    },
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (response.status === 200) {
                    const data = await response.json();
                    
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
                
                localStorage.removeItem('isAuthenticated');
                setAuth({
                    isAuthenticated: false,
                    user: null,
                    role: null,
                    loading: false
                });
            }
        };

        checkAuth();
    }, []);

    const login = (credentials) => {
        return fetch('/wp-json/hospital-manager/v1/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(credentials)
        }).then(res => {
            if (!res.ok) {
                throw new Error('Login failed');
            }
            return res.json();
        }).then(data => {
            // Store authentication state in localStorage for WebSocketService
            localStorage.setItem('isAuthenticated', 'true');
            
            setAuth({
                isAuthenticated: true,
                user: data.user,
                role: data.role,
                loading: false
            });
            
            return data;
        });
    };

    const logout = () => {
        return fetch('/wp-json/hospital-manager/v1/auth/logout', {
            method: 'POST'
        }).then(() => {
            // Remove authentication state from localStorage
            localStorage.removeItem('isAuthenticated');
            
            setAuth({
                isAuthenticated: false,
                user: null,
                role: null,
                loading: false
            });
        });
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

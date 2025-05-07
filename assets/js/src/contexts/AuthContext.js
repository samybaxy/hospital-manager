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
        // Check WordPress user session
        fetch('/wp-json/hospital-manager/v1/auth/me')
            .then(res => {
                if (res.status === 200) {
                    return res.json().then(data => {
                        // Store authentication state in localStorage for WebSocketService
                        localStorage.setItem('isAuthenticated', 'true');
                        
                        setAuth({
                            isAuthenticated: true,
                            user: data.user,
                            role: data.role,
                            loading: false
                        });
                    });
                } else {
                    // Handle 401 Unauthorized or other error statuses
                    localStorage.removeItem('isAuthenticated');
                    
                    setAuth({
                        isAuthenticated: false,
                        user: null,
                        role: null,
                        loading: false
                    });
                    return Promise.reject('Not authenticated');
                }
            })
            .catch((error) => {
                console.log('Authentication check failed:', error);
                setAuth({
                    isAuthenticated: false,
                    user: null,
                    role: null,
                    loading: false
                });
            });
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

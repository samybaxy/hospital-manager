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
            .then(res => res.json())
            .then(data => {
                setAuth({
                    isAuthenticated: true,
                    user: data.user,
                    role: data.role,
                    loading: false
                });
            })
            .catch(() => {
                setAuth(prev => ({ ...prev, loading: false }));
            });
    }, []);

    const login = (credentials) => {
        return fetch('/wp-json/hospital-manager/v1/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(credentials)
        }).then(res => res.json());
    };

    const logout = () => {
        return fetch('/wp-json/hospital-manager/v1/auth/logout', {
            method: 'POST'
        }).then(() => {
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

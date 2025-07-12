/**
 * Console utilities for Hospital Manager Development
 * These functions are available globally in the browser console
 */

// Enhanced console logging with Hospital Manager branding
const hospitalManagerConsole = {
    log: (message, data = null) => {
        console.log(`🏥 Hospital Manager: ${message}`, data || '');
    },
    warn: (message, data = null) => {
        console.warn(`🏥 Hospital Manager Warning: ${message}`, data || '');
    },
    error: (message, data = null) => {
        console.error(`🏥 Hospital Manager Error: ${message}`, data || '');
    },
    debug: (message, data = null) => {
        if (window.hospitalManagerDevMode?.status()) {
            console.debug(`🏥 Hospital Manager Debug: ${message}`, data || '');
        }
    }
};

// Development mode utilities
const devModeUtils = {
    // Show all available commands
    help: () => {
        console.group('🏥 Hospital Manager - Development Console Commands');
        console.log('🔧 Dev Mode Controls:');
        console.log('  devMode.enable()     - Enable development mode');
        console.log('  devMode.disable()    - Disable development mode');
        console.log('  devMode.toggle()     - Toggle development mode');
        console.log('  devMode.status()     - Check current status');
        console.log('  devMode.info()       - Get environment info');
        console.log('  devMode.clear()      - Clear localStorage override');
        console.log('');
        console.log('🔍 Debugging:');
        console.log('  debug.auth()         - Show auth information');
        console.log('  debug.permissions()  - Show user permissions');
        console.log('  debug.environment()  - Show environment details');
        console.log('  debug.api()          - Test API connection');
        console.log('');
        console.log('🚀 Quick Actions:');
        console.log('  quick.reload()       - Reload page');
        console.log('  quick.clearStorage() - Clear all storage');
        console.log('  quick.mockAdmin()    - Switch to mock admin (dev mode)');
        console.log('  quick.mockPatient()  - Switch to mock patient (dev mode)');
        console.groupEnd();
    },
    
    // Development mode controls
    enable: () => {
        localStorage.setItem('hospital_manager_dev_mode', 'true');
        hospitalManagerConsole.log('Development mode ENABLED');
        window.location.reload();
    },
    
    disable: () => {
        localStorage.setItem('hospital_manager_dev_mode', 'false');
        hospitalManagerConsole.log('Development mode DISABLED');
        window.location.reload();
    },
    
    toggle: () => {
        const current = window.hospitalManagerDevMode?.status();
        if (current) {
            devModeUtils.disable();
        } else {
            devModeUtils.enable();
        }
    },
    
    status: () => {
        const isDev = window.hospitalManagerDevMode?.status();
        const info = window.hospitalManagerDevMode?.info();
        
        console.group('🔧 Development Mode Status');
        console.log(`Status: ${isDev ? '✅ ENABLED' : '❌ DISABLED'}`);
        console.log('Environment Info:', info);
        console.groupEnd();
        
        return isDev;
    },
    
    info: () => {
        const info = window.hospitalManagerDevMode?.info();
        console.table(info);
        return info;
    },
    
    clear: () => {
        localStorage.removeItem('hospital_manager_dev_mode');
        hospitalManagerConsole.log('Development mode override CLEARED');
        window.location.reload();
    }
};

// Debugging utilities
const debugUtils = {
    auth: () => {
        const user = window.hospitalManagerAuth?.user;
        const isAuthenticated = window.hospitalManagerAuth?.isAuthenticated;
        
        console.group('🔐 Authentication Status');
        console.log('Authenticated:', isAuthenticated);
        console.log('User:', user);
        console.log('Tokens:', {
            hasToken: !!localStorage.getItem('hospital_manager_token') || !!sessionStorage.getItem('hospital_manager_token'),
            localStorage: !!localStorage.getItem('hospital_manager_token'),
            sessionStorage: !!sessionStorage.getItem('hospital_manager_token')
        });
        console.groupEnd();
    },
    
    permissions: () => {
        const accessData = window.hospitalManagerAccess?.getAccessData();
        
        console.group('🔑 User Permissions');
        console.log('Role:', accessData?.role);
        console.log('Permissions:', accessData?.permissions);
        console.groupEnd();
        
        return accessData;
    },
    
    environment: () => {
        const info = {
            hostname: window.location.hostname,
            port: window.location.port,
            protocol: window.location.protocol,
            userAgent: navigator.userAgent,
            screen: `${window.screen.width}x${window.screen.height}`,
            viewport: `${window.innerWidth}x${window.innerHeight}`,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            language: navigator.language,
            online: navigator.onLine,
            devMode: window.hospitalManagerDevMode?.status(),
            envVars: {
                NODE_ENV: import.meta.env?.NODE_ENV,
                VITE_APP_MODE: import.meta.env?.VITE_APP_MODE,
                VITE_ENABLE_DEBUG: import.meta.env?.VITE_ENABLE_DEBUG
            }
        };
        
        console.group('🌍 Environment Information');
        console.table(info);
        console.groupEnd();
        
        return info;
    },
    
    api: async () => {
        hospitalManagerConsole.log('Testing API connection...');
        
        try {
            const response = await fetch('/wp-json/hospital-manager/v1/auth/me', {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            console.group('🌐 API Test Results');
            console.log('Status:', response.status);
            console.log('OK:', response.ok);
            console.log('Response:', data);
            console.groupEnd();
            
            return { status: response.status, ok: response.ok, data };
        } catch (error) {
            hospitalManagerConsole.error('API test failed', error);
            return { error: error.message };
        }
    }
};

// Quick action utilities
const quickUtils = {
    reload: () => {
        hospitalManagerConsole.log('Reloading page...');
        window.location.reload();
    },
    
    clearStorage: () => {
        localStorage.clear();
        sessionStorage.clear();
        hospitalManagerConsole.log('All storage cleared');
    },
    
    mockAdmin: () => {
        if (!window.hospitalManagerDevMode?.status()) {
            hospitalManagerConsole.warn('Development mode must be enabled first');
            return;
        }
        
        // This would be implemented in the AuthContext
        hospitalManagerConsole.log('Switching to mock admin user...');
        // Implementation would depend on your auth system
    },
    
    mockPatient: () => {
        if (!window.hospitalManagerDevMode?.status()) {
            hospitalManagerConsole.warn('Development mode must be enabled first');
            return;
        }
        
        hospitalManagerConsole.log('Switching to mock patient user...');
        // Implementation would depend on your auth system
    }
};

// Make utilities available globally
if (typeof window !== 'undefined') {
    window.devMode = devModeUtils;
    window.debug = debugUtils;
    window.quick = quickUtils;
    window.hospitalManagerConsole = hospitalManagerConsole;
    
    // Show welcome message in development mode
    if (window.hospitalManagerDevMode?.status()) {
        console.log(
            '%c🏥 Hospital Manager - Development Mode Active',
            'color: #10B981; font-size: 16px; font-weight: bold;'
        );
        console.log('Type "devMode.help()" for available commands');
    }
}

export { devModeUtils, debugUtils, quickUtils, hospitalManagerConsole };

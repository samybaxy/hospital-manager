import React, { useState, useEffect } from 'react';
import { useUserAccess } from '../hooks/useUserAccess';

/**
 * Development Mode Toggle Component
 * Only visible to administrators and developers
 * Allows quick switching between development and production modes
 */
const DevModeToggle = () => {
    const [isDevMode, setIsDevMode] = useState(false);
    const [showToggle, setShowToggle] = useState(false);
    const [envInfo, setEnvInfo] = useState({});
    const [showDetails, setShowDetails] = useState(false);
    const { isAdministrator, role } = useUserAccess();
    
    // Function to get current development mode status
    const getCurrentDevMode = () => {
        // Check localStorage override first
        const override = localStorage.getItem('hospital_manager_dev_mode');
        if (override !== null) {
            return override === 'true';
        }
        
        // Check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('dev')) {
            return urlParams.get('dev') === 'true' || urlParams.get('dev') === '1';
        }
        
        // Check hostname for development environment
        const hostname = window.location.hostname;
        const isDevelopmentHost = hostname === 'localhost' || 
                                 hostname === '127.0.0.1' || 
                                 hostname.endsWith('.local') || 
                                 hostname.endsWith('.dev') ||
                                 window.location.port !== '';
        
        return isDevelopmentHost;
    };
    
    // Function to get environment information
    const getEnvironmentInfo = () => {
        const hostname = window.location.hostname;
        const port = window.location.port;
        const override = localStorage.getItem('hospital_manager_dev_mode');
        
        let mode = 'production';
        if (getCurrentDevMode()) {
            mode = 'development';
        }
        
        return {
            mode,
            hostname,
            port: port || '80',
            localStorageOverride: override,
            hasOverride: override !== null
        };
    };
    
    useEffect(() => {
        // Only show to administrators and developers
        const shouldShow = isAdministrator() || role === 'developer';
        setShowToggle(shouldShow);
        
        if (shouldShow) {
            // Get current mode
            const isDev = getCurrentDevMode();
            setIsDevMode(isDev);
            
            // Get environment information
            const info = getEnvironmentInfo();
            setEnvInfo(info);
        }
    }, [isAdministrator, role]);
    
    const toggleMode = () => {
        const newMode = !isDevMode;
        
        // Update localStorage without reloading the page
        localStorage.setItem('hospital_manager_dev_mode', newMode.toString());
        
        // Update state immediately
        setIsDevMode(newMode);
        
        // Update environment info
        const info = getEnvironmentInfo();
        setEnvInfo(info);
        
        // Notify user of change
        console.log(`🔧 Development Mode ${newMode ? 'ENABLED' : 'DISABLED'} - Reload page to apply changes`);
        
        // Optional: Show a small notification
        if (window.hospitalManagerConsole) {
            window.hospitalManagerConsole.log(`Development Mode ${newMode ? 'ENABLED' : 'DISABLED'} - Reload page to apply changes`);
        }
    };
    
    const clearOverride = () => {
        localStorage.removeItem('hospital_manager_dev_mode');
        
        // Update state to reflect natural environment
        const isDev = getCurrentDevMode();
        setIsDevMode(isDev);
        
        // Update environment info
        const info = getEnvironmentInfo();
        setEnvInfo(info);
        
        console.log('🔧 Development Mode override cleared - Using natural environment');
        
        if (window.hospitalManagerConsole) {
            window.hospitalManagerConsole.log('Development Mode override cleared - Using natural environment');
        }
    };
    
    if (!showToggle) return null;
    
    return (
        <div className="fixed bottom-4 right-4 z-50">
            {/* Toggle Button */}
            <div className="relative">
                <button
                    onClick={toggleMode}
                    onMouseEnter={() => setShowDetails(true)}
                    onMouseLeave={() => setShowDetails(false)}
                    className={`px-4 py-2 rounded-full text-white font-medium shadow-lg transition-all duration-200 transform hover:scale-105 ${
                        isDevMode 
                            ? 'bg-green-500 hover:bg-green-600 shadow-green-200' 
                            : 'bg-red-500 hover:bg-red-600 shadow-red-200'
                    }`}
                    title={`Development Mode: ${isDevMode ? 'ON' : 'OFF'} - Click to toggle`}
                >
                    <div className="flex items-center space-x-2">
                        <span className="text-lg">🔧</span>
                        <span className="text-sm font-semibold">
                            Dev: {isDevMode ? 'ON' : 'OFF'}
                        </span>
                    </div>
                </button>
                
                {/* Details Tooltip */}
                {showDetails && (
                    <div className="absolute bottom-full right-0 mb-2 w-80 bg-gray-900 text-white text-xs rounded-lg shadow-lg p-4 border border-gray-700">
                        <div className="space-y-2">
                            <div className="font-semibold text-yellow-300 border-b border-gray-600 pb-1">
                                Development Mode Status
                            </div>
                            
                            <div className="grid grid-cols-2 gap-2">
                                <div className="text-gray-300">Current Mode:</div>
                                <div className={`font-medium ${isDevMode ? 'text-green-400' : 'text-red-400'}`}>
                                    {isDevMode ? 'Development' : 'Production'}
                                </div>
                                
                                <div className="text-gray-300">Environment:</div>
                                <div className="text-blue-400">{envInfo.mode || 'Unknown'}</div>
                                
                                <div className="text-gray-300">Hostname:</div>
                                <div className="text-blue-400">{envInfo.hostname || 'Unknown'}</div>
                                
                                {envInfo.port && envInfo.port !== '80' && (
                                    <>
                                        <div className="text-gray-300">Port:</div>
                                        <div className="text-blue-400">{envInfo.port}</div>
                                    </>
                                )}
                            </div>
                            
                            {envInfo.hasOverride && (
                                <div className="mt-2 pt-2 border-t border-gray-600">
                                    <div className="text-yellow-300 text-xs">
                                        ⚠️ Override Active: {envInfo.localStorageOverride === 'true' ? 'Development' : 'Production'}
                                    </div>
                                    <button
                                        onClick={clearOverride}
                                        className="mt-1 px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs transition-colors"
                                    >
                                        Clear Override
                                    </button>
                                </div>
                            )}
                            
                            <div className="mt-2 pt-2 border-t border-gray-600 text-xs text-gray-400">
                                <div>• Click to toggle mode</div>
                                <div>• {envInfo.hasOverride ? 'Page reload required for full effect' : 'Changes effective immediately'}</div>
                                <div>• Only visible to admins & developers</div>
                            </div>
                        </div>
                        
                        {/* Arrow pointer */}
                        <div className="absolute top-full right-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                    </div>
                )}
            </div>
            
            {/* Environment Badge */}
            <div className="mt-2 text-center">
                <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${
                    envInfo.mode === 'development' 
                        ? 'bg-yellow-100 text-yellow-800' 
                        : 'bg-blue-100 text-blue-800'
                }`}>
                    {envInfo.mode || 'prod'}
                    {envInfo.hasOverride && (
                        <span className="ml-1 text-xs">*</span>
                    )}
                </span>
            </div>
        </div>
    );
};

export default DevModeToggle;

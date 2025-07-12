import React, { useState, useEffect } from 'react';
import { useUserAccess } from '../hooks/useUserAccess';

/**
 * Development Mode Status Indicator
 * Shows in the header when development mode is active
 * Only visible to administrators and developers
 */
const DevModeStatus = () => {
    const [isDevMode, setIsDevMode] = useState(false);
    const [showStatus, setShowStatus] = useState(false);
    const { isAdministrator, role } = useUserAccess();
    
    useEffect(() => {
        // Only show to administrators and developers
        const shouldShow = isAdministrator() || role === 'developer';
        setShowStatus(shouldShow);
        
        if (shouldShow) {
            // Get current mode from the global function
            const isDev = window.hospitalManagerDevMode?.status();
            setIsDevMode(isDev);
        }
    }, [isAdministrator, role]);
    
    if (!showStatus || !isDevMode) return null;
    
    return (
        <div className="hidden sm:flex items-center mr-3">
            <div className="bg-yellow-100 border border-yellow-300 px-2 py-1 rounded-md">
                <div className="flex items-center space-x-1">
                    <span className="text-yellow-600 text-xs">🔧</span>
                    <span className="text-yellow-800 text-xs font-medium">DEV MODE</span>
                </div>
            </div>
        </div>
    );
};

export default DevModeStatus;

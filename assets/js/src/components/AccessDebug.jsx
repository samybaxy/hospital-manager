import React, { useEffect, useRef } from 'react';
import { useUserAccess } from '../hooks/useUserAccess';

/**
 * Debug component to display current access state
 * Only shown in development mode
 */
const AccessDebug = () => {
  const { 
    role, 
    permissions, 
    loading, 
    error,
    hasAccess,
    hasCapability,
    isAdministrator,
    isDoctor,
    isNurse,
    getAccessData
  } = useUserAccess();
  const [isVisible, setIsVisible] = React.useState(false);
  
  // Only show in development mode or when debug is explicitly enabled
  const isDevEnv = process.env.NODE_ENV === 'development';
  const isDebugEnabled = import.meta.env.VITE_ENABLE_DEBUG === 'true';
  
  if (!isDevEnv && !isDebugEnabled) {
    // Log to console when in production mode to help with debugging
    console.log('AccessDebug component hidden - cannot run in production mode');
    return null;
  }
  
  return (
    <>
      {/* Toggle button always visible */}
      <button 
        onClick={() => setIsVisible(!isVisible)}
        style={{
          position: 'fixed',
          bottom: '10px',
          right: '10px',
          zIndex: 10000,
          background: 'rgba(0,0,0,0.7)',
          color: 'white',
          padding: '5px 10px',
          borderRadius: '3px',
          border: 'none',
          cursor: 'pointer',
          fontSize: '12px',
          fontFamily: 'monospace'
        }}
      >
        {isVisible ? 'Hide' : 'Show'} Access Debug
      </button>
      
      {/* Debug panel */}
      {isVisible && (
        <div style={{
          position: 'fixed',
          bottom: '45px',
          right: '10px',
          zIndex: 9999,
          background: 'rgba(0,0,0,0.8)',
          color: 'white',
          padding: '10px',
          borderRadius: '5px',
          maxWidth: '400px',
          maxHeight: '300px',
          overflow: 'auto',
          fontSize: '12px',
          fontFamily: 'monospace'
        }}>
          <h4 style={{margin: '0 0 5px 0'}}>Access Debug (Unified Service)</h4>
          {loading ? (
            <div>Loading access data...</div>
          ) : error ? (
            <div style={{color: 'red'}}>Error: {error}</div>
          ) : (
            <>
              <div><strong>Role:</strong> {role || 'undefined'}</div>
              <div style={{marginTop: '5px'}}><strong>Capabilities:</strong></div>
              <pre style={{fontSize: '10px', margin: '2px 0'}}>{JSON.stringify(permissions, null, 2)}</pre>
              <div style={{marginTop: '5px'}}><strong>Route Permissions:</strong></div>
              <pre style={{fontSize: '10px', margin: '2px 0'}}>{JSON.stringify({
                'patients': hasAccess('patients'),
                'doctors': hasAccess('doctors'),
                'appointments': hasAccess('appointments'),
                'visitations': hasAccess('visitations'),
                'billing': hasAccess('billing'),
                'reports': hasAccess('reports'),
                'settings': hasAccess('settings')
              }, null, 2)}</pre>
              <div style={{marginTop: '5px'}}><strong>Full Access Data:</strong></div>
              <pre style={{fontSize: '9px', margin: '2px 0'}}>{JSON.stringify(getAccessData(), null, 2)}</pre>
            </>
          )}
        </div>
      )}
    </>
  );
};

export default AccessDebug;

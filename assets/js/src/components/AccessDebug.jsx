import React, { useEffect, useRef } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { selectRole, selectPermissions, selectAccessLoading, selectAccessError } from '../redux/accessSlice';
import { fetchUserAccess } from '../utils/accessControl.jsx';

/**
 * Debug component to display current access state
 * Only shown in development mode
 */
const AccessDebug = () => {
  const dispatch = useDispatch();
  const role = useSelector(selectRole);
  const permissions = useSelector(selectPermissions);
  const isLoading = useSelector(selectAccessLoading);
  const error = useSelector(selectAccessError);
  const [isVisible, setIsVisible] = React.useState(false);
  const fetchedRef = useRef(false);
  
  // Fetch access permissions only once on mount
  useEffect(() => {
    // Only fetch if we haven't already and there's no data
    if (!fetchedRef.current && !role && !isLoading) {
      fetchedRef.current = true;
      dispatch(fetchUserAccess());
    }
  }, [dispatch, role, isLoading]);
  
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
          <h4 style={{margin: '0 0 5px 0'}}>Access Debug</h4>
          {isLoading ? (
            <div>Loading access data...</div>
          ) : error ? (
            <div style={{color: 'red'}}>Error: {error}</div>
          ) : (
            <>
              <div><strong>Role:</strong> {role || 'undefined'}</div>
              <div style={{marginTop: '5px'}}><strong>Permissions:</strong></div>
              <pre>{JSON.stringify(permissions, null, 2)}</pre>
            </>
          )}
        </div>
      )}
    </>
  );
};

export default AccessDebug;

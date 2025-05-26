import React, { useState, useEffect } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';

const Notifications = () => {
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchNotifications = async () => {
      try {
        const { apiUrl, nonce } = window.hospitalManagerData || {};
        
        if (!apiUrl) {
          throw new Error('API URL not available');
        }
        
        const response = await fetch(`${apiUrl}/notifications`, {
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json'
          }
        });
        
        if (!response.ok) {
          throw new Error('Failed to fetch notifications');
        }
        
        const data = await response.json();
        setNotifications(data);
        setLoading(false);
      } catch (err) {
        console.error('Error fetching notifications:', err);
        setError(err.message);
        setLoading(false);
      }
    };
    
    fetchNotifications();
  }, []);

  const markAsRead = async (ID) => {
    try {
      const { apiUrl, nonce } = window.hospitalManagerData || {};
      
      if (!apiUrl) {
        throw new Error('API URL not available');
      }
      
      const response = await fetch(`${apiUrl}/notifications/${ID}/read`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': nonce,
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to mark notification as read');
      }
      
      // Update the notification in the local state
      setNotifications(prevNotifications => 
        prevNotifications.map(notification => 
          notification.ID === ID 
            ? { ...notification, is_read: true } 
            : notification
        )
      );
    } catch (err) {
      console.error('Error marking notification as read:', err);
      setError(err.message);
    }
  };
  
  if (loading) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-4 mb-4">
        <p>Error: {error}</p>
        <Button 
          variant="primary" 
          className="mt-4"
          onClick={() => window.location.reload()}
        >
          Try Again
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Notifications</h1>
      <p className="text-lg text-gray-600">Stay updated with latest hospital activities</p>
      
      <div className="flex justify-end mb-4">
        <Button 
          variant="secondary"
          onClick={async () => {
            try {
              const { apiUrl, nonce } = window.hospitalManagerData || {};
              
              if (!apiUrl) {
                throw new Error('API URL not available');
              }
              
              const response = await fetch(`${apiUrl}/notifications/mark-all-read`, {
                method: 'POST',
                headers: {
                  'X-WP-Nonce': nonce,
                  'Content-Type': 'application/json'
                }
              });
              
              if (!response.ok) {
                throw new Error('Failed to mark all notifications as read');
              }
              
              // Update all notifications as read in the local state
              setNotifications(prevNotifications => 
                prevNotifications.map(notification => ({ ...notification, is_read: true }))
              );
            } catch (err) {
              console.error('Error marking all notifications as read:', err);
              setError(err.message);
            }
          }}
        >
          Mark All as Read
        </Button>
      </div>
      
      <Card>
        <div className="divide-y divide-gray-200">
          {notifications.length > 0 ? (
            notifications.map((notification) => (
              <div 
                key={notification.ID} 
                className={`p-4 ${notification.is_read ? 'bg-white' : 'bg-blue-50'}`}
              >
                <div className="sm:flex sm:justify-between sm:items-start">
                  <div className="sm:flex-1">
                    <h3 className={`text-base font-medium ${notification.is_read ? 'text-gray-900' : 'text-blue-800'}`}>
                      {notification.title}
                    </h3>
                    <p className="mt-1 text-sm text-gray-600">
                      {notification.message}
                    </p>
                    <div className="mt-2 flex items-center text-xs text-gray-500">
                      <span>{notification.created_at}</span>
                      <span className="mx-2">•</span>
                      <span>{notification.sender}</span>
                      {notification.category && (
                        <>
                          <span className="mx-2">•</span>
                          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100">
                            {notification.category}
                          </span>
                        </>
                      )}
                    </div>
                  </div>
                  <div className="mt-4 sm:mt-0 sm:ml-6">
                    {!notification.is_read && (
                      <Button 
                        variant="secondary" 
                        size="sm"
                        onClick={() => markAsRead(notification.ID)}
                      >
                        Mark as Read
                      </Button>
                    )}
                  </div>
                </div>
              </div>
            ))
          ) : (
            <div className="p-6 text-center text-gray-500">
              No notifications found
            </div>
          )}
        </div>
      </Card>
    </div>
  );
};

export default Notifications;

import React, { useState, useEffect, useCallback } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import { api } from '../services/apiService';

/** `read` comes back as "0"/"1" (or a boolean); normalise it. */
const isRead = (notification) => {
  const value = notification.read ?? isRead(notification);
  return value === true || value === 1 || value === '1';
};

const TYPE_LABELS = {
  appointment: 'Appointment',
  lab_result: 'Lab result',
  visitation: 'Visit',
  message: 'Message',
  inventory: 'Inventory',
  system: 'System',
};

const Notifications = () => {
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [actionError, setActionError] = useState(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const fetchNotifications = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.get('/notifications', { page, per_page: 20 });
      const data = response.data || {};
      // { data: [...], meta: { last_page, ... } }
      setNotifications(Array.isArray(data.data) ? data.data : []);
      setLastPage((data.meta && data.meta.last_page) || 1);
    } catch (err) {
      setError('Failed to fetch notifications');
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  const markLocal = (ids) =>
    setNotifications((prev) =>
      prev.map((n) => (ids === 'all' || ids.includes(n.ID) ? { ...n, read: '1' } : n))
    );

  const markAsRead = async (ID) => {
    try {
      setActionError(null);
      await api.post(`/notifications/${ID}/read`);
      markLocal([ID]);
    } catch (err) {
      setActionError('Failed to mark notification as read');
    }
  };

  const markAllAsRead = async () => {
    try {
      setActionError(null);
      await api.post('/notifications/mark-all-read');
      markLocal('all');
    } catch (err) {
      // Older servers lack the bulk route: mark the visible ones one by one.
      if (err.response && err.response.status === 404) {
        const unread = notifications.filter((n) => !isRead(n)).map((n) => n.ID);
        try {
          await Promise.all(unread.map((id) => api.post(`/notifications/${id}/read`)));
          markLocal(unread);
          return;
        } catch (e) { /* fall through */ }
      }
      setActionError('Failed to mark all notifications as read');
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
          onClick={fetchNotifications}
        >
          Try Again
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Notifications</h1>
      <p className="text-base sm:text-lg text-gray-600">Stay updated with latest hospital activities</p>
      
      {actionError && (
        <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-3">{actionError}</div>
      )}

      <div className="flex justify-end mb-4">
        <Button 
          variant="secondary"
          onClick={markAllAsRead}
          disabled={!notifications.some((n) => !isRead(n))}
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
                className={`p-4 ${isRead(notification) ? 'bg-white' : 'bg-blue-50'}`}
              >
                <div className="sm:flex sm:justify-between sm:items-start">
                  <div className="sm:flex-1">
                    <h3 className={`text-base font-medium ${isRead(notification) ? 'text-gray-900' : 'text-blue-800'}`}>
                      {notification.title}
                    </h3>
                    <p className="mt-1 text-sm text-gray-600">
                      {notification.message}
                    </p>
                    <div className="mt-2 flex items-center text-xs text-gray-500">
                      <span>{notification.created_at}</span>
                      {notification.type && (
                        <>
                          <span className="mx-2">•</span>
                          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100">
                            {TYPE_LABELS[notification.type] || notification.type}
                          </span>
                        </>
                      )}
                    </div>
                  </div>
                  <div className="mt-4 sm:mt-0 sm:ml-6">
                    {!isRead(notification) && (
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

      {lastPage > 1 && (
        <div className="flex justify-between items-center">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </Button>
          <span className="text-sm text-gray-600">Page {page} of {lastPage}</span>
          <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
            Next
          </Button>
        </div>
      )}
    </div>
  );
};

export default Notifications;

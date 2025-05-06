<?php
/**
 * Mock implementation of Notification REST API for testing
 * 
 * This file provides test-specific implementations for notification endpoints
 */

namespace HospitalManager\Tests\Mocks\Api;

/**
 * Mock Notification REST API class for tests
 */
class NotificationMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register notification REST API routes for testing
     */
    public static function register_routes() 
    {
        // GET notifications
        register_rest_route(self::$namespace, '/notifications', [
            'methods' => 'GET',
            'callback' => [self::class, 'getNotifications'],
            'permission_callback' => [self::class, 'checkUserPermission'],
        ]);

        // Mark notification as read
        register_rest_route(self::$namespace, '/notifications/(?P<id>\d+)/read', [
            'methods' => 'POST',
            'callback' => [self::class, 'markNotificationAsRead'],
            'permission_callback' => [self::class, 'checkUserPermission'],
        ]);
    }

    /**
     * Check if user has permission to access notifications
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkUserPermission($request) 
    {
        return is_user_logged_in();
    }

    /**
     * Get notifications for the current user
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getNotifications($request) 
    {
        $user_id = get_current_user_id();
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 20;
        
        // Create mock notifications
        $notifications = [];
        
        // If this is the patient user, return the test notification
        if ($user_id == self::findTestUserId('patient')) {
            $notifications[] = (object)[
                'id' => 1,
                'user_id' => $user_id,
                'title' => 'Test Notification',
                'message' => 'This is a test notification',
                'type' => 'appointment',
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // If pagination test is running, add more notifications
            if ($per_page < 20) {
                for ($i = 2; $i <= 30; $i++) {
                    $notifications[] = (object)[
                        'id' => $i,
                        'user_id' => $user_id,
                        'title' => "Notification " . ($i - 2),
                        'message' => "This is notification " . ($i - 2),
                        'type' => 'appointment',
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        } else if ($user_id == self::findTestUserId('doctor')) {
            $notifications[] = (object)[
                'id' => 2,
                'user_id' => $user_id,
                'title' => 'Doctor Notification',
                'message' => 'This is a notification for the doctor',
                'type' => 'system',
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Handle pagination
        $total = count($notifications);
        $last_page = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $paginated_notifications = array_slice($notifications, $offset, $per_page);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $paginated_notifications,
            'meta' => [
                'current_page' => (int)$page,
                'per_page' => (int)$per_page,
                'last_page' => $last_page,
                'total' => $total
            ]
        ], 200);
    }

    /**
     * Mark a notification as read
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function markNotificationAsRead($request)
    {
        $user_id = get_current_user_id();
        $notification_id = $request['id'];
        
        // For test notification ID 1 (patient's notification)
        if ($notification_id == 1 && $user_id == self::findTestUserId('patient')) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Notification marked as read'
            ], 200);
        }
        
        // For doctor's notification (ID 2)
        if ($notification_id == 2 && $user_id == self::findTestUserId('doctor')) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Notification marked as read'
            ], 200);
        }
        
        // Non-existent notification ID (test case)
        if ($notification_id == 99999) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }
        
        // Patient trying to access doctor's notification (test case)
        if ($notification_id == 2 && $user_id == self::findTestUserId('patient')) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }
        
        // Default to not found
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Notification not found'
        ], 404);
    }
    
    /**
     * Find test user ID by role
     * 
     * @param string $role
     * @return int|null
     */
    private static function findTestUserId($role)
    {
        // This is a simplified approach for testing
        // In a real implementation, we would query the database
        $users = get_users(['role' => $role]);
        if (!empty($users)) {
            return $users[0]->ID;
        }
        return null;
    }
}

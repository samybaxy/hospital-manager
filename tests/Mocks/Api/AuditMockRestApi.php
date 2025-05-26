<?php
/**
 * Mock implementation of Audit REST API for testing
 */

namespace HospitalManager\Tests\Mocks\Api;

/**
 * Mock Audit REST API class for tests
 */
class AuditMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register audit log REST API routes
     */
    public static function register_routes()
    {
        // GET /audit-logs - Get all audit logs (admin only)
        register_rest_route(self::$namespace, '/audit-logs', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAuditLogs'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);
        
        // GET /audit-logs/patient/{ID} - Get audit logs for a specific patient
        register_rest_route(self::$namespace, '/audit-logs/patient/(?P<ID>\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'getPatientLogs'],
            'permission_callback' => [self::class, 'checkPatientLogAccess'],
            'args' => [
                'ID' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
            ],
        ]);
    }

    /**
     * Check if user has admin permissions
     */
    public static function checkAdminPermission($request)
    {
        return current_user_can('administrator');
    }

    /**
     * Check if user has permission to access patient logs
     */
    public static function checkPatientLogAccess($request)
    {
        return current_user_can('administrator') || current_user_can('doctor');
    }

    /**
     * Get all audit logs (admin only)
     */
    public static function getAuditLogs($request)
    {
        // Get pagination parameters
        $page = $request->get_param('page') ? (int)$request->get_param('page') : 1;
        $per_page = $request->get_param('per_page') ? (int)$request->get_param('per_page') : 20;
        
        // Get audit logs with pagination
        $logs = \HospitalManager\Models\AuditLog::orderBy('created_at', 'DESC')
            ->paginate($per_page, ['*'], 'page', $page);
        
        // Process logs to properly expose protected attributes
        $processedLogs = [];
        foreach ($logs->items as $log) {
            // Use reflection to access protected attributes
            $reflection = new \ReflectionObject($log);
            $attributes = $reflection->getProperty('attributes');
            $attributes->setAccessible(true);
            $attr_values = $attributes->getValue($log);
            
            // Convert to simple object with public properties
            $processedLogs[] = (object)$attr_values;
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'logs' => $processedLogs,
                'total' => $logs->total,
                'per_page' => $logs->perPage,
                'current_page' => $logs->currentPage,
                'last_page' => $logs->lastPage,
            ],
        ]);
    }

    /**
     * Get audit logs for a specific patient
     */
    public static function getPatientLogs($request)
    {
        $patient_id = (int)$request->get_param('ID');
        
        // Get patient logs
        $logs = \HospitalManager\Models\AuditLog::where('entity_type', 'patient')
            ->where('entity_id', $patient_id)
            ->orderBy('created_at', 'DESC')
            ->get();
        
        // Process logs to properly expose protected attributes
        $processedLogs = [];
        foreach ($logs->items as $log) {
            // Use reflection to access protected attributes
            $reflection = new \ReflectionObject($log);
            $attributes = $reflection->getProperty('attributes');
            $attributes->setAccessible(true);
            $attr_values = $attributes->getValue($log);
            
            // Convert to simple object with public properties
            $processedLogs[] = (object)$attr_values;
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'logs' => $processedLogs,
                'patient_id' => $patient_id,
            ],
        ]);
    }
}
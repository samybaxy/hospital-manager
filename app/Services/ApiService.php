<?php

namespace HospitalManager\Services;

use HospitalManager\Controllers\Api\{
    PatientController,
    VisitationController,
    LabInvestigationController,
    ChatController,
    NotificationController,
    AppointmentController,
    AuthController,
    UserController,
    DoctorController,
    AuditController,
    DashboardController,
    StatsController,
    HMOController,
    InventoryController,
    ProfileController
};

/**
 * Service for managing API registration and standardization
 */
class ApiService extends BaseService
{
    /**
     * API version
     *
     * @var string
     */
    protected static $version = 'v1';

    /**
     * Get the controllers that need to be registered
     * Using a more maintainable approach with controller registry
     *
     * @return array Array of controller instances
     */
    public static function getControllers()
    {
        $controllers = [
            'patient' => PatientController::class,
            'visitation' => VisitationController::class,
            'lab_investigation' => LabInvestigationController::class,
            'chat' => ChatController::class,
            'notification' => NotificationController::class,
            'appointment' => AppointmentController::class,
            'auth' => AuthController::class,
            'user' => UserController::class,
            'doctor' => DoctorController::class,
            'audit' => AuditController::class,
            'dashboard' => DashboardController::class,
            'stats' => StatsController::class,
            'hmo' => HMOController::class,
            'inventory' => InventoryController::class,
            'profile' => ProfileController::class,
        ];

        // Initialize controllers
        $instances = [];
        foreach ($controllers as $key => $controller_class) {
            if (class_exists($controller_class)) {
                $instances[$key] = new $controller_class();
            }
        }

        return $instances;
    }

    /**
     * Register all API routes
     *
     * @return void
     */
    public static function registerRoutes()
    {
        // Register regular API controllers
        $controllers = self::getControllers();
        foreach ($controllers as $key => $controller) {
            if (method_exists($controller, 'register_routes')) {
                $controller->register_routes();
            } else {
                self::logError('ApiService', 'registerRoutes', "Controller {$key} missing register_routes method");
            }
        }
        
        // Register Http route classes
        self::registerHttpRoutes();
    }
    
    /**
     * Register routes defined in the Http\Routes namespace
     * 
     * @return void
     */
    private static function registerHttpRoutes()
    {
        // Check if AccessRoutes class exists before trying to instantiate
        if (class_exists('\HospitalManager\Http\Routes\AccessRoutes')) {
            $accessRoutes = new \HospitalManager\Http\Routes\AccessRoutes();
            $accessRoutes->register();
        }
    }

    /**
     * Set up common hooks for all API endpoints
     *
     * @return void
     */
    public static function init()
    {
        // Set CORS headers
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function($value) {
                header('Access-Control-Allow-Origin: ' . get_http_origin());
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
                
                if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
                    exit(0);
                }
                
                return $value;
            });
        }, 15);
    }

    /**
     * Format API response
     *
     * @param mixed $data The data to return
     * @param string $message Response message
     * @param int $status HTTP status code
     * @param bool $success Whether the request was successful
     * @return array Formatted response
     */
    public static function formatResponse($data = null, $message = '', $status = 200, $success = true)
    {
        return [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'status' => $status,
            'version' => self::$version,
        ];
    }
    
    /**
     * Format error response
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param mixed $errors Additional error details
     * @return array Formatted error response
     */
    public static function formatErrorResponse($message, $status = 400, $errors = null)
    {
        return self::formatResponse(null, $message, $status, false);
    }

    /**
     * Add a custom controller to the API
     *
     * @param object $controller Controller instance with register_routes method
     * @return void
     */
    public static function addController($controller)
    {
        add_action('rest_api_init', function() use ($controller) {
            if (method_exists($controller, 'register_routes')) {
                $controller->register_routes();
            }
        });
    }

    /**
     * Get the API namespace
     *
     * @return string
     */
    public static function getNamespace()
    {
        return 'hospital-manager/' . self::$version;
    }

    /**
     * Get the API version
     *
     * @return string
     */
    public static function getVersion()
    {
        return self::$version;
    }
}

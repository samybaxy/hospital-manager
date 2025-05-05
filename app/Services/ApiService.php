<?php

namespace HospitalManager\Services;

use HospitalManager\Controllers\Api\PatientController;
use HospitalManager\Controllers\Api\VisitationController;
use HospitalManager\Controllers\Api\LabInvestigationController;
use HospitalManager\Controllers\Api\ChatController;
use HospitalManager\Controllers\Api\NotificationController;
use HospitalManager\Controllers\Api\AppointmentController;
use HospitalManager\Controllers\Api\AuthController;
use HospitalManager\Controllers\Api\DoctorController;
use HospitalManager\Controllers\Api\AuditController;
use HospitalManager\Controllers\Api\DashboardController;
use HospitalManager\Controllers\Api\StatsController;

/**
 * Service for managing API registration and standardization
 */
class ApiService
{
    /**
     * API version
     *
     * @var string
     */
    protected static $version = 'v1';

    /**
     * Get the controllers that need to be registered
     *
     * @return array Array of controller instances
     */
    public static function getControllers()
    {
        return [
            new PatientController(),
            new VisitationController(),
            new LabInvestigationController(),
            new ChatController(),
            new NotificationController(),
            new AppointmentController(),
            new AuthController(),
            new DoctorController(),
            new AuditController(),
            new DashboardController(),
            new StatsController(),
        ];
    }

    /**
     * Register all API routes
     *
     * @return void
     */
    public static function registerRoutes()
    {
        $controllers = self::getControllers();

        foreach ($controllers as $controller) {
            $controller->register_routes();
        }
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
}

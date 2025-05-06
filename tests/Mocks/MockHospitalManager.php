<?php
/**
 * Mock implementation of HospitalManager for testing
 * 
 * This file provides test-specific implementations of the plugin
 * functionality without relying on the full WPMVC framework.
 */

namespace HospitalManager\Tests\Mocks;

/**
 * Mock HospitalManager class for tests
 */
class MockHospitalManager 
{
    /**
     * Initialize the mock plugin for testing
     */
    public static function init() 
    {
        // Register REST API routes needed for testing
        add_action('rest_api_init', [self::class, 'registerTestRoutes']);
    }

    /**
     * Register test API routes
     */
    public static function registerTestRoutes() 
    {
        // Register Authentication API routes using the dedicated class
        Api\AuthMockRestApi::register_routes();

        // Register Patient API routes using the dedicated class
        Api\PatientMockRestApi::register_routes();

        // Register Doctor API routes using the dedicated class
        Api\DoctorMockRestApi::register_routes();

        // Register Appointment API routes using the dedicated class
        Api\AppointmentMockRestApi::register_routes();

        // Register Visitation API routes using the dedicated class
        Api\VisitationMockRestApi::register_routes();

        // Register audit log API routes for testing
        Api\AuditMockRestApi::register_routes();

        // Register chat API routes for testing
        Api\ChatMockRestApi::register_routes();

        // Register Dashboard API routes for testing
        Api\DashboardMockRestApi::register_routes();
        
        // Register Medical Data API routes for testing
        Api\MedicalDataMockRestApi::register_routes();
        
        // Register Statistics API routes for testing
        Api\StatsMockRestApi::register_routes();
        
        // Register Lab Investigation API routes for testing
        Api\LabInvestigationMockRestApi::register_routes();
        
        // Register Notification API routes for testing
        Api\NotificationMockRestApi::register_routes();
    }
}

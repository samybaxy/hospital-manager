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
        AuthMockRestApi::registerRoutes();

        // Register Patient API routes using the dedicated class
        PatientMockRestApi::registerRoutes();
    }
}

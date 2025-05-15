<?php

namespace HospitalManager\Middleware;

/**
 * Middleware for detailed REST API error handling
 */
class ApiErrorMiddleware
{
    /**
     * Register the middleware
     */
    public static function register()
    {
        // Add custom error handling for REST API requests
        add_filter('rest_request_before_callbacks', [self::class, 'captureErrors'], 10, 3);
        
        // Enable better error handling for REST API
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::enableDetailedRestErrors();
        }
    }
    
    /**
     * Enable more detailed REST API errors
     */
    private static function enableDetailedRestErrors()
    {
        // Display PHP errors in REST API responses
        add_filter('rest_pre_dispatch', function ($result, $server, $request) {
            // Only for hospital-manager routes
            if (strpos($request->get_route(), '/hospital-manager/v1') !== 0) {
                return $result;
            }
            
            // Set error handling
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
            
            return $result;
        }, 10, 3);
        
        // Restore error handler after REST API request
        add_filter('rest_post_dispatch', function ($response, $handler, $request) {
            // Only for hospital-manager routes
            if (strpos($request->get_route(), '/hospital-manager/v1') !== 0) {
                return $response;
            }
            
            // Restore default error handler
            restore_error_handler();
            
            return $response;
        }, 10, 3);
    }
    
    /**
     * Capture and log PHP errors during REST API requests
     * 
     * @param mixed $response
     * @param array $handler
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public static function captureErrors($response, $handler, $request)
    {
        // Only for hospital-manager routes
        if (strpos($request->get_route(), '/hospital-manager/v1') !== 0) {
            return $response;
        }
        
        try {
            return $response;
        } catch (\Exception $e) {
            // Log detailed error
            error_log('Hospital Manager API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Return WP_Error with detailed message if in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return new \WP_Error(
                    'api_error',
                    $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(),
                    ['status' => 500]
                );
            } else {
                return new \WP_Error(
                    'api_error',
                    'An internal server error occurred.',
                    ['status' => 500]
                );
            }
        }
    }
}

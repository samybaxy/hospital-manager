<?php

namespace HospitalManager\Middleware;

/**
 * Middleware for logging API requests and responses
 */
class ApiLoggingMiddleware
{
    /**
     * Register the middleware
     */
    public static function register()
    {
        add_filter('rest_pre_dispatch', [self::class, 'logRequest'], 10, 3);
        add_filter('rest_post_dispatch', [self::class, 'logResponse'], 10, 3);
    }
    
    /**
     * Log the incoming REST API request
     * 
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public static function logRequest($result, $server, $request)
    {
        if (strpos($request->get_route(), '/hospital-manager/v1') !== 0) {
            return $result;
        }
        
        $route = $request->get_route();
        $method = $request->get_method();
        $params = $request->get_params();
        
        // Remove sensitive data
        if (isset($params['password'])) {
            $params['password'] = '[REDACTED]';
        }
        if (isset($params['user_password'])) {
            $params['user_password'] = '[REDACTED]';
        }
        
        error_log(sprintf(
            'Hospital Manager API Request: %s %s - Params: %s',
            $method,
            $route,
            json_encode($params)
        ));
        
        return $result;
    }
    
    /**
     * Log the outgoing REST API response
     * 
     * @param \WP_REST_Response|\WP_Error $response
     * @param mixed $handler
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function logResponse($response, $handler, $request)
    {
        if (strpos($request->get_route(), '/hospital-manager/v1') !== 0) {
            return $response;
        }
        
        $route = $request->get_route();
        $status = $response->get_status();
        
        if (is_wp_error($response)) {
            error_log(sprintf(
                'Hospital Manager API Error: %s %s - Status: %s - Error: %s',
                $request->get_method(),
                $route,
                $status,
                $response->get_error_message()
            ));
        } else {
            $data = $response->get_data();
            
            // Redact sensitive information
            if (isset($data['token'])) {
                $data['token'] = '[REDACTED]';
            }
            if (isset($data['user']) && isset($data['user']['email'])) {
                $data['user']['email'] = '[REDACTED]';
            }
            
            error_log(sprintf(
                'Hospital Manager API Response: %s %s - Status: %s - Data: %s',
                $request->get_method(),
                $route,
                $status,
                json_encode($data)
            ));
        }
        
        return $response;
    }
}

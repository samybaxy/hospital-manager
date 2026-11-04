<?php

namespace HospitalManager\Middleware;

use HospitalManager\Helpers\DebugLog;

/**
 * Middleware for logging API requests and responses
 *
 * Always logged: method, route and status (or error message) — no payloads.
 * Debug only:    request params and response bodies, because they contain patient
 *                and medical data. Gated by DebugLog::enabled() (HOSPITAL_MANAGER_LOG_PAYLOADS, overridable
 *                via the 'hospital_manager_log_api_payloads' filter). Credentials,
 *                tokens and emails are redacted even in debug logs.
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
     * Whether request params / response bodies may be logged.
     *
     * @return bool
     */
    public static function shouldLogPayloads()
    {
        return DebugLog::enabled();
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

        $line = sprintf('Hospital Manager API Request: %s %s', $request->get_method(), $request->get_route());

        if (self::shouldLogPayloads()) {
            $line .= ' - Params: ' . wp_json_encode(DebugLog::redact($request->get_params()));
        }

        error_log($line);

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

        $method = $request->get_method();
        $route = $request->get_route();

        if (is_wp_error($response)) {
            error_log(sprintf(
                'Hospital Manager API Error: %s %s - Error: %s',
                $method,
                $route,
                $response->get_error_message()
            ));
            return $response;
        }

        $line = sprintf(
            'Hospital Manager API Response: %s %s - Status: %s',
            $method,
            $route,
            $response->get_status()
        );

        if (self::shouldLogPayloads()) {
            // Controllers return arrays, stdClass objects or models; normalise to an
            // array so redaction can't fatal on "object used as array".
            $line .= ' - Data: ' . wp_json_encode(DebugLog::redact(DebugLog::normalize($response->get_data())));
        }

        error_log($line);

        return $response;
    }

    /**
     * Recursively replace sensitive values with "[REDACTED]".
     *
     * @param mixed $data
     * @return mixed
     */
    public static function redact($data)
    {
        return DebugLog::redact($data);
    }
}

<?php

namespace HospitalManager\Services;

/**
 * Base Service Class
 * 
 * Provides common functionality shared across all services to reduce redundancy
 */
abstract class BaseService
{
    /**
     * Global WordPress database instance
     * 
     * @var \wpdb
     */
    protected static $wpdb;

    /**
     * Cache settings for services
     * 
     * @var array
     */
    protected static $cache_settings = [
        'enabled' => true,
        'default_expiration' => 1800, // 30 minutes default
        'group' => 'hospital_manager_services'
    ];

    /**
     * Service-specific cache configurations
     * 
     * @var array
     */
    protected static $service_cache_config = [
        'PatientService' => [
            'enabled' => true,
            'expiration' => 1800, // 30 minutes
            'critical_methods' => ['searchPatients' => 600] // 10 minutes for search
        ],
        'DoctorService' => [
            'enabled' => true,
            'expiration' => 3600, // 1 hour
            'critical_methods' => ['getDoctorPatients' => 900] // 15 minutes for patient lists
        ],
        'AppointmentService' => [
            'enabled' => true,
            'expiration' => 1200, // 20 minutes
            'critical_methods' => [
                'getAppointments' => 600, // 10 minutes for appointment lists
                'getAvailability' => 600, // 10 minutes for availability
                'isSlotAvailable' => 300, // 5 minutes for slot checking
                'getBookingData' => 900 // 15 minutes for booking data
            ]
        ],
        'VisitationService' => [
            'enabled' => true,
            'expiration' => 1200, // 20 minutes
            'critical_methods' => ['getVisitations' => 600] // 10 minutes for visitation lists
        ],
        'LabResultService' => [
            'enabled' => true,
            'expiration' => 1200, // 20 minutes
            'critical_methods' => [
                'getPendingInvestigations' => 300, // 5 minutes for pending investigations
                'getPatientResults' => 1200, // 20 minutes for patient results
                'getDashboardStats' => 1800 // 30 minutes for statistics
            ]
        ],
        'InventoryService' => [
            'enabled' => true,
            'expiration' => 1800, // 30 minutes
            'critical_methods' => ['getDashboardData' => 900] // 15 minutes for dashboard
        ]
    ];

    /**
     * Initialize the base service
     */
    public static function init()
    {
        global $wpdb;
        self::$wpdb = $wpdb;
    }

    /**
     * Get cache key for service method
     * 
     * @param string $service_class Service class name
     * @param string $method Method name
     * @param array $params Method parameters
     * @return string Cache key
     */
    protected static function getCacheKey($service_class, $method, $params = [])
    {
        $params_hash = md5(serialize($params));
        return "hospital_manager_service_{$service_class}_{$method}_{$params_hash}";
    }

    /**
     * Get data from cache
     * 
     * @param string $cache_key Cache key
     * @return mixed Cached data or false if not found
     */
    protected static function getFromCache($cache_key)
    {
        if (!self::isCacheEnabled()) {
            return false;
        }
        
        return get_transient($cache_key);
    }

    /**
     * Set data to cache
     * 
     * @param string $cache_key Cache key
     * @param mixed $data Data to cache
     * @param int|null $expiration Cache expiration in seconds
     * @return bool Success status
     */
    protected static function setToCache($cache_key, $data, $expiration = null)
    {
        if (!self::isCacheEnabled()) {
            return false;
        }
        
        if ($expiration === null) {
            $expiration = self::getCacheExpiration(get_called_class());
        }
        
        return set_transient($cache_key, $data, $expiration);
    }

    /**
     * Check if caching is enabled for the service
     * 
     * @param string|null $service_class Service class name
     * @return bool Cache enabled status
     */
    protected static function isCacheEnabled($service_class = null)
    {
        if (!self::$cache_settings['enabled']) {
            return false;
        }
        
        if ($service_class === null) {
            $service_class = get_called_class();
        }
        
        $class_name = basename(str_replace('\\', '/', $service_class));
        
        return isset(self::$service_cache_config[$class_name]['enabled']) 
            ? self::$service_cache_config[$class_name]['enabled'] 
            : true;
    }

    /**
     * Get cache expiration for service or method
     * 
     * @param string $service_class Service class name
     * @param string|null $method Method name
     * @return int Cache expiration in seconds
     */
    protected static function getCacheExpiration($service_class, $method = null)
    {
        $class_name = basename(str_replace('\\', '/', $service_class));
        
        // Check for method-specific cache duration
        if ($method && isset(self::$service_cache_config[$class_name]['critical_methods'][$method])) {
            return self::$service_cache_config[$class_name]['critical_methods'][$method];
        }
        
        // Return service-specific default or global default
        return isset(self::$service_cache_config[$class_name]['expiration']) 
            ? self::$service_cache_config[$class_name]['expiration'] 
            : self::$cache_settings['default_expiration'];
    }

    /**
     * Invalidate service cache by pattern
     * 
     * @param string $service_class Service class name
     * @param string|null $method_pattern Method pattern (optional)
     * @param array $params Specific parameters (optional)
     */
    protected static function invalidateServiceCache($service_class, $method_pattern = null, $params = [])
    {
        global $wpdb;
        
        $class_name = basename(str_replace('\\', '/', $service_class));
        
        if ($method_pattern && !empty($params)) {
            // Invalidate specific cache entry
            $cache_key = self::getCacheKey($class_name, $method_pattern, $params);
            delete_transient($cache_key);
        } else {
            // Invalidate all cache entries for the service
            $pattern = "hospital_manager_service_{$class_name}%";
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                "_transient_{$pattern}"
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                "_transient_timeout_{$pattern}"
            ));
        }
    }

    /**
     * Execute cached service method
     * 
     * @param string $method Method name
     * @param array $params Method parameters
     * @param callable $callback Method callback
     * @param int|null $custom_expiration Custom cache expiration
     * @return mixed Method result
     */
    protected static function executeCached($method, $params, $callback, $custom_expiration = null)
    {
        $service_class = get_called_class();
        $class_name = basename(str_replace('\\', '/', $service_class));
        
        // Generate cache key
        $cache_key = self::getCacheKey($class_name, $method, $params);
        
        // Try to get from cache
        $cached_result = self::getFromCache($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Execute the callback
        $result = call_user_func($callback);
        
        // Cache the result
        $expiration = $custom_expiration ?: self::getCacheExpiration($service_class, $method);
        self::setToCache($cache_key, $result, $expiration);
        
        return $result;
    }

    /**
     * Configure cache settings
     * 
     * @param array $settings Cache settings
     */
    public static function configureCacheSettings($settings)
    {
        self::$cache_settings = array_merge(self::$cache_settings, $settings);
    }

    /**
     * Configure service-specific cache settings
     * 
     * @param string $service Service name
     * @param array $config Cache configuration
     */
    public static function configureServiceCache($service, $config)
    {
        self::$service_cache_config[$service] = array_merge(
            self::$service_cache_config[$service] ?? [],
            $config
        );
    }

    /**
     * Get the WordPress database instance
     * 
     * @return \wpdb
     */
    protected static function getWpdb()
    {
        if (!self::$wpdb) {
            global $wpdb;
            self::$wpdb = $wpdb;
        }
        return self::$wpdb;
    }

    /**
     * Check if a database table exists
     * 
     * @param string $table_name Table name without prefix
     * @return bool True if table exists
     */
    protected static function tableExists($table_name)
    {
        $wpdb = self::getWpdb();
        $table_name = $wpdb->prefix . $table_name;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        return !empty($table_exists);
    }

    /**
     * Log error with consistent format
     * 
     * @param string $service_name Name of the service
     * @param string $method_name Name of the method
     * @param string $message Error message
     * @param mixed $context Additional context (optional)
     */
    protected static function logError($service_name, $method_name, $message, $context = null)
    {
        $log_message = "{$service_name}::{$method_name} - {$message}";
        if ($context !== null) {
            $log_message .= ' | Context: ' . print_r($context, true);
        }
        error_log($log_message);
    }

    /**
     * Validate required fields
     * 
     * @param array $data Data to validate
     * @param array $required_fields Required field names
     * @return array Array of validation errors (empty if valid)
     */
    protected static function validateRequiredFields($data, $required_fields)
    {
        $errors = [];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "The {$field} field is required";
            }
        }
        return $errors;
    }

    /**
     * Validate numeric field
     * 
     * @param mixed $value Value to validate
     * @param string $field_name Field name for error message
     * @return string|null Error message or null if valid
     */
    protected static function validateNumeric($value, $field_name)
    {
        if (!is_numeric($value)) {
            return "The {$field_name} field must be numeric";
        }
        return null;
    }

    /**
     * Validate phone number format
     * 
     * @param string $phone Phone number to validate
     * @return string|null Error message or null if valid
     */
    protected static function validatePhone($phone)
    {
        if (!preg_match('/^\d{10,15}$/', $phone)) {
            return "Invalid phone number format. Phone number should contain 10-15 digits only";
        }
        return null;
    }

    /**
     * Validate email format
     * 
     * @param string $email Email to validate
     * @return string|null Error message or null if valid
     */
    protected static function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format";
        }
        return null;
    }

    /**
     * Standardize phone number format
     * 
     * @param string $phone Phone number to format
     * @return string Formatted phone number
     */
    protected static function formatPhone($phone)
    {
        // Ensure phone number format consistency
        if (substr($phone, 0, 1) !== '0' && strlen($phone) === 10) {
            return '0' . $phone;
        }
        return $phone;
    }

    /**
     * Check if current user has required permission
     * 
     * @param string $permission Permission to check
     * @return bool Whether user has permission
     */
    protected static function userCan($permission)
    {
        return current_user_can($permission) || current_user_can('administrator');
    }

    /**
     * Get current user ID with fallback
     * 
     * @return int User ID
     */
    protected static function getCurrentUserId()
    {
        return get_current_user_id() ?: 0;
    }

    /**
     * Safe JSON decode with error handling
     * 
     * @param string $json JSON string to decode
     * @param bool $assoc Return associative array
     * @return mixed Decoded data or null on error
     */
    protected static function safeJsonDecode($json, $assoc = true)
    {
        if (!is_string($json)) {
            return null;
        }

        $decoded = json_decode($json, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Safe JSON encode with error handling
     * 
     * @param mixed $data Data to encode
     * @return string JSON string or error fallback
     */
    protected static function safeJsonEncode($data)
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            return json_encode(['error' => 'Unable to encode data']);
        }
        return $encoded;
    }

    /**
     * Execute database query with error handling
     * 
     * @param string $query SQL query
     * @param array $args Query arguments for prepare
     * @return mixed Query result or false on error
     */
    protected static function executeQuery($query, $args = [])
    {
        $wpdb = self::getWpdb();
        
        if (!empty($args)) {
            $prepared_query = $wpdb->prepare($query, $args);
        } else {
            $prepared_query = $query;
        }

        $result = $wpdb->get_results($prepared_query);

        if ($wpdb->last_error) {
            self::logError(get_called_class(), 'executeQuery', $wpdb->last_error, $query);
            return false;
        }

        return $result;
    }

    /**
     * Execute database query and get single value
     * 
     * @param string $query SQL query
     * @param array $args Query arguments for prepare
     * @return mixed Single value or null on error
     */
    protected static function getVar($query, $args = [])
    {
        $wpdb = self::getWpdb();
        
        if (!empty($args)) {
            $prepared_query = $wpdb->prepare($query, $args);
        } else {
            $prepared_query = $query;
        }

        $result = $wpdb->get_var($prepared_query);

        if ($wpdb->last_error) {
            self::logError(get_called_class(), 'getVar', $wpdb->last_error, $query);
            return null;
        }

        return $result;
    }

    /**
     * Check if user has required role
     * 
     * @param string|array $roles Role(s) to check
     * @param int|null $user_id User ID (current user if null)
     * @return bool Whether user has role
     */
    protected static function userHasRole($roles, $user_id = null)
    {
        if ($user_id === null) {
            $user_id = self::getCurrentUserId();
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        if (is_string($roles)) {
            $roles = [$roles];
        }

        return !empty(array_intersect($roles, $user->roles));
    }

    /**
     * Sanitize data for database insertion
     * 
     * @param array $data Data to sanitize
     * @param array $allowed_fields Allowed field names
     * @return array Sanitized data
     */
    protected static function sanitizeData($data, $allowed_fields = [])
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (!empty($allowed_fields) && !in_array($key, $allowed_fields)) {
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_email($value)) {
                $sanitized[$key] = sanitize_email($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = is_float($value) ? floatval($value) : intval($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}

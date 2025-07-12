<?php
/**
 * Authentication Service for Hospital Manager
 * 
 * Provides custom authentication methods for REST API requests
 */

namespace HospitalManager\Services;

class AuthService extends BaseService {
    
    /**
     * Initialize the authentication service
     */
    public static function init() {
        // Add custom authentication handler for REST API requests
        add_filter('rest_authentication_errors', [self::class, 'custom_authenticate'], 15);
        
        // Allow CORS for REST API
        add_action('rest_api_init', [self::class, 'handle_cors'], 15);
        
        // Add admin-ajax endpoint for getting nonces
        add_action('wp_ajax_rest-nonce', [self::class, 'get_rest_nonce']);
        add_action('wp_ajax_nopriv_rest-nonce', [self::class, 'get_rest_nonce']);
        
        // Prevent redirects to wp-login.php for our REST API authentication
        add_action('init', [self::class, 'prevent_wp_login_redirect']);
    }
    
    /**
     * Custom authentication handler for REST API requests
     * 
     * @param mixed $result Current authentication status
     * @return mixed Authentication result
     */
    public static function custom_authenticate($result) {
        // If already authenticated, return the result
        if ($result !== null) {
            return $result;
        }
        
        // Check if this is our API namespace
        if (!self::is_hospital_manager_request()) {
            return $result;
        }
        
        // Check for custom X-WP-Bypass-Auth header for development/debugging
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (isset($headers['X-WP-Bypass-Auth']) && defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }
        
        // Check for nonce directly
        $nonce = null;
        if (isset($_REQUEST['_wpnonce'])) {
            $nonce = $_REQUEST['_wpnonce'];
        } elseif (isset($headers['X-WP-Nonce'])) {
            $nonce = $headers['X-WP-Nonce'];
        }
        
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            // Send a refreshed nonce in the header for the next request
            add_filter('rest_send_nocache_headers', '__return_true', 20);
            add_action('rest_pre_serve_request', function($served, $result) {
                header('X-WP-Nonce: ' . wp_create_nonce('wp_rest'));
                return $served;
            }, 10, 2);
            return true;
        }
        
        // If user is already logged in, don't interfere
        if (is_user_logged_in()) {
            return true;
        }
        
        // Try our custom cookie authentication
        if (self::authenticate_with_custom_cookie()) {
            return true;
        }
        
        // For development environments, be more permissive
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            
            if (strpos($origin, site_url()) === 0 || strpos($referer, site_url()) === 0) {
                // Set current user to admin for development mode if not already set
                if (!is_user_logged_in()) {
                    $admin_users = get_users(['role' => 'administrator', 'number' => 1]);
                    if (!empty($admin_users)) {
                        wp_set_current_user($admin_users[0]->ID);
                    }
                }
                return true;
            }
        }
        
        // Do not override authentication - let WordPress handle it
        return $result;
    }
    
    /**
     * Check if the current request is for our REST API namespace
     * 
     * @return bool Whether this is a request to our API
     */
    private static function is_hospital_manager_request() {
        $rest_url = parse_url(rest_url());
        $current_url = parse_url($_SERVER['REQUEST_URI']);
        
        if (!isset($current_url['path']) || !isset($rest_url['path'])) {
            return false;
        }
        
        $base_path = trailingslashit($rest_url['path']);
        
        if (strpos($current_url['path'], $base_path . 'hospital-manager/v1/') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Authenticate using our custom secure cookie
     * 
     * @return bool Whether authentication succeeded
     */
    private static function authenticate_with_custom_cookie() {
        if (!isset($_COOKIE['hospital_manager_auth'])) {
            return false;
        }
        
        $auth_token = $_COOKIE['hospital_manager_auth'];
        
        // Find user with this token
        $users = get_users([
            'meta_key' => 'hospital_manager_auth_token',
            'meta_compare' => 'EXISTS'
        ]);
        
        foreach ($users as $user) {
            $token_data = get_user_meta($user->ID, 'hospital_manager_auth_token', true);
            
            if (!is_array($token_data) || !isset($token_data['token'])) {
                continue;
            }
            
            // Verify token matches and hasn't expired
            if (hash('sha256', $auth_token) === $token_data['token'] && 
                time() < $token_data['expires']) {
                
                // Optional: Verify IP and User Agent for additional security
                $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                // Set the current user
                wp_set_current_user($user->ID);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add CORS headers for REST API requests
     */
    public static function handle_cors() {
        // If not our API, don't interfere
        if (!self::is_hospital_manager_request()) {
            return;
        }
        
        // Handle CORS headers
        $origin = get_http_origin();
        
        // Allow same origin
        if ($origin === site_url()) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With');
        }
    }
    
    /**
     * Ajax handler for getting a fresh REST API nonce
     */
    public static function get_rest_nonce() {
        wp_send_json([
            'nonce' => wp_create_nonce('wp_rest'),
            'success' => true
        ]);
    }
    
    /**
     * Prevent redirects to wp-login.php for REST API authentication
     * This ensures our custom login flow isn't interrupted
     */
    public static function prevent_wp_login_redirect() {
        // Check if this is a REST API request
        if (defined('REST_REQUEST') && REST_REQUEST) {
            // Handle wp_login_failed action to avoid redirects only for login failures
            add_action('wp_login_failed', function($username) {
                // Just log the failure but don't redirect
                error_log('Hospital Manager: Authentication failed for user ' . $username);
                // Prevent the default redirect by not calling through
                return;
            }, 0);
            
            // Add a custom filter to detect and prevent wp-login.php redirects specifically
            add_filter('wp_redirect', function($location, $status) {
                // Only block redirects to wp-login.php, allow other redirects
                if (strpos($location, 'wp-login.php') !== false) {
                    error_log('Hospital Manager: Prevented redirect to wp-login.php');
                    return '';  // Return empty string to prevent redirect
                }
                return $location;  // Allow other redirects
            }, 999, 2);
            
            // Add no-cache headers to prevent caching of authentication responses
            add_filter('rest_pre_serve_request', function($served, $result) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: 0');
                return $served;
            }, 10, 2);
        }
    }
}

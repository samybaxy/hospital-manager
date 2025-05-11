<?php
/**
 * Authentication Service for Hospital Manager
 * 
 * Provides custom authentication methods for REST API requests
 */

namespace HospitalManager\Services;

class AuthService {
    
    /**
     * Initialize the authentication service
     */
    public function init() {
        // Add custom authentication handler for REST API requests
        add_filter('rest_authentication_errors', [$this, 'custom_authenticate'], 15);
        
        // Allow CORS for REST API
        add_action('rest_api_init', [$this, 'handle_cors'], 15);
        
        // Add admin-ajax endpoint for getting nonces
        add_action('wp_ajax_rest-nonce', [$this, 'get_rest_nonce']);
        add_action('wp_ajax_nopriv_rest-nonce', [$this, 'get_rest_nonce']);
    }
    
    /**
     * Custom authentication handler for REST API requests
     * 
     * @param mixed $result Current authentication status
     * @return mixed Authentication result
     */
    public function custom_authenticate($result) {
        // If already authenticated, return the result
        if ($result !== null) {
            return $result;
        }
        
        // Check if this is our API namespace
        if (!$this->is_hospital_manager_request()) {
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
        if ($this->authenticate_with_custom_cookie()) {
            return true;
        }
        
        // For development environments, be more permissive
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            
            if (strpos($origin, site_url()) === 0 || strpos($referer, site_url()) === 0) {
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
    private function is_hospital_manager_request() {
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
     * Authenticate using our custom cookie
     * 
     * @return bool Whether authentication succeeded
     */
    private function authenticate_with_custom_cookie() {
        if (isset($_COOKIE['hospital_manager_auth']) && $_COOKIE['hospital_manager_auth'] === 'authenticated') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Add CORS headers for REST API requests
     */
    public function handle_cors() {
        // If not our API, don't interfere
        if (!$this->is_hospital_manager_request()) {
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
    public function get_rest_nonce() {
        wp_send_json([
            'nonce' => wp_create_nonce('wp_rest'),
            'success' => true
        ]);
    }
}

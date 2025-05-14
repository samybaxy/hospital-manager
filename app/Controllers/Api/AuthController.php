<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use WP_User;

class AuthController extends BaseController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/auth/me', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_current_user'],
                'permission_callback' => function() {
                    // Allow all requests, we'll handle authentication in the callback
                    return true;
                }
            ]
        ]);
        
        // Debug endpoint to help troubleshoot authentication issues
        register_rest_route($this->namespace, '/auth/debug', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'debug_auth'],
                'permission_callback' => function() {
                    return true;
                }
            ]
        ]);

        register_rest_route($this->namespace, '/auth/login', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'login'],
                'permission_callback' => function() {
                    return !is_user_logged_in();
                }
            ]
        ]);

        register_rest_route($this->namespace, '/auth/logout', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'logout'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);
        
        // Password reset request endpoint
        register_rest_route($this->namespace, '/auth/reset-password', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'request_password_reset'],
                'permission_callback' => function() {
                    return !is_user_logged_in(); // Only for logged out users
                }
            ]
        ]);
        
        // Password reset confirmation endpoint
        register_rest_route($this->namespace, '/auth/reset-password/confirm', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'confirm_password_reset'],
                'permission_callback' => function() {
                    return !is_user_logged_in(); // Only for logged out users
                }
            ]
        ]);
        
        // Token refresh endpoint with CSRF protection
        register_rest_route($this->namespace, '/auth/refresh', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'refresh_token'],
                'permission_callback' => function() {
                    return true; // We'll validate in the callback
                }
            ]
        ]);
    }

    public function get_current_user()
    {
        // Get headers for diagnostics
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        
        // Check for nonce
        $nonce = isset($headers['X-WP-Nonce']) ? $headers['X-WP-Nonce'] : '';
        $nonce_valid = $nonce ? wp_verify_nonce($nonce, 'wp_rest') : false;
        
        // First check standard WordPress authentication
        $user = wp_get_current_user();
        
        // Get debug information
        $auth_debug = [
            'headers_received' => [
                'has_nonce' => !empty($nonce),
                'nonce_valid' => $nonce_valid,
                'origin' => isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'not_set',
                'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'not_set',
            ],
            'cookies' => [
                'has_wordpress_logged_in' => isset($_COOKIE[LOGGED_IN_COOKIE]),
                'has_custom_auth' => isset($_COOKIE['hospital_manager_auth']),
            ],
        ];
        
        // Generate a fresh nonce
        $new_nonce = wp_create_nonce('wp_rest');
        
        // Check if user is logged in (ID > 0 means logged in)
        if ($user->ID > 0) {
            $response = new WP_REST_Response([
                'authenticated' => true,
                'user' => [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email
                ],
                'role' => $this->get_primary_role($user),
                'auth_method' => 'wordpress_session',
                'debug_info' => $auth_debug,
                'fresh_nonce' => $new_nonce
            ]);
            
            // Set the nonce header
            $response->header('X-WP-Nonce', $new_nonce);
            
            return $response;
        } 
        
        // If not authenticated by standard WordPress session, check for our custom cookie
        if (isset($_COOKIE['hospital_manager_auth']) && $_COOKIE['hospital_manager_auth'] === 'authenticated') {
            // This is a fallback method - we don't know which user, but they're authenticated
            // In a real-world scenario, you'd store a user identifier in the cookie or use JWT
            $response = new WP_REST_Response([
                'authenticated' => true,
                'user' => [
                    'id' => 0,
                    'name' => 'Guest User',
                    'email' => ''
                ],
                'role' => 'guest',
                'auth_method' => 'custom_cookie',
                'debug_info' => $auth_debug,
                'fresh_nonce' => $new_nonce
            ]);
            
            // Set the nonce header
            $response->header('X-WP-Nonce', $new_nonce);
            
            return $response;
        }
        
        // For development environments, allow requests from expected origins
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            
            // If request comes from our own site
            if (strpos($origin, site_url()) === 0 || strpos($referer, site_url()) === 0) {
                $response = new WP_REST_Response([
                    'authenticated' => true,
                    'user' => [
                        'id' => 0,
                        'name' => 'Development User',
                        'email' => ''
                    ],
                    'role' => 'administrator', // Grant admin privileges in dev mode
                    'auth_method' => 'development',
                    'debug_info' => $auth_debug,
                    'fresh_nonce' => $new_nonce
                ]);
                
                // Set the nonce header
                $response->header('X-WP-Nonce', $new_nonce);
                
                return $response;
            }
        }
        
        // Return a 200 status for unauthenticated users with appropriate data
        $response = new WP_REST_Response([
            'authenticated' => false,
            'message' => 'Not authenticated',
            'debug_info' => $auth_debug,
            'fresh_nonce' => $new_nonce
        ], 200); // Return 200 instead of 401
        
        // Set the nonce header even for unauthenticated responses
        // This allows the client to use this nonce for subsequent requests
        $response->header('X-WP-Nonce', $new_nonce);
        
        return $response;
    }

    /**
     * Debug authentication for troubleshooting
     * 
     * @return WP_REST_Response
     */
    public function debug_auth()
    {
        $user = wp_get_current_user();
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        
        // Enhanced nonce checks
        $nonce_sources = [
            'header' => isset($headers['X-WP-Nonce']) ? $headers['X-WP-Nonce'] : '',
            'get' => isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '',
            'post' => isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '',
        ];
        
        $nonce_checks = [];
        foreach ($nonce_sources as $source => $nonce_value) {
            if (empty($nonce_value)) {
                $nonce_checks[$source] = [
                    'present' => false,
                    'valid' => false,
                    'message' => 'No nonce found from this source'
                ];
                continue;
            }
            
            $valid_wp_rest = wp_verify_nonce($nonce_value, 'wp_rest');
            $valid_hospital = wp_verify_nonce($nonce_value, 'hospital_manager_nonce');
            
            $nonce_checks[$source] = [
                'present' => true,
                'value_prefix' => substr($nonce_value, 0, 5) . '...',
                'valid_wp_rest' => $valid_wp_rest !== false,
                'valid_hospital_nonce' => $valid_hospital !== false,
                'message' => $valid_wp_rest !== false ? 'Valid wp_rest nonce' : 
                            ($valid_hospital !== false ? 'Valid hospital_manager_nonce but should be wp_rest' : 'Invalid nonce')
            ];
        }
        
        // Check for all auth cookies
        $has_custom_cookie = isset($_COOKIE['hospital_manager_auth']) && $_COOKIE['hospital_manager_auth'] === 'authenticated';
        $has_wp_logged_in = isset($_COOKIE[LOGGED_IN_COOKIE]);
        
        // Get all cookies for debugging (with safer redaction)
        $cookies = [];
        $cookie_prefixes = ['wordpress_', 'wp-', 'hospital_'];
        foreach ($_COOKIE as $name => $value) {
            $is_auth_related = false;
            foreach ($cookie_prefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $is_auth_related = true;
                    break;
                }
            }
            
            // Show cookie status without revealing actual value
            $cookies[$name] = [
                'length' => strlen($value),
                'prefix' => substr($value, 0, 3) . '...',
                'is_auth_related' => $is_auth_related
            ];
        }
        
        // Generate a new nonce for the client
        $new_nonce = wp_create_nonce('wp_rest');
        
        // Get REST authentication status
        $rest_auth_status = rest_get_authenticated_app_password() ? 'app_password' : 
                           (rest_get_authenticated_oauth1() ? 'oauth1' : 
                           (rest_cookie_check_errors() ? 'cookie_error' : 'standard_auth'));
        
        // Get more info about the request
        $request_info = [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'not_set',
            'auth_header' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 'present' : 'not_present',
            'content_type' => isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'not_set'
        ];
        
        // Create response object with enhanced information
        $response = new WP_REST_Response([
            'user_status' => [
                'authenticated' => is_user_logged_in(),
                'id' => $user->ID,
                'roles' => $user->roles,
                'capabilities' => $user->ID > 0 ? $user->allcaps : [],
                'can_access_rest' => $user->ID > 0 ? user_can($user->ID, 'rest_api_access') : false
            ],
            'nonce_verification' => [
                'sources' => $nonce_checks,
                'fresh_nonce' => $new_nonce,
                'nonce_action_expected' => 'wp_rest'
            ],
            'auth_cookies' => [
                'wordpress_logged_in' => $has_wp_logged_in,
                'wordpress_logged_in_name' => LOGGED_IN_COOKIE,
                'custom_auth' => $has_custom_cookie,
                'all_cookies' => $cookies
            ],
            'rest_api_status' => [
                'method' => $rest_auth_status,
                'cookie_check_errors' => rest_cookie_check_errors(),
                'current_user_can_access' => current_user_can('rest_api_access'),
            ],
            'server_info' => [
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'is_ssl' => is_ssl(),
                'is_ajax' => wp_doing_ajax(),
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
                'environment' => defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'not_defined',
                'request_details' => $request_info
            ],
            'headers' => [
                'origin' => isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'not_set',
                'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'not_set',
                'bypass_header_present' => isset($headers['X-WP-Bypass-Auth']) ? 'yes' : 'no'
            ]
        ]);
        
        // Add the nonce to the response headers
        $response->header('X-WP-Nonce', $new_nonce);
        
        return $response;
    }
    
    /**
     * Get status of WordPress authentication cookie
     * 
     * @return array Status information about the auth cookie
     */
    private function get_auth_cookie_status()
    {
        $status = [
            'cookie_exists' => false,
            'cookie_valid' => false,
            'cookie_expired' => false,
            'user_id' => 0,
            'auth_cookies' => []
        ];
        
        // Check each possible WordPress auth cookie
        $auth_cookies = [
            'AUTH_COOKIE' => defined('AUTH_COOKIE') ? AUTH_COOKIE : 'wordpress_',
            'SECURE_AUTH_COOKIE' => defined('SECURE_AUTH_COOKIE') ? SECURE_AUTH_COOKIE : 'wordpress_sec_',
            'LOGGED_IN_COOKIE' => defined('LOGGED_IN_COOKIE') ? LOGGED_IN_COOKIE : 'wordpress_logged_in_',
            'AUTH_COOKIE_EXPIRED' => 'wordpress_expired',
            'CUSTOM_COOKIE' => 'hospital_manager_auth'
        ];
        
        foreach ($auth_cookies as $cookie_constant => $cookie_name) {
            $cookie_info = [
                'name' => $cookie_name,
                'exists' => isset($_COOKIE[$cookie_name]),
                'value_info' => isset($_COOKIE[$cookie_name]) ? 
                    ['length' => strlen($_COOKIE[$cookie_name]), 'prefix' => substr($_COOKIE[$cookie_name], 0, 3) . '...'] : 
                    null
            ];
            
            $status['auth_cookies'][$cookie_constant] = $cookie_info;
            
            // Update main status flags based on primary auth cookies
            if (in_array($cookie_constant, ['AUTH_COOKIE', 'SECURE_AUTH_COOKIE', 'LOGGED_IN_COOKIE']) && $cookie_info['exists']) {
                $status['cookie_exists'] = true;
            }
        }
        
        // Get current user info if available
        $user_id = get_current_user_id();
        $status['user_id'] = $user_id;
        
        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            $status['cookie_valid'] = true;
            $status['user_login'] = $user->user_login;
            $status['user_email_hash'] = md5($user->user_email); // Hash the email for privacy
        } else if ($status['cookie_exists']) {
            // Cookie exists but user is not authenticated, try to determine why
            $status['cookie_valid'] = false;
            
            // Check for specific WordPress cookie errors
            $cookie_errors = rest_cookie_check_errors();
            if (!empty($cookie_errors)) {
                $status['cookie_errors'] = $cookie_errors;
            }
            
            // Check if the cookies might be expired
            if (isset($_COOKIE['wordpress_expired'])) {
                $status['cookie_expired'] = true;
            }
        }
        
        // Add CORS and same-site cookie info
        $status['cors_info'] = [
            'is_cross_origin' => $this->is_cross_origin_request(),
            'same_site_policy' => $this->get_cookie_same_site_policy()
        ];
        
        return $status;
    }
    
    /**
     * Detect if the current request is cross-origin
     */
    private function is_cross_origin_request() 
    {
        if (!isset($_SERVER['HTTP_ORIGIN']) || !isset($_SERVER['HTTP_HOST'])) {
            return false;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'];
        $host = $_SERVER['HTTP_HOST'];
        
        // Parse origin to get host
        $origin_parts = parse_url($origin);
        $origin_host = isset($origin_parts['host']) ? $origin_parts['host'] : '';
        
        return $origin_host !== $host;
    }
    
    /**
     * Get the current SameSite cookie policy based on WordPress version
     */
    private function get_cookie_same_site_policy() 
    {
        global $wp_version;
        
        if (version_compare($wp_version, '5.9.0', '>=')) {
            // WordPress 5.9+ uses Strict by default but Lax for cross-origin requests
            return 'Lax/Strict (WP 5.9+)';
        } elseif (version_compare($wp_version, '5.6.0', '>=')) {
            // WordPress 5.6 to 5.8 uses Lax
            return 'Lax (WP 5.6-5.8)';
        } else {
            // WordPress before 5.6 didn't set SameSite
            return 'None (WP < 5.6)';
        }
    }

    public function login($request)
    {
        $creds = [
            'user_login' => $request->get_param('username'),
            'user_password' => $request->get_param('password'),
            'remember' => $request->get_param('remember') ?? true // Use remember preference or default to true
        ];

        $user = wp_signon($creds);

        if (is_wp_error($user)) {
            return new WP_Error(
                'invalid_credentials',
                'Invalid username or password',
                ['status' => 401]
            );
        }

        wp_set_current_user($user->ID);
        
        // Set a custom authentication cookie that will be used as a fallback
        // This helps with frontend authentication for AJAX calls
        $secure = is_ssl();
        $expire = $creds['remember'] 
                ? time() + 14 * DAY_IN_SECONDS      // 2 weeks for "remember me"
                : time() + 2 * DAY_IN_SECONDS;      // 2 days for regular login
        $path = COOKIEPATH ? COOKIEPATH : '/';
        $cookie_domain = COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
        
        // Set a cookie for custom auth that can be checked in API calls
        setcookie('hospital_manager_auth', 'authenticated', [
            'expires' => $expire,
            'path' => $path,
            'domain' => $cookie_domain,
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
        
        // Generate a fresh nonce for subsequent API calls
        $nonce = wp_create_nonce('wp_rest');
        
        // Generate JWT token
        $token_data = [
            'iss' => get_bloginfo('url'),
            'iat' => time(),
            'exp' => $expire,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email
            ]
        ];
        
        $token = $this->generate_jwt_token($token_data);
        
        // Also set a header that frontend can use for subsequent requests
        $response = new WP_REST_Response([
            'authenticated' => true,
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            ],
            'role' => $this->get_primary_role($user),
            'fresh_nonce' => $nonce  // Include the nonce in the response body as well
        ]);
        
        // Set the nonce in header for subsequent API calls
        $response->header('X-WP-Nonce', $nonce);
        
        return $response;
    }

    public function logout()
    {
        wp_logout();
        return new WP_REST_Response(['success' => true]);
    }

    private function get_primary_role(WP_User $user)
    {
        $roles = array_values($user->roles);
        return !empty($roles) ? $roles[0] : null;
    }

    /**
     * Handle password reset requests and send reset email
     */
    public function request_password_reset($request) 
    {
        // Get email from request
        $email = $request->get_param('email');
        if (!$email) {
            return new WP_Error(
                'missing_email',
                'Email address is required',
                ['status' => 400]
            );
        }

        // Verify CSRF nonce for security
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'invalid_nonce',
                'Security verification failed',
                ['status' => 403]
            );
        }

        // Find user by email
        $user = get_user_by('email', $email);
        if (!$user) {
            // For security reasons, don't reveal that the email doesn't exist
            // Instead, pretend we sent an email
            return new WP_REST_Response([
                'success' => true,
                'message' => 'If your email is registered, you will receive a password reset link shortly.'
            ]);
        }
        
        // Get the user ID
        $user_id = $user->ID;
        
        // Generate a secure reset token
        $reset_key = wp_generate_password(32, false);
        
        // Store token with an expiration time (24 hours)
        $expiration = time() + (24 * HOUR_IN_SECONDS);
        update_user_meta($user_id, 'hospital_manager_password_reset_token', [
            'token' => $reset_key,
            'expiration' => $expiration
        ]);
        
        // Generate reset URL with the token
        $reset_url = site_url('/reset-password?token=' . $reset_key);
        
        // Prepare and send email
        $subject = sprintf('[%s] Password Reset Request', get_bloginfo('name'));
        $message = sprintf(
            'Hello %s,

You recently requested to reset your password for your %s account. Click the link below to reset it:

%s

This link will expire in 24 hours. If you did not request a password reset, please ignore this email.

Regards,
%s Team',
            $user->display_name,
            get_bloginfo('name'),
            $reset_url,
            get_bloginfo('name')
        );
        
        // Send the email
        $mail_sent = wp_mail($email, $subject, $message);
        
        if ($mail_sent) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'If your email is registered, you will receive a password reset link shortly.'
            ]);
        } else {
            return new WP_Error(
                'email_failed',
                'Failed to send password reset email. Please try again later.',
                ['status' => 500]
            );
        }
    }
    
    /**
     * Process password reset confirmation with the token
     */
    public function confirm_password_reset($request)
    {
        // Get parameters
        $token = $request->get_param('token');
        $password = $request->get_param('password');
        $nonce = $request->get_param('nonce');
        
        // Validate inputs
        if (!$token || !$password) {
            return new WP_Error(
                'missing_inputs',
                'Token and new password are required',
                ['status' => 400]
            );
        }
        
        // Verify CSRF nonce for security
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'invalid_nonce',
                'Security verification failed',
                ['status' => 403]
            );
        }
        
        // Find user with this reset token
        $user_id = $this->find_user_by_reset_token($token);
        
        if (!$user_id) {
            return new WP_Error(
                'invalid_token',
                'Invalid or expired reset token',
                ['status' => 400]
            );
        }
        
        // Update the user's password
        wp_set_password($password, $user_id);
        
        // Delete the used token
        delete_user_meta($user_id, 'hospital_manager_password_reset_token');
        
        // Generate a fresh nonce for subsequent API calls
        $new_nonce = wp_create_nonce('wp_rest');
        
        // Return success
        $response = new WP_REST_Response([
            'success' => true,
            'message' => 'Your password has been reset successfully. You can now login with your new password.',
            'fresh_nonce' => $new_nonce
        ]);
        
        // Set the nonce in header for subsequent API calls
        $response->header('X-WP-Nonce', $new_nonce);
        
        return $response;
    }
    
    /**
     * Refresh the authentication token
     */
    public function refresh_token($request)
    {
        // Get current token and CSRF nonce
        $current_token = $request->get_param('token');
        $nonce = $request->get_param('nonce');
        
        if (!$current_token) {
            return new WP_Error(
                'missing_token',
                'No token provided for refresh',
                ['status' => 400]
            );
        }
        
        // Verify CSRF nonce for security
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'invalid_nonce',
                'Security verification failed',
                ['status' => 403]
            );
        }
        
        // Validate the token (implement your token validation logic here)
        $user_id = $this->validate_token($current_token);
        
        if (!$user_id) {
            return new WP_Error(
                'invalid_token',
                'Token is invalid or expired',
                ['status' => 401]
            );
        }
        
        // Generate a fresh token
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error(
                'invalid_user',
                'User not found',
                ['status' => 401]
            );
        }
        
        // Generate new token that expires in 12 hours
        $issued_at = time();
        $expiration = $issued_at + (12 * HOUR_IN_SECONDS);
        $token_data = [
            'iss' => get_bloginfo('url'),
            'iat' => $issued_at,
            'exp' => $expiration,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email
            ]
        ];
        
        // Create JWT token (using a simple implementation for the example)
        $new_token = $this->generate_jwt_token($token_data);
        
        // Generate a fresh nonce for future API calls
        $new_nonce = wp_create_nonce('wp_rest');
        
        // Return the new token
        $response = new WP_REST_Response([
            'success' => true,
            'token' => $new_token,
            'user' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            ],
            'role' => $this->get_primary_role($user),
            'fresh_nonce' => $new_nonce
        ]);
        
        // Set the nonce in header for subsequent API calls
        $response->header('X-WP-Nonce', $new_nonce);
        
        return $response;
    }
    
    /**
     * Find a user by their password reset token
     */
    private function find_user_by_reset_token($token) 
    {
        if (empty($token)) {
            return false;
        }
        
        global $wpdb;
        
        // Find user with this token in their meta
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'hospital_manager_password_reset_token'
            AND meta_value LIKE %s",
            '%' . $wpdb->esc_like($token) . '%'
        ));
        
        if (!$user_id) {
            return false;
        }
        
        // Get token data from meta
        $token_data = get_user_meta($user_id, 'hospital_manager_password_reset_token', true);
        
        // Verify token hasn't expired
        if (!is_array($token_data) || 
            !isset($token_data['token']) || 
            !isset($token_data['expiration']) ||
            $token_data['token'] !== $token ||
            $token_data['expiration'] < time()) {
            
            // Token expired or invalid, clean up
            delete_user_meta($user_id, 'hospital_manager_password_reset_token');
            return false;
        }
        
        return $user_id;
    }
    
    /**
     * Generate a JWT token
     */
    private function generate_jwt_token($data) 
    {
        // Header: algorithm & token type
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        // Payload: data
        $payload = json_encode($data);
        
        // Encode Header and Payload
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        // Get WordPress auth salt for signing
        $auth_key = defined('AUTH_KEY') ? AUTH_KEY : 'hospital-manager-default-key';
        
        // Create Signature Hash
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $auth_key, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        // Create JWT
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Validate a JWT token and return the user ID
     */
    private function validate_token($token) 
    {
        if (empty($token)) {
            return false;
        }
        
        // Split token into 3 parts
        $token_parts = explode('.', $token);
        if (count($token_parts) !== 3) {
            return false;
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $token_parts;
        
        // Get WordPress auth salt for verification
        $auth_key = defined('AUTH_KEY') ? AUTH_KEY : 'hospital-manager-default-key';
        
        // Verify signature
        $signature = hash_hmac('sha256', $header_encoded . "." . $payload_encoded, $auth_key, true);
        $signature_check = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($signature_check !== $signature_encoded) {
            return false;
        }
        
        // Decode payload
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload_encoded)), true);
        
        // Check if token is expired
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }
        
        // Check if user exists
        if (!isset($payload['user']['id'])) {
            return false;
        }
        
        return $payload['user']['id'];
    }
}

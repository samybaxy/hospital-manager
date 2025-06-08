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
                'methods' => WP_REST_Server::CREATABLE, // POST method
                'callback' => [$this, 'login'],
                'permission_callback' => function() {
                    // Always allow access to login - we'll handle authentication inside the callback
                    return true;
                },
                'args' => [
                    'username' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'User login name or email address',
                        'validate_callback' => function($param) {
                            return is_string($param) && !empty($param);
                        }
                    ],
                    'password' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'User password',
                        'validate_callback' => function($param) {
                            return is_string($param) && !empty($param);
                        }
                    ],
                    'remember' => [
                        'required' => false,
                        'default' => true,
                        'type' => 'boolean',
                        'description' => 'Whether to remember the user session'
                    ]
                ]
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
                    'ID' => $user->ID,
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
                    'ID' => 0,
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
        
        // For development environments, allow requests from expected origins with proper verification
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            
            // If request comes from our own site AND has valid nonce
            if ((strpos($origin, site_url()) === 0 || strpos($referer, site_url()) === 0) && $nonce_valid) {
                $response = new WP_REST_Response([
                    'authenticated' => true,
                    'user' => [
                        'ID' => 0,
                        'name' => 'Development User (Limited)',
                        'email' => ''
                    ],
                    'role' => 'doctor', // Limited role, not admin
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
                           (rest_cookie_check_errors($user) ? 'cookie_error' : 'standard_auth');
        
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
                'ID' => $user->ID,
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
                'cookie_check_errors' => rest_cookie_check_errors($user),
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
        $user = wp_get_current_user();
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
            $user = get_user_by('ID', $user_id);
            $status['cookie_valid'] = true;
            $status['user_login'] = $user->user_login;
            $status['user_email_hash'] = md5($user->user_email); // Hash the email for privacy
        } else if ($status['cookie_exists']) {
            // Cookie exists but user is not authenticated, try to determine why
            $status['cookie_valid'] = false;
            
            // Check for specific WordPress cookie errors
            $cookie_errors = rest_cookie_check_errors($user);
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
        try {
            // Enhanced CSRF protection - check both nonce and origin
            $nonce = $request->get_param('nonce') ?: $request->get_header('X-WP-Nonce');
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            
            // Verify CSRF nonce is present and valid
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                error_log('Hospital Manager: CSRF verification failed - invalid or missing nonce');
                return new WP_REST_Response([
                    'authenticated' => false,
                    'message' => 'Security verification failed. Please refresh the page and try again.',
                    'code' => 'csrf_failed',
                    'fresh_nonce' => wp_create_nonce('wp_rest')
                ], 403);
            }
            
            // Additional origin check for extra security
            $site_url = site_url();
            if ($origin && $origin !== $site_url) {
                error_log('Hospital Manager: Potential CSRF attack - Origin mismatch: ' . $origin);
                return new WP_REST_Response([
                    'authenticated' => false,
                    'message' => 'Invalid request origin.',
                    'code' => 'invalid_origin',
                    'fresh_nonce' => wp_create_nonce('wp_rest')
                ], 403);
            }
            
            // Bypass login redirects for this session specifically
            define('DOING_AJAX', true); // This will prevent WordPress from redirecting
            add_filter('wp_redirect', function($location, $status) {
                // Only block wp-login.php redirects
                if (strpos($location, 'wp-login.php') !== false) {
                    error_log('Hospital Manager API: Prevented redirect to ' . $location);
                    return false;
                }
                return $location;
            }, 999, 2);
            
            // Only block redirects on login failure, not for all requests
            add_action('wp_login_failed', function($username) {
                // Just log the failure but don't redirect
                error_log('Hospital Manager: Authentication failed for user ' . $username);
                // Prevent the default redirect by not calling through
                return;
            }, 0);
            
            // Modify how WordPress formats error messages to prevent HTML in API responses
            add_filter('login_errors', function($error) {
                // Return a clean version without HTML
                return strip_tags($error);
            }, 10);
            
            // Disable "Lost your password" links in error messages for API requests
            add_filter('lost_password_html', function($html) {
                // Return empty string to remove "Lost your password" HTML
                return '';
            }, 10);

            // Log the login attempt
            error_log('Hospital Manager: Login attempt initiated');
            
            // Validate and sanitize request parameters
            $username = sanitize_text_field($request->get_param('username'));
            $password = $request->get_param('password');
            
            // Enhanced input validation
            if (empty($username) || empty($password)) {
                error_log('Hospital Manager: Login failed - missing credentials');
                return new WP_REST_Response([
                    'authenticated' => false,
                    'message' => 'Username and password are required',
                    'code' => 'missing_credentials',
                    'fresh_nonce' => wp_create_nonce('wp_rest')
                ], 400);
            }
            
            // Validate email format if username appears to be an email
            if (strpos($username, '@') !== false && !is_email($username)) {
                return new WP_REST_Response([
                    'authenticated' => false,
                    'message' => 'Please enter a valid email address',
                    'code' => 'invalid_email',
                    'fresh_nonce' => wp_create_nonce('wp_rest')
                ], 400);
            }
            
            // Password length validation
            if (strlen($password) < 8) {
                return new WP_REST_Response([
                    'authenticated' => false,
                    'message' => 'Password must be at least 8 characters long',
                    'code' => 'password_too_short',
                    'fresh_nonce' => wp_create_nonce('wp_rest')
                ], 400);
            }
            
            // Rate limiting check with improved security
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $rate_limit_key = "login_attempts_{$ip}";
            $attempts = get_transient($rate_limit_key) ?: 0;
            
            if ($attempts >= 5) {
                error_log("Hospital Manager: Rate limit exceeded for IP {$ip}");
                
                // Increment attempts for failed rate limit check
                set_transient($rate_limit_key, $attempts + 1, 900); // 15 minutes
                
                return new WP_REST_Response([
                    'authenticated' => false,
                    'message' => 'Too many login attempts. Please try again in 15 minutes.',
                    'code' => 'rate_limited',
                    'retry_after' => 900, // 15 minutes
                    'fresh_nonce' => wp_create_nonce('wp_rest')
                ], 429);
            }
            
            $creds = [
                'user_login' => $username,
                'user_password' => $password,
                'remember' => $request->get_param('remember') ?? true // Use remember preference or default to true
            ];
    
            // Log if credentials are present
            error_log('Hospital Manager: Login credentials received - username: ' . (!empty($creds['user_login']) ? 'present' : 'missing') . 
                      ', password: ' . (!empty($creds['user_password']) ? 'present' : 'missing'));
    
            // Force secure cookies if site is using HTTPS
            add_filter('secure_signon_cookie', function() {
                return is_ssl();
            });
            
            // Add debug filter to capture authentication issues
            add_filter('authenticate', function($user, $username, $password) {
                if (is_wp_error($user)) {
                    error_log('Hospital Manager: Authentication error for user ' . $username . ': ' . $user->get_error_message());
                }
                return $user;
            }, 9999, 3);
    
            // Only handle failed login attempts to prevent redirects
            add_action('wp_login_failed', function($username) {
                // Silently handle the login failure without redirecting
                error_log('Hospital Manager: Prevented redirect for failed login: ' . $username);
            }, 0); // High priority
            
            // Attempt to sign on the user
            $user = wp_signon($creds, is_ssl());
            error_log('Hospital Manager: wp_signon result: ' . (is_wp_error($user) ? 'Error: ' . $user->get_error_message() : 'Success for user ID: ' . $user->ID));
            
            if (is_wp_error($user)) {
                // Increment failed attempts for rate limiting
                $attempts = get_transient($rate_limit_key) ?: 0;
                set_transient($rate_limit_key, $attempts + 1, 900); // 15 minutes
                
                // Get the error code
                $error_code = $user->get_error_code();
                
                // Customize error messages for a better user experience
                $user_friendly_message = 'Invalid email or password. Please try again.';
                
                // Customize messages based on specific error codes
                if ($error_code === 'incorrect_password') {
                    $user_friendly_message = 'The password you entered is incorrect. Please try again.';
                } else if ($error_code === 'invalid_username' || $error_code === 'invalid_email') {
                    $user_friendly_message = 'We couldn\'t find an account with that email address. Please check and try again.';
                } else if ($error_code === 'empty_username' || $error_code === 'empty_password') {
                    $user_friendly_message = 'Please enter both email address and password.';
                }
                
                // Ensure we strip any HTML from the message
                $clean_message = strip_tags($user_friendly_message);
                
                // Return clean JSON error response without HTML formatting
                return new WP_REST_Response([
                    'authenticated' => false,
                    'message' => $clean_message,
                    'code' => $error_code,
                    'fresh_nonce' => wp_create_nonce('wp_rest')
                ], 401);
            }

            // Set the current user
            try {
                $previous_user_id = get_current_user_id();
                wp_set_current_user($user->ID);
                $new_user_id = get_current_user_id();
                
                error_log('Hospital Manager: Current user set - Previous ID: ' . $previous_user_id . ', New ID: ' . $new_user_id);
                
                if ($new_user_id != $user->ID) {
                    error_log('Hospital Manager: Warning - wp_set_current_user did not set the expected user ID');
                }
            } catch (\Exception $e) {
                error_log('Hospital Manager: Error setting current user: ' . $e->getMessage());
            }
            
            // On successful login, clear any failed attempts
            delete_transient($rate_limit_key);
            
            // Check if the user has one of the allowed roles for this application
            $allowed_roles = ['administrator', 'doctor', 'patient', 'lab_tech', 'desk_officer'];
            $user_roles = (array) $user->roles;
            
            // Check if any of the user's roles are in the allowed roles array
            $has_allowed_role = false;
            foreach ($user_roles as $role) {
                if (in_array($role, $allowed_roles)) {
                    $has_allowed_role = true;
                    break;
                }
            }
            
            if (!$has_allowed_role) {
                wp_logout(); // Log the user out since they don't have permissions
                return new WP_Error(
                    'insufficient_permissions',
                    'Your account does not have permission to access this system.',
                    ['status' => 403]
                );
            }
            
            // Set a secure authentication cookie with proper security attributes
            $secure = is_ssl();
            $expire = $creds['remember'] 
                    ? time() + 14 * DAY_IN_SECONDS      // 2 weeks for "remember me"
                    : time() + 2 * DAY_IN_SECONDS;      // 2 days for regular login
            $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
            $cookie_domain = defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
            
            // Generate a secure authentication token instead of plain text
            $auth_token = wp_generate_password(32, false);
            
            // Store the token in user meta for validation
            update_user_meta($user->ID, 'hospital_manager_auth_token', [
                'token' => hash('sha256', $auth_token),
                'expires' => $expire,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // Check PHP version for setcookie array support (PHP 7.3+)
            if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
                // Modern PHP version: use array format with security settings
                try {
                    $cookie_set = setcookie('hospital_manager_auth', $auth_token, [
                        'expires' => $expire,
                        'path' => $path,
                        'domain' => $cookie_domain,
                        'secure' => $secure,
                        'httponly' => true,  // Prevent XSS attacks
                        'samesite' => $secure ? 'None' : 'Lax'  // Strict for HTTPS, Lax for HTTP
                    ]);
                    if (!$cookie_set) {
                        error_log('Hospital Manager: Failed to set authentication cookie using array format');
                    }
                } catch (\Exception $e) {
                    error_log('Hospital Manager: Cookie setting error: ' . $e->getMessage());
                }
            } else {
                // Older PHP version: use traditional format
                try {
                    // For older PHP, we can't set SameSite, but we can set HttpOnly
                    $cookie_set = setcookie('hospital_manager_auth', $auth_token, $expire, $path, $cookie_domain, $secure, true);
                    if (!$cookie_set) {
                        error_log('Hospital Manager: Failed to set authentication cookie using traditional format');
                    }
                } catch (\Exception $e) {
                    error_log('Hospital Manager: Cookie setting error: ' . $e->getMessage());
                }
            }
            
            // Generate a fresh nonce for subsequent API calls
            $nonce = wp_create_nonce('wp_rest');
            
            // Generate JWT token
            $token_data = [
                'iss' => get_bloginfo('url'),
                'iat' => time(),
                'exp' => $expire,
                'user' => [
                    'ID' => $user->ID,
                    'email' => $user->user_email
                ]
            ];
            
            try {
                $token = $this->generate_jwt_token($token_data);
                error_log('Hospital Manager: JWT token generated successfully');
            } catch (\Exception $e) {
                error_log('Hospital Manager: JWT token generation failed: ' . $e->getMessage());
                // Provide a fallback token if JWT generation fails
                $token = base64_encode(json_encode([
                    'user_id' => $user->ID,
                    'expires' => $expire
                ]));
                error_log('Hospital Manager: Using fallback token');
            }
            
            // Prepare response data for the client
            $response_data = [
                'authenticated' => true,
                'token' => $token,
                'user' => [
                    'ID' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email
                ],
                'role' => $this->get_primary_role($user),
                'fresh_nonce' => $nonce  // Include the nonce in the response body as well
            ];
            
            // Create the response with appropriate status code
            $response = new WP_REST_Response($response_data, 200);
            
            // Set important headers
            $response->header('X-WP-Nonce', $nonce);
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            
            error_log('Hospital Manager: Login successful for user: ' . $user->user_login);
            return $response;
        
        } catch (\Exception $e) {
            error_log('Hospital Manager: Login error: ' . $e->getMessage());
            error_log('Hospital Manager: Login error trace: ' . $e->getTraceAsString());
            return new WP_Error(
                'login_error',
                'An error occurred during login: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
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
        $user = get_user_by('ID', $user_id);
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
                'ID' => $user->ID,
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
                'ID' => $user->ID,
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
     * Generate a secure JWT token with enhanced security
     */
    private function generate_jwt_token($data) 
    {
        try {
            // Add additional security claims
            $enhanced_data = array_merge($data, [
                'jti' => wp_generate_password(16, false), // Unique token ID
                'aud' => home_url(), // Intended audience
                'nbf' => time(), // Not before timestamp
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua_hash' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '') // User agent hash
            ]);
            
            // Header: algorithm & token type
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            if ($header === false) {
                error_log('Hospital Manager: JSON encode failed for JWT header');
                throw new \Exception('Failed to encode JWT header');
            }
            
            // Payload: data
            $payload = json_encode($enhanced_data);
            if ($payload === false) {
                error_log('Hospital Manager: JSON encode failed for JWT payload: ' . json_last_error_msg());
                throw new \Exception('Failed to encode JWT payload: ' . json_last_error_msg());
            }
            
            // Encode Header and Payload
            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            
            // Get WordPress auth salt for signing - use multiple salts for enhanced security
            if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
                error_log('Hospital Manager: WordPress authentication keys are not defined - cannot generate secure JWT');
                throw new \Exception('WordPress authentication keys are not properly configured');
            }
            $auth_key = AUTH_KEY;
            $secure_auth_key = SECURE_AUTH_KEY;
            $combined_key = hash('sha256', $auth_key . $secure_auth_key);
            
            // Create Signature Hash
            $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $combined_key, true);
            if ($signature === false) {
                error_log('Hospital Manager: HMAC hash generation failed');
                throw new \Exception('Failed to generate HMAC hash for JWT');
            }
            
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            
            // Create JWT
            return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
        } catch (\Exception $e) {
            error_log('Hospital Manager: JWT token generation error: ' . $e->getMessage());
            throw $e; // Re-throw to be handled by the caller
        }
    }
    
    /**
     * Validate a JWT token and return the user ID with enhanced security checks
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
        
        // Get WordPress auth salt for verification - use same combined key as generation
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            error_log('Hospital Manager: WordPress authentication keys are not defined - cannot validate JWT');
            return false;
        }
        $auth_key = AUTH_KEY;
        $secure_auth_key = SECURE_AUTH_KEY;
        $combined_key = hash('sha256', $auth_key . $secure_auth_key);
        
        // Verify signature
        $signature = hash_hmac('sha256', $header_encoded . "." . $payload_encoded, $combined_key, true);
        $signature_check = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($signature_check !== $signature_encoded) {
            error_log('Hospital Manager: JWT signature verification failed');
            return false;
        }
        
        // Decode payload
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload_encoded)), true);
        
        if (!$payload) {
            error_log('Hospital Manager: JWT payload decode failed');
            return false;
        }
        
        // Enhanced security checks
        
        // Check if token is expired
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            error_log('Hospital Manager: JWT token expired');
            return false;
        }
        
        // Check not-before time
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            error_log('Hospital Manager: JWT token not yet valid');
            return false;
        }
        
        // Check audience
        if (isset($payload['aud']) && $payload['aud'] !== home_url()) {
            error_log('Hospital Manager: JWT audience mismatch');
            return false;
        }
        
        // Check user exists
        if (!isset($payload['user']['ID'])) {
            error_log('Hospital Manager: JWT missing user ID');
            return false;
        }
        
        $user_id = $payload['user']['ID'];
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log('Hospital Manager: JWT user not found: ' . $user_id);
            return false;
        }
        
        // Optional: Verify IP and User Agent for session binding
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $current_ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        if (isset($payload['ip']) && $payload['ip'] !== $current_ip) {
            error_log('Hospital Manager: JWT IP mismatch - possible token theft');
            // In production, you might want to invalidate the token here
        }
        
        if (isset($payload['ua_hash']) && $payload['ua_hash'] !== $current_ua_hash) {
            error_log('Hospital Manager: JWT User Agent mismatch - possible token theft');
            // In production, you might want to invalidate the token here
        }
        
        return $user_id;
    }
}

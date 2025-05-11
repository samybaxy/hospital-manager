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
            'remember' => true
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
        $expire = time() + 14 * DAY_IN_SECONDS;
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
        
        // Also set a header that frontend can use for subsequent requests
        $response = new WP_REST_Response([
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
}

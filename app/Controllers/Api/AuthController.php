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
        $user = wp_get_current_user();
        
        // Check if user is logged in (ID > 0 means logged in)
        if ($user->ID > 0) {
            return new WP_REST_Response([
                'authenticated' => true,
                'user' => [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email
                ],
                'role' => $this->get_primary_role($user)
            ]);
        } else {
            // Return a 200 status for unauthenticated users with appropriate data
            return new WP_REST_Response([
                'authenticated' => false,
                'message' => 'Not authenticated'
            ], 200); // Return 200 instead of 401
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
        
        // Also set a header that frontend can use for subsequent requests
        $response = new WP_REST_Response([
            'user' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            ],
            'role' => $this->get_primary_role($user)
        ]);
        
        // Set a nonce that can be used for subsequent API calls
        $response->header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        
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

<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class UserController extends BaseController
{
    public function register_routes()
    {
        // Change password endpoint
        register_rest_route($this->namespace, '/user/change-password', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'change_password'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
                'args' => [
                    'current_password' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Current password',
                        'validate_callback' => function($param) {
                            return is_string($param) && !empty($param);
                        }
                    ],
                    'new_password' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'New password',
                        'validate_callback' => function($param) {
                            return is_string($param) && strlen($param) >= 8;
                        }
                    ]
                ]
            ]
        ]);
    }

    /**
     * Handle password change with security checks
     */
    public function change_password($request)
    {
        // Verify CSRF nonce for security
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'invalid_nonce',
                'Security verification failed',
                ['status' => 403]
            );
        }

        $current_password = $request->get_param('current_password');
        $new_password = $request->get_param('new_password');
        
        // Get current user
        $user = wp_get_current_user();
        if (!$user || $user->ID === 0) {
            return new WP_Error(
                'not_authenticated',
                'You must be logged in to change your password',
                ['status' => 401]
            );
        }

        // Verify current password
        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            // Log failed password change attempt
            error_log('Hospital Manager: Failed password change attempt for user: ' . $user->user_login);
            
            return new WP_Error(
                'incorrect_password',
                'Current password is incorrect',
                ['status' => 400]
            );
        }

        // Password strength validation
        if (strlen($new_password) < 8) {
            return new WP_Error(
                'weak_password',
                'Password must be at least 8 characters long',
                ['status' => 400]
            );
        }

        // Additional password strength checks
        if (!preg_match('/[A-Z]/', $new_password)) {
            return new WP_Error(
                'weak_password',
                'Password must contain at least one uppercase letter',
                ['status' => 400]
            );
        }

        if (!preg_match('/[a-z]/', $new_password)) {
            return new WP_Error(
                'weak_password',
                'Password must contain at least one lowercase letter',
                ['status' => 400]
            );
        }

        if (!preg_match('/[0-9]/', $new_password)) {
            return new WP_Error(
                'weak_password',
                'Password must contain at least one number',
                ['status' => 400]
            );
        }

        // Check if new password is different from current
        if (wp_check_password($new_password, $user->user_pass, $user->ID)) {
            return new WP_Error(
                'same_password',
                'New password must be different from current password',
                ['status' => 400]
            );
        }

        // Rate limiting for password changes (max 3 per hour)
        $rate_limit_key = "password_change_attempts_{$user->ID}";
        $attempts = get_transient($rate_limit_key) ?: 0;
        
        if ($attempts >= 3) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many password change attempts. Please try again later.',
                ['status' => 429]
            );
        }

        // Update password
        wp_set_password($new_password, $user->ID);

        // Increment rate limiting counter
        set_transient($rate_limit_key, $attempts + 1, HOUR_IN_SECONDS);

        // Log successful password change
        error_log('Hospital Manager: Password changed successfully for user: ' . $user->user_login);

        // Invalidate all existing sessions except current one
        $sessions = \WP_Session_Tokens::get_instance($user->ID);
        $current_token = wp_get_session_token();
        $sessions->destroy_others($current_token);

        // Generate fresh nonce
        $fresh_nonce = wp_create_nonce('wp_rest');

        // Return success
        $response = new WP_REST_Response([
            'success' => true,
            'message' => 'Password changed successfully',
            'fresh_nonce' => $fresh_nonce
        ]);

        $response->header('X-WP-Nonce', $fresh_nonce);
        return $response;
    }
}

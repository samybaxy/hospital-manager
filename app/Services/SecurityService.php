<?php
/**
 * Security Service for Hospital Manager
 * 
 * Implements various security measures including rate limiting, brute force protection,
 * and security monitoring for the authentication system.
 */

namespace HospitalManager\Services;

class SecurityService {
    
    /**
     * Rate limiting constants
     */
    const MAX_LOGIN_ATTEMPTS = 5;          // Max failed login attempts
    const LOCKOUT_DURATION = 15 * 60;      // 15 minutes lockout
    const RATE_LIMIT_WINDOW = 60;          // 1 minute rate limit window
    const MAX_REQUESTS_PER_MINUTE = 10;     // Max requests per minute per IP
    
    /**
     * Initialize the security service
     */
    public function init() {
        // Add rate limiting to login endpoints
        add_action('rest_api_init', [$this, 'add_security_middleware'], 5);
        
        // Monitor failed login attempts
        add_action('wp_login_failed', [$this, 'handle_failed_login']);
        
        // Clean up expired lockouts
        add_action('init', [$this, 'cleanup_expired_lockouts']);
        
        // Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);
    }
    
    /**
     * Add security middleware to REST API
     */
    public function add_security_middleware() {
        add_filter('rest_pre_dispatch', [$this, 'rate_limit_check'], 10, 3);
    }
    
    /**
     * Rate limiting check for REST API requests
     */
    public function rate_limit_check($result, $server, $request) {
        // Only apply to our authentication endpoints
        $route = $request->get_route();
        if (!$this->is_auth_endpoint($route)) {
            return $result;
        }
        
        $ip = $this->get_client_ip();
        
        // Check if IP is currently locked out
        if ($this->is_ip_locked_out($ip)) {
            return new \WP_REST_Response([
                'error' => 'Too many failed login attempts. Please try again later.',
                'retry_after' => $this->get_lockout_time_remaining($ip)
            ], 429);
        }
        
        // Check rate limiting
        if ($this->is_rate_limited($ip)) {
            return new \WP_REST_Response([
                'error' => 'Too many requests. Please slow down.',
                'retry_after' => 60
            ], 429);
        }
        
        // Log the request
        $this->log_api_request($ip, $route);
        
        return $result;
    }
    
    /**
     * Handle failed login attempts
     */
    public function handle_failed_login($username) {
        $ip = $this->get_client_ip();
        
        // Skip if this is not our API endpoint
        if (!$this->is_hospital_manager_request()) {
            return;
        }
        
        $this->record_failed_attempt($ip, $username);
        
        // Check if we should lock out this IP
        $attempts = $this->get_failed_attempts($ip);
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->lockout_ip($ip);
            
            // Log security event
            error_log(sprintf(
                'Hospital Manager Security: IP %s locked out after %d failed login attempts for user %s',
                $ip, $attempts, $username
            ));
            
            // Optional: Send security notification email
            $this->send_security_alert($ip, $username, $attempts);
        }
    }
    
    /**
     * Check if route is an authentication endpoint
     */
    private function is_auth_endpoint($route) {
        $auth_endpoints = [
            '/hospital-manager/v1/auth/login',
            '/hospital-manager/v1/auth/reset-password',
            '/hospital-manager/v1/auth/reset-password/confirm'
        ];
        
        foreach ($auth_endpoints as $endpoint) {
            if (strpos($route, $endpoint) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if this is a Hospital Manager API request
     */
    private function is_hospital_manager_request() {
        $rest_url = parse_url(rest_url());
        $current_url = parse_url($_SERVER['REQUEST_URI']);
        
        if (!isset($current_url['path']) || !isset($rest_url['path'])) {
            return false;
        }
        
        $base_path = trailingslashit($rest_url['path']);
        return strpos($current_url['path'], $base_path . 'hospital-manager/v1/') === 0;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        // Check for various headers that might contain the real IP
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated list (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if IP is currently locked out
     */
    private function is_ip_locked_out($ip) {
        $lockout_time = get_transient("hospital_manager_lockout_{$ip}");
        return $lockout_time !== false;
    }
    
    /**
     * Get remaining lockout time for IP
     */
    private function get_lockout_time_remaining($ip) {
        $lockout_end = get_transient("hospital_manager_lockout_{$ip}");
        if ($lockout_end === false) {
            return 0;
        }
        
        return max(0, $lockout_end - time());
    }
    
    /**
     * Check if IP is rate limited
     */
    private function is_rate_limited($ip) {
        $requests = get_transient("hospital_manager_rate_{$ip}");
        
        if ($requests === false) {
            return false;
        }
        
        return $requests >= self::MAX_REQUESTS_PER_MINUTE;
    }
    
    /**
     * Log API request for rate limiting
     */
    private function log_api_request($ip, $route) {
        $key = "hospital_manager_rate_{$ip}";
        $requests = get_transient($key);
        
        if ($requests === false) {
            set_transient($key, 1, self::RATE_LIMIT_WINDOW);
        } else {
            set_transient($key, $requests + 1, self::RATE_LIMIT_WINDOW);
        }
    }
    
    /**
     * Record failed login attempt
     */
    private function record_failed_attempt($ip, $username) {
        $key = "hospital_manager_failed_{$ip}";
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            $attempts = 0;
        }
        
        $attempts++;
        set_transient($key, $attempts, self::LOCKOUT_DURATION);
        
        // Also log the attempt with timestamp and username
        $log_key = "hospital_manager_attempts_{$ip}";
        $log_data = get_transient($log_key) ?: [];
        $log_data[] = [
            'username' => $username,
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // Keep only last 10 attempts
        $log_data = array_slice($log_data, -10);
        set_transient($log_key, $log_data, self::LOCKOUT_DURATION);
    }
    
    /**
     * Get number of failed attempts for IP
     */
    private function get_failed_attempts($ip) {
        $attempts = get_transient("hospital_manager_failed_{$ip}");
        return $attempts !== false ? $attempts : 0;
    }
    
    /**
     * Lock out an IP address
     */
    private function lockout_ip($ip) {
        $lockout_end = time() + self::LOCKOUT_DURATION;
        set_transient("hospital_manager_lockout_{$ip}", $lockout_end, self::LOCKOUT_DURATION);
    }
    
    /**
     * Send security alert email
     */
    private function send_security_alert($ip, $username, $attempts) {
        // Only send if email notifications are enabled
        if (!apply_filters('hospital_manager_send_security_alerts', true)) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        $subject = sprintf('[%s] Security Alert: Multiple Failed Login Attempts', get_bloginfo('name'));
        
        $message = sprintf(
            'A security event has been detected on your Hospital Manager system.

IP Address: %s
Username: %s
Failed Attempts: %d
Timestamp: %s
User Agent: %s

The IP address has been temporarily locked out for %d minutes.

If this was a legitimate user, they should wait for the lockout period to expire before trying again.

This is an automated security notification.',
            $ip,
            $username,
            $attempts,
            date('Y-m-d H:i:s'),
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            self::LOCKOUT_DURATION / 60
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Clean up expired lockouts and rate limits
     */
    public function cleanup_expired_lockouts() {
        // WordPress handles transient cleanup automatically
        // This method is here for future custom cleanup if needed
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only add headers for our API requests
        if (!$this->is_hospital_manager_request()) {
            return;
        }
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (basic)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
    }
    
    /**
     * Get security statistics for monitoring
     */
    public function get_security_stats() {
        global $wpdb;
        
        // This would be enhanced with proper database logging in production
        return [
            'total_lockouts_today' => 0, // Would query actual data
            'failed_attempts_last_hour' => 0,
            'blocked_ips' => []
        ];
    }
    
    /**
     * Manually unlock an IP address (for admin use)
     */
    public function unlock_ip($ip) {
        delete_transient("hospital_manager_lockout_{$ip}");
        delete_transient("hospital_manager_failed_{$ip}");
        delete_transient("hospital_manager_rate_{$ip}");
        delete_transient("hospital_manager_attempts_{$ip}");
        
        error_log("Hospital Manager Security: IP {$ip} manually unlocked");
    }
    
    /**
     * Check if an IP should be permanently banned (too many lockouts)
     */
    private function should_permanent_ban($ip) {
        // Count how many times this IP has been locked out
        $ban_key = "hospital_manager_ban_count_{$ip}";
        $ban_count = get_option($ban_key, 0);
        
        // If more than 5 lockouts in 24 hours, consider permanent ban
        return $ban_count > 5;
    }
}

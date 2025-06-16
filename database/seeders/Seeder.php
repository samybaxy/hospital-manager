<?php

namespace HospitalManager\Database\Seeders;

abstract class Seeder
{
    protected $faker;

    public function __construct()
    {
        // Try to initialize faker if available, otherwise use fallback methods
        if (class_exists('Faker\Factory')) {
            try {
                $this->faker = \Faker\Factory::create('en_NG');
            } catch (\Exception $e) {
                $this->faker = null;
            }
        } else {
            $this->faker = null;
        }
    }

    /**
     * Run the seeder
     * @return bool True if seeding was successful, false otherwise
     */
    abstract public function run();

    /**
     * Get WordPress user IDs to use for seeding
     * 
     * @param int $count Number of users to get
     * @param string $role Role to filter by (optional)
     * @return array User IDs
     */
    protected function getUserIds($count, $role = null)
    {
        $args = [
            'fields' => 'ID',
            'number' => $count,
        ];
        
        if ($role) {
            $args['role'] = $role;
        }
        
        $users = get_users($args);
        return $users ?: [];
    }
    
    /**
     * Create a WordPress user with the specified role
     * 
     * @param string $role Role to assign to the user
     * @return int User ID
     */
    protected function createUser($role)
    {
        // Generate random names if faker is not available
        if ($this->faker) {
            $username = strtolower($this->faker->userName());
            $email = $this->faker->email();
            $firstName = $this->faker->firstName();
            $lastName = $this->faker->lastName();
        } else {
            // Fallback if faker is not available
            $username = strtolower('user_' . uniqid());
            $email = $username . '@example.com';
            $firstName = 'User';
            $lastName = uniqid();
        }
        
        // Create user
        $user_id = wp_create_user(
            $username,
            'password', // Use a default password for all seeded users
            $email
        );
        
        if (is_wp_error($user_id)) {
            return null;
        }
        
        // Set role
        $user = new \WP_User($user_id);
        $user->set_role($role);
        
        // Add some user meta
        update_user_meta($user_id, 'first_name', $firstName);
        update_user_meta($user_id, 'last_name', $lastName);
        
        return $user_id;
    }
    
    /**
     * Print progress message with color formatting
     * 
     * @param string $message The message to display
     * @param string $type The type of message: 'info', 'success', 'warning', 'error'
     */
    protected function log($message, $type = 'info')
    {
        $colors = [
            'info' => "",             // No color (default terminal color)
            'success' => "\033[32m",  // Green
            'warning' => "\033[33m",  // Yellow  
            'error' => "\033[31m",    // Red
            'reset' => "\033[0m"      // Reset
        ];
        
        $color = isset($colors[$type]) ? $colors[$type] : $colors['info'];
        $reset = ($color !== "") ? $colors['reset'] : "";
        echo $color . $message . $reset . PHP_EOL;
    }
    
    /**
     * Verify that a user can authenticate with the given credentials
     * 
     * @param string $username Username or email
     * @param string $password Password
     * @return bool True if authentication succeeds
     */
    protected function verifyUserCredentials($username, $password)
    {
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            $this->log("Authentication failed for {$username}: " . $user->get_error_message(), 'error');
            return false;
        }
        
        $this->log("Authentication successful for {$username}", 'success');
        return true;
    }
    
    /**
     * Create a user with proper password handling
     * 
     * @param string $username Username
     * @param string $password Plain text password
     * @param string $email Email address
     * @param string $role User role
     * @param array $meta Additional user meta
     * @return int|false User ID on success, false on failure
     */
    protected function createUserSafely($username, $password, $email, $role = 'subscriber', $meta = [])
    {
        // Check if user already exists
        if (username_exists($username)) {
            $this->log("Username '{$username}' already exists", 'warning');
            return false;
        }
        
        if (email_exists($email)) {
            $this->log("Email '{$email}' already exists", 'warning');
            return false;
        }
        
        // Create the user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $this->log("Error creating user '{$username}': " . $user_id->get_error_message(), 'error');
            return false;
        }
        
        // Set role
        $user = new \WP_User($user_id);
        $user->set_role($role);
        
        // Add user meta
        foreach ($meta as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
        
        $this->log("Created user '{$username}' with role '{$role}'", 'success');
        
        // Verify the password works
        if ($this->verifyUserCredentials($username, $password)) {
            $this->log("Password verification successful for '{$username}'", 'success');
        } else {
            $this->log("Password verification failed for '{$username}' - this might indicate a problem", 'warning');
        }
        
        return $user_id;
    }
}

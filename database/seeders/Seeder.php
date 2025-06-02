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
        $username = strtolower($this->faker->userName());
        $email = $this->faker->email();
        
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
        update_user_meta($user_id, 'first_name', $this->faker->firstName());
        update_user_meta($user_id, 'last_name', $this->faker->lastName());
        
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
}

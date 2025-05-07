<?php

namespace HospitalManager\Database\Seeders;

use Faker\Factory;

abstract class Seeder
{
    protected $faker;
    protected $wpdb;
    
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->faker = Factory::create('en_NG'); // Using Nigerian locale for Faker
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
     * Print progress message
     */
    protected function log($message)
    {
        echo "\033[32m" . "[Seeder] " . $message . "\033[0m\n";
    }
}

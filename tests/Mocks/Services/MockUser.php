<?php

namespace HospitalManager\Tests\Mocks\Services;

/**
 * Mock User functions
 */
class MockUser 
{
    private static $users = [];
    
    /**
     * Add a test user
     */
    public static function addUser($ID, $data = []) 
    {
        $userData = array_merge([
            'ID' => $ID,
            'display_name' => 'Test User ' . $ID,
            'user_email' => 'user' . $ID . '@example.com'
        ], $data);
        
        self::$users[$ID] = (object)$userData;
    }
    
    /**
     * Get user by field
     */
    public static function getUser($field, $value) 
    {
        if ($field === 'ID') {
            return isset(self::$users[$value]) ? self::$users[$value] : false;
        }
        
        foreach (self::$users as $user) {
            if (isset($user->$field) && $user->$field === $value) {
                return $user;
            }
        }
        
        return false;
    }
    
    /**
     * Reset for testing
     */
    public static function reset() 
    {
        self::$users = [];
    }
}
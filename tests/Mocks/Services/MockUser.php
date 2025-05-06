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
    public static function addUser($id, $data = []) 
    {
        $userData = array_merge([
            'ID' => $id,
            'display_name' => 'Test User ' . $id,
            'user_email' => 'user' . $id . '@example.com'
        ], $data);
        
        self::$users[$id] = (object)$userData;
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
<?php

namespace HospitalManager\Tests\Mocks\Services;

/**
 * Mock Notification class for testing
 */
class MockNotification 
{
    public $id;
    public $user_id;
    public $type;
    public $title;
    public $message;
    public $data;
    public $read;
    public $created_at;
    
    private static $mockNotifications = [];
    private static $nextId = 1;
    
    /**
     * Constructor
     */
    public function __construct($data = []) 
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
    
    /**
     * Save method
     */
    public function save() 
    {
        if (!isset($this->id)) {
            $this->id = self::$nextId++;
        }
        
        self::$mockNotifications[$this->id] = $this;
        return true;
    }
    
    /**
     * Find a notification by ID
     */
    public static function find($id) 
    {
        return isset(self::$mockNotifications[$id]) ? self::$mockNotifications[$id] : null;
    }
    
    /**
     * Where clause for finding notifications
     */
    public static function where($column, $value) 
    {
        $results = [];
        foreach (self::$mockNotifications as $notification) {
            if (isset($notification->$column) && $notification->$column === $value) {
                $results[] = $notification;
            }
        }
        return $results;
    }
    
    /**
     * Reset for testing
     */
    public static function reset() 
    {
        self::$mockNotifications = [];
        self::$nextId = 1;
    }
    
    /**
     * Count notifications matching criteria
     */
    public static function count($criteria = []) 
    {
        $count = 0;
        foreach (self::$mockNotifications as $notification) {
            $match = true;
            foreach ($criteria as $key => $value) {
                if (!isset($notification->$key) || $notification->$key !== $value) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) {
                $count++;
            }
        }
        return $count;
    }
}
<?php

namespace HospitalManager\Tests\Mocks\Services;

/**
 * Mock query builder for audit logs
 */
class MockQueryBuilder 
{
    protected $results = [];
    
    public function __construct($results) 
    {
        $this->results = $results;
    }
    
    public function get() 
    {
        return $this->results;
    }
    
    public function first() 
    {
        return count($this->results) > 0 ? $this->results[0] : null;
    }
    
    public function count() 
    {
        return count($this->results);
    }
    
    public function orderBy($column, $direction = 'asc') 
    {
        // Just return the same query builder for chaining
        return $this;
    }
}
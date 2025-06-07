<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

abstract class BaseModel extends PostModel
{
    use FindTrait;

    protected $primaryKey = 'ID';
    protected $tableName; // Name portion after prefix
    
    /**
     * Get the table name with the correct prefix
     */
    public function getTable()
    {
        global $wpdb;
        if (empty($this->table) && !empty($this->tableName)) {
            $this->table = $wpdb->prefix . $this->tableName;
        }
        return $this->table;
    }
    
    /**
     * Set the tableName property to be used with dynamic prefix
     */
    public function setTableName($name)
    {
        $this->tableName = $name;
        $this->table = null; // Reset table so getTable() will recompute with prefix
        return $this;
    }

    /**
     * Create table for this model
     */
    public static function createTable()
    {
        // This method should be implemented by child classes
        // to create their respective database tables
    }
    
    /**
     * Override PostModel's load_attributes to prevent issues with load_meta for custom tables
     */
    public function load_attributes( $attributes )
    {
        if ( !empty( $attributes ) ) {
            $this->attributes = $attributes;
            // Don't call load_meta() for custom database tables
        }
    }
    
    /**
     * Override PostModel's load_meta to prevent issues with custom tables
     */
    public function load_meta()
    {
        // Do nothing for custom database tables
        // Meta loading is handled differently for custom tables
    }
}

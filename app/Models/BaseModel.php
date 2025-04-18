<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

abstract class BaseModel extends PostModel
{
    use FindTrait;

    protected $primaryKey = 'id';

    /**
     * Create table for this model
     */
    public static function createTable()
    {
        // This method should be implemented by child classes
        // to create their respective database tables
    }
}

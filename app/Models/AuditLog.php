<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class AuditLog extends PostModel
{
    use FindTrait;

    protected $table = 'wp_hm_audit_logs';
    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'changes',
        'created_at'
    ];
}
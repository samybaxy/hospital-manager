<?php

namespace HospitalManager\Services;

use HospitalManager\Models\AuditLog;

class AuditLogger
{
    public static function log($action, $entityType, $entityId, $changes)
    {
        $userId = get_current_user_id();
        
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => json_encode($changes)
        ]);
    }
}
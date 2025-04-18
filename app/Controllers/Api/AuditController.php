<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\AuditLog;

class AuditController extends BaseController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/audit-logs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_logs'],
                'permission_callback' => function() {
                    return current_user_can('view_audit_log');
                }
            ]
        ]);

        register_rest_route($this->namespace, '/audit-logs/patient/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_patient_logs'],
                'permission_callback' => function() {
                    return current_user_can('view_audit_log') || current_user_can('doctor');
                }
            ]
        ]);
    }

    public function get_logs($request)
    {
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 20;
        
        $logs = AuditLog::orderBy('created_at', 'DESC')
            ->paginate($per_page, ['*'], 'page', $page);
            
        // Enhance logs with user details
        $logs->each(function($log) {
            $user = get_userdata($log->user_id);
            $log->user_name = $user ? $user->display_name : 'Unknown User';
            $log->user_role = $user ? implode(', ', $user->roles) : 'Unknown Role';
        });

        return new WP_REST_Response([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total()
            ]
        ]);
    }

    public function get_patient_logs($request)
    {
        $patient_id = $request->get_param('id');
        
        $logs = AuditLog::where('entity_type', 'patient')
            ->where('entity_id', $patient_id)
            ->orderBy('created_at', 'DESC')
            ->get();

        // Enhance logs with user details
        $logs->each(function($log) {
            $user = get_userdata($log->user_id);
            $log->user_name = $user ? $user->display_name : 'Unknown User';
            $log->user_role = $user ? implode(', ', $user->roles) : 'Unknown Role';
        });

        return new WP_REST_Response($logs);
    }
}

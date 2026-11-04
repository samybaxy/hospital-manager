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
                    return self::can_view_audit_log();
                }
            ]
        ]);

        register_rest_route($this->namespace, '/audit-logs/patient/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_patient_logs'],
                'permission_callback' => function() {
                    return self::can_view_audit_log() || current_user_can('doctor');
                }
            ]
        ]);
    }

    /**
     * RoleService grants 'access_audit_log' (administrators). 'view_audit_log' was the
     * old name this controller checked — no role is given it, so the endpoint was
     * forbidden for everyone. Accept both for backward compatibility.
     */
    private static function can_view_audit_log()
    {
        return current_user_can('access_audit_log') || current_user_can('view_audit_log');
    }

    public function get_logs($request)
    {
        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = max(1, min(100, (int) ($request->get_param('per_page') ?? 20)));

        $logs = AuditLog::orderBy('created_at', 'DESC')
            ->paginate($per_page, ['*'], 'page', $page);

        return new WP_REST_Response([
            'data' => array_map([$this, 'format_log'], $logs->items),
            'meta' => [
                'current_page' => $logs->currentPage,
                'last_page' => $logs->lastPage,
                'per_page' => $logs->perPage,
                'total' => $logs->total
            ]
        ]);
    }

    public function get_patient_logs($request)
    {
        $patient_id = (int) $request->get_param('ID');

        // Patient record entries + related records (labs, radiology, visits, ...)
        $logs = AuditLog::getForPatient($patient_id);

        return new WP_REST_Response(array_map([$this, 'format_log'], $logs));
    }

    /**
     * Flatten an AuditLog model to an array with the acting user's name/role.
     *
     * (The query builder returns `each` as a closure *property*; calling
     * $logs->each(...) is a method call and fatalled. Models also serialise to "{}".)
     */
    private function format_log($log)
    {
        $data = $log->getAttributes();

        foreach (['changes', 'details'] as $json_field) {
            if (isset($data[$json_field]) && is_string($data[$json_field])) {
                $decoded = json_decode($data[$json_field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$json_field] = $decoded;
                }
            }
        }

        // Fields the Audit Logs page renders (AuditLogs.jsx reads log.timestamp and
        // log.ip_address); the table stores these as created_at and details.ip_address.
        $data['timestamp'] = $data['created_at'] ?? null;
        $data['ip_address'] = is_array($data['details'] ?? null) ? ($data['details']['ip_address'] ?? null) : null;

        $user = !empty($data['user_id']) ? get_userdata($data['user_id']) : false;
        $data['user_name'] = $user ? $user->display_name : 'Unknown User';
        $data['user_role'] = $user ? implode(', ', $user->roles) : 'Unknown Role';

        return $data;
    }
}

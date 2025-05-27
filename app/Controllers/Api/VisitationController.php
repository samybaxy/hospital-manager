<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use HospitalManager\Models\Visitation;

class VisitationController extends BaseController 
{
    public function register_routes() 
    {
        register_rest_route($this->namespace, '/visitations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_visitations'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_visitation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'add_visitation');
                },
            ]
        ]);
    }

    public function get_visitations($request) 
    {
        try {
            $user = wp_get_current_user();
            $params = $request->get_params();

            // Add pagination parameters
            $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
            $per_page = isset($params['per_page']) ? min(100, max(1, intval($params['per_page']))) : 20;
            $offset = ($page - 1) * $per_page;

            // Apply patient role restrictions
            if (in_array('patient', $user->roles)) {
                // Patients can only see their own visitations
                $params['patient_id'] = $user->ID;
            }

            global $wpdb;
            $visitations_table = $wpdb->prefix . 'hm_visitations';
            $patients_table = $wpdb->prefix . 'hm_patients';
            $doctors_table = $wpdb->prefix . 'hm_doctors';

            // Build WHERE clause
            $where_conditions = ['1=1'];
            $where_values = [];

            if (!empty($params['patient_id'])) {
                $where_conditions[] = 'v.patient_id = %d';
                $where_values[] = intval($params['patient_id']);
            }
            if (!empty($params['doctor_id'])) {
                $where_conditions[] = 'v.doctor_id = %d';
                $where_values[] = intval($params['doctor_id']);
            }
            if (!empty($params['date'])) {
                $where_conditions[] = 'DATE(v.date) = %s';
                $where_values[] = $params['date'];
            }

            // Add search functionality
            if (!empty($params['search'])) {
                $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
                $where_conditions[] = '(
                    CONCAT(p.first_name, " ", p.last_name) LIKE %s OR
                    CONCAT(d.first_name, " ", d.last_name) LIKE %s OR
                    v.complaint LIKE %s OR
                    v.diagnosis LIKE %s OR
                    v.treatment LIKE %s
                )';
                $where_values = array_merge($where_values, [$search_term, $search_term, $search_term, $search_term, $search_term]);
            }

            $where_clause = implode(' AND ', $where_conditions);

            // Build ORDER BY clause
            $allowed_sort_fields = ['date', 'patient_name', 'doctor_name', 'complaint', 'diagnosis'];
            $sort_field = !empty($params['sort_by']) && in_array($params['sort_by'], $allowed_sort_fields) ? $params['sort_by'] : 'date';
            $sort_order = !empty($params['sort_order']) && strtolower($params['sort_order']) === 'asc' ? 'ASC' : 'DESC';
            
            // Map sort fields to actual column names
            $sort_mapping = [
                'date' => 'v.date',
                'patient_name' => 'patient_name',
                'doctor_name' => 'doctor_name',
                'complaint' => 'v.complaint',
                'diagnosis' => 'v.diagnosis'
            ];
            $order_by = $sort_mapping[$sort_field] . ' ' . $sort_order;

            // Count total records for pagination
            $count_query = "
                SELECT COUNT(*) 
                FROM {$visitations_table} v 
                WHERE {$where_clause}
            ";
            $total = $wpdb->get_var($where_values ? $wpdb->prepare($count_query, $where_values) : $count_query);

            // Get visitations with patient and doctor names
            $query = "
                SELECT 
                    v.*,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                FROM {$visitations_table} v
                LEFT JOIN {$patients_table} p ON v.patient_id = p.ID
                LEFT JOIN {$doctors_table} d ON v.doctor_id = d.ID
                WHERE {$where_clause}
                ORDER BY {$order_by}
                LIMIT %d OFFSET %d
            ";

            $query_values = array_merge($where_values, [$per_page, $offset]);
            $visitations = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);

            // Format response data
            $response_data = [
                'data' => $visitations ?: [],
                'total' => intval($total),
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil($total / $per_page),
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $per_page, $total)
            ];

            return $this->success_response($response_data, 'Visitations retrieved successfully');
        } catch (\Exception $e) {
            error_log('VisitationController get_visitations error: ' . $e->getMessage());
            return $this->error_response('Failed to fetch visitations: ' . $e->getMessage(), 500);
        }
    }

    public function create_visitation($request) 
    {
        $visitation = Visitation::create($request->get_params());
        return new WP_REST_Response($visitation, 201);
    }
}
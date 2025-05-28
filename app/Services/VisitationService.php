<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Visitation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use Exception;

/**
 * Service class for visitation-related business logic
 */
class VisitationService
{
    /**
     * Get visitations with enhanced filtering and pagination
     * 
     * @param array $query_params Query parameters including pagination, sorting, and filtering
     * @return array Visitation data with related information
     */
    public static function getVisitations(array $query_params = [])
    {
        global $wpdb;

        try {
            $user = wp_get_current_user();
            $params = $query_params;

            // Add pagination parameters
            $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
            $per_page = isset($params['per_page']) ? min(100, max(1, intval($params['per_page']))) : 20;
            $offset = ($page - 1) * $per_page;

            // Apply patient role restrictions
            if (in_array('patient', $user->roles)) {
                // Patients can only see their own visitations
                $params['patient_id'] = $user->ID;
            }

            // Initialize table names
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

            // Handle date range filtering
            if (!empty($params['date_from'])) {
                $where_conditions[] = 'DATE(v.date) >= %s';
                $where_values[] = sanitize_text_field($params['date_from']);
            }
            
            if (!empty($params['date_to'])) {
                $where_conditions[] = 'DATE(v.date) <= %s';
                $where_values[] = sanitize_text_field($params['date_to']);
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

            // Handle status filtering if applicable
            if (!empty($params['status'])) {
                $where_conditions[] = 'v.status = %s';
                $where_values[] = sanitize_text_field($params['status']);
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
                LEFT JOIN {$patients_table} p ON v.patient_id = p.ID
                LEFT JOIN {$doctors_table} d ON v.doctor_id = d.ID
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

            // Handle database errors
            if ($wpdb->last_error) {
                error_log('Database error in VisitationService::getVisitations: ' . $wpdb->last_error);
                error_log('Query: ' . $wpdb->last_query);
                throw new Exception('Database query failed');
            }

            // Format visitations data
            $formatted_visitations = array_map(function($visitation) {
                return [
                    'ID' => (int) $visitation['ID'],
                    'patient_id' => (int) $visitation['patient_id'],
                    'doctor_id' => (int) $visitation['doctor_id'],
                    'date' => $visitation['date'],
                    'time' => $visitation['time'] ?? null,
                    'complaint' => $visitation['complaint'] ?? '',
                    'diagnosis' => $visitation['diagnosis'] ?? '',
                    'treatment' => $visitation['treatment'] ?? '',
                    'notes' => $visitation['notes'] ?? '',
                    'status' => $visitation['status'] ?? '',
                    'vital_signs' => $visitation['vital_signs'] ?? null,
                    'created_at' => $visitation['created_at'],
                    'updated_at' => $visitation['updated_at'],
                    'patient_name' => $visitation['patient_name'],
                    'doctor_name' => $visitation['doctor_name']
                ];
            }, $visitations ?: []);

            // Calculate pagination metadata
            $last_page = ceil($total / $per_page);

            // Debug logging
            error_log('VisitationService::getVisitations executed: Found ' . count($formatted_visitations) . ' visitations, Total: ' . $total);

            // Format response data consistent with other services
            return [
                'visitations' => (object)[
                    'items' => $formatted_visitations,
                    'currentPage' => (int)$page,
                    'lastPage' => $last_page,
                    'perPage' => (int)$per_page,
                    'total' => (int)$total
                ],
                'meta' => [
                    'current_page' => (int)$page,
                    'last_page' => $last_page,
                    'per_page' => (int)$per_page,
                    'total' => (int)$total
                ]
            ];

        } catch (\Exception $e) {
            error_log('Exception in VisitationService::getVisitations: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new Exception('Failed to retrieve visitations: ' . $e->getMessage());
        }
    }
}
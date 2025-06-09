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
            // Initialize parameters
            $params = $query_params['params'] ?? $query_params;
            error_log('VisitationService::getVisitations called with params: ' . print_r($params, true));
            
            // Default parameters
            $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
            $perPage = isset($params['per_page']) ? min(100, max(1, intval($params['per_page']))) : 20;
            
            // Initialize tables
            $visitations_table = $wpdb->prefix . 'hm_visitations';
            $patients_table = $wpdb->prefix . 'hm_patients';
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            
            // Base query - join with patient and doctor tables to get names
            $query = "
                SELECT 
                    v.*,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                FROM {$visitations_table} v
                LEFT JOIN {$patients_table} p ON v.patient_id = p.ID
                LEFT JOIN {$doctors_table} d ON v.doctor_id = d.ID
                WHERE 1=1
            ";
            
            $countQuery = "SELECT COUNT(v.ID) FROM {$visitations_table} v WHERE 1=1";
            $values = [];
            
            // Apply role-based restrictions
            $user = wp_get_current_user();
            if (in_array('patient', $user->roles)) {
                // Patients can only see their own visitations
                $query .= " AND v.patient_id = %d";
                $countQuery .= " AND v.patient_id = %d";
                $values[] = Patient::findByUserId( $user->ID )->ID;
                error_log('VisitationService::getVisitations - Role restriction: patient can only see own visitations');
            }
            
            if (!empty($params['doctor_id'])) {
                $query .= " AND v.doctor_id = %d";
                $countQuery .= " AND v.doctor_id = %d";
                $values[] = intval($params['doctor_id']);
                error_log('VisitationService::getVisitations - Filter by doctor: ' . $params['doctor_id']);
            }
            
            if (!empty($params['date'])) {
                $query .= " AND DATE(v.date) = %s";
                $countQuery .= " AND DATE(v.date) = %s";
                $values[] = $params['date'];
                error_log('VisitationService::getVisitations - Filter by exact date: ' . $params['date']);
            }
            
            // Handle date range filtering
            if (!empty($params['date_from'])) {
                $query .= " AND DATE(v.date) >= %s";
                $countQuery .= " AND DATE(v.date) >= %s";
                $values[] = sanitize_text_field($params['date_from']);
                error_log('VisitationService::getVisitations - Filter by date from: ' . $params['date_from']);
            }
            
            if (!empty($params['date_to'])) {
                $query .= " AND DATE(v.date) <= %s";
                $countQuery .= " AND DATE(v.date) <= %s";
                $values[] = sanitize_text_field($params['date_to']);
                error_log('VisitationService::getVisitations - Filter by date to: ' . $params['date_to']);
            }
            
            // Add search functionality
            if (!empty($params['search'])) {
                $search = '%' . $wpdb->esc_like($params['search']) . '%';
                $query .= " AND (
                    CONCAT(p.first_name, ' ', p.last_name) LIKE %s OR
                    CONCAT(d.first_name, ' ', d.last_name) LIKE %s 
                )";
                $countQuery .= " AND (
                    v.patient_id IN (SELECT ID FROM {$patients_table} WHERE CONCAT(first_name, ' ', last_name) LIKE %s) OR
                    v.doctor_id IN (SELECT ID FROM {$doctors_table} WHERE CONCAT(first_name, ' ', last_name) LIKE %s) 
                )";
                $values[] = $search;
                $values[] = $search;
                error_log('VisitationService::getVisitations - Filter by search: ' . print_r($params, true));
            }
            
            // Handle status filtering if applicable
            if (!empty($params['status'])) {
                $query .= " AND v.status = %s";
                $countQuery .= " AND v.status = %s";
                $values[] = sanitize_text_field($params['status']);
                error_log('VisitationService::getVisitations - Filter by status: ' . $params['status']);
            }
            
            // Get total count for pagination
            $count_values = $values; // Copy values for count query
            $prepared_count = $wpdb->prepare($countQuery, $count_values);
            $total = (int)$wpdb->get_var($prepared_count);
            
            // Apply sorting
            $sortField = !empty($params['sort_by']) ? $params['sort_by'] : 'date';
            $sortOrder = !empty($params['sort_order']) && strtolower($params['sort_order']) === 'asc' ? 'ASC' : 'DESC';
            
            // Validate sort field to prevent SQL injection
            $allowed_sort_fields = ['date', 'patient_name', 'doctor_name', 'complaint', 'diagnosis', 'created_at'];
            if (!in_array($sortField, $allowed_sort_fields)) {
                $sortField = 'date'; // Default to date if invalid sort field
            }
            
            // Special case for patient name sorting
            if ($sortField === 'patient_name') {
                $query .= " ORDER BY patient_name {$sortOrder}, v.date DESC";
            } 
            // Special case for doctor name sorting
            else if ($sortField === 'doctor_name') {
                $query .= " ORDER BY doctor_name {$sortOrder}, v.date DESC";
            }
            // Standard field sorting
            else {
                // Add 'v.' prefix if the field is from the main visitations table
                $prefix = in_array($sortField, ['date', 'complaint', 'diagnosis', 'created_at']) ? 'v.' : '';
                $query .= " ORDER BY {$prefix}{$sortField} {$sortOrder}";
            }
            
            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT %d OFFSET %d";
            $values[] = $perPage;
            $values[] = $offset;
            
            // Execute query
            $prepared_query = $wpdb->prepare($query, $values);
            $items = $wpdb->get_results($prepared_query);
            error_log('Query returned ' . count($items) . ' visitations');
            
            // Process results
            $visitations = [];
            if ($items) {
                foreach ($items as $item) {
                    $visitationArray = (array)$item;
                    
                    // Ensure numeric values are properly typed
                    $visitationArray['ID'] = (int) $visitationArray['ID'];
                    $visitationArray['patient_id'] = (int) $visitationArray['patient_id'];
                    $visitationArray['doctor_id'] = (int) $visitationArray['doctor_id'];
                    
                    // Handle nullable or empty fields with defaults
                    $visitationArray['time'] = $visitationArray['time'] ?? null;
                    $visitationArray['complaint'] = $visitationArray['complaint'] ?? '';
                    $visitationArray['diagnosis'] = $visitationArray['diagnosis'] ?? '';
                    $visitationArray['treatment'] = $visitationArray['treatment'] ?? '';
                    $visitationArray['notes'] = $visitationArray['notes'] ?? '';
                    $visitationArray['status'] = $visitationArray['status'] ?? '';
                    
                    // Handle JSON fields if needed
                    if (!empty($visitationArray['vital_signs']) && is_string($visitationArray['vital_signs'])) {
                        $decoded = json_decode($visitationArray['vital_signs'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $visitationArray['vital_signs'] = $decoded;
                        }
                    } else {
                        $visitationArray['vital_signs'] = null;
                    }
                    
                    $visitations[] = $visitationArray;
                }
            }
            
            // Calculate pagination info
            $last_page = ceil($total / $perPage);
            
            // Handle database errors
            if ($wpdb->last_error) {
                error_log('Database error in VisitationService::getVisitations: ' . $wpdb->last_error);
                error_log('Query: ' . $wpdb->last_query);
                throw new Exception('Database query failed');
            }
            
            // Debug logging
            error_log('VisitationService::getVisitations executed: Found ' . count($visitations) . ' visitations, Total: ' . $total);
            
            // Return data in a format consistent with existing API
            return [
                'visitations' => (object)[
                    'items' => $visitations,
                    'currentPage' => (int)$page,
                    'lastPage' => $last_page,
                    'perPage' => (int)$perPage,
                    'total' => (int)$total
                ],
                'meta' => [
                    'current_page' => (int)$page,
                    'last_page' => $last_page,
                    'per_page' => (int)$perPage,
                    'total' => (int)$total
                ]
            ];

        } catch (\Exception $e) {
            error_log('Exception in VisitationService::getVisitations: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new Exception('Failed to retrieve visitations: ' . $e->getMessage());
        }
    }
    
    /**
     * Get a single visitation record by ID with related information
     * 
     * @param int $id The visitation ID
     * @return array|false Visitation data with related information, or false if not found
     * @throws Exception If there's a database error
     */
    public static function getVisitation(int $id)
    {
        global $wpdb;

        try {
            // Initialize tables
            $visitations_table = $wpdb->prefix . 'hm_visitations';
            $patients_table = $wpdb->prefix . 'hm_patients';
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            
            // Query to get visitation with patient and doctor information
            $query = "
                SELECT 
                    v.*,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                FROM {$visitations_table} v
                LEFT JOIN {$patients_table} p ON v.patient_id = p.ID
                LEFT JOIN {$doctors_table} d ON v.doctor_id = d.ID
                WHERE v.ID = %d
                LIMIT 1
            ";
            
            $prepared_query = $wpdb->prepare($query, $id);
            $visitation = $wpdb->get_row($prepared_query);
            
            // Check if visitation exists
            if (!$visitation) {
                return false;
            }
            
            // Convert to array
            $visitationArray = (array)$visitation;
            
            // Ensure numeric values are properly typed
            $visitationArray['ID'] = (int) $visitationArray['ID'];
            $visitationArray['patient_id'] = (int) $visitationArray['patient_id'];
            $visitationArray['doctor_id'] = (int) $visitationArray['doctor_id'];
            
            // Handle nullable or empty fields with defaults
            $visitationArray['time'] = $visitationArray['time'] ?? null;
            $visitationArray['complaint'] = $visitationArray['complaint'] ?? '';
            $visitationArray['diagnosis'] = $visitationArray['diagnosis'] ?? '';
            $visitationArray['treatment'] = $visitationArray['treatment'] ?? '';
            $visitationArray['notes'] = $visitationArray['notes'] ?? '';
            $visitationArray['status'] = $visitationArray['status'] ?? '';
            
            // Handle JSON fields if needed
            if (!empty($visitationArray['vital_signs']) && is_string($visitationArray['vital_signs'])) {
                $decoded = json_decode($visitationArray['vital_signs'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $visitationArray['vital_signs'] = $decoded;
                }
            } else {
                $visitationArray['vital_signs'] = null;
            }
            
            // Check user permission to view this visitation
            $user = wp_get_current_user();
            if (in_array('patient', $user->roles) && $visitationArray['patient_id'] !== $user->ID) {
                // Patients can only see their own visitations
                error_log('VisitationService::getVisitation - Permission denied: patient attempted to view another patient\'s visitation');
                return false;
            }
            
            // Log successful retrieval
            error_log('VisitationService::getVisitation - Successfully retrieved visitation #' . $id);
            
            return $visitationArray;
            
        } catch (\Exception $e) {
            error_log('Exception in VisitationService::getVisitation: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new Exception('Failed to retrieve visitation: ' . $e->getMessage());
        }
    }
}
<?php

namespace HospitalManager\Services;

use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;

class LabInvestigationService
{
    /**
     * Get lab investigations with pagination, filtering, and related data
     * 
     * @param array $filters Array of filters (patient_id, lab_tech_id, status, test_type, search)
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array Array with 'data' and 'pagination' keys
     */
    public static function getInvestigationsWithPagination($filters = [], $page = 1, $per_page = 20)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hm_lab_investigations';
        
        // Sanitize pagination parameters
        $page = max(1, intval($page));
        $per_page = min(100, max(1, intval($per_page)));
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE conditions
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($filters['patient_id'])) {
            $where_conditions[] = 'l.patient_id = %d';
            $where_values[] = $filters['patient_id'];
        }
        
        if (!empty($filters['lab_tech_id'])) {
            $where_conditions[] = 'l.lab_tech_id = %d';
            $where_values[] = $filters['lab_tech_id'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'l.status = %s';
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['test_type'])) {
            $where_conditions[] = 'l.test_type = %s';
            $where_values[] = $filters['test_type'];
        }
        
        if (!empty($filters['search'])) {
            // Make sure search value is properly sanitized
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            
            // Use OR conditions for search across multiple columns
            $search_conditions = [];
            $search_conditions[] = 'p.first_name LIKE %s';
            $search_conditions[] = 'p.last_name LIKE %s';
            $search_conditions[] = 'l.test_type LIKE %s';
            $search_conditions[] = 'l.sample_type LIKE %s';
            
            // Create a grouped condition
            $where_conditions[] = '(' . implode(' OR ', $search_conditions) . ')';
            
            // Add all search terms to values array
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$table} l 
                       LEFT JOIN {$wpdb->prefix}hm_patients p ON l.patient_id = p.ID
                       WHERE {$where_clause}";
        $count_result = $wpdb->prepare($count_query, ...$where_values);
        $total = $wpdb->get_var($count_result);
        
        // Get investigations with patient and doctor info
        $query = "SELECT l.*, 
                    p.first_name as patient_first_name, p.last_name as patient_last_name, p.gender as patient_gender,
                    d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                    lt.display_name as lab_tech_name
                 FROM {$table} l
                 LEFT JOIN {$wpdb->prefix}hm_patients p ON l.patient_id = p.ID
                 LEFT JOIN {$wpdb->prefix}hm_doctors d ON l.doctor_id = d.ID
                 LEFT JOIN {$wpdb->users} lt ON l.lab_tech_id = lt.ID
                 WHERE {$where_clause}
                 ORDER BY l.created_at DESC
                 LIMIT %d OFFSET %d";
        
        // Add pagination parameters
        $query_params = $where_values;
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        // Prepare and execute the query
        $prepared_query = $wpdb->prepare($query, ...$query_params);
        
        // Execute the query with error handling
        $investigations = $wpdb->get_results($prepared_query, ARRAY_A);
        
        // Check for SQL errors
        if ($wpdb->last_error) {
            error_log("SQL Error in LabInvestigationService::getInvestigationsWithPagination: " . $wpdb->last_error);
            throw new \Exception("Database query error: " . $wpdb->last_error);
        }
        
        // Initialize to empty array if null was returned
        if ($investigations === null) {
            error_log("Investigations query returned null. Using empty array instead.");
            $investigations = [];
        }
        
        // Format the results
        $formatted_investigations = [];
        if ($investigations && is_array($investigations)) {
            $formatted_investigations = array_map([self::class, 'formatInvestigationResult'], $investigations);
        }
        
        return [
            'data' => $formatted_investigations,
            'pagination' => [
                'total' => (int) ($total ? $total : 0),
                'total_pages' => ceil(($total ? $total : 0) / $per_page),
                'current_page' => $page,
                'per_page' => $per_page
            ]
        ];
    }

    /**
     * Format a single investigation result
     * 
     * @param array $investigation Raw investigation data from database
     * @return array Formatted investigation data
     */
    public static function formatInvestigationResult($investigation)
    {
        // Decode JSON fields
        if (!empty($investigation['test_results'])) {
            $investigation['test_results'] = json_decode($investigation['test_results'], true);
        }
        if (!empty($investigation['flags'])) {
            $investigation['flags'] = json_decode($investigation['flags'], true);
        }
        
        // Add formatted names
        $first_name = isset($investigation['patient_first_name']) ? $investigation['patient_first_name'] : '';
        $last_name = isset($investigation['patient_last_name']) ? $investigation['patient_last_name'] : '';
        $investigation['patient_name'] = trim($first_name . ' ' . $last_name);
        
        $doc_first_name = isset($investigation['doctor_first_name']) ? $investigation['doctor_first_name'] : '';
        $doc_last_name = isset($investigation['doctor_last_name']) ? $investigation['doctor_last_name'] : '';
        $investigation['doctor_name'] = trim($doc_first_name . ' ' . $doc_last_name);
        
        // Remove individual name fields
        unset($investigation['patient_first_name'], $investigation['patient_last_name']);
        unset($investigation['doctor_first_name'], $investigation['doctor_last_name']);
        
        return $investigation;
    }

    /**
     * Get formatted investigation data with related models
     * 
     * @param LabInvestigation $investigation
     * @return array Formatted investigation data
     */
    public static function formatInvestigationWithRelations(LabInvestigation $investigation)
    {
        // Get related data
        $patient = $investigation->patient();
        $doctor = $investigation->requestedBy();
        $lab_tech = $investigation->labTech();
        
        $formatted_investigation = $investigation->attributes;
        
        // Decode JSON fields
        if ($formatted_investigation['test_results']) {
            $formatted_investigation['test_results'] = json_decode($formatted_investigation['test_results'], true);
        }
        if ($formatted_investigation['flags']) {
            $formatted_investigation['flags'] = json_decode($formatted_investigation['flags'], true);
        }
        
        // Add related data
        $formatted_investigation['patient_name'] = $patient ? trim($patient->first_name . ' ' . $patient->last_name) : '';
        $formatted_investigation['patient_gender'] = $patient ? $patient->gender : '';
        $formatted_investigation['doctor_name'] = $doctor ? trim($doctor->first_name . ' ' . $doctor->last_name) : '';
        $formatted_investigation['lab_tech_name'] = $lab_tech ? $lab_tech->display_name : '';
        
        return $formatted_investigation;
    }
}

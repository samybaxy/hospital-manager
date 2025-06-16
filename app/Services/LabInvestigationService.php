<?php

namespace HospitalManager\Services;

use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;

class LabInvestigationService extends BaseService
{
    /**
     * Get lab investigations with pagination, filtering, and related data with caching
     * 
     * @param array $filters Array of filters (patient_id, lab_tech_id, status, test_type, search)
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array Array with 'data' and 'pagination' keys
     */
    public static function getInvestigationsWithPagination($filters = [], $page = 1, $per_page = 20)
    {
        return self::executeCached('getInvestigationsWithPagination', [
            'filters' => $filters,
            'page' => $page,
            'per_page' => $per_page
        ], function() use ($filters, $page, $per_page) {
            global $wpdb;
            
            $table = $wpdb->prefix . 'hm_lab_investigations';
            
            // Sanitize pagination parameters
            $page = max(1, intval($page));
            $per_page = min(100, max(1, intval($per_page)));
            $offset = ($page - 1) * $per_page;
            
            // Build optimized WHERE conditions
            $where_conditions = ['1=1'];
            $values = [];
            
            if (!empty($filters['patient_id'])) {
                $where_conditions[] = 'l.patient_id = %d';
                $values[] = $filters['patient_id'];
            }
            
            if (!empty($filters['lab_tech_id'])) {
                $where_conditions[] = 'l.lab_tech_id = %d';
                $values[] = $filters['lab_tech_id'];
            }
            
            if (!empty($filters['status'])) {
                $where_conditions[] = 'l.status = %s';
                $values[] = $filters['status'];
            }
            
            if (!empty($filters['test_type'])) {
                $where_conditions[] = 'l.test_type = %s';
                $values[] = $filters['test_type'];
            }
            
            if (!empty($filters['search'])) {
                // Optimized search with proper escaping
                $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
                $where_conditions[] = '(p.first_name LIKE %s OR p.last_name LIKE %s OR l.test_type LIKE %s OR l.sample_type LIKE %s)';
                $values = array_merge($values, [$search_term, $search_term, $search_term, $search_term]);
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Optimized count query
            $count_query = "SELECT COUNT(*) FROM {$table} l 
                           LEFT JOIN {$wpdb->prefix}hm_patients p ON l.patient_id = p.ID
                           WHERE {$where_clause}";
            $total = (int)$wpdb->get_var($wpdb->prepare($count_query, $values));
            
            // Main query with optimized joins
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
            
            // Execute the query
            $query_params = array_merge($values, [$per_page, $offset]);
            $investigations = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
            
            // Check for SQL errors
            if ($wpdb->last_error) {
                self::logError('LabInvestigationService', 'getInvestigationsWithPagination', $wpdb->last_error);
                throw new \Exception("Database query error: " . $wpdb->last_error);
            }
            
            // Format the results
            $formatted_investigations = [];
            if ($investigations && is_array($investigations)) {
                $formatted_investigations = array_map([self::class, 'formatInvestigationResult'], $investigations);
            }
            
            return [
                'data' => $formatted_investigations,
                'pagination' => [
                    'total' => $total,
                    'total_pages' => ceil($total / $per_page),
                    'current_page' => $page,
                    'per_page' => $per_page
                ]
            ];
        }, 300); // Cache for 5 minutes (frequently changing lab results)
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
     * Get formatted investigation data with optimized caching
     * 
     * @param int $investigation_id Investigation ID
     * @return array|null Formatted investigation data or null if not found
     */
    public static function getInvestigationWithDetails($investigation_id)
    {
        return self::executeCached('getInvestigationWithDetails', ['id' => $investigation_id], function() use ($investigation_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_lab_investigations';
            
            // Get investigation with all related data in single query
            $investigation = $wpdb->get_row($wpdb->prepare(
                "SELECT l.*, 
                    p.first_name as patient_first_name, p.last_name as patient_last_name, p.gender as patient_gender,
                    d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                    lt.display_name as lab_tech_name
                 FROM {$table} l
                 LEFT JOIN {$wpdb->prefix}hm_patients p ON l.patient_id = p.ID
                 LEFT JOIN {$wpdb->prefix}hm_doctors d ON l.doctor_id = d.ID
                 LEFT JOIN {$wpdb->users} lt ON l.lab_tech_id = lt.ID
                 WHERE l.ID = %d",
                $investigation_id
            ), ARRAY_A);
            
            if (!$investigation) {
                return null;
            }
            
            return self::formatInvestigationResult($investigation);
        }, 1200); // Cache for 20 minutes
    }

    /**
     * Get investigation statistics with caching
     * 
     * @param array $filters Optional filters
     * @return array Investigation statistics
     */
    public static function getInvestigationStatistics($filters = [])
    {
        return self::executeCached('getInvestigationStatistics', $filters, function() use ($filters) {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_lab_investigations';
            
            // Build where conditions for filters
            $where_conditions = ['1=1'];
            $values = [];
            
            if (!empty($filters['date_from'])) {
                $where_conditions[] = 'DATE(created_at) >= %s';
                $values[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where_conditions[] = 'DATE(created_at) <= %s';
                $values[] = $filters['date_to'];
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Get comprehensive statistics in a single query
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_investigations,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(DISTINCT patient_id) as unique_patients,
                    COUNT(DISTINCT lab_tech_id) as active_technicians
                FROM {$table} 
                WHERE {$where_clause}",
                $values
            ), ARRAY_A);
            
            return [
                'total_investigations' => (int)($stats['total_investigations'] ?? 0),
                'pending' => (int)($stats['pending'] ?? 0),
                'in_progress' => (int)($stats['in_progress'] ?? 0),
                'completed' => (int)($stats['completed'] ?? 0),
                'cancelled' => (int)($stats['cancelled'] ?? 0),
                'unique_patients' => (int)($stats['unique_patients'] ?? 0),
                'active_technicians' => (int)($stats['active_technicians'] ?? 0)
            ];
        }, 1800); // Cache for 30 minutes
    }
}

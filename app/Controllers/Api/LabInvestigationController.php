<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_REST_Request;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Services\LabResultService;

class LabInvestigationController extends BaseController 
{
    public function register_routes() 
    {
        register_rest_route($this->namespace, '/lab-investigations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_investigations'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
                'args' => [
                    'patient_id' => [
                        'description' => 'Filter by patient ID',
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'lab_tech_id' => [
                        'description' => 'Filter by lab technician ID', 
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'status' => [
                        'description' => 'Filter by status',
                        'type' => 'string',
                        'enum' => ['requested', 'sample_collected', 'in_progress', 'completed', 'verified', 'cancelled'],
                    ],
                    'page' => [
                        'description' => 'Page number',
                        'type' => 'integer',
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'description' => 'Items per page',
                        'type' => 'integer',
                        'default' => 20,
                        'sanitize_callback' => 'absint',
                    ]
                ]
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_investigation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_lab_investigations');
                },
                'args' => [
                    'visitation_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'patient_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'doctor_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'lab_tech_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'sample_type' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'request_notes' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/lab-investigations/(?P<ID>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_investigation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_investigation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'update_lab_results');
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_investigation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_lab_investigations');
                },
            ]
        ]);

        register_rest_route($this->namespace, '/lab-investigations/(?P<ID>\d+)/results', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_results'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'update_lab_results');
                },
                'args' => [
                    'test_results' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                    'flags' => [
                        'type' => 'object',
                    ],
                    'lab_notes' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/lab-investigations/(?P<ID>\d+)/status', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_status'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'update_lab_results');
                },
                'args' => [
                    'status' => [
                        'required' => true,
                        'type' => 'string',
                        'enum' => ['requested', 'sample_collected', 'in_progress', 'completed', 'verified', 'cancelled'],
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/lab-investigations/pending', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_pending_investigations'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ]
        ]);

        // Lab Categories routes
        register_rest_route($this->namespace, '/lab-categories', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_lab_categories'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ]
        ]);

        // Test Definitions routes
        register_rest_route($this->namespace, '/lab-test-definitions', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_test_definitions'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
                'args' => [
                    'category_id' => [
                        'description' => 'Filter by category ID',
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/lab-test-definitions/(?P<ID>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_test_definition'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ]
        ]);
    }

    /**
     * Check if the current user has the patient role
     * 
     * @return bool
     */
    private function current_user_is_patient()
    {
        return current_user_can('patient') && !current_user_can('administrator') && !current_user_can('doctor');
    }

    /**
     * Get the patient ID for the current user
     * 
     * @return int|null The patient ID or null if not found
     */
    private function get_current_user_patient_id()
    {
        global $wpdb;
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return null;
        }
        
        $patient_table = $wpdb->prefix . 'hm_patients';
        $patient_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$patient_table} WHERE user_id = %d",
            $current_user_id
        ));
        
        return $patient_id ? (int) $patient_id : null;
    }

    public function get_investigations(WP_REST_Request $request) 
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_lab_investigations';
            
            $patient_id = $request->get_param('patient_id');
            $lab_tech_id = $request->get_param('lab_tech_id');
            $test_type = $request->get_param('test_type');
            $status = $request->get_param('status');
            $search = $request->get_param('search');
            $page = max(1, intval($request->get_param('page') ?: 1));
            $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 20)));
            $offset = ($page - 1) * $per_page;
            
            // Security: If the current user is a patient, restrict to their own records only
            if ($this->current_user_is_patient()) {
                $current_patient_id = $this->get_current_user_patient_id();
                if ($current_patient_id) {
                    // Override any patient_id parameter with the current user's patient ID
                    $patient_id = $current_patient_id;
                } else {
                    // Patient user but no patient record found - return empty results
                    return new WP_REST_Response([
                        'data' => [],
                        'pagination' => [
                            'total' => 0,
                            'total_pages' => 0,
                            'current_page' => $page,
                            'per_page' => $per_page
                        ]
                    ], 200);
                }
            }
            
            // Log pagination info for debugging
            error_log("Pagination: page={$page}, per_page={$per_page}, offset={$offset}");
            
            $where_conditions = ['1=1'];
            $where_values = [];
            
            if ($patient_id) {
                $where_conditions[] = 'l.patient_id = %d';
                $where_values[] = $patient_id;
            }
            
            if ($lab_tech_id) {
                $where_conditions[] = 'l.lab_tech_id = %d';
                $where_values[] = $lab_tech_id;
            }
            
            if ($status) {
                $where_conditions[] = 'l.status = %s';
                $where_values[] = $status;
            }
            
            if ($test_type) {
                $where_conditions[] = 'l.test_type = %s';
                $where_values[] = $test_type;
            }
            
            if ($search) {
                // Make sure search value is properly sanitized
                $search_term = '%' . $wpdb->esc_like($search) . '%';
                
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
            
            // Add proper JOIN to count query to match filters on patient data
            $count_query = "SELECT COUNT(*) FROM {$table} l 
                           LEFT JOIN {$wpdb->prefix}hm_patients p ON l.patient_id = p.ID
                           WHERE {$where_clause}";
            $count_result = $wpdb->prepare($count_query, ...$where_values);
            $total = $wpdb->get_var($count_result);
            
            error_log("Total count: {$total}");
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
                error_log("SQL Error in get_investigations: " . $wpdb->last_error);
                throw new \Exception("Database query error: " . $wpdb->last_error);
            }
            
            // Initialize to empty array if null was returned
            if ($investigations === null) {
                error_log("Investigations query returned null. Using empty array instead.");
                $investigations = [];
            }
            
            error_log("Investigations count: " . count($investigations));
            // Format the results
            $formatted_investigations = [];
            if ($investigations && is_array($investigations)) {
                $formatted_investigations = array_map(function($investigation) {
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
                }, $investigations);
            }
            
            // Check if we have any database table errors
            if ($wpdb->last_error) {
                error_log("Database error in get_investigations: " . $wpdb->last_error);
                return new WP_REST_Response([
                    'error' => 'Database error occurred',
                    'message' => $wpdb->last_error
                ], 500);
            }
            
            // Return the data with pagination
            $response = new WP_REST_Response([
                'data' => $formatted_investigations,
                'pagination' => [
                    'total' => (int) ($total ? $total : 0),
                    'total_pages' => ceil(($total ? $total : 0) / $per_page),
                    'current_page' => $page,
                    'per_page' => $per_page
                ]
            ], 200);
            
            return $response;
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to fetch lab investigations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function get_investigation(WP_REST_Request $request)
    {
        try {
            $investigation = LabInvestigation::find($request['ID']);
            if (!$investigation) {
                return new WP_REST_Response(['error' => 'Investigation not found'], 404);
            }

            // Security: If the current user is a patient, ensure they can only view their own investigations
            if ($this->current_user_is_patient()) {
                $current_patient_id = $this->get_current_user_patient_id();
                if (!$current_patient_id || $investigation->patient_id != $current_patient_id) {
                    return new WP_REST_Response(['error' => 'Investigation not found'], 404);
                }
            }

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
            
            return new WP_REST_Response($formatted_investigation, 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to fetch investigation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function create_investigation(WP_REST_Request $request) 
    {
        try {
            $params = $request->get_params();
            
            // Validate required fields
            $required_fields = ['patient_id', 'doctor_id', 'lab_tech_id', 'test_type'];
            foreach ($required_fields as $field) {
                if (empty($params[$field])) {
                    return new WP_REST_Response(['error' => ucfirst(str_replace('_', ' ', $field)) . ' is required'], 400);
                }
            }
            
            // Validate required relationships exist
            $patient = Patient::find($params['patient_id']);
            if (!$patient) {
                return new WP_REST_Response(['error' => 'Patient not found'], 400);
            }
            
            $doctor = Doctor::find($params['doctor_id']);
            if (!$doctor) {
                return new WP_REST_Response(['error' => 'Doctor not found'], 400);
            }
            
            $lab_tech = get_user_by('ID', $params['lab_tech_id']);
            if (!$lab_tech) {
                return new WP_REST_Response(['error' => 'Lab technician not found'], 400);
            }
            
            $investigation = ( LabInvestigation::create($params) )->to_array();
            
            if (!$investigation) {
                error_log("LabInvestigationController::create_investigation - Investigation creation failed");
                return new WP_REST_Response(['error' => 'Failed to create investigation'], 500);
            }
            
            // Handle different return types from create method
            $investigation_data = null;
            $investigation_id = null;
            
            if (is_array($investigation) && isset($investigation['ID'])) {
                $investigation_data = $investigation;
                $investigation_id = $investigation['ID'];
                error_log("LabInvestigationController::create_investigation - Using array, ID: " . $investigation_id);
            }
            
            if (!$investigation_data || !$investigation_id) {
                error_log("LabInvestigationController::create_investigation - Could not extract investigation data or ID");
                return new WP_REST_Response(['error' => 'Failed to create investigation - invalid response'], 500);
            }
            
            // Trigger notification
            try {
                LabResultService::notifyLabRequest($investigation_id);
            } catch (\Exception $e) {
                error_log("LabInvestigationController::create_investigation - Notification failed: " . $e->getMessage());
                // Don't fail the whole request if notification fails
            }
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $investigation_data,
                'message' => 'Investigation created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to create investigation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update_investigation(WP_REST_Request $request) 
    {
        try {
            $investigation = LabInvestigation::find($request['ID']);
            if (!$investigation) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Investigation not found'
                ], 404);
            }

            // Security: Patients should not be able to update lab investigations
            if ($this->current_user_is_patient()) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            $params = $request->get_params();
            
            // Remove ID from params to avoid updating it
            unset($params['ID']);
            
            // Clean up any null or undefined values that might cause issues
            $cleanParams = [];
            foreach ($params as $key => $value) {
                if ($value !== null && $value !== 'undefined') {
                    $cleanParams[$key] = $value;
                }
            }
            
            // Handle test_results specifically if it's a JSON string
            if (isset($cleanParams['test_results']) && is_string($cleanParams['test_results'])) {
                // Validate JSON before storing
                $decodedResults = json_decode($cleanParams['test_results'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $cleanParams['test_results'] = $cleanParams['test_results']; // Keep as string for storage
                } else {
                    unset($cleanParams['test_results']); // Remove invalid JSON
                }
            }
            
            $success = $investigation->update($cleanParams);
            
            if (!$success) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Failed to update investigation'
                ], 500);
            }
            
            // Return proper success response format
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Investigation updated successfully',
                'data' => $investigation->attributes
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update investigation: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update_results(WP_REST_Request $request)
    {
        try {
            $investigation = LabInvestigation::find($request['ID']);
            if (!$investigation) {
                return new WP_REST_Response(['error' => 'Investigation not found'], 404);
            }

            // Security: Patients should not be able to update lab results
            if ($this->current_user_is_patient()) {
                return new WP_REST_Response(['error' => 'Access denied'], 403);
            }

            $test_results = $request->get_param('test_results');
            $flags = $request->get_param('flags');
            $lab_notes = $request->get_param('lab_notes');
            
            $success = $investigation->updateResults($test_results, $flags, $lab_notes);
            
            if (!$success) {
                return new WP_REST_Response(['error' => 'Failed to update results'], 500);
            }
            
            // Trigger notifications
            LabResultService::notifyResultsReady($investigation->attributes['ID']);
            
            return new WP_REST_Response($investigation->attributes, 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to update results',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update_status(WP_REST_Request $request)
    {
        try {
            $investigation = LabInvestigation::find($request['ID']);
            if (!$investigation) {
                return new WP_REST_Response(['error' => 'Investigation not found'], 404);
            }

            // Security: Patients should not be able to update lab investigation status
            if ($this->current_user_is_patient()) {
                return new WP_REST_Response(['error' => 'Access denied'], 403);
            }

            $status = $request->get_param('status');
            $success = $investigation->updateStatus($status);
            
            if (!$success) {
                return new WP_REST_Response(['error' => 'Failed to update status'], 500);
            }
            
            return new WP_REST_Response($investigation->attributes, 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to update status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function delete_investigation(WP_REST_Request $request)
    {
        try {
            $investigation = LabInvestigation::find($request['ID']);
            if (!$investigation) {
                return new WP_REST_Response(['error' => 'Investigation not found'], 404);
            }

            // Security: Patients should not be able to delete lab investigations
            if ($this->current_user_is_patient()) {
                return new WP_REST_Response(['error' => 'Access denied'], 403);
            }

            $success = $investigation->delete();
            
            if (!$success) {
                return new WP_REST_Response(['error' => 'Failed to delete investigation'], 500);
            }
            
            return new WP_REST_Response(['message' => 'Investigation deleted successfully'], 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to delete investigation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function get_pending_investigations(WP_REST_Request $request)
    {
        try {
            $lab_tech_id = $request->get_param('lab_tech_id');
            $limit = $request->get_param('limit') ?: 20;
            
            $investigations = LabInvestigation::getPendingForTech($lab_tech_id, $limit);
            
            return new WP_REST_Response($investigations, 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get lab categories
     */
    public function get_lab_categories(WP_REST_Request $request)
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_lab_categories';
            
            $categories = $wpdb->get_results(
                "SELECT ID, name, description, display_order, status 
                 FROM {$table} 
                 WHERE status = 'active' 
                 ORDER BY display_order ASC, name ASC",
                ARRAY_A
            );
            
            if ($wpdb->last_error) {
                throw new \Exception('Database error: ' . $wpdb->last_error);
            }
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $categories ?: []
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get test definitions
     */
    public function get_test_definitions(WP_REST_Request $request)
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_lab_test_definitions';
            $category_id = $request->get_param('category_id');
            
            $where_clause = "WHERE td.status = 'active'";
            $where_params = [];
            
            if ($category_id) {
                $where_clause .= " AND td.category_id = %d";
                $where_params[] = $category_id;
            }
            
            $query = "SELECT td.ID, td.category_id, td.code, td.name, td.description, 
                             td.sample_type, td.container, td.sample_volume, 
                             td.turnaround_time, td.test_parameters, td.specimen_requirements,
                             td.preparation_instructions, td.methodology, td.cost, td.is_panel,
                             c.name as category_name
                      FROM {$table} td
                      LEFT JOIN {$wpdb->prefix}hm_lab_categories c ON td.category_id = c.ID
                      {$where_clause}
                      ORDER BY td.name ASC";
            
            if (!empty($where_params)) {
                $prepared_query = $wpdb->prepare($query, ...$where_params);
            } else {
                $prepared_query = $query;
            }
            
            $test_definitions = $wpdb->get_results($prepared_query, ARRAY_A);
            
            if ($wpdb->last_error) {
                throw new \Exception('Database error: ' . $wpdb->last_error);
            }
            
            // Decode JSON test_parameters for each test
            if ($test_definitions) {
                foreach ($test_definitions as &$test) {
                    if ($test['test_parameters']) {
                        $test['test_parameters'] = json_decode($test['test_parameters'], true);
                    }
                }
            }
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $test_definitions ?: []
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single test definition
     */
    public function get_test_definition(WP_REST_Request $request)
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_lab_test_definitions';
            $test_id = intval($request['ID']);
            
            $test_definition = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT td.*, c.name as category_name
                     FROM {$table} td
                     LEFT JOIN {$wpdb->prefix}hm_lab_categories c ON td.category_id = c.ID
                     WHERE td.ID = %d AND td.status = 'active'",
                    $test_id
                ),
                ARRAY_A
            );
            
            if ($wpdb->last_error) {
                throw new \Exception('Database error: ' . $wpdb->last_error);
            }
            
            if (!$test_definition) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Test definition not found'
                ], 404);
            }
            
            // Decode JSON test_parameters
            if ($test_definition['test_parameters']) {
                $test_definition['test_parameters'] = json_decode($test_definition['test_parameters'], true);
            }
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $test_definition
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
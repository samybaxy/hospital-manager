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
                'args' => [
                    'lab_tech_id' => [
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'default' => 20,
                        'sanitize_callback' => 'absint',
                    ]
                ]
            ]
        ]);
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
                        p.first_name as patient_first_name, p.last_name as patient_last_name,
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
            
            $investigation = LabInvestigation::create($params);
            
            if (!$investigation) {
                return new WP_REST_Response(['error' => 'Failed to create investigation'], 500);
            }
            
            // Trigger notification
            LabResultService::notifyLabRequest($investigation->attributes['ID']);
            
            return new WP_REST_Response($investigation->attributes, 201);
            
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
                return new WP_REST_Response(['error' => 'Investigation not found'], 404);
            }

            $investigation->update($request->get_params());
            return new WP_REST_Response($investigation->attributes, 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to update investigation',
                'message' => $e->getMessage()
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
                'error' => 'Failed to fetch pending investigations',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
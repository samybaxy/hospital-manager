<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_REST_Request;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\LabCategory;
use HospitalManager\Models\LabTestDefinition;
use HospitalManager\Services\LabResultService;
use HospitalManager\Services\LabInvestigationService;

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
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return null;
        }
        
        $patient = Patient::findByUserId($current_user_id);
        return $patient ? $patient->ID : null;
    }

    public function get_investigations(WP_REST_Request $request) 
    {
        try {
            $patient_id = $request->get_param('patient_id');
            $lab_tech_id = $request->get_param('lab_tech_id');
            $test_type = $request->get_param('test_type');
            $status = $request->get_param('status');
            $search = $request->get_param('search');
            $page = max(1, intval($request->get_param('page') ?: 1));
            $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 20)));
            
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
            
            // Prepare filters for the service
            $filters = [
                'patient_id' => $patient_id,
                'lab_tech_id' => $lab_tech_id,
                'test_type' => $test_type,
                'status' => $status,
                'search' => $search
            ];
            
            // Remove empty filters
            $filters = array_filter($filters, function($value) {
                return !empty($value);
            });
            
            // Use the service to get investigations with pagination
            $result = LabInvestigationService::getInvestigationsWithPagination($filters, $page, $per_page);
            
            return new WP_REST_Response($result, 200);
            
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

            // Use the service to format the investigation with relations
            $formatted_investigation = LabInvestigationService::formatInvestigationWithRelations($investigation);
            
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
            $categories = LabCategory::getAllActive();
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $categories
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
            $category_id = $request->get_param('category_id');
            
            $test_definitions = LabTestDefinition::getActive($category_id);
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $test_definitions
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
            $test_id = intval($request['ID']);
            
            $test_definition = LabTestDefinition::getActiveWithCategory($test_id);
            
            if (!$test_definition) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Test definition not found'
                ], 404);
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
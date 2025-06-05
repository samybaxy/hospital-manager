<?php

namespace HospitalManager\Database\Seeders;

class LabInvestigationSeeder extends Seeder
{
    /**
     * Mapping of test types to categories
     * 
     * @var array
     */
    protected $testTypeToCategory = [
        'Complete Blood Count (CBC)' => 'Hematology',
        'Blood Glucose Test' => 'Clinical Chemistry',
        'Liver Function Test' => 'Clinical Chemistry',
        'Lipid Profile' => 'Clinical Chemistry',
        'Thyroid Function Test' => 'Endocrinology',
        'Urinalysis' => 'Urinalysis',
        'Kidney Function Test' => 'Clinical Chemistry',
        'Electrolyte Panel' => 'Clinical Chemistry',
        'HbA1c (Glycated Hemoglobin)' => 'Clinical Chemistry',
        'Malaria Parasite Test' => 'Microbiology',
        'Typhoid Test (Widal)' => 'Serology',
        'HIV Test' => 'Serology',
        'Hepatitis B Test' => 'Serology',
        'Hepatitis C Test' => 'Serology',
        'Tuberculosis Test' => 'Microbiology',
        'Stool Analysis' => 'Microbiology',
        'Blood Culture' => 'Microbiology',
        'Urine Culture' => 'Microbiology',
        'Pap Smear' => 'Molecular Diagnostics',
        'PSA (Prostate-Specific Antigen)' => 'Immunology'
    ];
    
    /**
     * Lab test types
     * 
     * @var array
     */
    protected $testTypes = [
        'Complete Blood Count (CBC)',
        'Blood Glucose Test',
        'Liver Function Test',
        'Lipid Profile',
        'Thyroid Function Test',
        'Urinalysis',
        'Kidney Function Test',
        'Electrolyte Panel',
        'HbA1c (Glycated Hemoglobin)',
        'Malaria Parasite Test',
        'Typhoid Test (Widal)',
        'HIV Test',
        'Hepatitis B Test',
        'Hepatitis C Test',
        'Tuberculosis Test',
        'Stool Analysis',
        'Blood Culture',
        'Urine Culture',
        'Pap Smear',
        'PSA (Prostate-Specific Antigen)'
    ];
    
    /**
     * Sample types for tests
     * 
     * @var array
     */
    protected $sampleTypes = [
        'Complete Blood Count (CBC)' => 'Whole Blood',
        'Blood Glucose Test' => 'Plasma',
        'Liver Function Test' => 'Serum',
        'Lipid Profile' => 'Serum',
        'Thyroid Function Test' => 'Serum',
        'Urinalysis' => 'Urine',
        'Kidney Function Test' => 'Serum',
        'Electrolyte Panel' => 'Serum',
        'HbA1c (Glycated Hemoglobin)' => 'Whole Blood',
        'Malaria Parasite Test' => 'Whole Blood',
        'Typhoid Test (Widal)' => 'Serum',
        'HIV Test' => 'Serum',
        'Hepatitis B Test' => 'Serum',
        'Hepatitis C Test' => 'Serum',
        'Tuberculosis Test' => 'Sputum',
        'Stool Analysis' => 'Stool',
        'Blood Culture' => 'Blood',
        'Urine Culture' => 'Urine',
        'Pap Smear' => 'Cervical Cells',
        'PSA (Prostate-Specific Antigen)' => 'Serum'
    ];
    
    /**
     * Test results data structure
     * 
     * @var array
     */
    protected $testResults = [
        'Complete Blood Count (CBC)' => [
            'parameters' => [
                [
                    'id' => 'WBC',
                    'name' => 'White Blood Cell Count',
                    'value' => ['min' => 4.5, 'max' => 10.5],
                    'unit' => 'x10^9/L',
                    'reference_range' => ['min' => 4.0, 'max' => 11.0]
                ],
                [
                    'id' => 'RBC',
                    'name' => 'Red Blood Cell Count',
                    'value' => ['min' => 4.1, 'max' => 5.3],
                    'unit' => 'x10^12/L',
                    'reference_range' => ['min' => 4.5, 'max' => 5.5, 'gender' => 'male']
                ],
                [
                    'id' => 'HGB',
                    'name' => 'Hemoglobin',
                    'value' => ['min' => 13.0, 'max' => 16.5],
                    'unit' => 'g/dL',
                    'reference_range' => ['min' => 13.5, 'max' => 17.5, 'gender' => 'male']
                ],
                [
                    'id' => 'HCT',
                    'name' => 'Hematocrit',
                    'value' => ['min' => 39.0, 'max' => 47.0],
                    'unit' => '%',
                    'reference_range' => ['min' => 41.0, 'max' => 50.0, 'gender' => 'male']
                ],
                [
                    'id' => 'PLT',
                    'name' => 'Platelet Count',
                    'value' => ['min' => 160, 'max' => 370],
                    'unit' => 'x10^9/L',
                    'reference_range' => ['min' => 150, 'max' => 400]
                ]
            ]
        ],
        'Liver Function Test' => [
            'parameters' => [
                [
                    'id' => 'ALT',
                    'name' => 'Alanine Transaminase',
                    'value' => ['min' => 10, 'max' => 50],
                    'unit' => 'U/L',
                    'reference_range' => ['min' => 7, 'max' => 55, 'gender' => 'male']
                ],
                [
                    'id' => 'AST',
                    'name' => 'Aspartate Transaminase',
                    'value' => ['min' => 10, 'max' => 40],
                    'unit' => 'U/L',
                    'reference_range' => ['min' => 8, 'max' => 48, 'gender' => 'male']
                ],
                [
                    'id' => 'ALP',
                    'name' => 'Alkaline Phosphatase',
                    'value' => ['min' => 45, 'max' => 115],
                    'unit' => 'U/L',
                    'reference_range' => ['min' => 40, 'max' => 129]
                ],
                [
                    'id' => 'TBIL',
                    'name' => 'Total Bilirubin',
                    'value' => ['min' => 0.2, 'max' => 1.0],
                    'unit' => 'mg/dL',
                    'reference_range' => ['min' => 0.1, 'max' => 1.2]
                ],
                [
                    'id' => 'ALB',
                    'name' => 'Albumin',
                    'value' => ['min' => 3.6, 'max' => 4.8],
                    'unit' => 'g/dL',
                    'reference_range' => ['min' => 3.5, 'max' => 5.0]
                ]
            ]
        ],
        'Thyroid Function Test' => [
            'parameters' => [
                [
                    'id' => 'TSH',
                    'name' => 'Thyroid Stimulating Hormone',
                    'value' => ['min' => 0.5, 'max' => 3.7],
                    'unit' => 'mIU/L',
                    'reference_range' => ['min' => 0.4, 'max' => 4.0]
                ],
                [
                    'id' => 'FT4',
                    'name' => 'Free Thyroxine',
                    'value' => ['min' => 0.9, 'max' => 1.7],
                    'unit' => 'ng/dL',
                    'reference_range' => ['min' => 0.8, 'max' => 1.8]
                ],
                [
                    'id' => 'FT3',
                    'name' => 'Free Triiodothyronine',
                    'value' => ['min' => 2.5, 'max' => 4.0],
                    'unit' => 'pg/mL',
                    'reference_range' => ['min' => 2.3, 'max' => 4.2]
                ]
            ]
        ]
    ];
    
    /**
     * Lab result statuses
     * 
     * @var array
     */
    protected $statuses = [
        'requested',
        'sample_collected',
        'in_progress', 
        'completed',
        'verified'
    ];
    
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Seeding lab investigations...');
        
        global $wpdb;
        
        // Get existing patients, doctors, lab techs and visitations
        $patient_ids = $this->getExistingIds('hm_patients');
        $doctor_ids = $this->getExistingIds('hm_doctors');
        $lab_tech_ids = $this->getUserIds(5, 'lab_tech');
        $visitation_ids = $this->getExistingIds('hm_visitations');
        
        if (empty($patient_ids) || empty($doctor_ids) || empty($lab_tech_ids) || empty($visitation_ids)) {
            $this->log('Missing required data for lab investigations. Make sure patients, doctors, and visitations exist.');
            return;
        }
        
        // Get category IDs
        $categories = $wpdb->get_results("SELECT ID, name FROM {$wpdb->prefix}hm_lab_categories", OBJECT_K);
        if (empty($categories)) {
            $this->log('No laboratory categories found. Please run LabTestCategorySeeder first.', 'error');
            return;
        }
        
        // Create some lab investigations for each visitation
        $count = 0;
        $maxRecords = 50;
        
        foreach ($visitation_ids as $visitation_id) {
            if ($count >= $maxRecords) break;
            
            // Get patient and doctor IDs from visitation
            $visitation = $wpdb->get_row("SELECT patient_id, doctor_id FROM {$wpdb->prefix}hm_visitations WHERE ID = {$visitation_id}");
            
            if (!$visitation) continue;
            
            // Create 1-3 lab investigations per visitation
            $investigations_count = rand(1, 3);
            
            for ($i = 0; $i < $investigations_count; $i++) {
                if ($count >= $maxRecords) break;
                
                $test_type = $this->testTypes[array_rand($this->testTypes)];
                $status = $this->statuses[array_rand($this->statuses)];
                $lab_tech_id = $lab_tech_ids[array_rand($lab_tech_ids)];
                
                // Get category ID for this test type
                $category_name = isset($this->testTypeToCategory[$test_type]) ? 
                    $this->testTypeToCategory[$test_type] : 'Clinical Chemistry';
                
                $category_id = isset($categories[$category_name]) ? 
                    $categories[$category_name]->ID : array_values($categories)[0]->ID;
                
                // Generate created_at date based on visitation date
                $created_at = $wpdb->get_var("SELECT date FROM {$wpdb->prefix}hm_visitations WHERE ID = {$visitation_id}");
                if (!$created_at) $created_at = date('Y-m-d H:i:s');
                
                $request_notes = [
                    'Routine lab investigation ordered.',
                    'Follow-up test requested by doctor.',
                    'Patient symptoms require lab confirmation.',
                    'Pre-operative lab work ordered.',
                    'Monitoring chronic condition.'
                ];
                $notes = $request_notes[array_rand($request_notes)];
                
                // Sample data
                $sample_type = isset($this->sampleTypes[$test_type]) ? 
                    $this->sampleTypes[$test_type] : 'Blood';
                
                // For completed tests, add results
                $test_results = null;
                $flags = null;
                $is_abnormal = 0;
                $is_critical = 0;
                $verified_by = null;
                $verified_at = null;
                $lab_notes = null;
                
                if (in_array($status, ['completed', 'verified'])) {
                    if (isset($this->testResults[$test_type])) {
                        $result_data = $this->testResults[$test_type];
                        
                        // Process each parameter and randomly make some abnormal
                        $abnormal_parameters = [];
                        $critical_parameters = [];
                        
                        foreach ($result_data['parameters'] as &$param) {
                            // Generate a random value within or slightly outside the range
                            $min_val = $param['value']['min'];
                            $max_val = $param['value']['max'];
                            
                            // 20% chance of abnormal value
                            if (rand(1, 100) <= 20) {
                                // Generate slightly abnormal value
                                $is_low = (rand(0, 1) === 0);
                                if ($is_low) {
                                    $value = $min_val * (rand(70, 95) / 100); // 5-30% below min
                                } else {
                                    $value = $max_val * (rand(105, 130) / 100); // 5-30% above max
                                }
                                
                                $abnormal_parameters[] = $param['id'];
                                $is_abnormal = 1;
                                $param['is_abnormal'] = true;
                                $param['flag'] = $is_low ? 'L' : 'H';
                                
                                // 5% chance of critical value
                                if (rand(1, 100) <= 25) {
                                    if ($is_low) {
                                        $value = $min_val * (rand(40, 69) / 100); // 31-60% below min
                                    } else {
                                        $value = $max_val * (rand(131, 160) / 100); // 31-60% above max
                                    }
                                    $critical_parameters[] = $param['id'];
                                    $is_critical = 1;
                                    $param['is_critical'] = true;
                                    $param['flag'] = $is_low ? 'LL' : 'HH';
                                }
                            } else {
                                // Normal value
                                $value = $min_val + (($max_val - $min_val) * (rand(10, 90) / 100));
                                $param['is_abnormal'] = false;
                                $param['is_critical'] = false;
                                $param['flag'] = null;
                            }
                            
                            // Round to appropriate decimal places based on the typical precision for this type of test
                            if (strpos($param['unit'], 'g/dL') !== false) {
                                $value = round($value, 1); // Hemoglobin, proteins
                            } elseif (strpos($param['unit'], 'x10^') !== false) {
                                $value = round($value, 1); // Cell counts
                            } elseif (strpos($param['unit'], 'mg/dL') !== false || 
                                      strpos($param['unit'], 'mIU/L') !== false) {
                                $value = round($value, 2); // Chemistry tests
                            } else {
                                $value = round($value, is_int($value) ? 0 : 2);
                            }
                            
                            $param['value'] = $value;
                        }
                        
                        $test_results = json_encode($result_data);
                        
                        if (!empty($abnormal_parameters)) {
                            $flags = json_encode([
                                'abnormal' => $abnormal_parameters,
                                'critical' => $critical_parameters
                            ]);
                            
                            $lab_notes = 'Abnormal values detected for: ' . implode(', ', $abnormal_parameters);
                            if (!empty($critical_parameters)) {
                                $lab_notes .= '. CRITICAL values for: ' . implode(', ', $critical_parameters) . '. Physician notified.';
                            }
                        } else {
                            $lab_notes = 'All values within normal ranges.';
                        }
                    } else {
                        // Generic test results for tests without specific parameters
                        $test_results = json_encode([
                            'result' => 'Test completed',
                            'interpretation' => [
                                'Negative', 'Positive', 'Within normal limits', 'Abnormal'
                            ][rand(0, 3)]
                        ]);
                        
                        $lab_notes = 'Standard testing protocol followed.';
                    }
                    
                    // For verified tests
                    if ($status === 'verified') {
                        $verified_by = $lab_tech_ids[array_rand($lab_tech_ids)];
                        $verified_at = date('Y-m-d H:i:s', strtotime($created_at . ' +4 hours'));
                    }
                }
                
                $data = [
                    'visitation_id' => $visitation_id,
                    'doctor_id' => $visitation->doctor_id,
                    'lab_tech_id' => $lab_tech_id,
                    'patient_id' => $visitation->patient_id,
                    'category_id' => $category_id,
                    'test_type' => $test_type,
                    'sample_type' => $sample_type,
                    'request_notes' => $notes,
                    'lab_notes' => $lab_notes,
                    'test_results' => $test_results,
                    'flags' => $flags,
                    'is_abnormal' => $is_abnormal,
                    'is_critical' => $is_critical,
                    'verified_by' => $verified_by,
                    'verified_at' => $verified_at,
                    'status' => $status,
                    'created_at' => $created_at,
                    'updated_at' => $created_at
                ];
                
                $wpdb->insert($wpdb->prefix . 'hm_lab_investigations', $data);
                $count++;
            }
        }
        
        $this->log("Created {$count} lab investigations", 'success');
    }
    
    /**
     * Get existing record IDs from a table
     * 
     * @param string $table Table name without prefix
     * @return array Array of IDs
     */
    protected function getExistingIds($table)
    {
        global $wpdb;
        return $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}{$table}");
    }
    
    /**
     * Get user IDs by role
     * 
     * @param int $limit Maximum number of IDs to return
     * @param string $role User role to filter by
     * @return array Array of user IDs
     */
    protected function getUserIds($limit = 5, $role = 'lab_tech')
    {
        global $wpdb;
        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}usermeta 
                WHERE meta_key = '{$wpdb->prefix}capabilities' 
                AND meta_value LIKE %s
                LIMIT %d",
                '%' . $wpdb->esc_like('"' . $role . '"') . '%',
                $limit
            )
        );
        
        // If no users with specific role found, return any user IDs
        if (empty($user_ids)) {
            $user_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->users} LIMIT {$limit}");
        }
        
        return $user_ids;
    }
}
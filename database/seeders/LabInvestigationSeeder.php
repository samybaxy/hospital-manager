<?php

namespace HospitalManager\Database\Seeders;

class LabInvestigationSeeder extends Seeder
{
    
    /**
     * Lab test types - updated to match database test definition names
     * 
     * @var array
     */
    protected $testTypes = [
        'Complete Blood Count',
        'Liver Function Test',
        'Lipid Profile', 
        'Thyroid Function Test',
        'Urine Dipstick', // Changed from 'Urinalysis'
        'Kidney Function Test',
        'Blood Culture',
        'Urine Culture',
        'HIV Antibody Test', // Changed from 'HIV Test'
        'Hepatitis B Surface Antigen', // Changed from 'Hepatitis B Test'
        'Ferritin',
        'Erythrocyte Sedimentation Rate',
        'Prothrombin Time and INR',
        'Activated Partial Thromboplastin Time',
        'Cortisol',
        'Vitamin B12',
        '24-Hour Urine Protein',
        'COVID-19 PCR',
        'Influenza PCR',
        'Rheumatoid Factor'
    ];
    
    /**
     * Sample types for tests - updated to match new test names
     * 
     * @var array
     */
    protected $sampleTypes = [
        'Complete Blood Count' => 'Whole Blood',
        'Liver Function Test' => 'Serum',
        'Lipid Profile' => 'Serum',
        'Thyroid Function Test' => 'Serum',
        'Urine Dipstick' => 'Urine',
        'Kidney Function Test' => 'Serum',
        'Blood Culture' => 'Blood',
        'Urine Culture' => 'Urine',
        'HIV Antibody Test' => 'Serum',
        'Hepatitis B Surface Antigen' => 'Serum',
        'Ferritin' => 'Serum',
        'Erythrocyte Sedimentation Rate' => 'Whole Blood',
        'Prothrombin Time and INR' => 'Plasma',
        'Activated Partial Thromboplastin Time' => 'Plasma',
        'Cortisol' => 'Serum',
        'Vitamin B12' => 'Serum',
        '24-Hour Urine Protein' => 'Urine',
        'COVID-19 PCR' => 'Nasopharyngeal Swab',
        'Influenza PCR' => 'Nasopharyngeal Swab',
        'Rheumatoid Factor' => 'Serum'
    ];
    
    /**
     * Cache for test parameters fetched from database
     * 
     * @var array
     */
    protected $testParametersCache = [];
    
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
                
                // Generate realistic created_at date
                $visitation_date = $wpdb->get_var("SELECT date FROM {$wpdb->prefix}hm_visitations WHERE ID = {$visitation_id}");
                $created_at = $this->generateRealisticDate($visitation_date);
                
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
                $lab_notes = null;
                
                if (in_array($status, ['completed', 'verified'])) {
                    // Get test parameters from database
                    $test_parameters = $this->getTestParameters($test_type);
                    
                    if ($test_parameters && !empty($test_parameters)) {
                        // Get patient gender for gender-specific ranges
                        $patient_gender = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT gender FROM {$wpdb->prefix}hm_patients WHERE ID = %d",
                                $visitation->patient_id
                            )
                        ) ?: 'male';
                        
                        // Generate test results using database parameters
                        $results_data = $this->generateTestResults($test_parameters, $patient_gender);
                        
                        $test_results = json_encode(['parameters' => $results_data['parameters']]);
                        $is_abnormal = $results_data['is_abnormal'];
                        $is_critical = $results_data['is_critical'];
                        
                        if (!empty($results_data['abnormal_parameters'])) {
                            $flags = json_encode([
                                'abnormal' => $results_data['abnormal_parameters'],
                                'critical' => $results_data['critical_parameters']
                            ]);
                            
                            $lab_notes = 'Abnormal values detected for: ' . implode(', ', $results_data['abnormal_parameters']);
                            if (!empty($results_data['critical_parameters'])) {
                                $lab_notes .= '. CRITICAL values for: ' . implode(', ', $results_data['critical_parameters']) . '. Physician notified.';
                            }
                        } else {
                            $lab_notes = 'All values within normal ranges.';
                        }
                    } else {
                        // Fallback for tests without specific parameters in database
                        $test_results = json_encode([
                            'result' => 'Test completed',
                            'interpretation' => [
                                'Negative', 'Positive', 'Within normal limits', 'Abnormal'
                            ][rand(0, 3)]
                        ]);
                        
                        $lab_notes = 'Standard testing protocol followed.';
                    }
                }
                
                // Generate realistic updated_at date based on status
                $updated_at = $this->generateUpdatedDate($created_at, $status);
                
                $data = [
                    'visitation_id' => $visitation_id,
                    'doctor_id' => $visitation->doctor_id,
                    'lab_tech_id' => $lab_tech_id,
                    'patient_id' => $visitation->patient_id,
                    'test_type' => $test_type,
                    'sample_type' => $sample_type,
                    'request_notes' => $notes,
                    'lab_notes' => $lab_notes,
                    'test_results' => $test_results,
                    'flags' => $flags,
                    'is_abnormal' => $is_abnormal,
                    'is_critical' => $is_critical,
                    'status' => $status,
                    'created_at' => $created_at,
                    'updated_at' => $updated_at
                ];
                
                $result = $wpdb->insert($wpdb->prefix . 'hm_lab_investigations', $data);
                
                if ($result === false) {
                    $this->log("Failed to insert lab investigation: " . $wpdb->last_error, 'error');
                    $this->log("Data: " . json_encode($data), 'error');
                } else {
                    $count++;
                }
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
    
    /**
     * Get test parameters from database for a specific test type
     * 
     * @param string $testType The test type name
     * @return array|null Test parameters or null if not found
     */
    protected function getTestParameters($testType)
    {
        // Check cache first
        if (isset($this->testParametersCache[$testType])) {
            return $this->testParametersCache[$testType];
        }
        
        global $wpdb;
        
        // Fetch test parameters from database
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT test_parameters FROM {$wpdb->prefix}hm_lab_test_definitions WHERE name = %s",
                $testType
            )
        );
        
        if ($result && !empty($result->test_parameters)) {
            $parameters = json_decode($result->test_parameters, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Extract the parameters array from the nested structure
                $parametersList = isset($parameters['parameters']) ? $parameters['parameters'] : $parameters;
                
                // Cache the result
                $this->testParametersCache[$testType] = $parametersList;
                return $parametersList;
            }
        }
        
        return null;
    }
    
    /**
     * Generate test results based on test parameters
     * 
     * @param array $parameters Test parameters from database
     * @param string $patientGender Patient gender for gender-specific ranges
     * @return array Generated test results with flags
     */
    protected function generateTestResults($parameters, $patientGender = 'male')
    {
        $results = [];
        $abnormal_parameters = [];
        $critical_parameters = [];
        $is_abnormal = 0;
        $is_critical = 0;
        
        // Validate that parameters is an array
        if (!is_array($parameters)) {
            return [
                'parameters' => [],
                'abnormal_parameters' => [],
                'critical_parameters' => [],
                'is_abnormal' => 0,
                'is_critical' => 0
            ];
        }
        
        foreach ($parameters as $param) {
            // Validate parameter structure
            if (!is_array($param)) {
                continue;
            }
            
            // Ensure required keys exist with default values
            $param_id = $param['ID'] ?? $param['id'] ?? 'param_' . uniqid();
            $param_name = $param['name'] ?? 'Unknown Parameter';
            $param_unit = $param['unit'] ?? '';
            $param_ref_range = $param['reference_range'] ?? null;
            
            // Skip parameter if no reference range is available
            if (empty($param_ref_range) || !is_array($param_ref_range)) {
                continue;
            }
            
            $result_param = [
                'id' => $param_id,
                'name' => $param_name,
                'unit' => $param_unit,
                'reference_range' => $param_ref_range,
                'is_abnormal' => false,
                'is_critical' => false,
                'flag' => null
            ];
            
            // Determine reference range based on gender and age
            $ref_range = $param_ref_range;
            
            // Handle nested reference ranges (male/female, adult/child)
            if (isset($ref_range['male']) && isset($ref_range['female'])) {
                $gender_key = strtolower($patientGender);
                $ref_range = $ref_range[$gender_key] ?? $ref_range['male'];
            } elseif (isset($ref_range['adult'])) {
                // Default to adult ranges
                $ref_range = $ref_range['adult'];
            }
            
            // Validate min/max values exist
            if (!isset($ref_range['min']) || !isset($ref_range['max']) || 
                !is_numeric($ref_range['min']) || !is_numeric($ref_range['max'])) {
                continue;
            }
            
            $min_normal = (float) $ref_range['min'];
            $max_normal = (float) $ref_range['max'];
            
            // Skip if invalid range
            if ($min_normal >= $max_normal) {
                continue;
            }
            
            // Get critical range if available
            $critical_range = $param['critical_range'] ?? null;
            
            // 20% chance of abnormal value
            if (rand(1, 100) <= 20) {
                $is_low = (rand(0, 1) === 0);
                
                if ($is_low) {
                    // Generate low abnormal value (5-30% below normal min)
                    $value = $min_normal * (rand(70, 95) / 100);
                } else {
                    // Generate high abnormal value (5-30% above normal max)
                    $value = $max_normal * (rand(105, 130) / 100);
                }
                
                $abnormal_parameters[] = $param_id;
                $is_abnormal = 1;
                $result_param['is_abnormal'] = true;
                $result_param['flag'] = $is_low ? 'L' : 'H';
                
                // Check if value falls into critical range (25% chance if abnormal)
                if (is_array($critical_range) && 
                    isset($critical_range['min']) && isset($critical_range['max']) &&
                    is_numeric($critical_range['min']) && is_numeric($critical_range['max']) &&
                    rand(1, 100) <= 25) {
                    
                    $crit_min = (float) $critical_range['min'];
                    $crit_max = (float) $critical_range['max'];
                    
                    if ($is_low && $value < $crit_min) {
                        $critical_parameters[] = $param_id;
                        $is_critical = 1;
                        $result_param['is_critical'] = true;
                        $result_param['flag'] = 'LL';
                    } elseif (!$is_low && $value > $crit_max) {
                        $critical_parameters[] = $param_id;
                        $is_critical = 1;
                        $result_param['is_critical'] = true;
                        $result_param['flag'] = 'HH';
                    }
                }
            } else {
                // Normal value (10-90% of normal range)
                $value = $min_normal + (($max_normal - $min_normal) * (rand(10, 90) / 100));
            }
            
            // Round to appropriate decimal places
            $value = $this->roundToAppropriateDecimals($value, $param_unit);
            $result_param['value'] = $value;
            
            $results[] = $result_param;
        }
        
        return [
            'parameters' => $results,
            'abnormal_parameters' => $abnormal_parameters,
            'critical_parameters' => $critical_parameters,
            'is_abnormal' => $is_abnormal,
            'is_critical' => $is_critical
        ];
    }
    
    /**
     * Round value to appropriate decimal places based on unit
     * 
     * @param float $value The value to round
     * @param string $unit The unit of measurement
     * @return float Rounded value
     */
    protected function roundToAppropriateDecimals($value, $unit)
    {
        // Handle null or empty units
        if (empty($unit) || !is_string($unit)) {
            return round($value, 2);
        }
        
        // Different units need different precision
        if (strpos($unit, 'g/dL') !== false || strpos($unit, 'g/L') !== false) {
            return round($value, 1); // Hemoglobin, proteins
        } elseif (strpos($unit, 'x10^') !== false) {
            return round($value, 1); // Cell counts
        } elseif (strpos($unit, 'mg/dL') !== false || 
                  strpos($unit, 'mmol/L') !== false ||
                  strpos($unit, 'mIU/L') !== false ||
                  strpos($unit, 'ng/dL') !== false ||
                  strpos($unit, 'pg/mL') !== false) {
            return round($value, 2); // Chemistry tests
        } elseif (strpos($unit, 'U/L') !== false || strpos($unit, '%') !== false) {
            return round($value, 0); // Enzyme activities, percentages
        }
        
        return round($value, 2); // Default
    }
    
    /**
     * Generate a realistic date for lab investigation creation
     * 
     * @param string|null $visitationDate The visitation date as reference
     * @return string Formatted date string
     */
    protected function generateRealisticDate($visitationDate = null)
    {
        $today = new \DateTime();
        $todayTimestamp = $today->getTimestamp();
        
        if ($visitationDate) {
            try {
                $visitationDateTime = new \DateTime($visitationDate);
                $visitationTimestamp = $visitationDateTime->getTimestamp();
                
                // Lab investigation should be created on or after visitation date
                // but not beyond today's date
                $minTimestamp = $visitationTimestamp;
                $maxTimestamp = min($todayTimestamp, $visitationTimestamp + (30 * 24 * 60 * 60)); // Max 30 days after visitation or today, whichever is earlier
                
                // Ensure min doesn't exceed max
                if ($minTimestamp > $maxTimestamp) {
                    $maxTimestamp = $minTimestamp;
                }
                
                // Generate random timestamp between min and max
                $randomTimestamp = rand($minTimestamp, $maxTimestamp);
                
                // Add random hours and minutes for realistic time
                $randomHours = rand(8, 17); // Business hours 8 AM to 5 PM
                $randomMinutes = rand(0, 59);
                
                $date = new \DateTime();
                $date->setTimestamp($randomTimestamp);
                $date->setTime($randomHours, $randomMinutes, 0);
                
                // Final check to ensure we don't exceed today
                if ($date->getTimestamp() > $todayTimestamp) {
                    $date = $today;
                }
                
                return $date->format('Y-m-d H:i:s');
                
            } catch (\Exception $e) {
                // Fall back to generating date within last 90 days if visitation date is invalid
                $this->log("Invalid visitation date: {$visitationDate}, using fallback", 'warning');
            }
        }
        
        // Fallback: Generate date within last 90 days, not exceeding today
        $daysBack = rand(1, 90);
        $randomTimestamp = $todayTimestamp - ($daysBack * 24 * 60 * 60);
        
        // Add random hours and minutes
        $randomHours = rand(8, 17);
        $randomMinutes = rand(0, 59);
        
        $date = new \DateTime();
        $date->setTimestamp($randomTimestamp);
        $date->setTime($randomHours, $randomMinutes, 0);
        
        return $date->format('Y-m-d H:i:s');
    }
    
    /**
     * Generate a realistic updated_at date based on investigation status
     * 
     * @param string $createdAt The creation date
     * @param string $status The investigation status
     * @return string Formatted date string
     */
    protected function generateUpdatedDate($createdAt, $status)
    {
        try {
            $createdDateTime = new \DateTime($createdAt);
            $today = new \DateTime();
            
            // For 'requested' status, updated_at should be same as created_at
            if ($status === 'requested') {
                return $createdAt;
            }
            
            // Calculate realistic time progression based on status
            $hoursToAdd = 0;
            switch ($status) {
                case 'sample_collected':
                    $hoursToAdd = rand(1, 24); // 1-24 hours after creation
                    break;
                case 'in_progress':
                    $hoursToAdd = rand(2, 48); // 2-48 hours after creation
                    break;
                case 'completed':
                    $hoursToAdd = rand(24, 72); // 1-3 days after creation
                    break;
                case 'verified':
                    $hoursToAdd = rand(48, 120); // 2-5 days after creation
                    break;
                default:
                    $hoursToAdd = rand(1, 12); // Default 1-12 hours
            }
            
            $updatedDateTime = clone $createdDateTime;
            $updatedDateTime->add(new \DateInterval("PT{$hoursToAdd}H"));
            
            // Add random minutes for more realistic timing
            $minutesToAdd = rand(0, 59);
            $updatedDateTime->add(new \DateInterval("PT{$minutesToAdd}M"));
            
            // Ensure updated date doesn't exceed today
            if ($updatedDateTime->getTimestamp() > $today->getTimestamp()) {
                return $today->format('Y-m-d H:i:s');
            }
            
            return $updatedDateTime->format('Y-m-d H:i:s');
            
        } catch (\Exception $e) {
            // Fallback to created_at if there's any issue
            return $createdAt;
        }
    }
}
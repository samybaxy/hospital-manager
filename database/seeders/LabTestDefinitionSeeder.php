<?php

namespace HospitalManager\Database\Seeders;

class LabTestDefinitionSeeder extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Seeding lab test definitions...');
        
        global $wpdb;
        $categories = $wpdb->get_results("SELECT ID, name FROM {$wpdb->prefix}hm_laboratory_categories");
        
        if (empty($categories)) {
            $this->log('No laboratory categories found. Please run LaboratoryCategorySeeder first.', 'error');
            return;
        }
        
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->name] = $category->ID;
        }
        
        $count = 0;
        $testTable = $wpdb->prefix . 'hm_lab_test_definitions';
        
        // Hematology Tests
        if (isset($categoryMap['Hematology'])) {
            $test = [
                'category_id' => $categoryMap['Hematology'],
                'code' => 'CBC',
                'name' => 'Complete Blood Count',
                'description' => 'Basic screening test for blood disorders',
                'sample_type' => 'Whole Blood',
                'container' => 'EDTA Tube (Purple Top)',
                'sample_volume' => '3-5 mL',
                'turnaround_time' => '1-2 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'name' => 'White Blood Cell (WBC) Count',
                            'unit' => 'x10^9/L',
                            'reference_range' => [
                                'adult' => ['min' => 4.0, 'max' => 11.0],
                                'child' => ['min' => 5.0, 'max' => 14.5]
                            ],
                            'critical_range' => ['min' => 2.0, 'max' => 30.0]
                        ],
                        [
                            'name' => 'Red Blood Cell (RBC) Count',
                            'unit' => 'x10^12/L',
                            'reference_range' => [
                                'male' => ['min' => 4.5, 'max' => 5.5],
                                'female' => ['min' => 3.8, 'max' => 5.0]
                            ],
                            'critical_range' => ['min' => 2.5, 'max' => 7.0]
                        ],
                        [
                            'name' => 'Hemoglobin (Hgb)',
                            'unit' => 'g/dL',
                            'reference_range' => [
                                'male' => ['min' => 13.5, 'max' => 17.5],
                                'female' => ['min' => 12.0, 'max' => 16.0]
                            ],
                            'critical_range' => ['min' => 7.0, 'max' => 20.0]
                        ],
                        [
                            'name' => 'Hematocrit (Hct)',
                            'unit' => '%',
                            'reference_range' => [
                                'male' => ['min' => 41.0, 'max' => 50.0],
                                'female' => ['min' => 36.0, 'max' => 46.0]
                            ],
                            'critical_range' => ['min' => 20.0, 'max' => 60.0]
                        ],
                        [
                            'name' => 'Platelet Count',
                            'unit' => 'x10^9/L',
                            'reference_range' => ['min' => 150, 'max' => 450],
                            'critical_range' => ['min' => 50, 'max' => 1000]
                        ],
                        [
                            'name' => 'Mean Corpuscular Volume (MCV)',
                            'unit' => 'fL',
                            'reference_range' => ['min' => 80, 'max' => 100],
                            'critical_range' => ['min' => 60, 'max' => 120]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Fresh whole blood, less than 24 hours old',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Flow Cytometry, Electrical Impedance',
                'cost' => 35.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
            
            // Add more Hematology tests
        }
        
        // Clinical Chemistry Tests
        if (isset($categoryMap['Clinical Chemistry'])) {
            $test = [
                'category_id' => $categoryMap['Clinical Chemistry'],
                'code' => 'LFT',
                'name' => 'Liver Function Test',
                'description' => 'Panel of tests to assess liver function',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '2-4 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'name' => 'Alanine Transaminase (ALT)',
                            'unit' => 'U/L',
                            'reference_range' => [
                                'male' => ['min' => 7, 'max' => 55],
                                'female' => ['min' => 7, 'max' => 45]
                            ],
                            'critical_range' => ['min' => 5, 'max' => 1000]
                        ],
                        [
                            'name' => 'Aspartate Transaminase (AST)',
                            'unit' => 'U/L',
                            'reference_range' => [
                                'male' => ['min' => 8, 'max' => 48],
                                'female' => ['min' => 8, 'max' => 40]
                            ],
                            'critical_range' => ['min' => 5, 'max' => 1000]
                        ],
                        [
                            'name' => 'Alkaline Phosphatase (ALP)',
                            'unit' => 'U/L',
                            'reference_range' => ['min' => 40, 'max' => 129],
                            'critical_range' => ['min' => 20, 'max' => 500]
                        ],
                        [
                            'name' => 'Total Bilirubin',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0.1, 'max' => 1.2],
                            'critical_range' => ['min' => 0.0, 'max' => 15.0]
                        ],
                        [
                            'name' => 'Direct Bilirubin',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0.0, 'max' => 0.3],
                            'critical_range' => ['min' => 0.0, 'max' => 8.0]
                        ],
                        [
                            'name' => 'Albumin',
                            'unit' => 'g/dL',
                            'reference_range' => ['min' => 3.5, 'max' => 5.0],
                            'critical_range' => ['min' => 1.5, 'max' => 6.0]
                        ],
                        [
                            'name' => 'Total Protein',
                            'unit' => 'g/dL',
                            'reference_range' => ['min' => 6.0, 'max' => 8.3],
                            'critical_range' => ['min' => 4.0, 'max' => 10.0]
                        ],
                        [
                            'name' => 'Gamma-glutamyl Transferase (GGT)',
                            'unit' => 'U/L',
                            'reference_range' => [
                                'male' => ['min' => 8, 'max' => 61],
                                'female' => ['min' => 5, 'max' => 36]
                            ],
                            'critical_range' => ['min' => 5, 'max' => 1000]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'Fast for 8-12 hours before sample collection',
                'methodology' => 'Spectrophotometry, Enzymatic assay',
                'cost' => 45.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }
        
        // Endocrinology Tests
        if (isset($categoryMap['Endocrinology'])) {
            $test = [
                'category_id' => $categoryMap['Endocrinology'],
                'code' => 'TFT',
                'name' => 'Thyroid Function Test',
                'description' => 'Panel of tests to assess thyroid function',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'name' => 'Thyroid Stimulating Hormone (TSH)',
                            'unit' => 'mIU/L',
                            'reference_range' => ['min' => 0.4, 'max' => 4.0],
                            'critical_range' => ['min' => 0.01, 'max' => 100]
                        ],
                        [
                            'name' => 'Free Thyroxine (FT4)',
                            'unit' => 'ng/dL',
                            'reference_range' => ['min' => 0.8, 'max' => 1.8],
                            'critical_range' => ['min' => 0.1, 'max' => 5.0]
                        ],
                        [
                            'name' => 'Free Triiodothyronine (FT3)',
                            'unit' => 'pg/mL',
                            'reference_range' => ['min' => 2.3, 'max' => 4.2],
                            'critical_range' => ['min' => 0.5, 'max' => 20.0]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'No special preparation needed',
                'methodology' => 'Immunoassay',
                'cost' => 55.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }
        
        $this->log("Created {$count} lab test definitions", 'success');
    }
    
    /**
     * Insert or update test definition
     * 
     * @param string $table Table name
     * @param array $test Test data
     */
    private function insertOrUpdateTest($table, $test)
    {
        global $wpdb;
        
        // Check if test already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT ID FROM $table WHERE code = %s", $test['code'])
        );
        
        if ($exists) {
            $wpdb->update(
                $table,
                $test,
                ['ID' => $exists]
            );
        } else {
            $test['created_at'] = current_time('mysql');
            $test['updated_at'] = current_time('mysql');
            $wpdb->insert($table, $test);
        }
    }
}
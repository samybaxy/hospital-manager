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
        $categories = $wpdb->get_results("SELECT ID, name FROM {$wpdb->prefix}hm_lab_categories");
        
        if (empty($categories)) {
            $this->log('No laboratory categories found. Please run LabTestCategorySeeder first.', 'error');
            return;
        }
        
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->name] = $category->ID;
        }
        
        $count = 0;
        $testTable = $wpdb->prefix . 'hm_lab_test_definitions';

        if (isset($categoryMap['Hematology'])) {
            // Complete Blood Count (CBC) with Differential
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
                            'id' => 'WBC',
                            'name' => 'White Blood Cell Count',
                            'unit' => 'x10^9/L',
                            'reference_range' => [
                                'adult' => ['min' => 4.0, 'max' => 11.0],
                                'child' => ['min' => 5.0, 'max' => 14.5]
                            ],
                            'critical_range' => ['min' => 2.0, 'max' => 30.0]
                        ],
                        [
                            'id' => 'RBC',
                            'name' => 'Red Blood Cell Count',
                            'unit' => 'x10^12/L',
                            'reference_range' => [
                                'male' => ['min' => 4.5, 'max' => 5.5],
                                'female' => ['min' => 3.8, 'max' => 5.0]
                            ],
                            'critical_range' => ['min' => 2.5, 'max' => 7.0]
                        ],
                        [
                            'id' => 'HGB',
                            'name' => 'Hemoglobin',
                            'unit' => 'g/dL',
                            'reference_range' => [
                                'male' => ['min' => 13.5, 'max' => 17.5],
                                'female' => ['min' => 12.0, 'max' => 16.0]
                            ],
                            'critical_range' => ['min' => 7.0, 'max' => 20.0]
                        ],
                        [
                            'id' => 'HCT',
                            'name' => 'Hematocrit',
                            'unit' => '%',
                            'reference_range' => [
                                'male' => ['min' => 41.0, 'max' => 50.0],
                                'female' => ['min' => 36.0, 'max' => 46.0]
                            ],
                            'critical_range' => ['min' => 20.0, 'max' => 60.0]
                        ],
                        [
                            'id' => 'PLT',
                            'name' => 'Platelet Count',
                            'unit' => 'x10^9/L',
                            'reference_range' => ['min' => 150, 'max' => 450],
                            'critical_range' => ['min' => 50, 'max' => 1000]
                        ],
                        [
                            'id' => 'MCV',
                            'name' => 'Mean Corpuscular Volume',
                            'unit' => 'fL',
                            'reference_range' => ['min' => 80, 'max' => 100],
                            'critical_range' => ['min' => 60, 'max' => 120]
                        ],
                        [
                            'id' => 'NEUT',
                            'name' => 'Neutrophils (%)',
                            'unit' => '%',
                            'reference_range' => ['min' => 40, 'max' => 75],
                            'critical_range' => ['min' => 10, 'max' => 90]
                        ],
                        [
                            'id' => 'LYMPH',
                            'name' => 'Lymphocytes (%)',
                            'unit' => '%',
                            'reference_range' => ['min' => 20, 'max' => 40],
                            'critical_range' => ['min' => 5, 'max' => 80]
                        ],
                        [
                            'id' => 'MONO',
                            'name' => 'Monocytes (%)',
                            'unit' => '%',
                            'reference_range' => ['min' => 2, 'max' => 10],
                            'critical_range' => ['min' => 0, 'max' => 20]
                        ],
                        [
                            'id' => 'EOS',
                            'name' => 'Eosinophils (%)',
                            'unit' => '%',
                            'reference_range' => ['min' => 0, 'max' => 7],
                            'critical_range' => ['min' => 0, 'max' => 20]
                        ],
                        [
                            'id' => 'BASO',
                            'name' => 'Basophils (%)',
                            'unit' => '%',
                            'reference_range' => ['min' => 0, 'max' => 3],
                            'critical_range' => ['min' => 0, 'max' => 5]
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

            // Erythrocyte Sedimentation Rate (ESR)
            $test = [
                'category_id' => $categoryMap['Hematology'],
                'code' => 'ESR',
                'name' => 'Erythrocyte Sedimentation Rate',
                'description' => 'Measures the rate at which red blood cells settle in a tube of blood',
                'sample_type' => 'Whole Blood',
                'container' => 'EDTA Tube (Purple Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '1-2 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'ESR',
                            'name' => 'ESR',
                            'unit' => 'mm/h',
                            'reference_range' => [
                                'male' => ['min' => 0, 'max' => 15],
                                'female' => ['min' => 0, 'max' => 20]
                            ],
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Whole blood collected in EDTA tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Westergren method',
                'cost' => 10.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Ferritin
            $test = [
                'category_id' => $categoryMap['Hematology'],
                'code' => 'FERRITIN',
                'name' => 'Ferritin',
                'description' => 'Measures the level of ferritin in the blood',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'FERRITIN',
                            'name' => 'Ferritin',
                            ' unit' => 'ng/mL',
                            'reference_range' => [
                                'male' => ['min' => 20, 'max' => 250],
                                'female' => ['min' => 10, 'max' => 120]
                            ],
                            'critical_range' => ['min' => 5, 'max' => 1000]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'Fast for 8-12 hours before sample collection',
                'methodology' => 'Immunoassay',
                'cost' => 25.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Vitamin B12
            $test = [
                'category_id' => $categoryMap['Hematology'],
                'code' => 'B12',
                'name' => 'Vitamin B12',
                'description' => 'Measures vitamin B12 levels in blood',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'B12',
                            'name' => 'Vitamin B12',
                            'unit' => 'pg/mL',
                            'reference_range' => ['min' => 200, 'max' => 900],
                            'critical_range' => ['min' => 100, 'max' => 2000]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'Fast for 8-12 hours before sample collection',
                'methodology' => 'Immunoassay',
                'cost' => 30.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        // Clinical Chemistry Tests
        if (isset($categoryMap['Clinical Chemistry'])) {
            // Liver Function Test (LFT)
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
                            'id' => 'ALT',
                            'name' => 'Alanine Transaminase',
                            'unit' => 'U/L',
                            'reference_range' => [
                                'male' => ['min' => 7, 'max' => 55],
                                'female' => ['min' => 7, 'max' => 45]
                            ],
                            'critical_range' => ['min' => 5, 'max' => 1000]
                        ],
                        [
                            'id' => 'AST',
                            'name' => 'Aspartate Transaminase',
                            'unit' => 'U/L',
                            'reference_range' => [
                                'male' => ['min' => 8, 'max' => 48],
                                'female' => ['min' => 8, 'max' => 40]
                            ],
                            'critical_range' => ['min' => 5, 'max' => 1000]
                        ],
                        [
                            'id' => 'ALP',
                            'name' => 'Alkaline Phosphatase',
                            'unit' => 'U/L',
                            'reference_range' => ['min' => 40, 'max' => 129],
                            'critical_range' => ['min' => 20, 'max' => 500]
                        ],
                        [
                            'id' => 'TBIL',
                            'name' => 'Total Bilirubin',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0.1, 'max' => 1.2],
                            'critical_range' => ['min' => 0.0, 'max' => 15.0]
                        ],
                        [
                            'id' => 'DBIL',
                            'name' => 'Direct Bilirubin',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0.0, 'max' => 0.3],
                            'critical_range' => ['min' => 0.0, 'max' => 8.0]
                        ],
                        [
                            'id' => 'ALB',
                            'name' => 'Albumin',
                            'unit' => 'g/dL',
                            'reference_range' => ['min' => 3.5, 'max' => 5.0],
                            'critical_range' => ['min' => 1.5, 'max' => 6.0]
                        ],
                        [
                            'id' => 'TP',
                            'name' => 'Total Protein',
                            'unit' => 'g/dL',
                            'reference_range' => ['min' => 6.0, 'max' => 8.3],
                            'critical_range' => ['min' => 4.0, 'max' => 10.0]
                        ],
                        [
                            'id' => 'GGT',
                            'name' => 'Gamma-glutamyl Transferase',
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

            // Kidney Function Test (KFT)
            $test = [
                'category_id' => $categoryMap['Clinical Chemistry'],
                'code' => 'KFT',
                'name' => 'Kidney Function Test',
                'description' => 'Panel of tests to assess kidney function',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '2-4 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'BUN',
                            'name' => 'Blood Urea Nitrogen',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 7, 'max' => 20],
                            'critical_range' => ['min' => 5, 'max' => 100]
                        ],
                        [
                            'id' => 'CREAT',
                            'name' => 'Creatinine',
                            'unit' => 'mg/dL',
                            'reference_range' => [
                                'male' => ['min' => 0.7, 'max' => 1.3],
                                'female' => ['min' => 0.6, 'max' => 1.1]
                            ],
                            'critical_range' => ['min' => 0.3, 'max' => 10.0]
                        ],
                        [
                            'id' => 'NA',
                            'name' => 'Sodium',
                            'unit' => 'mmol/L',
                            'reference_range' => ['min' => 135, 'max' => 145],
                            'critical_range' => ['min' => 120, 'max' => 160]
                        ],
                        [
                            'id' => 'K',
                            'name' => 'Potassium',
                            'unit' => 'mmol/L',
                            'reference_range' => ['min' => 3.5, 'max' => 5.1],
                            'critical_range' => ['min' => 2.5, 'max' => 6.5]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'Fast for 8-12 hours before sample collection',
                'methodology' => 'Spectrophotometry, Ion-selective electrode',
                'cost' => 30.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Lipid Profile
            $test = [
                'category_id' => $categoryMap['Clinical Chemistry'],
                'code' => 'LIPID',
                'name' => 'Lipid Profile',
                'description' => 'Measures cholesterol and triglyceride levels',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'CHOL',
                            'name' => 'Total Cholesterol',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 200],
                            'critical_range' => ['min' => 0, 'max' => 500]
                        ],
                        [
                            'id' => 'HDL',
                            'name' => 'HDL Cholesterol',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 40, 'max' => 60],
                            'critical_range' => ['min' => 20, 'max' => 100]
                        ],
                        [
                            'id' => 'LDL',
                            'name' => 'LDL Cholesterol',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 100],
                            'critical_range' => ['min' => 0, 'max' => 200]
                        ],
                        [
                            'id' => 'TRIG',
                            'name' => 'Triglycerides',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 150],
                            'critical_range' => ['min' => 0, 'max' => 500]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'Fast for 12 hours before sample collection',
                'methodology' => 'Spectrophotometry',
                'cost' => 30.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        // Endocrinology Tests
        if (isset($categoryMap['Endocrinology'])) {
            // Thyroid Function Test (TFT)
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
                            'id' => 'TSH',
                            'name' => 'Thyroid Stimulating Hormone',
                            'unit' => 'mIU/L',
                            'reference_range' => ['min' => 0.4, 'max' => 4.0],
                            'critical_range' => ['min' => 0.01, 'max' => 100]
                        ],
                        [
                            'id' => 'FT4',
                            'name' => 'Free Thyroxine',
                            'unit' => 'ng/dL',
                            'reference_range' => ['min' => 0.8, 'max' => 1.8],
                            'critical_range' => ['min' => 0.1, 'max' => 5.0]
                        ],
                        [
                            'id' => 'FT3',
                            'name' => 'Free Triiodothyronine',
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

            // Cortisol
            $test = [
                'category_id' => $categoryMap['Endocrinology'],
                'code' => 'CORT',
                'name' => 'Cortisol',
                'description' => 'Measures cortisol levels in blood',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'CORT',
                            'name' => 'Cortisol',
                            'unit' => 'mcg/dL',
                            'reference_range' => [
                                'morning' => ['min' => 5, 'max' => 25],
                                'afternoon' => ['min' => 3, 'max' => 16],
                                'evening' => ['min' => 3, 'max' => 11]
                            ],
                            'critical_range' => ['min' => 1, 'max' => 50]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'Sample should be collected at specific times (morning, afternoon, evening)',
                'methodology' => 'Immunoassay',
                'cost' => 40.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        if (isset($categoryMap['Microbiology'])) {
            // Blood Culture
            $test = [
                'category_id' => $categoryMap['Microbiology'],
                'code' => 'BC',
                'name' => 'Blood Culture',
                'description' => 'Detects bacteria or fungi in the blood',
                'sample_type' => 'Whole Blood',
                'container' => 'Blood Culture Bottle',
                'sample_volume' => '10-20 mL',
                'turnaround_time' => '2-5 days',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'BC_RESULT',
                            'name' => 'Blood Culture Result',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Aseptically collected blood in culture bottles',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Automated blood culture system',
                'cost' => 50.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Urine Culture
            $test = [
                'category_id' => $categoryMap['Microbiology'],
                'code' => 'UC',
                'name' => 'Urine Culture',
                'description' => 'Detects bacteria in urine',
                'sample_type' => 'Urine',
                'container' => 'Sterile Container',
                'sample_volume' => '10-50 mL',
                'turnaround_time' => '24-48 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'UC_RESULT',
                            'name' => 'Urine Culture Result',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Midstream clean catch urine',
                'preparation_instructions' => 'Clean genital area before collection',
                'methodology' => 'Culture on agar plates',
                'cost' => 20.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Stool Culture
            $test = [
                'category_id' => $categoryMap['Microbiology'],
                'code' => 'SC',
                'name' => 'Stool Culture',
                'description' => 'Detects pathogenic bacteria in stool',
                'sample_type' => 'Stool',
                'container' => 'Sterile Container',
                'sample_volume' => '5-10 g',
                'turnaround_time' => '48-72 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'SC_RESULT',
                            'name' => 'Stool Culture Result',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Fresh stool sample in sterile container',
                'preparation_instructions' => 'Avoid contamination with urine or water',
                'methodology' => 'Culture on selective media',
                'cost' => 25.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        if (isset($categoryMap['Immunology'])) {
            // HIV Antibody Test
            $test = [
                'category_id' => $categoryMap['Immunology'],
                'code' => 'HIV_AB',
                'name' => 'HIV Antibody Test',
                'description' => 'Detects antibodies to HIV',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'HIV_AB',
                            'name' => 'HIV Antibody',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Enzyme Immunoassay (EIA)',
                'cost' => 30.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Hepatitis B Surface Antigen (HBsAg)
            $test = [
                'category_id' => $categoryMap['Immunology'],
                'code' => 'HBsAg',
                'name' => 'Hepatitis B Surface Antigen',
                'description' => 'Detects HBsAg in blood',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'HBsAg',
                            'name' => 'HBsAg',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Chemiluminescence Immunoassay (CLIA)',
                'cost' => 25.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Rheumatoid Factor (RF)
            $test = [
                'category_id' => $categoryMap['Immunology'],
                'code' => 'RF{keyword}RF',
                'name' => 'Rheumatoid Factor',
                'description' => 'Measures rheumatoid factor levels in blood',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'RF',
                            'name' => 'Rheumatoid Factor',
                            'unit' => 'IU/mL',
                            'reference_range' => ['min' => 0, 'max' => 14],
                            'critical_range' => ['min' => 0, 'max' => 100]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Nephelometry',
                'cost' => 20.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        if (isset($categoryMap['Urinalysis'])) {
            // Urine Dipstick
            $test = [
                'category_id' => $categoryMap['Urinalysis'],
                'code' => 'UD',
                'name' => 'Urine Dipstick',
                'description' => 'Screens for various substances in urine',
                'sample_type' => 'Urine',
                'container' => 'Sterile Container',
                'sample_volume' => '10 mL',
                'turnaround_time' => '15 minutes',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'pH',
                            'name' => 'pH',
                            'unit' => '',
                            'reference_range' => ['min' => 4.6, 'max' => 8.0],
                            'critical_range' => null
                        ],
                        [
                            'id' => 'PROT',
                            'name' => 'Protein',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 10],
                            'critical_range' => ['min' => 0, 'max' => 100]
                        ],
                        [
                            'id' => 'GLU',
                            'name' => 'Glucose',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 0],
                            'critical_range' => ['min' => 0, 'max' => 500]
                        ],
                        [
                            'id' => 'KET',
                            'name' => 'Ketones',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 0],
                            'critical_range' => ['min' => 0, 'max' => 160]
                        ],
                        [
                            'id' => 'BIL',
                            'name' => 'Bilirubin',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 0],
                            'critical_range' => ['min' => 0, 'max' => 10]
                        ],
                        [
                            'id' => 'UBG',
                            'name' => 'Urobilinogen',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 1],
                            'critical_range' => ['min' => 0, 'max' => 8]
                        ],
                        [
                            'id' => 'BLD',
                            'name' => 'Blood',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ],
                        [
                            'id' => 'LEU',
                            'name' => 'Leukocytes',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ],
                        [
                            'id' => 'NIT',
                            'name' => 'Nitrite',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Fresh urine sample',
                'preparation_instructions' => 'Collect midstream urine',
                'methodology' => 'Dipstick test',
                'cost' => 5.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // 24-Hour Urine Protein
            $test = [
                'category_id' => $categoryMap['Urinalysis'],
                'code' => '24HUP',
                'name' => '24-Hour Urine Protein',
                'description' => 'Measures total protein in urine over 24 hours',
                'sample_type' => 'Urine',
                'container' => '24-Hour Urine Container',
                'sample_volume' => 'All urine in 24 hours',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => '24HUP',
                            'name' => 'Total Protein',
                            'unit' => 'mg/24h',
                            'reference_range' => ['min' => 0, 'max' => 150],
                            'critical_range' => ['min' => 0, 'max' => 1000]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Collect all urine over 24 hours in a container',
                'preparation_instructions' => 'Start collection after first morning void',
                'methodology' => 'Spectrophotometry',
                'cost' => 30.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        if (isset($categoryMap['Toxicology'])) {
            // Drug Screen
            $test = [
                'category_id' => $categoryMap['Toxicology'],
                'code' => 'DS',
                'name' => 'Drug Screen',
                'description' => 'Screens for common drugs of abuse',
                'sample_type' => 'Urine',
                'container' => 'Sterile Container',
                'sample_volume' => '30 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'AMPH',
                            'name' => 'Amphetamines',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ],
                        [
                            'id' => 'COC',
                            'name' => 'Cocaine Metabolites',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ],
                        [
                            'id' => 'OPI',
                            'name' => 'Opiates',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ],
                        [
                            'id' => 'THC',
                            'name' => 'THC Metabolites',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ],
                        [
                            'id' => 'PCP',
                            'name' => 'Phencyclidine',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Random urine sample',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Immunoassay',
                'cost' => 40.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Alcohol Level
            $test = [
                'category_id' => $categoryMap['Toxicology'],
                'code' => 'ALC',
                'name' => 'Alcohol Level',
                'description' => 'Measures ethanol levels in blood',
                'sample_type' => 'Whole Blood',
                'container' => 'EDTA Tube (Purple Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '1-2 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'ALC',
                            'name' => 'Ethanol',
                            'unit' => 'mg/dL',
                            'reference_range' => ['min' => 0, 'max' => 0],
                            'critical_range' => ['min' => 0, 'max' => 400]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Whole blood collected in EDTA tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Gas Chromatography',
                'cost' => 35.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        if (isset($categoryMap['Serology'])) {
            // Rubella IgG
            $test = [
                'category_id' => $categoryMap['Serology'],
                'code' => 'RUB_IgG',
                'name' => 'Rubella IgG',
                'description' => 'Detects IgG antibodies to rubella virus',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'RUB_IgG',
                            'name' => 'Rubella IgG',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Enzyme Immunoassay (EIA)',
                'cost' => 25.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Toxoplasma IgM
            $test = [
                'category_id' => $categoryMap['Serology'],
                'code' => 'TOXO_IgM',
                'name' => 'Toxoplasma IgM',
                'description' => 'Detects IgM antibodies to Toxoplasma gondii',
                'sample_type' => 'Serum',
                'container' => 'SST Tube (Gold Top)',
                'sample_volume' => '5 mL',
                'turnaround_time' => '24 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'TOXO_IgM',
                            'name' => 'Toxoplasma IgM',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Serum from blood collected in SST tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Enzyme Immunoassay (EIA)',
                'cost' => 30.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        if (isset($categoryMap['Molecular Diagnostics'])) {
            // COVID-19 PCR
            $test = [
                'category_id' => $categoryMap['Molecular Diagnostics'],
                'code' => 'COVID_PCR',
                'name' => 'COVID-19 PCR',
                'description' => 'Detects SARS-CoV-2 RNA by PCR',
                'sample_type' => 'Nasopharyngeal Swab',
                'container' => 'Viral Transport Medium',
                'sample_volume' => '1-2 mL',
                'turnaround_time' => '24-48 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'COVID_PCR',
                            'name' => 'COVID-19 PCR Result',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Nasopharyngeal swab in viral transport medium',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Real-time RT-PCR',
                'cost' => 60.00,
                'is_panel' => 0,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Influenza PCR
            $test = [
                'category_id' => $categoryMap['Molecular Diagnostics'],
                'code' => 'FLU_PCR',
                'name' => 'Influenza PCR',
                'description' => 'Detects Influenza A and B RNA by PCR',
                'sample_type' => 'Nasopharyngeal Swab',
                'container' => 'Viral Transport Medium',
                'sample_volume' => '1-2 mL',
                'turnaround_time' => '24-48 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'FLU_A',
                            'name' => 'Influenza A',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ],
                        [
                            'id' => 'FLU_B',
                            'name' => 'Influenza B',
                            'unit' => 'Qualitative',
                            'reference_range' => null,
                            'critical_range' => null
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Nasopharyngeal swab in viral transport medium',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Real-time RT-PCR',
                'cost' => 55.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;
        }

        if (isset($categoryMap['Coagulation Studies'])) {
            // Prothrombin Time (PT) and INR
            $test = [
                'category_id' => $categoryMap['Coagulation Studies'],
                'code' => 'PT_INR',
                'name' => 'Prothrombin Time and INR',
                'description' => 'Measures blood clotting time and calculates INR',
                'sample_type' => 'Whole Blood',
                'container' => 'Citrate Tube (Blue Top)',
                'sample_volume' => '2.7 mL',
                'turnaround_time' => '1-2 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'PT',
                            'name' => 'Prothrombin Time',
                            'unit' => 'seconds',
                            'reference_range' => ['min' => 10, 'max' => 13],
                            'critical_range' => ['min' => 5, 'max' => 20]
                        ],
                        [
                            'id' => 'INR',
                            'name' => 'International Normalized Ratio',
                            'unit' => '',
                            'reference_range' => ['min' => 0.8, 'max' => 1.2],
                            'critical_range' => ['min' => 0.5, 'max' => 5.0]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Whole blood collected in citrate tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Coagulation analyzer',
                'cost' => 15.00,
                'is_panel' => 1,
                'status' => 'active'
            ];
            $this->insertOrUpdateTest($testTable, $test);
            $count++;

            // Activated Partial Thromboplastin Time (aPTT)
            $test = [
                'category_id' => $categoryMap['Coagulation Studies'],
                'code' => 'APTT',
                'name' => 'Activated Partial Thromboplastin Time',
                'description' => 'Measures intrinsic pathway clotting time',
                'sample_type' => 'Whole Blood',
                'container' => 'Citrate Tube (Blue Top)',
                'sample_volume' => '2.7 mL',
                'turnaround_time' => '1-2 hours',
                'test_parameters' => json_encode([
                    'parameters' => [
                        [
                            'id' => 'APTT',
                            'name' => 'aPTT',
                            'unit' => 'seconds',
                            'reference_range' => ['min' => 25, 'max' => 35],
                            'critical_range' => ['min' => 15, 'max' => 50]
                        ]
                    ]
                ]),
                'specimen_requirements' => 'Whole blood collected in citrate tube',
                'preparation_instructions' => 'No special preparation required',
                'methodology' => 'Coagulation analyzer',
                'cost' => 15.00,
                'is_panel' => 0,
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
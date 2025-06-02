<?php

namespace HospitalManager\Database\Seeders;

class RadiologicalExamSeeder extends Seeder
{
    /**
     * Radiological exam types
     * 
     * @var array
     */
    protected $examTypes = [
        'X-Ray',
        'MRI Scan',
        'CT Scan',
        'Ultrasound',
        'Mammography',
        'PET Scan',
        'Bone Density Scan',
        'Fluoroscopy',
        'Angiography',
        'Echocardiography'
    ];
    
    /**
     * Random radiological exam results
     * 
     * @var array
     */
    protected $results = [
        'X-Ray' => [
            'No significant abnormalities detected.',
            'Normal chest radiograph.',
            'Mild degenerative changes noted in the spine.',
            'No acute fracture or dislocation.',
            'Soft tissues appear normal.'
        ],
        'MRI Scan' => [
            'Normal brain MRI with no evidence of mass, infarction or hemorrhage.',
            'Mild disc degeneration at L4-L5 without nerve compression.',
            'No abnormal enhancement or structural abnormality.',
            'Intact ligamentous structures with no tears identified.',
            'Small joint effusion noted, otherwise unremarkable.'
        ],
        'CT Scan' => [
            'No evidence of acute intracranial abnormality.',
            'No pulmonary embolism. Lungs clear.',
            'Normal abdominal organs with no masses or adenopathy.',
            'No acute fracture or dislocation identified.',
            'Mild degenerative changes, otherwise unremarkable.'
        ]
    ];
    
    /**
     * Radiological exam statuses
     * 
     * @var array
     */
    protected $statuses = [
        'pending',
        'in_progress',
        'completed'
    ];
    
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Seeding radiological exams...');
        
        global $wpdb;
        
        // Get existing visitations
        $visitation_ids = $this->getExistingIds('hm_visitations');
        
        // Get lab_tech IDs to use for radiological technicians (since we don't have a dedicated role)
        $tech_ids = $this->getUserIds(5, 'lab_tech');
        
        if (empty($visitation_ids) || empty($tech_ids)) {
            $this->log('Missing required data for radiological exams. Make sure visitations and lab techs exist.');
            return;
        }
        
        // Create some radiological exams
        $count = 0;
        $maxRecords = 40;
        
        foreach ($visitation_ids as $visitation_id) {
            if ($count >= $maxRecords) break;
            
            // 60% chance of creating a radiological exam for this visitation
            if (rand(1, 10) > 4) {
                // Get visitation date
                $created_at = $wpdb->get_var(
                    "SELECT date FROM {$wpdb->prefix}hm_visitations WHERE ID = {$visitation_id}"
                );
                if (!$created_at) $created_at = date('Y-m-d H:i:s');
                
                // Select a random exam type and tech ID
                $exam_type = $this->examTypes[array_rand($this->examTypes)];
                $tech_id = $tech_ids[array_rand($tech_ids)];
                $status = $this->statuses[array_rand($this->statuses)];
                
                // For completed exams, add results
                $results = null;
                if ($status === 'completed') {
                    // Get specific results for known exam types, or generate generic results
                    if (isset($this->results[$exam_type])) {
                        $result_text = $this->results[$exam_type][array_rand($this->results[$exam_type])];
                    } else {
                        $generic_results = [
                            'Examination completed. No abnormalities detected.',
                            'Examination completed. Mild changes noted.',
                            'Examination completed. Requires follow-up.',
                            'Examination completed. Normal findings.'
                        ];
                        $result_text = $generic_results[array_rand($generic_results)];
                    }
                    
                    // Generate detailed results with structured reporting format
                    $impressions = [
                        'Normal study with no acute findings.',
                        'Mild degenerative changes noted.',
                        'No significant abnormalities identified.',
                        'Findings consistent with clinical presentation.'
                    ];
                    
                    $recommendations = [
                        'Continue current treatment plan.',
                        'Follow-up as clinically indicated.',
                        'Correlate with clinical findings.',
                        'Recommend specialist consultation if symptoms persist.'
                    ];
                    
                    $results = [
                        'exam_type' => $exam_type,
                        'findings' => $result_text,
                        'impression' => $impressions[array_rand($impressions)],
                        'recommendations' => $recommendations[array_rand($recommendations)]
                    ];
                    
                    $results = json_encode($results);
                }
                
                $data = [
                    'visitation_id' => $visitation_id,
                    'tech_id' => $tech_id,
                    'exam_type' => $exam_type,
                    'results' => $results,
                    'status' => $status,
                    'created_at' => $created_at,
                    'updated_at' => $created_at
                ];
                
                $wpdb->insert($wpdb->prefix . 'hm_radiological_exams', $data);
                $count++;
            }
        }
        
        $this->log("Created {$count} radiological exams");
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
}

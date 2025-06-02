<?php

namespace HospitalManager\Database\Seeders;

class LabInvestigationSeeder extends Seeder
{
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
     * Random lab results
     * 
     * @var array
     */
    protected $results = [
        'Complete Blood Count (CBC)' => [
            'WBC: 5.8 x 10^9/L (Normal: 4.0-11.0)',
            'RBC: 4.8 x 10^12/L (Normal: 4.5-5.5)',
            'Hemoglobin: 14.2 g/dL (Normal: 13.5-17.5)',
            'Hematocrit: 42% (Normal: 41-50%)',
            'Platelets: 250 x 10^9/L (Normal: 150-400)'
        ],
        'Blood Glucose Test' => [
            'Fasting Blood Glucose: 98 mg/dL (Normal: 70-100)',
            'Random Blood Glucose: 120 mg/dL (Normal: <200)',
            '2-Hour Postprandial: 135 mg/dL (Normal: <140)'
        ],
        'Liver Function Test' => [
            'ALT: 30 U/L (Normal: 7-55)',
            'AST: 25 U/L (Normal: 8-48)',
            'ALP: 70 U/L (Normal: 40-129)',
            'Total Bilirubin: 0.8 mg/dL (Normal: 0.1-1.2)',
            'Albumin: 4.0 g/dL (Normal: 3.5-5.0)'
        ]
    ];
    
    /**
     * Lab result statuses
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
                
                // Generate created_at date based on visitation date
                $created_at = $wpdb->get_var("SELECT date FROM {$wpdb->prefix}hm_visitations WHERE ID = {$visitation_id}");
                if (!$created_at) $created_at = date('Y-m-d H:i:s');
                
                $sample_notes = [
                    'Routine lab investigation ordered.',
                    'Follow-up test requested by doctor.',
                    'Patient symptoms require lab confirmation.',
                    'Pre-operative lab work ordered.',
                    'Monitoring chronic condition.'
                ];
                $notes = $sample_notes[array_rand($sample_notes)];
                
                // For completed tests, add results
                $results = null;
                
                if ($status === 'completed') {
                    // Get specific results for known test types, or generate generic results
                    if (isset($this->results[$test_type])) {
                        $result_text = $this->results[$test_type][array_rand($this->results[$test_type])];
                    } else {
                        $generic_results = [
                            'Test completed. Results within normal limits.',
                            'Test completed. Some abnormal values noted.',
                            'Test completed. Requires follow-up consultation.',
                            'Test completed. No significant findings.'
                        ];
                        $result_text = $generic_results[array_rand($generic_results)];
                    }
                    
                    $results = $result_text;
                }
                
                $data = [
                    'visitation_id' => $visitation_id,
                    'doctor_id' => $visitation->doctor_id,
                    'lab_tech_id' => $lab_tech_id,
                    'patient_id' => $visitation->patient_id,
                    'test_type' => $test_type,
                    'notes' => $notes,
                    'results' => $results,
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
}

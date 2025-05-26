<?php

namespace HospitalManager\Database\Seeders;

class MedicalReportSeeder extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Seeding medical reports...');
        
        // Get existing visitations
        $visitations = $this->wpdb->get_results(
            "SELECT v.ID, v.patient_id, v.doctor_id, v.diagnosis, v.treatment 
            FROM {$this->wpdb->prefix}hm_visitations v
            ORDER BY v.date DESC
            LIMIT 40"
        );
        
        if (empty($visitations)) {
            $this->log('No visitations found. Cannot create medical reports.');
            return;
        }
        
        $count = 0;
        
        // Create medical reports for about 70% of visitations
        foreach ($visitations as $visitation) {
            // 70% chance of creating a report
            if (rand(1, 10) > 3) {
                $status = rand(1, 10) > 7 ? 'pending' : 'completed';
                $created_at = $this->wpdb->get_var(
                    "SELECT date FROM {$this->wpdb->prefix}hm_visitations WHERE ID = {$visitation->ID}"
                );
                
                if (!$created_at) {
                    $created_at = date('Y-m-d H:i:s');
                }
                
                $updated_at = $status === 'completed' 
                    ? date('Y-m-d H:i:s', strtotime($created_at . ' +2 days')) 
                    : $created_at;
                
                // Generate report content based on diagnosis and treatment
                $report_content = $this->generateReportContent($visitation);
                
                $data = [
                    'patient_id' => $visitation->patient_id,
                    'doctor_id' => $visitation->doctor_id,
                    'visitation_id' => $visitation->ID,
                    'report_content' => $report_content,
                    'status' => $status,
                    'created_at' => $created_at,
                    'updated_at' => $updated_at
                ];
                
                $this->wpdb->insert($this->wpdb->prefix . 'hm_medical_reports', $data);
                $count++;
            }
        }
        
        $this->log("Created {$count} medical reports");
    }
    
    /**
     * Generate medical report content based on visitation data
     * 
     * @param object $visitation Visitation data
     * @return string Report content
     */
    protected function generateReportContent($visitation)
    {
        // Get patient details
        $patient = $this->wpdb->get_row(
            "SELECT first_name, last_name, gender, age FROM {$this->wpdb->prefix}hm_patients WHERE ID = {$visitation->patient_id}"
        );
        
        // Get doctor details
        $doctor = $this->wpdb->get_row(
            "SELECT first_name, last_name FROM {$this->wpdb->prefix}hm_doctors WHERE ID = {$visitation->doctor_id}"
        );
        
        if (!$patient || !$doctor) {
            // Fallback to generic report if patient or doctor not found
            return $this->generateGenericReport();
        }
        
        $diagnosis = $visitation->diagnosis ?: 'No specific diagnosis noted';
        $treatment = $visitation->treatment ?: 'No specific treatment noted';
        
        $gender = $patient->gender ?: 'Unknown';
        $age = $patient->age ?: 'Unknown';
        
        // Build report sections
        $patientDetails = "### Patient Information\n" .
            "**Name:** {$patient->first_name} {$patient->last_name}\n" .
            "**Gender:** {$gender}\n" .
            "**Age:** {$age}\n\n";
        
        $clinicalFindings = "### Clinical Findings\n" .
            $this->faker->paragraph(3) . "\n\n";
        
        $diagnosisSection = "### Diagnosis\n" .
            $diagnosis . "\n\n";
        
        $treatmentSection = "### Treatment Plan\n" .
            $treatment . "\n\n";
        
        $recommendations = "### Recommendations\n" .
            $this->faker->paragraph(2) . "\n\n";
        
        $conclusion = "### Conclusion\n" .
            $this->faker->paragraph(1) . "\n\n";
        
        $signature = "**Physician:** Dr. {$doctor->first_name} {$doctor->last_name}\n" .
            "**Date:** " . date('F j, Y') . "\n";
        
        // Combine all sections
        return $patientDetails . $clinicalFindings . $diagnosisSection . 
               $treatmentSection . $recommendations . $conclusion . $signature;
    }
    
    /**
     * Generate a generic medical report
     * 
     * @return string Generic report content
     */
    protected function generateGenericReport()
    {
        $sections = [
            "### Patient Information\n" .
            "**Name:** [Patient Name]\n" .
            "**Gender:** [Gender]\n" .
            "**Age:** [Age]\n\n",
            
            "### Clinical Findings\n" .
            $this->faker->paragraph(3) . "\n\n",
            
            "### Diagnosis\n" .
            $this->faker->paragraph(1) . "\n\n",
            
            "### Treatment Plan\n" .
            $this->faker->paragraph(2) . "\n\n",
            
            "### Recommendations\n" .
            $this->faker->paragraph(2) . "\n\n",
            
            "### Conclusion\n" .
            $this->faker->paragraph(1) . "\n\n",
            
            "**Physician:** [Doctor Name]\n" .
            "**Date:** " . date('F j, Y') . "\n"
        ];
        
        return implode('', $sections);
    }
}

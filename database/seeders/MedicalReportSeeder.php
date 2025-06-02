<?php

namespace HospitalManager\Database\Seeders;

class MedicalReportSeeder extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Creating medical reports...');
        
        global $wpdb;
        
        // Get patient and doctor IDs
        $patients_table = $wpdb->prefix . 'hm_patients';
        $patient_ids = $wpdb->get_col("SELECT ID FROM {$patients_table} LIMIT 20");
        
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        $doctor_ids = $wpdb->get_col("SELECT ID FROM {$doctors_table} LIMIT 10");
        
        if (empty($patient_ids) || empty($doctor_ids)) {
            $this->log('No patients or doctors found. Cannot create medical reports.');
            return;
        }
        
        $table = $wpdb->prefix . 'hm_medical_reports';
        $count = 0;
        $total = 30;
        
        $report_types = [
            'General Checkup',
            'Follow-up Examination',
            'Specialist Consultation',
            'Diagnostic Report',
            'Treatment Summary',
            'Pre-operative Assessment',
            'Post-operative Report',
            'Emergency Consultation'
        ];
        
        $diagnoses = [
            'Normal health status',
            'Hypertension - controlled',
            'Diabetes Type 2 - managed',
            'Upper respiratory infection',
            'Gastroenteritis - acute',
            'Allergic rhinitis',
            'Lower back pain - chronic',
            'Anxiety disorder - mild',
            'Vitamin D deficiency',
            'Iron deficiency anemia'
        ];
        
        $treatments = [
            'Lifestyle modification and regular monitoring',
            'Medication prescribed as per protocol',
            'Physical therapy recommended',
            'Follow-up in 2 weeks',
            'Dietary changes advised',
            'Exercise routine prescribed',
            'Symptomatic treatment provided',
            'Specialist referral made',
            'Lab tests ordered for monitoring',
            'Patient education provided'
        ];
        
        for ($i = 0; $i < $total; $i++) {
            $patient_id = $patient_ids[array_rand($patient_ids)];
            $doctor_id = $doctor_ids[array_rand($doctor_ids)];
            $report_type = $report_types[array_rand($report_types)];
            $diagnosis = $diagnoses[array_rand($diagnoses)];
            $treatment = $treatments[array_rand($treatments)];
            
            $days_ago = mt_rand(1, 90);
            $report_date = date('Y-m-d', strtotime("-{$days_ago} days"));
            $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
            
            // Generate additional findings and recommendations
            $findings = "Patient presented with symptoms consistent with {$diagnosis}. ";
            $findings .= "Physical examination and assessment completed. ";
            $findings .= "Vital signs within normal limits. ";
            
            $recommendations = "{$treatment}. ";
            $recommendations .= "Patient advised to maintain regular follow-up appointments. ";
            $recommendations .= "Emergency contact information provided. ";
            
            $data = [
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'report_type' => $report_type,
                'report_date' => $report_date,
                'diagnosis' => $diagnosis,
                'findings' => $findings,
                'treatment_plan' => $treatment,
                'recommendations' => $recommendations,
                'status' => mt_rand(0, 100) <= 85 ? 'completed' : 'pending', // 85% completed
                'created_at' => $created_at,
                'updated_at' => $created_at
            ];
            
            $result = $wpdb->insert($table, $data);
            if ($result) {
                $count++;
            }
        }
        
        $this->log("Created {$count} medical reports", 'success');
    }
}

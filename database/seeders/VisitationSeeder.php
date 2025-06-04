<?php

namespace HospitalManager\Database\Seeders;

use Faker\Factory as Faker;

class VisitationSeeder extends Seeder
{
    protected $complaints = [
        'Fever and chills', 'Headache', 'Back pain', 'Fatigue', 'Joint pain',
        'Abdominal pain', 'Difficulty breathing', 'Chest pain', 'Sore throat',
        'Cough', 'Nasal congestion', 'Dizziness', 'Nausea', 'Vomiting',
        'Diarrhea', 'Skin rash', 'Eye irritation', 'Muscle weakness',
        'Sleep problems', 'Anxiety symptoms'
    ];
    
    protected $diagnoses = [
        'Common cold', 'Influenza', 'Gastroenteritis', 'Hypertension',
        'Type 2 diabetes', 'Bronchitis', 'Urinary tract infection',
        'Sinusitis', 'Allergic rhinitis', 'Migraine', 'Asthma exacerbation',
        'Gastroesophageal reflux disease', 'Sprains and strains',
        'Dermatitis', 'Anxiety', 'Depression', 'Osteoarthritis',
        'Pneumonia', 'Appendicitis', 'Malaria'
    ];

    protected $treatments = [
        'Prescribed medication: {medication}', 'Rest and hydration',
        'Antibiotics course', 'Physical therapy', 'Dietary modifications',
        'Regular monitoring', 'Surgical intervention', 'Symptomatic management',
        'Lifestyle counseling', 'Wound care', 'Respiratory therapy'
    ];
    
    protected $medications = [
        'Paracetamol 500mg', 'Amoxicillin 500mg', 'Ibuprofen 400mg',
        'Omeprazole 20mg', 'Amlodipine 5mg', 'Hydrochlorothiazide 25mg',
        'Ciprofloxacin 250mg', 'Azithromycin 500mg', 'Loratadine 10mg',
        'Metformin 500mg', 'Atorvastatin 10mg', 'Salbutamol inhaler',
        'Fluoxetine 20mg', 'Diazepam 5mg', 'Lisinopril 10mg'
    ];

    public function run()
    {
        $this->log("Creating visitation records...");
        
        // Initialize Faker with English locale to avoid encoding issues
        $this->faker = Faker::create('en_US');
        
        global $wpdb;
        
        // Get patient and doctor IDs
        $patients_table = $wpdb->prefix . 'hm_patients';
        $patient_ids = $wpdb->get_col("SELECT ID FROM {$patients_table}");
        
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        $doctor_ids = $wpdb->get_col("SELECT ID FROM {$doctors_table}");
        
        if (empty($patient_ids) || empty($doctor_ids)) {
            $this->log("No patients or doctors found. Cannot create visitations.");
            return;
        }
        
        $visitations_table = $wpdb->prefix . 'hm_visitations';
        
        // Get appointments for creating visitations
        $appointments = $this->getAppointmentsForVisitations();
        
        // Create visitations from appointments
        $count = $this->createVisitationsFromAppointments($visitations_table, $appointments, $wpdb);
        
        // Create some walk-in visitations (not tied to appointments)
        $count += $this->createWalkInVisitations($patient_ids, $doctor_ids, $visitations_table, 20, $wpdb);
        
        // Create some follow-up visitations
        $count += $this->createFollowUpVisitations($patient_ids, $doctor_ids, $visitations_table, 15, $wpdb);
        
        $this->log("Created {$count} visitation records", 'success');
    }
    
    /**
     * Get appointments to create visitations for
     */
    private function getAppointmentsForVisitations()
    {
        // Get from transient if available
        $appointments = get_transient('hm_appointments_for_visitations');
        if (!empty($appointments)) {
            return $appointments;
        }
        
        // Otherwise get completed appointments from database
        global $wpdb;
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        
        $completed_appointments = $wpdb->get_results(
            "SELECT ID, patient_id, doctor_id, appointment_date, appointment_time, reason 
             FROM {$appointments_table} 
             WHERE status = 'completed' 
             ORDER BY RAND() 
             LIMIT 30",
            ARRAY_A
        );
        
        if (empty($completed_appointments)) {
            return [];
        }
        
        // Format for use in visitations
        $formatted = [];
        foreach ($completed_appointments as $appointment) {
            $formatted[] = [
                'appointment_id' => $appointment['ID'],
                'patient_id' => $appointment['patient_id'],
                'doctor_id' => $appointment['doctor_id'],
                'date' => $appointment['appointment_date'],
                'time' => $appointment['appointment_time'],
                'status' => 'completed',
                'reason' => $appointment['reason']
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Create visitations from appointments
     */
    private function createVisitationsFromAppointments($visitations_table, $appointments, $wpdb)
    {
        $count = 0;
        
        if (empty($appointments)) {
            return $count;
        }
        
        foreach ($appointments as $appointment) {
            // First check if visitation already exists for this appointment
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$visitations_table} WHERE appointment_id = %d",
                $appointment['appointment_id']
            ));
            
            if ($exists) {
                continue;
            }
            
            // Generate visitation data
            $complaint = $this->sanitizeText($this->getComplaintFromReason($appointment['reason']));
            $diagnosis = $this->sanitizeText($this->diagnoses[array_rand($this->diagnoses)]);
            $treatment = $this->sanitizeText($this->generateTreatment());
            $medication = $this->sanitizeText($this->medications[array_rand($this->medications)]);
            
            // Replace placeholders in treatment string
            $treatment = str_replace('{medication}', $medication, $treatment);
            
            // Generate medical history
            $medical_history = $this->generateMedicalHistory($this->faker);
            
            $data = [
                'appointment_id' => $appointment['appointment_id'],
                'patient_id' => $appointment['patient_id'],
                'doctor_id' => $appointment['doctor_id'],
                'date' => $appointment['date'],
                'time' => $appointment['time'],
                'complaint' => $complaint,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'medical_history' => $medical_history,
                'created_at' => date('Y-m-d H:i:s', strtotime("{$appointment['date']} {$appointment['time']}")),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $wpdb->insert($visitations_table, $data);
            if ($result === false) {
                $this->log("Error inserting visitation for appointment {$appointment['appointment_id']}: " . $wpdb->last_error, 'error');
            } else {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Create walk-in visitations (not tied to appointments)
     */
    private function createWalkInVisitations($patient_ids, $doctor_ids, $visitations_table, $target, $wpdb)
    {
        $count = 0;
        
        for ($i = 0; $i < $target; $i++) {
            // Random patient and doctor
            $patient_id = $patient_ids[array_rand($patient_ids)];
            $doctor_id = $doctor_ids[array_rand($doctor_ids)];
            
            // Random date within last 3 months
            $days_ago = mt_rand(1, 90); // 1-90 days ago
            $date = date('Y-m-d', strtotime("-{$days_ago} days"));
            
            // Random time during office hours
            $hour = mt_rand(8, 16); // 8 AM to 4 PM
            $minute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
            $time = sprintf('%02d:%02d:00', $hour, $minute);
            
            // Generate medical data
            $complaint = $this->complaints[array_rand($this->complaints)];
            $diagnosis = $this->diagnoses[array_rand($this->diagnoses)];
            $treatment = $this->generateTreatment();
            $medical_history = $this->generateMedicalHistory($this->faker);
            
            $data = [
                'appointment_id' => null, // walk-in, no appointment
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'date' => $date,
                'time' => $time,
                'complaint' => $complaint,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'medical_history' => $medical_history,
                'created_at' => date('Y-m-d H:i:s', strtotime("{$date} {$time}")),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $wpdb->insert($visitations_table, $data);
            if ($result === false) {
                $this->log("Error inserting walk-in visitation: " . $wpdb->last_error, 'error');
            } else {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Create follow-up visitations
     */
    private function createFollowUpVisitations($patient_ids, $doctor_ids, $visitations_table, $target, $wpdb)
    {
        $count = 0;
        
        for ($i = 0; $i < $target; $i++) {
            // Random patient and doctor
            $patient_id = $patient_ids[array_rand($patient_ids)];
            $doctor_id = $doctor_ids[array_rand($doctor_ids)];
            
            // Random date within last 30 days
            $days_ago = mt_rand(1, 30); // 1-30 days ago
            $date = date('Y-m-d', strtotime("-{$days_ago} days"));
            
            // Random time during office hours
            $hour = mt_rand(9, 17); // 9 AM to 5 PM
            $minute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
            $time = sprintf('%02d:%02d:00', $hour, $minute);
            
            // Generate medical data
            $complaint = $this->complaints[array_rand($this->complaints)];
            $diagnosis = $this->diagnoses[array_rand($this->diagnoses)];
            $treatment = $this->generateTreatment();
            $medical_history = $this->generateMedicalHistory($this->faker);
            
            $data = [
                'appointment_id' => null, // no appointment
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'date' => $date,
                'time' => $time,
                'complaint' => $complaint,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'medical_history' => $medical_history,
                'created_at' => date('Y-m-d H:i:s', strtotime("{$date} {$time}")),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $wpdb->insert($visitations_table, $data);
            if ($result === false) {
                $this->log("Error inserting follow-up visitation: " . $wpdb->last_error, 'error');
            } else {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Generate medical history sample data
     */
    private function generateMedicalHistory($faker)
    {
        $medical_history = [];
        
        // Allergies
        $allergies = $faker->randomElement([
            'No known allergies',
            'Allergic to penicillin',
            'Allergic to sulfa drugs', 
            'Allergic to aspirin',
            'Food allergies: peanuts, shellfish',
            'Environmental allergies: pollen, dust mites',
            'Multiple drug allergies: penicillin, codeine'
        ]);
        $medical_history[] = "Allergies: " . $allergies;
        
        // Past surgeries
        $surgeries = $faker->randomElement([
            'No previous surgeries',
            'Appendectomy (' . $faker->year($max = 'now') . ')',
            'Tonsillectomy as child',
            'Cesarean section (' . $faker->year($max = 'now') . ')',
            'Hernia repair (' . $faker->year($max = 'now') . ')',
            'Gallbladder removal (' . $faker->year($max = 'now') . ')',
            'Knee surgery (' . $faker->year($max = 'now') . ')'
        ]);
        $medical_history[] = "Past surgeries: " . $surgeries;
        
        // Chronic conditions
        if ($faker->boolean(30)) { // 30% chance of having chronic conditions
            $conditions = $faker->randomElements([
                'Hypertension (controlled with medication)',
                'Type 2 diabetes (diet controlled)',
                'Asthma (mild, occasional inhaler use)',
                'Arthritis (osteoarthritis in knees)',
                'Migraine headaches (monthly episodes)',
                'Anxiety disorder (managed with therapy)',
                'Depression (stable on medication)',
                'High cholesterol (controlled with statins)'
            ], $faker->numberBetween(1, 2));
            $medical_history[] = "Chronic conditions: " . implode(', ', $conditions);
        } else {
            $medical_history[] = "Chronic conditions: None";
        }
        
        // Family history
        $family_history = $faker->randomElement([
            'No significant family history',
            'Family history of diabetes (maternal side)',
            'Family history of heart disease (paternal grandfather)',
            'Family history of hypertension (both parents)',
            'Family history of cancer (maternal aunt - breast cancer)',
            'Family history of stroke (paternal grandmother)',
            'Family history of diabetes and hypertension'
        ]);
        $medical_history[] = "Family history: " . $family_history;
        
        // Social history
        $smoking = $faker->randomElement([
            'Non-smoker',
            'Former smoker (quit ' . $faker->numberBetween(1, 20) . ' years ago)',
            'Current smoker (' . $faker->numberBetween(5, 30) . ' cigarettes/day)',
            'Social smoker (occasional)'
        ]);
        $medical_history[] = "Smoking: " . $smoking;
        
        $alcohol = $faker->randomElement([
            'Does not drink alcohol',
            'Occasional social drinking',
            'Moderate alcohol consumption (2-3 drinks/week)',
            'Regular alcohol consumption (daily wine with dinner)',
            'Former drinker (stopped ' . $faker->numberBetween(1, 10) . ' years ago)'
        ]);
        $medical_history[] = "Alcohol: " . $alcohol;
        
        // Current medications
        if ($faker->boolean(40)) { // 40% chance of being on medications
            $medications = $faker->randomElements([
                'Lisinopril 10mg daily for blood pressure',
                'Metformin 500mg twice daily for diabetes',
                'Atorvastatin 20mg daily for cholesterol',
                'Levothyroxine 50mcg daily for thyroid',
                'Omeprazole 20mg daily for acid reflux',
                'Ibuprofen as needed for joint pain',
                'Multivitamin daily',
                'Calcium with Vitamin D daily'
            ], $faker->numberBetween(1, 3));
            $medical_history[] = "Current medications: " . implode(', ', $medications);
        } else {
            $medical_history[] = "Current medications: None";
        }
        
        // Add vital signs from last visit if this is a follow-up
        if ($faker->boolean(20)) { // 20% chance of including previous vitals
            $medical_history[] = "Previous visit vitals: BP " . 
                $faker->numberBetween(110, 140) . "/" . $faker->numberBetween(70, 90) . 
                ", HR " . $faker->numberBetween(60, 100) . 
                ", Temp " . $faker->randomFloat(1, 36.0, 37.5) . "C";
        }
        
        // Clean and sanitize the medical history text
        $medical_text = implode(". ", $medical_history) . ".";
        
        // Remove any problematic characters that might cause encoding issues
        $medical_text = $this->sanitizeText($medical_text);
        
        return $medical_text;
    }
    
    /**
     * Extract complaint from appointment reason
     */
    private function getComplaintFromReason($reason)
    {
        $reason_to_complaint = [
            'General checkup' => 'Routine health assessment',
            'Follow-up consultation' => 'Follow-up on previous condition',
            'Prescription renewal' => 'Medication refill needed',
        ];
        
        if (isset($reason_to_complaint[$reason])) {
            return $reason_to_complaint[$reason];
        }
        
        return $this->complaints[array_rand($this->complaints)];
    }
    
    /**
     * Generate treatment text
     */
    private function generateTreatment()
    {
        $treatment = $this->treatments[array_rand($this->treatments)];
        
        // 50% chance of adding a second treatment recommendation
        if (mt_rand(0, 1) === 1) {
            $treatment .= '; ' . $this->treatments[array_rand($this->treatments)];
        }
        
        return $treatment;
    }
    
    /**
     * Sanitize text to remove problematic characters that cause encoding issues
     */
    private function sanitizeText($text)
    {
        // Remove or replace problematic characters
        $text = str_replace('°', '', $text); // Remove degree symbol
        
        // Convert to UTF-8 and remove any invalid characters
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Remove any remaining non-printable characters except basic punctuation
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        
        // Clean up multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
}

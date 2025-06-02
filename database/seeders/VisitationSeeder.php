<?php

namespace HospitalManager\Database\    public function run()
    {
        $this->log("Creating visitation records...");
        
        global $wpdb;
        
        // Get patient and doctor IDs
        $patients_table = $wpdb->prefix . 'hm_patients';
        $patient_ids = $wpdb->get_col("SELECT ID FROM {$patients_table}");
        
        $doctors_table = $wpdb->prefix . 'hm_doctors';
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
        'Physical therapy referral', 'Dietary modifications',
        'Lab tests ordered', 'Radiological examination',
        'Follow-up in 2 weeks', 'Referred to specialist',
        'Prescribed antibiotics for 7 days', 'Pain management protocol',
        'Lifestyle counseling', 'Wound care instructions',
        'Medication adjustment', 'Surgery recommended',
        'Observation and monitoring'
    ];
    
    protected $medications = [
        'Paracetamol 500mg', 'Ibuprofen 400mg', 'Amoxicillin 500mg',
        'Loratadine 10mg', 'Omeprazole 20mg', 'Metformin 500mg',
        'Amlodipine 5mg', 'Salbutamol inhaler', 'Cetirizine 10mg',
        'Ciprofloxacin 500mg', 'Aspirin 75mg', 'Prednisolone 5mg',
        'Chloroquine 250mg', 'Artemether/Lumefantrine', 'Multivitamin tablets'
    ];

    public function run()
    {
        $this->log("Creating visitation records with appointment relationships...");
        
        // Get patient and doctor IDs
        $patients_table = $this->wpdb->prefix . 'hm_patients';
        $patient_ids = $this->wpdb->get_col("SELECT ID FROM {$patients_table}");
        
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        $doctor_ids = $wpdb->get_col("SELECT ID FROM {$doctors_table}");
        
        if (empty($patient_ids) || empty($doctor_ids)) {
            $this->log("No patients or doctors found. Cannot create visitations.");
            return;
        }
        
        $visitations_table = $wpdb->prefix . 'hm_visitations';
        $count = 0;
        
        // Step 1: Create visitations from completed appointments (70% of completed appointments)
        $count += $this->createVisitationsFromAppointments($visitations_table);
        
        // Step 2: Create walk-in visitations (no prior appointment)
        $count += $this->createWalkInVisitations($patient_ids, $doctor_ids, $visitations_table, 40);
        
        // Step 3: Create follow-up visitations
        $count += $this->createFollowUpVisitations($patient_ids, $doctor_ids, $visitations_table, 20);
        
        // Clean up transient data
        delete_transient('hm_appointments_for_visitations');
        
        $this->log("Created {$count} visitation records with realistic appointment relationships");
    }
    
    /**
     * Create visitations from completed appointments
     */
    private function createVisitationsFromAppointments($visitations_table)
    {
        $appointments_table = $this->wpdb->prefix . 'hm_appointments';
        $count = 0;
        
        // Get completed appointments
        $completed_appointments = $this->wpdb->get_results(
            "SELECT * FROM {$appointments_table} WHERE status = 'completed'",
            ARRAY_A
        );
        
        foreach ($completed_appointments as $appointment) {
            // 80% chance that a completed appointment has a visitation
            if (rand(1, 100) <= 80) {
                $visit_time = $appointment['appointment_time'];
                
                // Sometimes the visit time is slightly different from appointment time
                if (rand(1, 100) <= 30) {
                    $datetime = new \DateTime($appointment['appointment_time']);
                    $datetime->modify('+' . rand(-15, 30) . ' minutes');
                    $visit_time = $datetime->format('H:i:s');
                }
                
                $medical_history = $this->generateMedicalHistory();
                $complaint = $this->getComplaintFromReason($appointment['reason']);
                $diagnosis = $this->faker->randomElement($this->diagnoses);
                $treatment = $this->generateTreatment();
                
                $result = $this->wpdb->insert(
                    $visitations_table,
                    [
                        'patient_id' => $appointment['patient_id'],
                        'doctor_id' => $appointment['doctor_id'],
                        'appointment_id' => $appointment['ID'], // Link to appointment
                        'date' => $appointment['appointment_date'],
                        'time' => $visit_time,
                        'medical_history' => $medical_history,
                        'complaint' => $complaint,
                        'diagnosis' => $diagnosis,
                        'treatment' => $treatment,
                        'created_at' => $appointment['appointment_date'] . ' ' . $visit_time,
                        'updated_at' => $appointment['appointment_date'] . ' ' . $visit_time,
                    ],
                    [
                        '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                    ]
                );
                
                if ($result) {
                    $count++;
                }
            }
        }
        
        $this->log("Created {$count} visitations from completed appointments");
        return $count;
    }
    
    /**
     * Create walk-in visitations (no appointment)
     */
    private function createWalkInVisitations($patient_ids, $doctor_ids, $visitations_table, $target)
    {
        $count = 0;
        
        for ($i = 0; $i < $target; $i++) {
            $patient_id = $this->faker->randomElement($patient_ids);
            $doctor_id = $this->faker->randomElement($doctor_ids);
            
            $visit_date = $this->faker->dateTimeBetween('-6 months', 'now');
            $visit_time = $this->faker->time('H:i:s', '18:00:00');
            
            // Check if this exact combination already exists
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$visitations_table} 
                 WHERE patient_id = %d AND doctor_id = %d AND DATE(date) = %s AND time = %s",
                $patient_id, $doctor_id, $visit_date->format('Y-m-d'), $visit_time
            ));
            
            if ($exists) {
                continue;
            }
            
            $medical_history = $this->generateMedicalHistory();
            $complaint = $this->faker->randomElement($this->complaints);
            $diagnosis = $this->faker->randomElement($this->diagnoses);
            $treatment = $this->generateTreatment();
            
            $result = $this->wpdb->insert(
                $visitations_table,
                [
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'appointment_id' => null, // Walk-in, no appointment
                    'date' => $visit_date->format('Y-m-d'),
                    'time' => $visit_time,
                    'medical_history' => $medical_history,
                    'complaint' => $complaint,
                    'diagnosis' => $diagnosis,
                    'treatment' => $treatment,
                    'created_at' => $visit_date->format('Y-m-d H:i:s'),
                    'updated_at' => $visit_date->format('Y-m-d H:i:s'),
                ],
                [
                    '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            if ($result) {
                $count++;
            }
        }
        
        $this->log("Created {$count} walk-in visitations");
        return $count;
    }
    
    /**
     * Create follow-up visitations
     */
    private function createFollowUpVisitations($patient_ids, $doctor_ids, $visitations_table, $target)
    {
        $count = 0;
        
        // Get some existing visitations to create follow-ups for
        $existing_visitations = $this->wpdb->get_results(
            "SELECT patient_id, doctor_id, date FROM {$visitations_table} 
             WHERE DATE(date) < CURDATE() - INTERVAL 14 DAY 
             ORDER BY RAND() LIMIT {$target}",
            ARRAY_A
        );
        
        foreach ($existing_visitations as $original_visit) {
            $original_date = new \DateTime($original_visit['date']);
            $follow_up_date = clone $original_date;
            $follow_up_date->modify('+' . rand(14, 45) . ' days');
            
            // Don't create future follow-ups
            if ($follow_up_date > new \DateTime()) {
                $follow_up_date = $this->faker->dateTimeBetween($original_date->format('Y-m-d'), 'now');
            }
            
            $visit_time = $this->faker->time('H:i:s', '18:00:00');
            
            $result = $this->wpdb->insert(
                $visitations_table,
                [
                    'patient_id' => $original_visit['patient_id'],
                    'doctor_id' => $original_visit['doctor_id'],
                    'appointment_id' => null,
                    'date' => $follow_up_date->format('Y-m-d'),
                    'time' => $visit_time,
                    'medical_history' => 'Follow-up visit for previous condition',
                    'complaint' => 'Follow-up consultation',
                    'diagnosis' => 'Condition improving, continue treatment',
                    'treatment' => 'Continue current medication, return if symptoms worsen',
                    'created_at' => $follow_up_date->format('Y-m-d H:i:s'),
                    'updated_at' => $follow_up_date->format('Y-m-d H:i:s'),
                ],
                [
                    '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            if ($result) {
                $count++;
            }
        }
        
        $this->log("Created {$count} follow-up visitations");
        return $count;
    }
    
    /**
     * Generate realistic medical history
     */
    private function generateMedicalHistory()
    {
        $histories = [
            "No significant past medical history.",
            "History of hypertension, well controlled on medication.",
            "Previous history of diabetes mellitus type 2.",
            "Past surgical history includes appendectomy in 2015.",
            "Family history of cardiovascular disease.",
            "Known allergies to penicillin and sulfa drugs.",
            "Previous hospitalization for pneumonia last year.",
            "Chronic back pain due to occupational hazards.",
            "History of asthma since childhood.",
            "Previous treatment for malaria 6 months ago."
        ];
        
        return rand(1, 10) <= 7 ? $this->faker->randomElement($histories) : "No significant past medical history.";
    }
    
    /**
     * Get appropriate complaint based on appointment reason
     */
    private function getComplaintFromReason($reason)
    {
        $reason_to_complaint = [
            'General checkup' => 'Routine health assessment',
            'Follow-up consultation' => 'Follow-up on previous condition',
            'Symptoms evaluation' => $this->faker->randomElement($this->complaints),
            'Emergency consultation' => $this->faker->randomElement(['Severe pain', 'High fever', 'Difficulty breathing']),
        ];
        
        return $reason_to_complaint[$reason] ?? $this->faker->randomElement($this->complaints);
    }
    
    /**
     * Generate treatment with medication
     */
    private function generateTreatment()
    {
        $treatment_template = $this->faker->randomElement($this->treatments);
        $medication = $this->faker->randomElement($this->medications);
        return str_replace('{medication}', $medication, $treatment_template);
    }
}

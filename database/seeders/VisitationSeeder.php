<?php

namespace HospitalManager\Database\Seeders;

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
            $complaint = $this->getComplaintFromReason($appointment['reason']);
            $diagnosis = $this->diagnoses[array_rand($this->diagnoses)];
            $treatment = $this->generateTreatment();
            $medication = $this->medications[array_rand($this->medications)];
            
            // Replace placeholders in treatment string
            $treatment = str_replace('{medication}', $medication, $treatment);
            
            // Generate medical history
            $medical_history = $this->generateMedicalHistory();
            
            // Calculate end time (30-60 min after start)
            $duration = mt_rand(30, 60);
            $start_time = strtotime($appointment['time']);
            $end_time_str = date('H:i:s', $start_time + ($duration * 60));
            
            $data = [
                'appointment_id' => $appointment['appointment_id'],
                'patient_id' => $appointment['patient_id'],
                'doctor_id' => $appointment['doctor_id'],
                'date' => $appointment['date'],
                'start_time' => $appointment['time'],
                'end_time' => $end_time_str,
                'complaint' => $complaint,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'medical_history' => json_encode($medical_history),
                'notes' => 'Visitation created from appointment',
                'created_at' => date('Y-m-d H:i:s', strtotime("{$appointment['date']} {$appointment['time']}")),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $wpdb->insert($visitations_table, $data);
            $count++;
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
            $start_time = sprintf('%02d:%02d:00', $hour, $minute);
            
            // Visit duration between 30-60 minutes
            $duration = mt_rand(30, 60);
            $end_time = date('H:i:s', strtotime($start_time) + ($duration * 60));
            
            // Generate medical data
            $complaint = $this->complaints[array_rand($this->complaints)];
            $diagnosis = $this->diagnoses[array_rand($this->diagnoses)];
            $treatment = $this->generateTreatment();
            $medical_history = $this->generateMedicalHistory();
            
            $data = [
                'appointment_id' => null, // walk-in, no appointment
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'date' => $date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'complaint' => $complaint,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'medical_history' => json_encode($medical_history),
                'notes' => 'Walk-in visitation',
                'created_at' => date('Y-m-d H:i:s', strtotime("{$date} {$start_time}")),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $wpdb->insert($visitations_table, $data);
            $count++;
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
            $start_time = sprintf('%02d:%02d:00', $hour, $minute);
            
            // Visit duration between 15-45 minutes (follow-ups typically shorter)
            $duration = mt_rand(15, 45);
            $end_time = date('H:i:s', strtotime($start_time) + ($duration * 60));
            
            // Generate medical data
            $complaint = $this->complaints[array_rand($this->complaints)];
            $diagnosis = $this->diagnoses[array_rand($this->diagnoses)];
            $treatment = $this->generateTreatment();
            $medical_history = $this->generateMedicalHistory();
            
            $data = [
                'appointment_id' => null, // no appointment
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'date' => $date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'complaint' => $complaint,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'medical_history' => json_encode($medical_history),
                'notes' => 'Follow-up visitation',
                'created_at' => date('Y-m-d H:i:s', strtotime("{$date} {$start_time}")),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $wpdb->insert($visitations_table, $data);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Generate medical history sample data
     */
    private function generateMedicalHistory()
    {
        $allergies = ['None', 'Penicillin', 'Aspirin', 'Sulfa drugs', 'Peanuts', 'Shellfish', 'Eggs', 'Dairy products'];
        $past_surgeries = ['None', 'Appendectomy', 'Tonsillectomy', 'Hernia repair', 'Cholecystectomy'];
        $chronic_conditions = ['None', 'Hypertension', 'Diabetes', 'Asthma', 'Arthritis', 'Migraine'];
        $family_history = [
            'None significant',
            'Diabetes in father',
            'Hypertension in mother',
            'Heart disease in family',
            'Cancer in siblings'
        ];
        
        return [
            'allergies' => $allergies[array_rand($allergies)],
            'past_surgeries' => $past_surgeries[array_rand($past_surgeries)],
            'chronic_conditions' => $chronic_conditions[array_rand($chronic_conditions)],
            'family_history' => $family_history[array_rand($family_history)],
            'smoker' => (bool)mt_rand(0, 1),
            'alcohol' => ['None', 'Occasional', 'Moderate', 'Heavy'][array_rand(['None', 'Occasional', 'Moderate', 'Heavy'])],
        ];
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
}

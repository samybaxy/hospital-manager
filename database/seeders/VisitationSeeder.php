<?php

namespace HospitalManager\Database\Seeders;

class VisitationSeeder extends Seeder
{
    protected $complaints = [
        'Fever and chills',
        'Headache',
        'Back pain',
        'Fatigue',
        'Joint pain',
        'Abdominal pain',
        'Difficulty breathing',
        'Chest pain',
        'Sore throat',
        'Cough',
        'Nasal congestion',
        'Dizziness',
        'Nausea',
        'Vomiting',
        'Diarrhea'
    ];
    
    protected $diagnoses = [
        'Common cold',
        'Influenza',
        'Gastroenteritis',
        'Hypertension',
        'Type 2 diabetes',
        'Bronchitis',
        'Urinary tract infection',
        'Sinusitis',
        'Allergic rhinitis',
        'Migraine',
        'Asthma exacerbation',
        'Gastroesophageal reflux disease',
        'Sprains and strains',
        'Dermatitis',
        'Anxiety'
    ];
    
    protected $treatments = [
        'Prescribed medication: {medication}',
        'Rest and hydration',
        'Physical therapy referral',
        'Dietary modifications',
        'Lab tests ordered',
        'Radiological examination',
        'Follow-up in 2 weeks',
        'Referred to specialist',
        'Prescribed antibiotics for 7 days',
        'Pain management protocol'
    ];
    
    protected $medications = [
        'Paracetamol 500mg',
        'Ibuprofen 400mg',
        'Amoxicillin 500mg',
        'Loratadine 10mg',
        'Omeprazole 20mg',
        'Metformin 500mg',
        'Amlodipine 5mg',
        'Salbutamol inhaler',
        'Cetirizine 10mg',
        'Ciprofloxacin 500mg'
    ];
    
    public function run()
    {
        // Get completed appointments (to create visitations for them)
        $appointments_table = $this->wpdb->prefix . 'hm_appointments';
        $completed_appointments = $this->wpdb->get_results(
            "SELECT id, patient_id, doctor_id, appointment_date, appointment_time 
             FROM {$appointments_table} 
             WHERE status = 'completed' 
             ORDER BY appointment_date DESC"
        );
        
        if (empty($completed_appointments)) {
            $this->log("No completed appointments found. Make sure AppointmentSeeder was run before this seeder.");
            
            // Get patient and doctor IDs directly
            $patients_table = $this->wpdb->prefix . 'hm_patients';
            $patient_ids = $this->wpdb->get_col("SELECT id FROM {$patients_table}");
            
            $doctors_table = $this->wpdb->prefix . 'hm_doctors';
            $doctor_ids = $this->wpdb->get_col("SELECT id FROM {$doctors_table}");
            
            if (empty($patient_ids) || empty($doctor_ids)) {
                $this->log("No patients or doctors found. Cannot create visitations.");
                return;
            }
            
            // Create some fake appointments and visitations
            $appointments = [];
            $count = min(count($patient_ids), 20);
            
            for ($i = 0; $i < $count; $i++) {
                $patient_id = $this->faker->randomElement($patient_ids);
                $doctor_id = $this->faker->randomElement($doctor_ids);
                $appointment_date = $this->faker->dateTimeBetween('-3 months', '-1 day');
                
                $appointments[] = (object)[
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'appointment_date' => $appointment_date->format('Y-m-d'),
                    'appointment_time' => $appointment_date->format('H:i:s')
                ];
            }
            
            $completed_appointments = $appointments;
        }
        
        $visitations_table = $this->wpdb->prefix . 'hm_visitations';
        $count = 0;
        
        $this->log("Creating visitations");
        
        foreach ($completed_appointments as $appointment) {
            // Check if visitation already exists for this appointment
            if (isset($appointment->id)) {
                $exists = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$visitations_table} 
                     WHERE patient_id = %d AND doctor_id = %d AND DATE(date) = %s",
                    $appointment->patient_id, $appointment->doctor_id, $appointment->appointment_date
                ));
                
                if ($exists) {
                    continue;
                }
            }
            
            // Generate medical history
            $medical_history = $this->faker->optional(0.8, "No significant past medical history.") 
                ->paragraphs(rand(1, 2), true);
            
            // Select random complaint, diagnosis and treatment
            $complaint = $this->faker->randomElement($this->complaints);
            $diagnosis = $this->faker->randomElement($this->diagnoses);
            
            // Format treatment with medication
            $treatment_template = $this->faker->randomElement($this->treatments);
            $medication = $this->faker->randomElement($this->medications);
            $treatment = str_replace('{medication}', $medication, $treatment_template);
            
            // Insert visitation record
            $this->wpdb->insert(
                $visitations_table,
                [
                    'patient_id' => $appointment->patient_id,
                    'doctor_id' => $appointment->doctor_id,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'medical_history' => $medical_history,
                    'complaint' => $complaint,
                    'diagnosis' => $diagnosis,
                    'treatment' => $treatment,
                    'created_at' => $appointment->appointment_date . ' ' . $appointment->appointment_time,
                    'updated_at' => $appointment->appointment_date . ' ' . $appointment->appointment_time,
                ],
                [
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            $count++;
        }
        
        $this->log("Created {$count} visitation records");
    }
}

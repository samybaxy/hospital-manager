<?php

namespace HospitalManager\Database\Seeders;

class AppointmentSeeder extends Seeder
{
    protected $appointment_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    protected $appointment_reasons = [
        'Regular check-up',
        'Acute illness',
        'Follow-up appointment',
        'Vaccination',
        'Chronic condition management',
        'Prescription renewal',
        'Medical certificate',
        'Pre-operative assessment',
        'Post-operative check-up',
        'Test results discussion'
    ];
    
    public function run()
    {
        // Get patient IDs
        $patients_table = $this->wpdb->prefix . 'hm_patients';
        $patient_ids = $this->wpdb->get_col("SELECT id FROM {$patients_table}");
        
        if (empty($patient_ids)) {
            $this->log("No patients found. Make sure PatientSeeder was run before this seeder.");
            return;
        }
        
        // Get doctor IDs
        $doctors_table = $this->wpdb->prefix . 'hm_doctors';
        $doctor_ids = $this->wpdb->get_col("SELECT id FROM {$doctors_table}");
        
        if (empty($doctor_ids)) {
            $this->log("No doctors found. Make sure DoctorSeeder was run before this seeder.");
            return;
        }
        
        $appointments_table = $this->wpdb->prefix . 'hm_appointments';
        $count = 0;
        $target = min(count($patient_ids) * 2, 100); // Create up to 2 appointments per patient, max 100
        
        $this->log("Creating appointments");
        
        // Create past appointments (mostly completed)
        for ($i = 0; $i < $target / 2; $i++) {
            $patient_id = $this->faker->randomElement($patient_ids);
            $doctor_id = $this->faker->randomElement($doctor_ids);
            $status = $this->faker->randomElement(['completed', 'cancelled']);
            $past_date = $this->faker->dateTimeBetween('-6 months', '-1 day');
            
            $this->wpdb->insert(
                $appointments_table,
                [
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'appointment_date' => $past_date->format('Y-m-d'),
                    'appointment_time' => $past_date->format('H:i:s'),
                    'status' => $status,
                    'reason' => $this->faker->randomElement($this->appointment_reasons),
                    'notes' => $this->faker->optional(0.7)->text(100),
                    'created_at' => $this->faker->dateTimeBetween('-7 months', '-6 months')->format('Y-m-d H:i:s'),
                    'updated_at' => $past_date->format('Y-m-d H:i:s'),
                ],
                [
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            $count++;
        }
        
        // Create future appointments (pending or confirmed)
        for ($i = 0; $i < $target / 2; $i++) {
            $patient_id = $this->faker->randomElement($patient_ids);
            $doctor_id = $this->faker->randomElement($doctor_ids);
            $status = $this->faker->randomElement(['pending', 'confirmed']);
            $future_date = $this->faker->dateTimeBetween('tomorrow', '+3 months');
            
            $this->wpdb->insert(
                $appointments_table,
                [
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'appointment_date' => $future_date->format('Y-m-d'),
                    'appointment_time' => $future_date->format('H:i:s'),
                    'status' => $status,
                    'reason' => $this->faker->randomElement($this->appointment_reasons),
                    'notes' => $this->faker->optional(0.3)->text(100),
                    'created_at' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s'),
                    'updated_at' => current_time('mysql'),
                ],
                [
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            $count++;
        }
        
        $this->log("Created {$count} appointments");
    }
}

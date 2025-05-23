<?php

namespace HospitalManager\Database\Seeders;

class AppointmentSeeder extends Seeder
{
    public function run()
    {
        $this->log("Creating appointment records...");
        
        // Get patient and doctor IDs
        $patients_table = $this->wpdb->prefix . 'hm_patients';
        $patient_ids = $this->wpdb->get_col("SELECT id FROM {$patients_table}");
        
        $doctors_table = $this->wpdb->prefix . 'hm_doctors';
        $doctor_ids = $this->wpdb->get_col("SELECT id FROM {$doctors_table}");
        
        if (empty($patient_ids) || empty($doctor_ids)) {
            $this->log("No patients or doctors found. Cannot create appointments.");
            return;
        }
        
        $appointments_table = $this->wpdb->prefix . 'hm_appointments';
        $count = 0;
        $target = 150; // Increased from 100 to create more appointments
        
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        $reasons = [
            'General checkup',
            'Follow-up consultation',
            'Routine examination',
            'Symptoms evaluation',
            'Prescription renewal',
            'Lab results review',
            'Vaccination',
            'Physical therapy',
            'Specialist consultation',
            'Emergency consultation',
            'Preventive care',
            'Chronic disease management',
            'Pre-operative assessment',
            'Post-operative follow-up',
            'Health screening'
        ];
        
        for ($i = 0; $i < $target; $i++) {
            $patient_id = $this->faker->randomElement($patient_ids);
            $doctor_id = $this->faker->randomElement($doctor_ids);
            
            // Create appointments across different time ranges
            if ($i < 60) {
                // Past appointments (60 appointments) - higher chance of completion
                $appointment_date = $this->faker->dateTimeBetween('-6 months', '-1 day');
                $status_weights = [
                    'completed' => 60,  // 60% completed (these will likely have visitations)
                    'cancelled' => 20,  // 20% cancelled
                    'confirmed' => 15,  // 15% confirmed but no visitation yet
                    'pending' => 5      // 5% still pending
                ];
            } elseif ($i < 90) {
                // Recent appointments (30 appointments)
                $appointment_date = $this->faker->dateTimeBetween('-7 days', 'now');
                $status_weights = [
                    'completed' => 40,
                    'confirmed' => 35,
                    'pending' => 15,
                    'cancelled' => 10
                ];
            } else {
                // Future appointments (60 appointments)
                $appointment_date = $this->faker->dateTimeBetween('tomorrow', '+3 months');
                $status_weights = [
                    'pending' => 50,
                    'confirmed' => 45,
                    'cancelled' => 5,
                    'completed' => 0
                ];
            }
            
            // Select status based on weights
            $status = $this->weightedRandomSelection($status_weights);
            
            $appointment_time = $this->faker->time('H:i:s', '17:00:00');
            $reason = $this->faker->randomElement($reasons);
            
            // Check for duplicates
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$appointments_table} 
                 WHERE patient_id = %d AND doctor_id = %d AND appointment_date = %s AND appointment_time = %s",
                $patient_id, $doctor_id, $appointment_date->format('Y-m-d'), $appointment_time
            ));
            
            if ($exists) {
                continue;
            }
            
            // Insert appointment
            $result = $this->wpdb->insert(
                $appointments_table,
                [
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'appointment_date' => $appointment_date->format('Y-m-d'),
                    'appointment_time' => $appointment_time,
                    'reason' => $reason,
                    'status' => $status,
                    'notes' => $status === 'cancelled' ? 'Patient cancelled due to emergency' : null,
                    'created_at' => (clone $appointment_date)->modify('-' . rand(1, 30) . ' days')->format('Y-m-d H:i:s'),
                    'updated_at' => $appointment_date->format('Y-m-d H:i:s'),
                ],
                [
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            if ($result) {
                $count++;
                // Store appointment info for visitation seeder
                $this->storeAppointmentForVisitation([
                    'appointment_id' => $this->wpdb->insert_id,
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'date' => $appointment_date->format('Y-m-d'),
                    'time' => $appointment_time,
                    'status' => $status,
                    'reason' => $reason
                ]);
            }
        }
        
        $this->log("Created {$count} appointment records");
    }
    
    /**
     * Weighted random selection
     */
    private function weightedRandomSelection($weights)
    {
        $total = array_sum($weights);
        $random = rand(1, $total);
        $current = 0;
        
        foreach ($weights as $item => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $item;
            }
        }
        
        return array_keys($weights)[0]; // fallback
    }
    
    /**
     * Store appointment data for use by VisitationSeeder
     */
    private function storeAppointmentForVisitation($appointment_data)
    {
        // Store in WordPress transient for use by VisitationSeeder
        $existing = get_transient('hm_appointments_for_visitations') ?: [];
        $existing[] = $appointment_data;
        set_transient('hm_appointments_for_visitations', $existing, HOUR_IN_SECONDS);
    }
}

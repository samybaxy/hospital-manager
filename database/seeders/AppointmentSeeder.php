<?php

namespace HospitalManager\Database\Seeders;

class AppointmentSeeder extends Seeder
{
    public function run()
    {
        $this->log("Creating appointment records...");
        
        global $wpdb;
        
        // Get patient and doctor IDs
        $patients_table = $wpdb->prefix . 'hm_patients';
        $patient_ids = $wpdb->get_col("SELECT ID FROM {$patients_table}");
        
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        $doctor_ids = $wpdb->get_col("SELECT ID FROM {$doctors_table}");
        
        if (empty($patient_ids) || empty($doctor_ids)) {
            $this->log("No patients or doctors found. Cannot create appointments.");
            return;
        }
        
        $appointments_table = $wpdb->prefix . 'hm_appointments';
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
            $patient_id = $patient_ids[array_rand($patient_ids)];
            $doctor_id = $doctor_ids[array_rand($doctor_ids)];
            
            // Create appointments across different time ranges
            if ($i < 80) {
                // Past appointments (80 appointments) - higher chance of completion
                $days_ago = mt_rand(1, 90); // 1-90 days ago (max 3 months back)
                $appointment_date = date('Y-m-d', strtotime("-{$days_ago} days"));
                $status_weights = [
                    'completed' => 70,  // 70% completed (these will likely have visitations)
                    'cancelled' => 15,  // 15% cancelled
                    'confirmed' => 10,  // 10% confirmed but no visitation yet
                    'pending' => 5      // 5% still pending
                ];
            } elseif ($i < 120) {
                // Recent appointments (40 appointments) - last 2 weeks
                $days_ago = mt_rand(0, 14); // 0-14 days ago
                $appointment_date = date('Y-m-d', strtotime("-{$days_ago} days"));
                $status_weights = [
                    'completed' => 50,
                    'confirmed' => 30,
                    'pending' => 15,
                    'cancelled' => 5
                ];
            } else {
                // Today's appointments (30 appointments) - today only
                $appointment_date = date('Y-m-d'); // Today's date
                $status_weights = [
                    'pending' => 40,
                    'confirmed' => 50,
                    'cancelled' => 5,
                    'completed' => 5   // Some might be completed if early in the day
                ];
            }
            
            // Select status based on weights
            $status = $this->weightedRandomSelection($status_weights);
            
            $hour = mt_rand(8, 16); // 8 AM to 4 PM
            $minute = mt_rand(0, 3) * 15; // 0, 15, 30, 45 minutes
            $appointment_time = sprintf('%02d:%02d:00', $hour, $minute);
            $reason = $reasons[array_rand($reasons)];
            
            // Check for duplicates
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$appointments_table} 
                 WHERE patient_id = %d AND doctor_id = %d AND appointment_date = %s AND appointment_time = %s",
                $patient_id, $doctor_id, $appointment_date, $appointment_time
            ));
            
            if ($exists) {
                continue;
            }
            
            // Insert appointment
            $result = $wpdb->insert(
                $appointments_table,
                [
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'appointment_date' => $appointment_date,
                    'appointment_time' => $appointment_time,
                    'reason' => $reason,
                    'status' => $status,
                    'notes' => $status === 'cancelled' ? 'Patient cancelled due to emergency' : null,
                    'created_at' => date('Y-m-d H:i:s', strtotime($appointment_date . ' -' . mt_rand(1, 30) . ' days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime($appointment_date)),
                ],
                [
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            if ($result) {
                $count++;
                // Store appointment info for visitation seeder
                $this->storeAppointmentForVisitation([
                    'appointment_id' => $wpdb->insert_id,
                    'patient_id' => $patient_id,
                    'doctor_id' => $doctor_id,
                    'date' => $appointment_date,
                    'time' => $appointment_time,
                    'status' => $status,
                    'reason' => $reason
                ]);
            }
        }
        
        $this->log("Created {$count} appointment records", 'success');
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

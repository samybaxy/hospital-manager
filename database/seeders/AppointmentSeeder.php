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
        
        // Clear the legacy hand-off cache: it outlived truncates and fed stale
        // appointment IDs to VisitationSeeder (which now reads the table directly).
        delete_transient('hm_appointments_for_visitations');

        // Coverage pass: every patient gets a completed past appointment (so they
        // have a visit, medication, labs and a report downstream) and most get an
        // upcoming one, so no patient's screens are empty.
        foreach ($patient_ids as $patient_id) {
            $days_ago = mt_rand(3, 120);
            if ($this->insertAppointment(
                $appointments_table,
                $patient_id,
                $doctor_ids[array_rand($doctor_ids)],
                date('Y-m-d', strtotime("-{$days_ago} days")),
                $this->randomSlot(),
                'completed',
                $reasons[array_rand($reasons)]
            )) {
                $count++;
            }

            if (mt_rand(1, 100) <= 75) {
                $days_ahead = mt_rand(1, 30);
                if ($this->insertAppointment(
                    $appointments_table,
                    $patient_id,
                    $doctor_ids[array_rand($doctor_ids)],
                    date('Y-m-d', strtotime("+{$days_ahead} days")),
                    $this->randomSlot(),
                    mt_rand(0, 1) ? 'pending' : 'confirmed',
                    $reasons[array_rand($reasons)]
                )) {
                    $count++;
                }
            }
        }

        for ($i = 0; $i < $target; $i++) {
            $patient_id = $patient_ids[array_rand($patient_ids)];
            $doctor_id = $doctor_ids[array_rand($doctor_ids)];
            
            // Create appointments across different time ranges
            if ($i < 60) {
                // Past appointments (60 appointments) - higher chance of completion
                $days_ago = mt_rand(1, 90); // 1-90 days ago (max 3 months back)
                $appointment_date = date('Y-m-d', strtotime("-{$days_ago} days"));
                $status_weights = [
                    'completed' => 70,  // 70% completed (these will likely have visitations)
                    'cancelled' => 15,  // 15% cancelled
                    'confirmed' => 10,  // 10% confirmed but no visitation yet
                    'pending' => 5      // 5% still pending
                ];
            } elseif ($i < 90) {
                // Recent appointments (30 appointments) - last 2 weeks
                $days_ago = mt_rand(0, 14); // 0-14 days ago
                $appointment_date = date('Y-m-d', strtotime("-{$days_ago} days"));
                $status_weights = [
                    'completed' => 50,
                    'confirmed' => 30,
                    'pending' => 15,
                    'cancelled' => 5
                ];
            } elseif ($i < 120) {
                // Today's appointments (30 appointments) - today only
                $appointment_date = date('Y-m-d'); // Today's date
                $status_weights = [
                    'pending' => 40,
                    'confirmed' => 50,
                    'cancelled' => 5,
                    'completed' => 5   // Some might be completed if early in the day
                ];
            } else {
                // Future appointments (30 appointments) - next 30 days
                $days_ahead = mt_rand(1, 30); // 1-30 days in the future
                $appointment_date = date('Y-m-d', strtotime("+{$days_ahead} days"));
                $status_weights = [
                    'pending' => 50,    // Most future appointments are pending
                    'confirmed' => 45,  // Some are already confirmed
                    'cancelled' => 5,   // Few are cancelled in advance
                    'completed' => 0    // Future appointments can't be completed
                ];
            }
            
            // Select status based on weights
            $status = $this->weightedRandomSelection($status_weights);
            
            $appointment_time = $this->randomSlot();
            $reason = $reasons[array_rand($reasons)];
            
            if ($this->insertAppointment($appointments_table, $patient_id, $doctor_id, $appointment_date, $appointment_time, $status, $reason)) {
                $count++;
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
     * Random 15-minute slot between 08:00 and 16:45
     */
    private function randomSlot()
    {
        return sprintf('%02d:%02d:00', mt_rand(8, 16), mt_rand(0, 3) * 15);
    }

    /**
     * Insert one appointment unless the same patient/doctor/slot already exists.
     *
     * @return bool
     */
    private function insertAppointment($table, $patient_id, $doctor_id, $date, $time, $status, $reason)
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE patient_id = %d AND doctor_id = %d AND appointment_date = %s AND appointment_time = %s",
            $patient_id, $doctor_id, $date, $time
        ));
        if ($exists) {
            return false;
        }

        return (bool) $wpdb->insert(
            $table,
            [
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'reason' => $reason,
                'status' => $status,
                'notes' => $status === 'cancelled' ? 'Patient cancelled due to emergency' : null,
                // Booked 1-30 days before the slot, but never in the future
                'created_at' => date('Y-m-d H:i:s', min(
                    strtotime($date . ' -' . mt_rand(1, 30) . ' days'),
                    current_time('timestamp') - mt_rand(HOUR_IN_SECONDS, 10 * DAY_IN_SECONDS)
                )),
                'updated_at' => date('Y-m-d H:i:s', strtotime($date)),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
}

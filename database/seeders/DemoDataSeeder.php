<?php

namespace HospitalManager\Database\Seeders;

/**
 * Gives the demo accounts (demo_doctor / demo_patient / demo_lab_tech / ...) realistic,
 * connected data so every role has something to look at right after login:
 *   - friendly display names on the demo users + their hm_doctors / hm_patients rows
 *   - appointments between demo_doctor and demo_patient (past, today, upcoming)
 *   - visitations + medical reports + lab investigations for the completed visits
 *   - a chat thread between them, and notifications for every demo user
 *   - today's appointments for demo_doctor with other patients
 *
 * Must run after UserSeeder, PatientSeeder, DoctorSeeder and the lab seeders.
 * Safe to re-run: it skips anything it has already created (tagged in `notes`).
 */
class DemoDataSeeder extends Seeder
{
    const TAG = '[demo-seed]';

    protected $profiles = [
        'demo_administrator' => ['Adaeze', 'Nwosu'],
        'demo_doctor' => ['Chukwuemeka', 'Okafor'],
        'demo_patient' => ['Ngozi', 'Eze'],
        'demo_lab_tech' => ['Tunde', 'Bakare'],
        'demo_hospital_nurse' => ['Blessing', 'Udoh'],
        'demo_hospital_staff' => ['Ibrahim', 'Musa'],
        'demo_pharmacy_staff' => ['Folake', 'Adeyemi'],
        'demo_inventory_manager' => ['Kingsley', 'Obi'],
        'demo_developer' => ['Samuel', 'Dev'],
    ];

    public function run()
    {
        global $wpdb;
        $this->log('Linking demo accounts with sample data...');

        $this->applyProfiles();

        $doctor_user = get_user_by('login', 'demo_doctor');
        $patient_user = get_user_by('login', 'demo_patient');
        $lab_user = get_user_by('login', 'demo_lab_tech');

        if (!$doctor_user || !$patient_user) {
            $this->log('demo_doctor / demo_patient not found. Run UserSeeder first.', 'error');
            return false;
        }

        $doctor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_doctors WHERE user_id = %d", $doctor_user->ID
        ));
        $patient = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_patients WHERE user_id = %d", $patient_user->ID
        ));

        if (!$doctor || !$patient) {
            $this->log('Demo doctor/patient records missing. Run DoctorSeeder and PatientSeeder first.', 'error');
            return false;
        }

        $already = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hm_appointments WHERE notes LIKE %s",
            '%' . $wpdb->esc_like(self::TAG) . '%'
        ));
        if ($already) {
            $this->log('Demo data already present, skipping appointments/visits.', 'warning');
        } else {
            $this->seedDemoPairHistory($doctor, $patient, $doctor_user->ID, $lab_user ? $lab_user->ID : null);
            $this->seedDoctorDayWithOtherPatients($doctor, $patient);
        }

        // Chat between demo doctor and demo patient (ChatSeeder skips existing pairs)
        require_once __DIR__ . '/ChatSeeder.php';
        (new ChatSeeder())->createThread($doctor_user->ID, $patient_user->ID, 8, 3);

        $this->seedDemoNotifications();

        $this->log('Demo data linked successfully', 'success');
        return true;
    }

    protected function applyProfiles()
    {
        global $wpdb;
        foreach ($this->profiles as $login => [$first, $last]) {
            $user = get_user_by('login', $login);
            if (!$user) {
                continue;
            }
            $display = $login === 'demo_doctor' ? "Dr. {$first} {$last}" : "{$first} {$last}";
            update_user_meta($user->ID, 'first_name', $first);
            update_user_meta($user->ID, 'last_name', $last);
            wp_update_user(['ID' => $user->ID, 'display_name' => $display, 'nickname' => $display]);

            $wpdb->update("{$wpdb->prefix}hm_doctors", ['first_name' => $first, 'last_name' => $last], ['user_id' => $user->ID]);
            $wpdb->update("{$wpdb->prefix}hm_patients", ['first_name' => $first, 'last_name' => $last], ['user_id' => $user->ID]);
        }

        // Fixed, predictable details for the demo doctor/patient
        $doctor_user = get_user_by('login', 'demo_doctor');
        if ($doctor_user) {
            $wpdb->update("{$wpdb->prefix}hm_doctors", [
                'specialty' => 'Internal Medicine',
                'office' => 'Room 204',
                'phone' => '+234-803-555-0101',
            ], ['user_id' => $doctor_user->ID]);
            update_user_meta($doctor_user->ID, 'specialty', 'Internal Medicine');
        }
        $patient_user = get_user_by('login', 'demo_patient');
        if ($patient_user) {
            $wpdb->update("{$wpdb->prefix}hm_patients", [
                'age' => 34,
                'gender' => 'Female',
                'phone' => '+234-805-555-0202',
                'city' => 'Asaba',
                'state' => 'Delta',
                'address' => '12 Nnebisi Road',
                'bio_data' => wp_json_encode([
                    'blood_group' => 'O+',
                    'allergies' => 'Penicillin',
                    'chronic_conditions' => 'Hypertension',
                    'emergency_contact' => ['name' => 'Obinna Eze', 'phone' => '+234-805-555-0203', 'relationship' => 'Spouse'],
                    'height' => 165,
                    'weight' => 68,
                ]),
            ], ['user_id' => $patient_user->ID]);
        }
    }

    /**
     * Past completed visits (with reports + labs), today's appointment, and upcoming ones.
     */
    protected function seedDemoPairHistory($doctor, $patient, $doctor_user_id, $lab_user_id)
    {
        $plan = [
            // days offset, time, status, reason, complaint, diagnosis, treatment, labs
            [-60, '10:00:00', 'completed', 'Initial consultation', 'Recurring headaches and dizziness', 'Stage 1 hypertension', 'Amlodipine 5mg daily; low-salt diet', ['Lipid Panel', 'Complete Blood Count']],
            [-30, '11:30:00', 'completed', 'Follow-up consultation', 'Blood pressure review', 'Hypertension - improving', 'Continue Amlodipine 5mg; daily BP log', ['Basic Metabolic Panel']],
            [-7, '09:15:00', 'completed', 'Lab results review', 'Fatigue and mild fever', 'Malaria (uncomplicated)', 'Artemether-Lumefantrine 3-day course', ['Malaria Parasite Test']],
            [-14, '14:00:00', 'cancelled', 'Routine check-up', null, null, null, []],
            [0, date('H', strtotime('+1 hour')) . ':00:00', 'confirmed', 'Blood pressure review', null, null, null, []],
            [3, '10:30:00', 'pending', 'Follow-up on malaria treatment', null, null, null, []],
            [14, '09:00:00', 'confirmed', 'Quarterly hypertension review', null, null, null, []],
        ];

        $appointments = 0;
        $visits = 0;
        $labs = 0;
        $reports = 0;

        foreach ($plan as $i => [$offset, $time, $status, $reason, $complaint, $diagnosis, $treatment, $tests]) {
            $date = date('Y-m-d', strtotime("{$offset} days"));
            $appointment_id = $this->insertAppointment($patient->ID, $doctor->ID, $date, $time, $status, $reason);
            if (!$appointment_id) {
                continue;
            }
            $appointments++;

            if ($status !== 'completed') {
                continue;
            }

            $visitation_id = $this->insertVisitation($patient->ID, $doctor->ID, $appointment_id, $date, $time, $complaint, $diagnosis, $treatment);
            if (!$visitation_id) {
                continue;
            }
            $visits++;

            // Most recent visit's report is still pending so the doctor dashboard shows one
            $is_latest = ($offset === -7);
            $visitation = (object) [
                'ID' => $visitation_id,
                'patient_id' => $patient->ID,
                'doctor_user_id' => $doctor_user_id,
                'date' => $date,
                'time' => $time,
                'complaint' => $complaint,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
            ];
            require_once __DIR__ . '/MedicalReportSeeder.php';
            if (MedicalReportSeeder::createForVisitation($visitation, $is_latest ? 'pending' : 'completed')) {
                $reports++;
            }

            if ($lab_user_id) {
                foreach ($tests as $test) {
                    if ($this->insertLab($visitation_id, $patient->ID, $doctor->ID, $lab_user_id, $test, $date, $is_latest)) {
                        $labs++;
                    }
                }
            }
        }

        $this->log("Demo pair: {$appointments} appointments, {$visits} visitations, {$reports} reports, {$labs} lab investigations", 'success');
    }

    /**
     * A few appointments today for demo_doctor with other patients, so the schedule looks busy.
     */
    protected function seedDoctorDayWithOtherPatients($doctor, $demo_patient)
    {
        global $wpdb;
        $others = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->prefix}hm_patients WHERE ID != %d ORDER BY RAND() LIMIT 4",
            $demo_patient->ID
        ));
        $slots = ['08:30:00', '12:00:00', '15:30:00', '16:30:00'];
        $statuses = ['completed', 'confirmed', 'confirmed', 'pending'];
        $reasons = ['General consultation', 'Diabetes follow-up', 'Chest pain evaluation', 'Prescription renewal'];
        $today = current_time('Y-m-d');
        foreach ($others as $i => $patient_id) {
            $status = $statuses[$i];
            // A slot can only be "completed" once it has passed
            if ($status === 'completed' && strtotime("{$today} {$slots[$i]}") > current_time('timestamp')) {
                $status = 'confirmed';
            }

            $appointment_id = $this->insertAppointment($patient_id, $doctor->ID, $today, $slots[$i], $status, $reasons[$i]);

            // Completed appointments always have a visit (+ report awaiting sign-off)
            if ($appointment_id && $status === 'completed') {
                $visitation_id = $this->insertVisitation($patient_id, $doctor->ID, $appointment_id, $today, $slots[$i],
                    $reasons[$i], 'Under evaluation', 'Paracetamol 500mg as needed; review in 1 week',
                    'No significant past medical history recorded.');
                if ($visitation_id) {
                    require_once __DIR__ . '/MedicalReportSeeder.php';
                    MedicalReportSeeder::createForVisitation((object) [
                        'ID' => $visitation_id,
                        'patient_id' => $patient_id,
                        'doctor_user_id' => $doctor->user_id,
                        'date' => $today,
                        'time' => $slots[$i],
                        'complaint' => $reasons[$i],
                        'diagnosis' => 'Under evaluation',
                        'treatment' => 'Paracetamol 500mg as needed; review in 1 week',
                    ], 'pending');
                }
            }
        }
        $this->log('Added ' . count($others) . " other appointments for demo_doctor today", 'success');
    }

    protected function insertAppointment($patient_id, $doctor_id, $date, $time, $status, $reason)
    {
        global $wpdb;
        $ok = $wpdb->insert("{$wpdb->prefix}hm_appointments", [
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'status' => $status,
            'reason' => $reason,
            'notes' => ($status === 'cancelled' ? 'Patient requested to reschedule. ' : '') . self::TAG,
            'created_at' => date('Y-m-d H:i:s', min(strtotime("{$date} -5 days"), current_time('timestamp') - DAY_IN_SECONDS)),
            'updated_at' => current_time('mysql'),
        ]);
        return $ok ? $wpdb->insert_id : false;
    }

    protected function insertVisitation($patient_id, $doctor_id, $appointment_id, $date, $time, $complaint, $diagnosis, $treatment, $medical_history = null)
    {
        // Default history is the demo patient's (Ngozi Eze)
        $medical_history = $medical_history ?? 'Allergies: Penicillin. Chronic conditions: Hypertension (diagnosed this year). Family history: Father - type 2 diabetes. Non-smoker.';
        global $wpdb;
        $ok = $wpdb->insert("{$wpdb->prefix}hm_visitations", [
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'appointment_id' => $appointment_id,
            'date' => $date,
            'time' => $time,
            'complaint' => $complaint,
            'diagnosis' => $diagnosis,
            'treatment' => $treatment,
            'medical_history' => $medical_history,
            'created_at' => "{$date} {$time}",
            'updated_at' => "{$date} {$time}",
        ]);
        return $ok ? $wpdb->insert_id : false;
    }

    protected function insertLab($visitation_id, $patient_id, $doctor_id, $lab_tech_id, $test, $date, $pending)
    {
        global $wpdb;

        $results = [
            'Lipid Panel' => [['Total Cholesterol', 212, 'mg/dL', '<200', true], ['HDL', 48, 'mg/dL', '>40', false], ['LDL', 138, 'mg/dL', '<130', true]],
            'Complete Blood Count' => [['Hemoglobin', 12.8, 'g/dL', '12.0-15.5', false], ['WBC', 6.4, 'x10^9/L', '4.0-11.0', false]],
            'Basic Metabolic Panel' => [['Sodium', 139, 'mmol/L', '135-145', false], ['Potassium', 4.1, 'mmol/L', '3.5-5.0', false], ['Creatinine', 0.9, 'mg/dL', '0.6-1.1', false]],
            'Malaria Parasite Test' => [['Plasmodium falciparum', 'Positive (+)', '', 'Negative', true]],
        ];

        $params = [];
        $abnormal = false;
        foreach ($results[$test] ?? [] as [$name, $value, $unit, $range, $flag]) {
            $abnormal = $abnormal || $flag;
            $params[] = [
                'name' => $name, 'value' => $value, 'unit' => $unit, 'reference_range' => $range,
                'is_abnormal' => $flag, 'is_critical' => false, 'flag' => $flag ? 'H' : null,
            ];
        }

        return $wpdb->insert("{$wpdb->prefix}hm_lab_investigations", [
            'visitation_id' => $visitation_id,
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'lab_tech_id' => $lab_tech_id,
            'test_type' => $test,
            'sample_type' => $test === 'Malaria Parasite Test' ? 'Whole Blood' : 'Serum',
            'request_notes' => 'Requested during consultation. ' . self::TAG,
            'lab_notes' => $pending ? null : ($abnormal ? 'Abnormal values flagged for doctor review.' : 'All values within normal ranges.'),
            'test_results' => $pending ? null : wp_json_encode(['parameters' => $params]),
            'is_abnormal' => $pending ? 0 : (int) $abnormal,
            'is_critical' => 0,
            'status' => $pending ? 'in_progress' : 'completed',
            'created_at' => "{$date} 12:00:00",
            'updated_at' => current_time('mysql'),
        ]);
    }

    protected function seedDemoNotifications()
    {
        global $wpdb;
        require_once __DIR__ . '/NotificationSeeder.php';

        $by_user = [
            'demo_patient' => ['appointment_reminder', 'appointment_confirmed', 'lab_result_ready', 'medical_report_ready', 'new_message'],
            'demo_doctor' => ['appointment_reminder', 'new_message', 'lab_result_ready', 'appointment_cancelled'],
            'demo_lab_tech' => ['lab_result_ready', 'system_maintenance'],
            'demo_administrator' => ['inventory_low_stock', 'system_maintenance', 'appointment_cancelled'],
            'demo_inventory_manager' => ['inventory_low_stock', 'inventory_low_stock'],
            'demo_pharmacy_staff' => ['prescription_ready', 'inventory_low_stock'],
            'demo_hospital_nurse' => ['appointment_reminder', 'system_maintenance'],
            'demo_hospital_staff' => ['system_maintenance'],
            'demo_developer' => ['system_maintenance'],
        ];

        $count = 0;
        foreach ($by_user as $login => $types) {
            $user = get_user_by('login', $login);
            if (!$user) {
                continue;
            }
            $has = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hm_notifications WHERE user_id = %d", $user->ID
            ));
            if ($has >= count($types)) {
                continue;
            }
            foreach ($types as $i => $type) {
                // newest two unread so the bell badge shows a count
                if (NotificationSeeder::insertNotification($user->ID, $type, $i, $i < 2 ? 0 : 1)) {
                    $count++;
                }
            }
        }
        $this->log("Created {$count} demo notifications", 'success');
    }
}

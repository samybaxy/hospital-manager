<?php

namespace HospitalManager\Database\Seeders;

/**
 * Seeds hm_notifications. Columns: user_id (WP user), type, title, message, reference_id, read.
 */
class NotificationSeeder extends Seeder
{
    public static $templates = [
        'appointment_reminder' => ['Appointment Reminder', 'You have an upcoming appointment scheduled.'],
        'appointment_confirmed' => ['Appointment Confirmed', 'Your appointment has been confirmed.'],
        'appointment_cancelled' => ['Appointment Cancelled', 'Your appointment has been cancelled. Please reschedule.'],
        'lab_result_ready' => ['Lab Results Available', 'Lab results are now available for review.'],
        'prescription_ready' => ['Prescription Ready', 'A prescription is ready for pickup at the pharmacy.'],
        'medical_report_ready' => ['Medical Report Ready', 'A medical report is ready for review.'],
        'new_message' => ['New Message', 'You have received a new chat message.'],
        'inventory_low_stock' => ['Low Stock Alert', 'One or more inventory items are below the reorder level.'],
        'system_maintenance' => ['Scheduled System Maintenance', 'The system will be under maintenance this weekend.'],
    ];

    protected $types_by_role = [
        'doctor' => ['appointment_reminder', 'appointment_confirmed', 'appointment_cancelled', 'lab_result_ready', 'new_message', 'system_maintenance'],
        'lab_tech' => ['lab_result_ready', 'system_maintenance'],
        'inventory_manager' => ['inventory_low_stock', 'system_maintenance'],
        'pharmacy_staff' => ['prescription_ready', 'inventory_low_stock'],
        'administrator' => ['inventory_low_stock', 'system_maintenance', 'appointment_cancelled'],
    ];

    public function run()
    {
        global $wpdb;

        $this->log('Creating notifications...');
        $table = $wpdb->prefix . 'hm_notifications';
        $count = 0;

        foreach ($this->types_by_role as $role => $types) {
            $user_ids = get_users(['role' => $role, 'fields' => 'ID']);
            foreach ($user_ids as $user_id) {
                $n = mt_rand(1, 4);
                for ($i = 0; $i < $n; $i++) {
                    if (self::insertNotification($user_id, $types[array_rand($types)], mt_rand(0, 30))) {
                        $count++;
                    }
                }
            }
        }

        $count += $this->seedPatientNotifications();

        $this->log("Created {$count} notifications", 'success');
        return true;
    }

    /**
     * Patient notifications that point at the patient's real records via reference_id
     * (upcoming appointment, finished lab, completed report, latest visit's treatment).
     *
     * @return int
     */
    protected function seedPatientNotifications()
    {
        global $wpdb;
        $count = 0;
        $patients = $wpdb->get_results("SELECT ID, user_id FROM {$wpdb->prefix}hm_patients WHERE user_id > 0");

        foreach ($patients as $patient) {
            $pick = function ($sql) use ($wpdb, $patient) {
                return $wpdb->get_row($wpdb->prepare($sql, $patient->ID));
            };

            $upcoming = $pick("SELECT ID, status FROM {$wpdb->prefix}hm_appointments
                WHERE patient_id = %d AND appointment_date >= CURDATE() AND status IN ('pending','confirmed')
                ORDER BY appointment_date ASC LIMIT 1");
            if ($upcoming) {
                $type = $upcoming->status === 'confirmed' ? 'appointment_confirmed' : 'appointment_reminder';
                $count += (int) self::insertNotification($patient->user_id, $type, mt_rand(0, 3), 0, $upcoming->ID);
            }

            $cancelled = $pick("SELECT ID FROM {$wpdb->prefix}hm_appointments
                WHERE patient_id = %d AND status = 'cancelled' ORDER BY appointment_date DESC LIMIT 1");
            if ($cancelled) {
                $count += (int) self::insertNotification($patient->user_id, 'appointment_cancelled', mt_rand(3, 30), 1, $cancelled->ID);
            }

            $lab = $pick("SELECT ID FROM {$wpdb->prefix}hm_lab_investigations
                WHERE patient_id = %d AND status IN ('completed','verified') ORDER BY updated_at DESC LIMIT 1");
            if ($lab) {
                $count += (int) self::insertNotification($patient->user_id, 'lab_result_ready', mt_rand(0, 14), null, $lab->ID);
            }

            $report = $pick("SELECT ID FROM {$wpdb->prefix}hm_medical_reports
                WHERE patient_id = %d AND status = 'completed' ORDER BY created_at DESC LIMIT 1");
            if ($report) {
                $count += (int) self::insertNotification($patient->user_id, 'medical_report_ready', mt_rand(0, 20), null, $report->ID);
            }

            // Prescription ready, tied to the visit whose treatment lists the medication
            $visit = $pick("SELECT ID FROM {$wpdb->prefix}hm_visitations
                WHERE patient_id = %d AND treatment IS NOT NULL AND treatment <> '' ORDER BY date DESC LIMIT 1");
            if ($visit) {
                $count += (int) self::insertNotification($patient->user_id, 'prescription_ready', mt_rand(0, 10), null, $visit->ID);
            }
        }

        return $count;
    }

    public static function insertNotification($user_id, $type, $days_ago = 0, $read = null, $reference_id = null)
    {
        global $wpdb;
        [$title, $message] = self::$templates[$type];

        return $wpdb->insert($wpdb->prefix . 'hm_notifications', [
            'user_id' => $user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'reference_id' => $reference_id,
            'read' => $read ?? (mt_rand(0, 100) <= 50 ? 1 : 0),
            'created_at' => date('Y-m-d H:i:s', strtotime("-{$days_ago} days") - mt_rand(0, 12 * HOUR_IN_SECONDS)),
        ]);
    }
}

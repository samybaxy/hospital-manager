<?php

namespace HospitalManager\Database\Seeders;

/**
 * Seeds hm_audit_logs from the data that actually exists, so every entry points
 * at a real record and the acting user is the person who would really have done it.
 *
 * On the patient record itself (entity_type='patient'):
 *   create_patient, view_patient (by each treating doctor), update_patient, update_biodata
 * Per related record (entity_type matches the record; details.patient_id links back,
 * so these also appear in the patient's trail):
 *   book_appointment / cancel_appointment / complete_appointment, create_visitation,
 *   request_lab_investigation / update_lab_results,
 *   request_radiological_exam / update_radiology_results, create_medical_report,
 *   send_notification (by whoever triggered it) / read_notification (by the patient),
 *   start_chat / send_message / read_chat (chat activity; message text is not logged)
 * Plus general session activity: login, logout, profile_update, password_change.
 *
 * Action names follow what the app writes via AuditLogger::log()
 * (e.g. LabResultService uses request_lab_investigation / update_lab_results).
 * IP address and user agent live in `details` — hm_audit_logs has no such columns.
 *
 * Must run after the clinical seeders (appointments, visitations, labs, reports).
 */
class AuditLogSeeder extends Seeder
{
    /** Max related records of each kind logged per patient (keeps volume sane) */
    const PER_PATIENT_LIMIT = 4;

    protected $ip_addresses = ['192.168.1.100', '10.0.0.25', '172.16.0.50', '192.168.0.75', '10.0.0.41'];

    protected $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15',
    ];

    protected $count = 0;

    public function run()
    {
        global $wpdb;
        $this->log('Seeding audit logs...');

        $staff_ids = array_map('intval', get_users([
            'role__in' => ['administrator', 'hospital_staff', 'hospital_nurse'],
            'fields' => 'ID',
        ]));
        if (empty($staff_ids)) {
            $this->log('No administrator/staff users found. Cannot attribute patient registrations.', 'warning');
            return false;
        }

        $patients = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hm_patients ORDER BY ID");
        $doctor_user_by_record = $wpdb->get_results(
            "SELECT ID, user_id FROM {$wpdb->prefix}hm_doctors",
            OBJECT_K
        );

        foreach ($patients as $patient) {
            $this->seedPatientTimeline($patient, $staff_ids, $doctor_user_by_record);
        }
        $patient_entries = $this->count;

        $this->seedSessionActivity();

        $this->log("Created {$this->count} audit logs ({$patient_entries} from patient timelines)", 'success');
        return true;
    }

    /**
     * Audit trail for one patient, derived from their real records.
     */
    protected function seedPatientTimeline($patient, array $staff_ids, array $doctor_user_by_record)
    {
        global $wpdb;
        $limit = self::PER_PATIENT_LIMIT;
        $patient_user_id = (int) $patient->user_id;
        $doctor_user = function ($doctor_record_id) use ($doctor_user_by_record) {
            return isset($doctor_user_by_record[$doctor_record_id])
                ? (int) $doctor_user_by_record[$doctor_record_id]->user_id
                : null;
        };

        // Registration
        $registered_at = $patient->created_at ?: current_time('mysql');
        $this->insert($staff_ids[array_rand($staff_ids)], 'create_patient', 'patient', $patient->ID, $registered_at, [
            'patient_name' => trim("{$patient->first_name} {$patient->last_name}"),
        ]);

        // Appointments
        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_appointments WHERE patient_id = %d ORDER BY appointment_date DESC LIMIT %d",
            $patient->ID,
            $limit
        ));
        foreach ($appointments as $appointment) {
            $context = ['patient_id' => (int) $patient->ID, 'appointment_date' => $appointment->appointment_date];
            $booked_at = $appointment->created_at ?: $registered_at;

            if ($patient_user_id) {
                $this->insert($patient_user_id, 'book_appointment', 'appointment', $appointment->ID, $booked_at, $context);
            }
            if ($appointment->status === 'cancelled' && $patient_user_id) {
                $this->insert($patient_user_id, 'cancel_appointment', 'appointment', $appointment->ID,
                    $this->between($booked_at, "{$appointment->appointment_date} 08:00:00"), $context,
                    ['status' => ['old' => 'confirmed', 'new' => 'cancelled']]);
            }
            if ($appointment->status === 'completed' && ($by = $doctor_user($appointment->doctor_id))) {
                $this->insert($by, 'complete_appointment', 'appointment', $appointment->ID,
                    "{$appointment->appointment_date} {$appointment->appointment_time}", $context,
                    ['status' => ['old' => 'confirmed', 'new' => 'completed']]);
            }
        }

        // Visits: the treating doctor opens the record, then records the visit
        $visitations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_visitations WHERE patient_id = %d ORDER BY date DESC LIMIT %d",
            $patient->ID,
            $limit
        ));
        foreach ($visitations as $visit) {
            $by = $doctor_user($visit->doctor_id);
            if (!$by) {
                continue;
            }
            $seen_at = "{$visit->date} {$visit->time}";
            $this->insert($by, 'view_patient', 'patient', $patient->ID, $seen_at, [
                'accessed_sections' => ['personal_info', 'medical_history', 'lab_results'],
                'visitation_id' => (int) $visit->ID,
            ]);
            $this->insert($by, 'create_visitation', 'visitation', $visit->ID,
                date('Y-m-d H:i:s', strtotime("{$seen_at} +20 minutes")),
                ['patient_id' => (int) $patient->ID, 'diagnosis' => $visit->diagnosis]);
        }

        // Lab investigations: doctor requests, lab tech posts results
        $labs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_lab_investigations WHERE patient_id = %d ORDER BY created_at DESC LIMIT %d",
            $patient->ID,
            $limit
        ));
        foreach ($labs as $lab) {
            $context = ['patient_id' => (int) $patient->ID, 'test_type' => $lab->test_type, 'sample_type' => $lab->sample_type];
            if ($by = $doctor_user($lab->doctor_id)) {
                $this->insert($by, 'request_lab_investigation', 'lab_investigation', $lab->ID, $lab->created_at, $context);
            }
            if (in_array($lab->status, ['completed', 'verified'], true) && $lab->lab_tech_id) {
                $this->insert((int) $lab->lab_tech_id, 'update_lab_results', 'lab_investigation', $lab->ID,
                    $lab->updated_at ?: $lab->created_at, $context,
                    ['status' => ['old' => 'in_progress', 'new' => $lab->status]]);
            }
        }

        // Radiological exams (linked to the patient through their visit): the treating
        // doctor orders the exam, the radiology tech (tech_id, a WP user) posts results.
        $exams = $wpdb->get_results($wpdb->prepare(
            "SELECT r.ID, r.tech_id, r.exam_type, r.status, v.ID AS visitation_id, v.doctor_id, v.date, v.time
             FROM {$wpdb->prefix}hm_radiological_exams r
             JOIN {$wpdb->prefix}hm_visitations v ON v.ID = r.visitation_id
             WHERE v.patient_id = %d
             ORDER BY v.date DESC",
            $patient->ID
        ));
        // Not capped at PER_PATIENT_LIMIT: exams are few and every one should be traceable
        foreach ($exams as $exam) {
            $context = [
                'patient_id' => (int) $patient->ID,
                'visitation_id' => (int) $exam->visitation_id,
                'exam_type' => $exam->exam_type,
            ];
            $ordered_at = date('Y-m-d H:i:s', strtotime("{$exam->date} {$exam->time} +30 minutes"));

            if ($by = $doctor_user($exam->doctor_id)) {
                $this->insert($by, 'request_radiological_exam', 'radiological_exam', $exam->ID, $ordered_at, $context);
            }
            if ($exam->status === 'completed' && $exam->tech_id) {
                $this->insert((int) $exam->tech_id, 'update_radiology_results', 'radiological_exam', $exam->ID,
                    date('Y-m-d H:i:s', strtotime($ordered_at) + mt_rand(1, 3) * DAY_IN_SECONDS), $context,
                    ['status' => ['old' => 'in_progress', 'new' => 'completed']]);
            }
        }

        // Medical reports (doctor_id is the doctor's WP user ID)
        $reports = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, doctor_id, visitation_id, status, created_at FROM {$wpdb->prefix}hm_medical_reports
             WHERE patient_id = %d ORDER BY created_at DESC LIMIT %d",
            $patient->ID,
            $limit
        ));
        foreach ($reports as $report) {
            $this->insert((int) $report->doctor_id, 'create_medical_report', 'medical_report', $report->ID, $report->created_at, [
                'patient_id' => (int) $patient->ID,
                'visitation_id' => (int) $report->visitation_id,
                'status' => $report->status,
            ]);
        }

        // Notifications sent to the patient, and the patient reading them
        if ($patient_user_id) {
            $this->seedNotificationEntries($patient, $patient_user_id, $staff_ids, $doctor_user);
            $this->seedChatEntries($patient, $patient_user_id);
        }

        // Occasional record edits by front-desk staff, with real "new" values
        if (mt_rand(1, 100) <= 50 && !empty($patient->phone)) {
            $this->insert($staff_ids[array_rand($staff_ids)], 'update_patient', 'patient', $patient->ID,
                $this->between($registered_at, current_time('mysql')), [],
                ['phone' => ['old' => '+234-80' . mt_rand(0, 9) . '-' . mt_rand(100, 999) . '-' . mt_rand(1000, 9999), 'new' => $patient->phone]]);
        }
        $bio = json_decode((string) $patient->bio_data, true);
        if (mt_rand(1, 100) <= 30 && !empty($bio['allergies'])) {
            $this->insert($staff_ids[array_rand($staff_ids)], 'update_biodata', 'patient', $patient->ID,
                $this->between($registered_at, current_time('mysql')), [],
                ['allergies' => ['old' => null, 'new' => $bio['allergies']]]);
        }
    }

    /**
     * send_notification for every notification the patient received, attributed to the
     * person whose action produced it (resolved from the notification's reference_id),
     * and read_notification by the patient for the ones they've read. Not capped at
     * PER_PATIENT_LIMIT: a patient only has a handful and each should be traceable.
     *
     *   lab_result_ready      -> lab tech who posted the results (hm_lab_investigations)
     *   medical_report_ready  -> doctor who wrote the report     (hm_medical_reports)
     *   prescription_ready    -> treating doctor of the visit    (hm_visitations)
     *   appointment_* / other -> front-desk staff
     */
    protected function seedNotificationEntries($patient, $patient_user_id, array $staff_ids, callable $doctor_user)
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, type, title, reference_id, `read`, created_at FROM {$p}hm_notifications
             WHERE user_id = %d ORDER BY created_at ASC",
            $patient_user_id
        ));

        foreach ($notifications as $notification) {
            $original_ref = $notification->reference_id ? (int) $notification->reference_id : null;
            $ref = $original_ref;
            $sender = null;

            // Unlinked clinical notifications (e.g. the demo account's) fall back to the
            // patient's most recent matching record, so the sender is still the right person.
            if (!$ref) {
                $fallback = [
                    'lab_result_ready' => "SELECT ID FROM {$p}hm_lab_investigations WHERE patient_id = %d AND status IN ('completed','verified') ORDER BY updated_at DESC LIMIT 1",
                    'medical_report_ready' => "SELECT ID FROM {$p}hm_medical_reports WHERE patient_id = %d ORDER BY created_at DESC LIMIT 1",
                    'prescription_ready' => "SELECT ID FROM {$p}hm_visitations WHERE patient_id = %d ORDER BY date DESC LIMIT 1",
                ];
                if (isset($fallback[$notification->type])) {
                    $ref = (int) $wpdb->get_var($wpdb->prepare($fallback[$notification->type], $patient->ID)) ?: null;
                }
            }

            if ($ref && $notification->type === 'lab_result_ready') {
                $sender = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT lab_tech_id FROM {$p}hm_lab_investigations WHERE ID = %d AND patient_id = %d", $ref, $patient->ID
                ));
            } elseif ($ref && $notification->type === 'medical_report_ready') {
                $sender = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT doctor_id FROM {$p}hm_medical_reports WHERE ID = %d AND patient_id = %d", $ref, $patient->ID
                ));
            } elseif ($ref && $notification->type === 'prescription_ready') {
                $doctor_record = $wpdb->get_var($wpdb->prepare(
                    "SELECT doctor_id FROM {$p}hm_visitations WHERE ID = %d AND patient_id = %d", $ref, $patient->ID
                ));
                $sender = $doctor_record ? $doctor_user($doctor_record) : null;
            } elseif ($notification->type === 'new_message') {
                // Sent by the other side of the patient's most recent conversation
                $sender = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT doctor_id FROM {$p}hm_chats WHERE patient_id = %d ORDER BY last_message_at DESC LIMIT 1",
                    $patient_user_id
                ));
            }
            if (!$sender) {
                $sender = $staff_ids[array_rand($staff_ids)];
            }

            $context = [
                'patient_id' => (int) $patient->ID,
                'notification_type' => $notification->type,
                'title' => $notification->title,
                'reference_id' => $original_ref, // what the notification itself links to
            ];

            $this->insert($sender, 'send_notification', 'notification', $notification->ID, $notification->created_at, $context);

            if ((int) $notification->read === 1) {
                $read_at = strtotime($notification->created_at) + mt_rand(5 * MINUTE_IN_SECONDS, 2 * DAY_IN_SECONDS);
                $this->insert($patient_user_id, 'read_notification', 'notification', $notification->ID,
                    date('Y-m-d H:i:s', $read_at), $context);
            }
        }
    }

    /**
     * Chat activity for the patient's conversations (hm_chats/hm_chat_messages use WP
     * user IDs), mirroring the chat API:
     *   start_chat   (chat)          by the patient — POST /chats/start is patient-initiated
     *   send_message (chat_message)  by the sender of each message
     *   read_chat    (chat)          by each participant who has read messages, shortly
     *                                after the last message they read (PUT /chats/{id}/read)
     * Message text is deliberately NOT copied into the audit log. Not capped at
     * PER_PATIENT_LIMIT: every message should be traceable.
     */
    protected function seedChatEntries($patient, $patient_user_id)
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, doctor_id, created_at FROM {$p}hm_chats WHERE patient_id = %d ORDER BY created_at ASC",
            $patient_user_id
        ));

        foreach ($chats as $chat) {
            $context = [
                'patient_id' => (int) $patient->ID,
                'chat_id' => (int) $chat->ID,
                'doctor_user_id' => (int) $chat->doctor_id,
            ];

            $this->insert($patient_user_id, 'start_chat', 'chat', $chat->ID, $chat->created_at, $context);

            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, sender_id, receiver_id, `read`, created_at FROM {$p}hm_chat_messages
                 WHERE chat_id = %d ORDER BY created_at ASC, ID ASC",
                $chat->ID
            ));

            $last_read_by = [];
            foreach ($messages as $message) {
                $this->insert((int) $message->sender_id, 'send_message', 'chat_message', $message->ID,
                    $message->created_at, $context + ['receiver_id' => (int) $message->receiver_id]);

                if ((int) $message->read === 1) {
                    $last_read_by[(int) $message->receiver_id] = $message->created_at;
                }
            }

            foreach ($last_read_by as $reader_id => $last_read_message_at) {
                $this->insert($reader_id, 'read_chat', 'chat', $chat->ID,
                    date('Y-m-d H:i:s', strtotime($last_read_message_at) + mt_rand(2, 90) * MINUTE_IN_SECONDS), $context);
            }
        }
    }

    /**
     * Logins/logouts/profile changes across all users.
     */
    protected function seedSessionActivity()
    {
        $user_ids = array_map('intval', get_users(['fields' => 'ID']));
        if (empty($user_ids)) {
            return;
        }

        $actions = ['login' => 60, 'logout' => 25, 'profile_update' => 10, 'password_change' => 5];
        $total = min(300, count($user_ids) * 2);

        for ($i = 0; $i < $total; $i++) {
            $user_id = $user_ids[array_rand($user_ids)];
            $action = $this->weighted($actions);
            $details = $action === 'login' || $action === 'logout' ? ['session_id' => md5(uniqid('', true))] : [];
            $this->insert($user_id, $action, 'user', $user_id, date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 60 * 24 * 60) . ' minutes')), $details);
        }
    }

    /**
     * Insert one audit row. Timestamps are clamped to "now" so nothing is in the future.
     */
    protected function insert($user_id, $action, $entity_type, $entity_id, $created_at, array $details = [], array $changes = [])
    {
        global $wpdb;

        if (!$user_id || !$entity_id) {
            return false;
        }

        $timestamp = min(strtotime($created_at) ?: time(), current_time('timestamp'));
        $details = array_merge([
            'ip_address' => $this->ip_addresses[array_rand($this->ip_addresses)],
            'user_agent' => $this->user_agents[array_rand($this->user_agents)],
        ], $details);

        $ok = $wpdb->insert($wpdb->prefix . 'hm_audit_logs', [
            'user_id' => (int) $user_id,
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => (int) $entity_id,
            'changes' => $changes ? wp_json_encode($changes) : null,
            'details' => wp_json_encode($details),
            'created_at' => date('Y-m-d H:i:s', $timestamp),
        ]);

        if ($ok) {
            $this->count++;
        }
        return (bool) $ok;
    }

    /**
     * Random datetime between two datetimes (falls back to $from).
     */
    protected function between($from, $to)
    {
        $a = strtotime($from) ?: time();
        $b = strtotime($to) ?: $a;
        return date('Y-m-d H:i:s', $b > $a ? mt_rand($a, $b) : $a);
    }

    protected function weighted(array $weights)
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }
        return array_key_first($weights);
    }
}

<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;

/**
 * Runs the real clinical seeders against a small set of patients/doctors and asserts
 * the data is connected the way the app reads it:
 *   - every patient has appointments, a visit (with medication), labs, a report,
 *     notifications and a patient audit trail
 *   - visits only exist for completed appointments of the same patient + doctor
 *   - labs/reports/chats/notifications/audit rows point at real, matching records
 *
 * (ClinicalDataSeeder itself isn't used: TRUNCATE would commit the test transaction.
 *  Seeders live in database/seeders/ — lowercase — so the PSR-4 autoloader can't find
 *  them on case-sensitive filesystems; they're required explicitly.)
 */
class SeededPatientRelationshipsTest extends TestCase
{
    const SEEDERS = [
        'LabTestCategorySeeder',
        'LabTestDefinitionSeeder',
        'AppointmentSeeder',
        'VisitationSeeder',
        'LabInvestigationSeeder',
        'RadiologicalExamSeeder',
        'MedicalReportSeeder',
        'ChatSeeder',
        'NotificationSeeder',
        'AuditLogSeeder',
    ];

    private $patients = [];

    public function setUp(): void
    {
        parent::setUp();

        $dir = dirname(__DIR__, 2) . '/database/seeders/';
        require_once $dir . 'Seeder.php';
        foreach (self::SEEDERS as $seeder) {
            require_once $dir . $seeder . '.php';
        }

        for ($i = 0; $i < 3; $i++) {
            $this->createTestDoctor([
                'user_id' => $this->createUserWithRole('doctor'),
                'first_name' => "Doc{$i}",
                'last_name' => 'Tester',
            ]);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->patients[] = $this->createTestPatient([
                'user_id' => $this->createUserWithRole('patient'),
                'first_name' => "Pat{$i}",
                'last_name' => 'Tester',
                'created_at' => date('Y-m-d H:i:s', strtotime('-200 days')),
            ]);
        }
        $this->createUserWithRole('lab_tech');
        $this->createUserWithRole('administrator');

        ob_start();
        try {
            foreach (self::SEEDERS as $seeder) {
                $class = 'HospitalManager\\Database\\Seeders\\' . $seeder;
                (new $class())->run();
            }
        } finally {
            ob_end_clean();
        }
    }

    public function test_every_patient_has_connected_records()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        foreach ($this->patients as $patient) {
            $id = (int) $patient->ID;
            $user = (int) $patient->user_id;
            $label = "patient {$id}";

            $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_appointments WHERE patient_id = %d AND status = 'completed'", $id), "{$label}: completed appointment");
            $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_visitations WHERE patient_id = %d AND treatment <> ''", $id), "{$label}: visit with medication");
            $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_lab_investigations WHERE patient_id = %d", $id), "{$label}: lab investigation");
            $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_medical_reports WHERE patient_id = %d", $id), "{$label}: medical report");
            $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_notifications WHERE user_id = %d", $user), "{$label}: notification");
            $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_audit_logs WHERE entity_type = 'patient' AND entity_id = %d", $id), "{$label}: patient audit entry");
        }
    }

    public function test_visits_belong_to_completed_appointments_of_same_patient_and_doctor()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_visitations WHERE appointment_id IS NOT NULL"));
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_visitations v
             LEFT JOIN {$p}hm_appointments a ON a.ID = v.appointment_id
             WHERE v.appointment_id IS NOT NULL
               AND (a.ID IS NULL OR a.status <> 'completed' OR a.patient_id <> v.patient_id OR a.doctor_id <> v.doctor_id)"
        ), 'Visits must only exist for completed appointments of the same patient/doctor');
        $this->assertSame(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_visitations WHERE date > CURDATE()"), 'No visits in the future');
    }

    public function test_labs_and_reports_match_their_visit()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_lab_investigations l
             LEFT JOIN {$p}hm_visitations v ON v.ID = l.visitation_id
             WHERE v.ID IS NULL OR l.patient_id <> v.patient_id OR l.doctor_id <> v.doctor_id"
        ), 'Lab patient/doctor must match its visit');

        // Reports store the doctor's WP user ID
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_medical_reports r
             LEFT JOIN {$p}hm_visitations v ON v.ID = r.visitation_id
             LEFT JOIN {$p}hm_doctors d ON d.ID = v.doctor_id
             WHERE v.ID IS NULL OR r.patient_id <> v.patient_id OR r.doctor_id <> d.user_id"
        ), 'Report patient/doctor must match its visit');
    }

    public function test_chats_and_notifications_reference_real_care_relationships()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_chats c
             WHERE NOT EXISTS (
                SELECT 1 FROM {$p}hm_visitations v
                JOIN {$p}hm_doctors d ON d.ID = v.doctor_id
                JOIN {$p}hm_patients pt ON pt.ID = v.patient_id
                WHERE d.user_id = c.doctor_id AND pt.user_id = c.patient_id)"
        ), 'Chats only between a patient and a doctor they have seen');

        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_notifications n
             LEFT JOIN {$p}hm_appointments a ON a.ID = n.reference_id
             LEFT JOIN {$p}hm_patients pt ON pt.ID = a.patient_id
             WHERE n.type LIKE 'appointment\\_%' AND n.reference_id IS NOT NULL
               AND (a.ID IS NULL OR pt.user_id <> n.user_id)"
        ), "Appointment notifications must reference the recipient's own appointment");
    }

    public function test_audit_entries_point_at_existing_records()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $this->assertSame(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_audit_logs WHERE entity_type = 'unknown'"));
        $this->assertSame(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_audit_logs WHERE created_at > NOW() + INTERVAL 1 MINUTE"), 'No audit entries in the future');

        $tables = [
            'patient' => 'hm_patients',
            'appointment' => 'hm_appointments',
            'visitation' => 'hm_visitations',
            'lab_investigation' => 'hm_lab_investigations',
            'medical_report' => 'hm_medical_reports',
            'radiological_exam' => 'hm_radiological_exams',
            'notification' => 'hm_notifications',
            'chat' => 'hm_chats',
            'chat_message' => 'hm_chat_messages',
        ];
        foreach ($tables as $type => $table) {
            $this->assertSame(0, $this->countRows(
                "SELECT COUNT(*) FROM {$p}hm_audit_logs a
                 LEFT JOIN {$p}{$table} x ON x.ID = a.entity_id
                 WHERE a.entity_type = %s AND x.ID IS NULL",
                $type
            ), "Audit entries of type {$type} must reference an existing record");
        }

        // view_patient is done by the doctor who saw the patient
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_audit_logs a
             WHERE a.action = 'view_patient' AND NOT EXISTS (
                SELECT 1 FROM {$p}hm_visitations v JOIN {$p}hm_doctors d ON d.ID = v.doctor_id
                WHERE v.patient_id = a.entity_id AND d.user_id = a.user_id)"
        ), 'view_patient must be performed by a treating doctor');
    }

    public function test_radiology_exams_have_audit_entries_by_the_right_people()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_radiological_exams"), 'Fixture should produce radiology exams');

        // Every exam is ordered by the treating doctor of its visit
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_radiological_exams r
             JOIN {$p}hm_visitations v ON v.ID = r.visitation_id
             JOIN {$p}hm_doctors d ON d.ID = v.doctor_id
             WHERE NOT EXISTS (
                SELECT 1 FROM {$p}hm_audit_logs a
                WHERE a.entity_type = 'radiological_exam' AND a.entity_id = r.ID
                  AND a.action = 'request_radiological_exam' AND a.user_id = d.user_id
                  AND CAST(JSON_EXTRACT(a.details, '$.patient_id') AS UNSIGNED) = v.patient_id)"
        ), 'Each exam needs a request entry by the treating doctor, tagged with the patient');

        // Completed exams have results posted by the exam's tech
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_radiological_exams r
             WHERE r.status = 'completed' AND NOT EXISTS (
                SELECT 1 FROM {$p}hm_audit_logs a
                WHERE a.entity_type = 'radiological_exam' AND a.entity_id = r.ID
                  AND a.action = 'update_radiology_results' AND a.user_id = r.tech_id)"
        ), 'Completed exams need a results entry by their tech');
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_audit_logs a
             JOIN {$p}hm_radiological_exams r ON r.ID = a.entity_id
             WHERE a.entity_type = 'radiological_exam' AND a.action = 'update_radiology_results' AND r.status <> 'completed'"
        ), 'No results entries for exams that are not completed');
    }

    public function test_patient_notifications_have_send_and_read_audit_entries()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $this->assertGreaterThan(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_notifications n JOIN {$p}hm_patients pt ON pt.user_id = n.user_id"
        ), 'Fixture should produce patient notifications');

        // Every patient notification was sent (entry tagged with that patient)
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_notifications n
             JOIN {$p}hm_patients pt ON pt.user_id = n.user_id
             WHERE NOT EXISTS (
                SELECT 1 FROM {$p}hm_audit_logs a
                WHERE a.entity_type = 'notification' AND a.entity_id = n.ID AND a.action = 'send_notification'
                  AND CAST(JSON_EXTRACT(a.details, '$.patient_id') AS UNSIGNED) = pt.ID)"
        ), 'Each patient notification needs a send_notification entry');

        // Read notifications were read by the patient, after they were sent; unread ones weren't
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_notifications n
             JOIN {$p}hm_patients pt ON pt.user_id = n.user_id
             WHERE n.`read` = 1 AND NOT EXISTS (
                SELECT 1 FROM {$p}hm_audit_logs a
                WHERE a.entity_type = 'notification' AND a.entity_id = n.ID
                  AND a.action = 'read_notification' AND a.user_id = n.user_id)"
        ), 'Read notifications need a read_notification entry by the patient');
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_audit_logs a JOIN {$p}hm_notifications n ON n.ID = a.entity_id
             WHERE a.action = 'read_notification' AND n.`read` = 0"
        ), 'No read entries for unread notifications');
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_audit_logs r
             JOIN {$p}hm_audit_logs s ON s.entity_type = 'notification' AND s.entity_id = r.entity_id AND s.action = 'send_notification'
             WHERE r.entity_type = 'notification' AND r.action = 'read_notification' AND r.created_at < s.created_at"
        ), 'A notification cannot be read before it was sent');

        // Clinical notifications are sent by whoever produced the linked record
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_audit_logs a
             JOIN {$p}hm_notifications n ON n.ID = a.entity_id
             JOIN {$p}hm_lab_investigations l ON l.ID = n.reference_id
             WHERE a.action = 'send_notification' AND n.type = 'lab_result_ready' AND a.user_id <> l.lab_tech_id"
        ), 'Lab result notifications are sent by the lab tech');
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_audit_logs a
             JOIN {$p}hm_notifications n ON n.ID = a.entity_id
             JOIN {$p}hm_medical_reports r ON r.ID = n.reference_id
             WHERE a.action = 'send_notification' AND n.type = 'medical_report_ready' AND a.user_id <> r.doctor_id"
        ), 'Report notifications are sent by the reporting doctor');
    }

    public function test_chat_activity_has_audit_entries_without_message_text()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $this->assertGreaterThan(0, $this->countRows("SELECT COUNT(*) FROM {$p}hm_chat_messages"), 'Fixture should produce chat messages');

        // Patients start their chats
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_chats c
             JOIN {$p}hm_patients pt ON pt.user_id = c.patient_id
             WHERE NOT EXISTS (
                SELECT 1 FROM {$p}hm_audit_logs a
                WHERE a.entity_type = 'chat' AND a.entity_id = c.ID AND a.action = 'start_chat'
                  AND a.user_id = c.patient_id
                  AND CAST(JSON_EXTRACT(a.details, '$.patient_id') AS UNSIGNED) = pt.ID)"
        ), 'Each chat needs a start_chat entry by the patient, tagged with the patient');

        // Every message is logged as sent by its actual sender
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_chat_messages m
             WHERE NOT EXISTS (
                SELECT 1 FROM {$p}hm_audit_logs a
                WHERE a.entity_type = 'chat_message' AND a.entity_id = m.ID
                  AND a.action = 'send_message' AND a.user_id = m.sender_id)"
        ), 'Each message needs a send_message entry by its sender');

        // read_chat only by participants who actually received read messages
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_audit_logs a
             JOIN {$p}hm_chats c ON c.ID = a.entity_id
             WHERE a.entity_type = 'chat' AND a.action = 'read_chat'
               AND NOT EXISTS (
                SELECT 1 FROM {$p}hm_chat_messages m
                WHERE m.chat_id = c.ID AND m.receiver_id = a.user_id AND m.`read` = 1)"
        ), 'read_chat only by a participant who has read messages');

        // No message happens before its chat was started
        $this->assertSame(0, $this->countRows(
            "SELECT COUNT(*) FROM {$p}hm_audit_logs s
             JOIN {$p}hm_audit_logs m ON m.action = 'send_message'
                AND CAST(JSON_EXTRACT(m.details, '$.chat_id') AS UNSIGNED) = s.entity_id
             WHERE s.action = 'start_chat' AND m.created_at < s.created_at"
        ), 'Messages cannot predate the start of their chat');

        // Conversation content must not be copied into the audit log
        $texts = $wpdb->get_col("SELECT DISTINCT message FROM {$p}hm_chat_messages");
        $details = implode("\n", $wpdb->get_col(
            "SELECT details FROM {$p}hm_audit_logs WHERE entity_type IN ('chat', 'chat_message')"
        ));
        foreach ($texts as $text) {
            $this->assertStringNotContainsString($text, $details, 'Chat message text leaked into the audit log');
        }
    }

    public function test_patient_trail_includes_related_records_including_radiology()
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $patient_id = (int) $wpdb->get_var(
            "SELECT v.patient_id FROM {$p}hm_radiological_exams r
             JOIN {$p}hm_visitations v ON v.ID = r.visitation_id LIMIT 1"
        );
        $this->assertGreaterThan(0, $patient_id);

        $trail = \HospitalManager\Models\AuditLog::getForPatient($patient_id);
        $types = array_unique(array_map(function ($log) {
            return $log->entity_type;
        }, $trail));

        $this->assertContains('patient', $types);
        $this->assertContains('radiological_exam', $types);
        $this->assertContains('visitation', $types);
        $this->assertContains('notification', $types);

        // Not every patient has every kind of record (e.g. chats), so for each related
        // type pick a patient who has one and check it reaches their trail.
        foreach (['chat', 'chat_message', 'radiological_exam', 'notification', 'lab_investigation'] as $type) {
            $owner = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT CAST(JSON_EXTRACT(details, '$.patient_id') AS UNSIGNED) FROM {$p}hm_audit_logs
                 WHERE entity_type = %s LIMIT 1",
                $type
            ));
            $this->assertGreaterThan(0, $owner, "Fixture should produce {$type} audit entries");
            $owner_types = array_map(function ($log) {
                return $log->entity_type;
            }, \HospitalManager\Models\AuditLog::getForPatient($owner));
            $this->assertContains($type, $owner_types, "{$type} entries must appear in the patient's trail");
        }

        foreach ($trail as $log) {
            if ($log->entity_type === 'patient') {
                $this->assertEquals($patient_id, $log->entity_id);
            } else {
                $details = json_decode($log->details, true);
                $this->assertEquals($patient_id, $details['patient_id'], 'Related entries must belong to this patient');
            }
        }

        $times = array_map(function ($log) {
            return $log->created_at;
        }, $trail);
        $sorted = $times;
        rsort($sorted);
        $this->assertSame($sorted, $times, 'Trail is newest first');
    }

    private function countRows($sql, ...$args)
    {
        global $wpdb;
        return (int) $wpdb->get_var($args ? $wpdb->prepare($sql, ...$args) : $sql);
    }
}

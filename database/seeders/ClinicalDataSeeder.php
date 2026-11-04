<?php

namespace HospitalManager\Database\Seeders;

/**
 * Rebuilds the clinical/activity data for the EXISTING users, patients and doctors.
 *
 *   php database/seeders.php clinical
 *
 * Empties and re-seeds: appointments, visitations, lab investigations, radiological
 * exams, medical reports, chats (+ messages), notifications and audit logs, then
 * re-links the demo accounts. Users, patient/doctor records, HMOs, lab catalogues
 * and inventory are left untouched, so logins and record IDs stay the same.
 *
 * Destructive for those tables — anything entered through the app in them is lost.
 */
class ClinicalDataSeeder extends Seeder
{
    /** Truncated in this order (children first) */
    const TABLES = [
        'hm_chat_messages',
        'hm_chats',
        'hm_notifications',
        'hm_audit_logs',
        'hm_medical_reports',
        'hm_radiological_exams',
        'hm_lab_investigations',
        'hm_visitations',
        'hm_appointments',
    ];

    /** Seeded in dependency order */
    const SEEDERS = [
        AppointmentSeeder::class,
        VisitationSeeder::class,
        LabInvestigationSeeder::class,
        RadiologicalExamSeeder::class,
        MedicalReportSeeder::class,
        ChatSeeder::class,
        NotificationSeeder::class,
        DemoDataSeeder::class,
        AuditLogSeeder::class,
    ];

    public function run()
    {
        global $wpdb;

        $patients = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_patients");
        $doctors = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_doctors");
        if (!$patients || !$doctors) {
            $this->log('No patients/doctors found. Run the full seeder (all) first.', 'error');
            return false;
        }

        $this->log("Rebuilding clinical data for {$patients} patients and {$doctors} doctors...");

        // Not clinical, but idempotent and needed for readable names in visits,
        // chats and the audit trail (seeded users used to show their login).
        require_once __DIR__ . '/UserSeeder.php';
        $renamed = UserSeeder::normalizeDisplayNames();
        if ($renamed) {
            $this->log("Set readable display names for {$renamed} users");
        }

        foreach (self::TABLES as $table) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$table}");
        }
        delete_transient('hm_appointments_for_visitations');

        foreach (self::SEEDERS as $class) {
            $this->log('Running: ' . basename(str_replace('\\', '/', $class)));
            (new $class())->run();
        }

        // Models cache query results in transients (up to an hour); after a rebuild
        // those would serve rows/IDs that no longer exist.
        $cleared = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '\\_transient\\_hospital\\_manager\\_%'
                OR option_name LIKE '\\_transient\\_timeout\\_hospital\\_manager\\_%'"
        );
        wp_cache_flush();
        $this->log("Cleared {$cleared} cached query results");

        $this->log('Clinical data rebuilt', 'success');
        return true;
    }
}

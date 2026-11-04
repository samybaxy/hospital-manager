<?php

namespace HospitalManager\Database\Seeders;

/**
 * Seeds hm_medical_reports from existing visitations.
 *
 * Columns: patient_id (hm_patients.ID), doctor_id (doctor's WP user ID — this is what
 * DashboardController / MedicalReport::getPendingForDoctor() query by), visitation_id,
 * report_content, status ('pending' | 'completed').
 */
class MedicalReportSeeder extends Seeder
{
    public function run()
    {
        global $wpdb;

        $this->log('Creating medical reports...');

        $visitations = $wpdb->get_results(
            "SELECT v.*, d.user_id AS doctor_user_id
             FROM {$wpdb->prefix}hm_visitations v
             JOIN {$wpdb->prefix}hm_doctors d ON d.ID = v.doctor_id"
        );

        if (empty($visitations)) {
            $this->log('No visitations found. Run VisitationSeeder first.', 'warning');
            return false;
        }

        $count = 0;
        // Every visit gets a report so each patient's history is complete. Reports for
        // visits in the last 3 days may still be awaiting sign-off.
        $recent_cutoff = date('Y-m-d', strtotime('-3 days'));
        foreach ($visitations as $visitation) {
            $is_recent = $visitation->date >= $recent_cutoff;
            $status = ($is_recent && mt_rand(0, 1)) ? 'pending' : 'completed';
            if (self::createForVisitation($visitation, $status)) {
                $count++;
            }
        }

        $this->log("Created {$count} medical reports", 'success');
        return true;
    }

    public static function createForVisitation($visitation, $status = 'completed')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_medical_reports';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE visitation_id = %d",
            $visitation->ID
        ));
        if ($exists) {
            return false;
        }

        $content = "MEDICAL REPORT\n\n"
            . "Visit date: {$visitation->date} {$visitation->time}\n"
            . "Presenting complaint: " . ($visitation->complaint ?: 'Routine review') . "\n\n"
            . "Findings: Physical examination and assessment completed. Vital signs recorded.\n"
            . "Diagnosis: " . ($visitation->diagnosis ?: 'Under evaluation') . "\n"
            . "Treatment plan: " . ($visitation->treatment ?: 'Observation') . "\n\n"
            . "Recommendations: Patient advised to adhere to the treatment plan and attend scheduled follow-up appointments.";

        $created_at = date('Y-m-d H:i:s', strtotime("{$visitation->date} {$visitation->time} +2 hours"));

        return $wpdb->insert($table, [
            'patient_id' => $visitation->patient_id,
            'doctor_id' => $visitation->doctor_user_id,
            'visitation_id' => $visitation->ID,
            'report_content' => $content,
            'status' => $status,
            'created_at' => $created_at,
            'updated_at' => $created_at,
        ]);
    }
}

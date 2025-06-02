<?php

namespace HospitalManager\Database\Seeders;

class NotificationSeeder extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Creating notifications...');
        
        global $wpdb;
        
        // Get user IDs for recipients
        $users = get_users(['fields' => ['ID']]);
        $user_ids = array_column($users, 'ID');
        
        if (empty($user_ids)) {
            $this->log('No users found. Cannot create notifications.');
            return;
        }
        
        $table = $wpdb->prefix . 'hm_notifications';
        $count = 0;
        $total = 50;
        
        $notification_types = [
            'appointment_reminder',
            'lab_result_ready',
            'prescription_ready',
            'appointment_cancelled',
            'appointment_confirmed',
            'system_maintenance',
            'payment_due',
            'medical_report_ready'
        ];
        
        $titles = [
            'appointment_reminder' => 'Appointment Reminder',
            'lab_result_ready' => 'Lab Results Available',
            'prescription_ready' => 'Prescription Ready for Pickup',
            'appointment_cancelled' => 'Appointment Cancelled',
            'appointment_confirmed' => 'Appointment Confirmed',
            'system_maintenance' => 'Scheduled System Maintenance',
            'payment_due' => 'Payment Due Reminder',
            'medical_report_ready' => 'Medical Report Ready'
        ];
        
        $messages = [
            'appointment_reminder' => 'You have an upcoming appointment scheduled.',
            'lab_result_ready' => 'Your lab results are now available for review.',
            'prescription_ready' => 'Your prescription is ready for pickup at the pharmacy.',
            'appointment_cancelled' => 'Your appointment has been cancelled. Please reschedule.',
            'appointment_confirmed' => 'Your appointment has been confirmed.',
            'system_maintenance' => 'System will be under maintenance. Please plan accordingly.',
            'payment_due' => 'You have an outstanding payment due.',
            'medical_report_ready' => 'Your medical report is ready for review.'
        ];
        
        for ($i = 0; $i < $total; $i++) {
            $type = $notification_types[array_rand($notification_types)];
            $recipient_id = $user_ids[array_rand($user_ids)];
            $days_ago = mt_rand(0, 30);
            $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
            
            $data = [
                'recipient_id' => $recipient_id,
                'type' => $type,
                'title' => $titles[$type],
                'message' => $messages[$type],
                'is_read' => mt_rand(0, 100) <= 60 ? 1 : 0, // 60% read
                'created_at' => $created_at
            ];
            
            $result = $wpdb->insert($table, $data);
            if ($result) {
                $count++;
            }
        }
        
        $this->log("Created {$count} notifications");
    }
}

<?php

namespace HospitalManager\Database\Seeders;

class NotificationSeeder extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Seeding notifications...');
        
        // Get users by role
        $patients = $this->getUserIds(10, 'patient');
        $doctors = $this->getUserIds(10, 'doctor');
        $lab_techs = $this->getUserIds(5, 'lab_tech');
        $receptionists = $this->getUserIds(3, 'receptionist');
        
        // Combine all users
        $all_users = array_merge($patients, $doctors, $lab_techs, $receptionists);
        
        if (empty($all_users)) {
            $this->log('No users found. Cannot create notifications.');
            return;
        }
        
        // Get existing entities for reference IDs
        $appointments = $this->getEntityIds('hm_appointments');
        $lab_investigations = $this->getEntityIds('hm_lab_investigations');
        $medical_reports = $this->getEntityIds('hm_medical_reports');
        
        // Create notifications
        $count = 0;
        $notification_types = $this->getNotificationTypes();
        $total_to_create = 100; // Total notifications to create
        
        for ($i = 0; $i < $total_to_create; $i++) {
            // Select random user
            $user_id = $all_users[array_rand($all_users)];
            
            // Select random notification type
            $notification_type = array_rand($notification_types);
            $notification_data = $notification_types[$notification_type];
            
            // Get reference ID based on type
            $reference_id = $this->getReferenceIdForType($notification_type, [
                'appointments' => $appointments,
                'lab_investigations' => $lab_investigations,
                'medical_reports' => $medical_reports
            ]);
            
            // Generate random read status (70% read, 30% unread)
            $read = rand(1, 10) <= 7 ? 1 : 0;
            
            // Generate creation date (last 30 days)
            $days_ago = rand(0, 30);
            $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
            
            // Build notification data
            $data = [
                'user_id' => $user_id,
                'type' => $notification_type,
                'title' => $notification_data['title'],
                'message' => $this->generateMessage($notification_data['messages']),
                'reference_id' => $reference_id,
                'read' => $read,
                'created_at' => $created_at
            ];
            
            $this->wpdb->insert($this->wpdb->prefix . 'hm_notifications', $data);
            $count++;
        }
        
        $this->log("Created {$count} notifications");
    }
    
    /**
     * Get entity IDs from a table
     * 
     * @param string $table Table name without prefix
     * @return array Array of IDs
     */
    protected function getEntityIds($table)
    {
        return $this->wpdb->get_col("SELECT id FROM {$this->wpdb->prefix}{$table} LIMIT 30");
    }
    
    /**
     * Generate a message from message templates
     * 
     * @param array $messages Array of message templates
     * @return string Generated message
     */
    protected function generateMessage($messages)
    {
        // Select random message template
        $template = $messages[array_rand($messages)];
        
        // Replace placeholders with random values
        $template = str_replace('[DATE]', $this->faker->date('F j, Y'), $template);
        $template = str_replace('[TIME]', $this->faker->time('g:i A'), $template);
        $template = str_replace('[NAME]', $this->faker->name(), $template);
        $template = str_replace('[TEST]', $this->faker->randomElement(['blood test', 'urine analysis', 'X-ray', 'MRI scan']), $template);
        
        return $template;
    }
    
    /**
     * Get reference ID based on notification type
     * 
     * @param string $type Notification type
     * @param array $entities Array of entity IDs by type
     * @return int|null Reference ID
     */
    protected function getReferenceIdForType($type, $entities)
    {
        switch ($type) {
            case 'appointment':
            case 'appointment_reminder':
            case 'appointment_cancelled':
                return !empty($entities['appointments']) 
                    ? $entities['appointments'][array_rand($entities['appointments'])] 
                    : null;
                
            case 'lab_results':
            case 'lab_request':
                return !empty($entities['lab_investigations']) 
                    ? $entities['lab_investigations'][array_rand($entities['lab_investigations'])] 
                    : null;
                
            case 'medical_report':
                return !empty($entities['medical_reports']) 
                    ? $entities['medical_reports'][array_rand($entities['medical_reports'])] 
                    : null;
                
            default:
                return null;
        }
    }
    
    /**
     * Get notification types with titles and message templates
     * 
     * @return array Notification types
     */
    protected function getNotificationTypes()
    {
        return [
            'appointment' => [
                'title' => 'New Appointment',
                'messages' => [
                    'You have a new appointment scheduled for [DATE] at [TIME].',
                    'An appointment has been booked for [DATE] at [TIME].',
                    'New appointment created with Dr. [NAME] on [DATE] at [TIME].'
                ]
            ],
            'appointment_reminder' => [
                'title' => 'Appointment Reminder',
                'messages' => [
                    'Reminder: You have an appointment tomorrow at [TIME].',
                    'Don\'t forget your appointment on [DATE] at [TIME] with Dr. [NAME].',
                    'Your appointment is scheduled for tomorrow at [TIME]. Please arrive 15 minutes early.'
                ]
            ],
            'appointment_cancelled' => [
                'title' => 'Appointment Cancelled',
                'messages' => [
                    'Your appointment for [DATE] has been cancelled.',
                    'The appointment scheduled for [DATE] at [TIME] has been cancelled.',
                    'We regret to inform you that your appointment with Dr. [NAME] has been cancelled.'
                ]
            ],
            'lab_results' => [
                'title' => 'Lab Results Available',
                'messages' => [
                    'Your lab results are now available.',
                    'The results for your [TEST] are ready for review.',
                    'Dr. [NAME] has received your lab results and will discuss them during your next visit.'
                ]
            ],
            'lab_request' => [
                'title' => 'New Lab Test Request',
                'messages' => [
                    'A new [TEST] has been requested for patient #[ID].',
                    'Dr. [NAME] has requested a [TEST] for a patient.',
                    'New laboratory investigation requested: [TEST].'
                ]
            ],
            'medical_report' => [
                'title' => 'Medical Report Ready',
                'messages' => [
                    'Your medical report is now available for review.',
                    'Dr. [NAME] has completed your medical report.',
                    'The medical report from your visit on [DATE] is now ready.'
                ]
            ],
            'system' => [
                'title' => 'System Notification',
                'messages' => [
                    'Your password was changed successfully.',
                    'Your account information has been updated.',
                    'Please complete your profile information.',
                    'The system will be undergoing maintenance on [DATE] at [TIME].'
                ]
            ]
        ];
    }
}

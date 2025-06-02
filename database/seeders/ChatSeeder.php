<?php

namespace HospitalManager\Database\Seeders;

class ChatSeeder extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Creating chat messages...');
        
        global $wpdb;
        
        // Get user IDs
        $users = get_users(['fields' => ['ID', 'display_name']]);
        $user_data = [];
        foreach ($users as $user) {
            $user_data[] = ['id' => $user->ID, 'name' => $user->display_name];
        }
        
        if (count($user_data) < 2) {
            $this->log('Need at least 2 users for chat. Skipping chat seeder.');
            return;
        }
        
        $table = $wpdb->prefix . 'hm_chats';
        $count = 0;
        $total_messages = 100;
        
        $sample_messages = [
            'Hello, how are you today?',
            'Can we schedule a meeting for tomorrow?',
            'I have reviewed the patient records.',
            'The lab results are ready for review.',
            'Please check the latest medical reports.',
            'Thank you for your assistance.',
            'When is the next appointment available?',
            'I need to discuss the treatment plan.',
            'The medication has been prescribed.',
            'Please confirm the appointment time.',
            'How is the patient doing today?',
            'We need to update the medical history.',
            'The test results look normal.',
            'Can you provide more details?',
            'I will be available this afternoon.',
            'Please send me the updated schedule.',
            'The surgery went well.',
            'Recovery is progressing as expected.',
            'We should monitor the vitals closely.',
            'All procedures have been completed.'
        ];
        
        for ($i = 0; $i < $total_messages; $i++) {
            // Pick random sender and receiver
            $sender = $user_data[array_rand($user_data)];
            do {
                $receiver = $user_data[array_rand($user_data)];
            } while ($receiver['id'] === $sender['id']); // Ensure different users
            
            $message = $sample_messages[array_rand($sample_messages)];
            $days_ago = mt_rand(0, 60);
            $hours_ago = mt_rand(0, 23);
            $minutes_ago = mt_rand(0, 59);
            $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days -{$hours_ago} hours -{$minutes_ago} minutes"));
            
            $data = [
                'sender_id' => $sender['id'],
                'receiver_id' => $receiver['id'],
                'message' => $message,
                'is_read' => mt_rand(0, 100) <= 70 ? 1 : 0, // 70% read
                'created_at' => $created_at
            ];
            
            $result = $wpdb->insert($table, $data);
            if ($result) {
                $count++;
            }
        }
        
        $this->log("Created {$count} chat messages", 'success');
    }
}

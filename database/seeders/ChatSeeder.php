<?php

namespace HospitalManager\Database\Seeders;

class ChatSeeder extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Seeding chat conversations...');
        
        // Get patient and doctor IDs
        $patients = $this->wpdb->get_results("
            SELECT id, user_id FROM {$this->wpdb->prefix}hm_patients
            ORDER BY id ASC
            LIMIT 15
        ");
        
        $doctors = $this->wpdb->get_results("
            SELECT id, user_id FROM {$this->wpdb->prefix}hm_doctors
            ORDER BY id ASC
            LIMIT 10
        ");
        
        if (empty($patients) || empty($doctors)) {
            $this->log('Patients or doctors not found. Cannot create chats.');
            return;
        }
        
        $chat_count = 0;
        $message_count = 0;
        
        // For each patient, create chats with 1-3 doctors
        foreach ($patients as $patient) {
            // Choose how many doctors this patient will chat with
            $chat_with_count = min(rand(1, 3), count($doctors));
            
            // Randomly select doctors
            $chat_with_doctors = array_rand(array_flip(range(0, count($doctors) - 1)), $chat_with_count);
            if (!is_array($chat_with_doctors)) {
                $chat_with_doctors = [$chat_with_doctors];
            }
            
            foreach ($chat_with_doctors as $doctor_index) {
                $doctor = $doctors[$doctor_index];
                
                // Create the chat
                $created_at_dt = $this->faker->dateTimeBetween('-3 months', '-1 day');
                $created_at = $created_at_dt->format('Y-m-d H:i:s');
                $chat_data = [
                    'doctor_id' => $doctor->id,
                    'patient_id' => $patient->id,
                    'status' => 'active',
                    'created_at' => $created_at,
                    'updated_at' => $created_at,
                    'last_message_at' => $created_at
                ];
                
                $result = $this->wpdb->insert($this->wpdb->prefix . 'hm_chats', $chat_data);
                
                if ($result) {
                    $chat_id = $this->wpdb->insert_id;
                    $chat_count++;
                    
                    // Now create messages for this chat
                    $message_count += $this->createChatMessages(
                        $chat_id,
                        $doctor->user_id,
                        $patient->user_id,
                        $created_at
                    );
                    
                    // Update the chat's last_message_at
                    $this->wpdb->update(
                        $this->wpdb->prefix . 'hm_chats',
                        ['last_message_at' => date('Y-m-d H:i:s')],
                        ['id' => $chat_id]
                    );
                }
            }
        }
        
        $this->log("Created {$chat_count} chats with {$message_count} messages");
    }
    
    /**
     * Create messages for a chat conversation
     * 
     * @param int $chat_id Chat ID
     * @param int $doctor_user_id Doctor's WordPress user ID
     * @param int $patient_user_id Patient's WordPress user ID
     * @param string $start_date Start date for message timeline
     * @return int Number of messages created
     */
    protected function createChatMessages($chat_id, $doctor_user_id, $patient_user_id, $start_date)
    {
        // Create between 3 and 15 messages
        $message_count = rand(3, 15);
        $count = 0;
        
        // Initial question from patient
        $initial_messages = [
            "Hello doctor, I've been experiencing persistent headaches for the past week.",
            "Good day doctor. I need some advice about my medication.",
            "Doctor, I'm having some side effects from the medicine you prescribed.",
            "Hi doctor, my symptoms haven't improved since our last appointment.",
            "Doctor, I need to reschedule my appointment next week.",
            "Hello, I've been having difficulty sleeping lately.",
            "I've been feeling dizzy when I stand up quickly. Should I be concerned?",
            "Good day doctor. My test results are in. When can I come discuss them?",
            "Hello doctor, my child has had a fever for two days. What should I do?",
            "Doctor, I'm experiencing pain in my lower back. Can you advise?"
        ];
        
        // Doctor responses
        $doctor_responses = [
            "Hello! I'm sorry to hear that. Can you tell me more about your symptoms?",
            "Good day. I'd be happy to help. What specific concerns do you have?",
            "Thank you for letting me know. What side effects are you experiencing?",
            "I understand your concern. Let's discuss what symptoms you're still having.",
            "Of course, we can reschedule. What day works better for you?",
            "Sleep issues can be challenging. How long have you been experiencing this?",
            "Dizziness can have several causes. Are you staying hydrated?",
            "I'll review your results and we can discuss them. Are you available tomorrow?",
            "Fevers in children can be concerning. What's their temperature?",
            "Back pain can be uncomfortable. Did you injure yourself recently?"
        ];
        
        // Patient follow-ups
        $patient_followups = [
            "The pain is mostly on the right side of my head and gets worse in the evening.",
            "I'm wondering if I should increase my dosage as the symptoms persist.",
            "I've been feeling nauseous and having headaches after taking it.",
            "I still have the cough and now I'm also feeling more tired than usual.",
            "Would next Tuesday at 2 PM work?",
            "About two weeks now. I try to sleep but I keep waking up.",
            "I think so. I drink water regularly but the dizziness continues.",
            "Yes, I can come in the morning around 10 AM if that works.",
            "It's around 38.5°C and they're also complaining of a sore throat.",
            "No injury, but I've been sitting at my desk a lot for work lately."
        ];
        
        // Convert start_date to a timestamp
        $timestamp = strtotime($start_date);
        
        // Select an initial message
        $initial_message = $initial_messages[array_rand($initial_messages)];
        
        // First message from patient
        $this->wpdb->insert($this->wpdb->prefix . 'hm_chat_messages', [
            'chat_id' => $chat_id,
            'sender_id' => $patient_user_id,
            'receiver_id' => $doctor_user_id,
            'message' => $this->sanitizeText($initial_message),
            'read' => 1,
            'created_at' => date('Y-m-d H:i:s', $timestamp)
        ]);
        $count++;
        $timestamp += rand(300, 3600); // 5 min to 1 hour later
        
        // Response from doctor
        $doctor_response = $doctor_responses[array_rand($doctor_responses)];
        $this->wpdb->insert($this->wpdb->prefix . 'hm_chat_messages', [
            'chat_id' => $chat_id,
            'sender_id' => $doctor_user_id,
            'receiver_id' => $patient_user_id,
            'message' => $this->sanitizeText($doctor_response),
            'read' => 1,
            'created_at' => date('Y-m-d H:i:s', $timestamp)
        ]);
        $count++;
        $timestamp += rand(300, 3600); // 5 min to 1 hour later
        
        // Follow-up from patient
        $patient_followup = $patient_followups[array_rand($patient_followups)];
        $this->wpdb->insert($this->wpdb->prefix . 'hm_chat_messages', [
            'chat_id' => $chat_id,
            'sender_id' => $patient_user_id,
            'receiver_id' => $doctor_user_id,
            'message' => $this->sanitizeText($patient_followup),
            'read' => 1,
            'created_at' => date('Y-m-d H:i:s', $timestamp)
        ]);
        $count++;
        $timestamp += rand(300, 3600); // 5 min to 1 hour later
        
        // Create additional random messages
        $remaining_messages = $message_count - 3;
        for ($i = 0; $i < $remaining_messages; $i++) {
            // Random sender (60% doctor, 40% patient)
            $is_doctor_sender = (rand(1, 10) <= 6);
            $sender_id = $is_doctor_sender ? $doctor_user_id : $patient_user_id;
            $receiver_id = $is_doctor_sender ? $patient_user_id : $doctor_user_id;
            
            // Random read status (90% read, 10% unread)
            $read = (rand(1, 10) <= 9) ? 1 : 0;
            
            // Random time delay (5 minutes to 12 hours)
            $timestamp += rand(300, 43200);
            
            // Generate message text
            if ($is_doctor_sender) {
                $message = $this->generateDoctorMessage();
            } else {
                $message = $this->generatePatientMessage();
            }
            
            $this->wpdb->insert($this->wpdb->prefix . 'hm_chat_messages', [
                'chat_id' => $chat_id,
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message' => $this->sanitizeText($message),
                'read' => $read,
                'created_at' => date('Y-m-d H:i:s', $timestamp)
            ]);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Generate a random doctor message
     * 
     * @return string Message text
     */
    protected function generateDoctorMessage()
    {
        $messages = [
            "Based on what you've described, I think we should do some additional tests.",
            "Your symptoms could be related to several conditions. Let's narrow it down.",
            "I'd recommend continuing with your current medication for another week.",
            "It might be best if you come in for a follow-up appointment soon.",
            "That's a common side effect, but it should subside within a few days.",
            "Make sure you're getting enough rest and staying hydrated.",
            "I've reviewed your test results, and everything looks normal.",
            "Have you noticed any other symptoms besides what you've mentioned?",
            "Try applying a warm compress to the affected area.",
            "Let's schedule you for a follow-up in two weeks to see how you're progressing.",
            "I'll send a prescription to your pharmacy today.",
            "Those symptoms could be related to stress or anxiety.",
            "Make sure you're taking the medication with food to reduce stomach irritation.",
            "Your blood pressure readings are a bit high. Let's monitor that.",
            "I'd like you to keep a daily log of when these symptoms occur."
        ];
        
        return $this->sanitizeText($messages[array_rand($messages)]);
    }
    
    /**
     * Generate a random patient message
     * 
     * @return string Message text
     */
    protected function generatePatientMessage()
    {
        $messages = [
            "Should I be concerned if the symptoms get worse?",
            "How long will I need to take this medication?",
            "I've noticed the pain is worse in the morning.",
            "Is it normal to feel this tired all the time?",
            "Are there any foods I should avoid while taking this medication?",
            "I've been following your advice and feeling a bit better.",
            "My fever has gone down since yesterday.",
            "I forgot to mention that I'm also taking supplements.",
            "When will I be able to resume normal activities?",
            "Should I come in for a check-up sooner?",
            "The pain medication isn't helping much.",
            "I've been drinking plenty of fluids as you suggested.",
            "Is it safe to exercise with this condition?",
            "My family has a history of this condition. Does that matter?",
            "The symptoms seem to be getting worse at night."
        ];
        
        return $this->sanitizeText($messages[array_rand($messages)]);
    }

    /**
     * Sanitize text to ensure it contains only valid UTF-8 characters
     * 
     * @param string $text Text to sanitize
     * @return string Sanitized text
     */
    protected function sanitizeText($text)
    {
        // Handle potential NULL or invalid inputs
        if (!is_string($text) || empty($text)) {
            return "Message content unavailable";
        }
        
        // Force to ASCII only to avoid encoding issues
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        
        // Remove any control characters
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        
        // Additional sanitization to be extra safe - using modern approach
        // Use htmlspecialchars instead of deprecated FILTER_SANITIZE_STRING
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        
        return $text ?: "Message content unavailable";
    }
}

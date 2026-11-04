<?php

namespace HospitalManager\Database\Seeders;

/**
 * Seeds doctor <-> patient chat threads (hm_chats) and their messages (hm_chat_messages).
 *
 * hm_chats.doctor_id / patient_id and hm_chat_messages.sender_id / receiver_id
 * all reference WordPress user IDs (see Models\Chat and ChatController).
 */
class ChatSeeder extends Seeder
{
    protected $doctor_lines = [
        'Hello, how are you feeling today?',
        'I have reviewed your lab results.',
        'Your test results look normal.',
        'Please continue the medication for another week.',
        'Remember to take your medication after meals.',
        'Can you come in for a follow-up on Thursday?',
        'Please keep monitoring your blood pressure at home.',
        'I have sent a prescription to the pharmacy.',
        'Let me know if the symptoms persist.',
        'We should repeat the blood test in two weeks.',
    ];

    protected $patient_lines = [
        'Good morning doctor, I am feeling much better.',
        'Thank you for your assistance.',
        'When is the next appointment available?',
        'I still have a mild headache in the evenings.',
        'Should I continue with the same dosage?',
        'Please confirm the appointment time.',
        'Are the lab results ready?',
        'I will be available this afternoon.',
        'The medication is making me a bit drowsy.',
        'Okay, noted. Thank you doctor.',
    ];

    public function run()
    {
        global $wpdb;

        $this->log('Creating chats and chat messages...');

        // Pair each patient with a doctor they have actually seen (from visitations),
        // so conversations make sense in both users' inboxes.
        $pairs = $wpdb->get_results(
            "SELECT DISTINCT d.user_id AS doctor_user_id, p.user_id AS patient_user_id
             FROM {$wpdb->prefix}hm_visitations v
             JOIN {$wpdb->prefix}hm_doctors d ON d.ID = v.doctor_id
             JOIN {$wpdb->prefix}hm_patients p ON p.ID = v.patient_id
             WHERE d.user_id > 0 AND p.user_id > 0"
        );

        if (empty($pairs)) {
            $this->log('No doctor/patient visit pairs found. Run VisitationSeeder first.', 'warning');
            return false;
        }

        shuffle($pairs);
        $chat_count = 0;
        $message_count = 0;
        $patients_with_chat = [];

        foreach ($pairs as $pair) {
            // ~60% of patients get one conversation with a treating doctor
            if (isset($patients_with_chat[$pair->patient_user_id]) || mt_rand(1, 100) > 60) {
                continue;
            }
            $result = $this->createThread((int) $pair->doctor_user_id, (int) $pair->patient_user_id, mt_rand(3, 8));
            if ($result) {
                $patients_with_chat[$pair->patient_user_id] = true;
                $chat_count++;
                $message_count += $result;
            }
        }

        $this->log("Created {$chat_count} chats with {$message_count} messages", 'success');
        return true;
    }

    /**
     * Create one chat thread with alternating messages.
     *
     * @return int|false Number of messages created, or false on failure
     */
    public function createThread($doctor_id, $patient_id, $message_total, $days_ago = null)
    {
        global $wpdb;

        $chats_table = $wpdb->prefix . 'hm_chats';
        $messages_table = $wpdb->prefix . 'hm_chat_messages';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$chats_table} WHERE doctor_id = %d AND patient_id = %d",
            $doctor_id,
            $patient_id
        ));
        if ($existing) {
            return false;
        }

        $days_ago = $days_ago ?? mt_rand(1, 45);
        $timestamp = strtotime("-{$days_ago} days") - mt_rand(0, 8 * HOUR_IN_SECONDS);
        $created_at = date('Y-m-d H:i:s', $timestamp);

        $ok = $wpdb->insert($chats_table, [
            'doctor_id' => $doctor_id,
            'patient_id' => $patient_id,
            'status' => 'active',
            'last_message_at' => $created_at,
            'created_at' => $created_at,
            'updated_at' => $created_at,
        ]);
        if (!$ok) {
            return false;
        }
        $chat_id = $wpdb->insert_id;

        $count = 0;
        for ($i = 0; $i < $message_total; $i++) {
            // Patient opens the conversation, then alternate
            $from_patient = ($i % 2 === 0);
            $timestamp += mt_rand(5, 180) * MINUTE_IN_SECONDS;
            $sent_at = date('Y-m-d H:i:s', min($timestamp, time()));
            $is_last_two = ($i >= $message_total - 2);

            $inserted = $wpdb->insert($messages_table, [
                'chat_id' => $chat_id,
                'sender_id' => $from_patient ? $patient_id : $doctor_id,
                'receiver_id' => $from_patient ? $doctor_id : $patient_id,
                'message' => $from_patient
                    ? $this->patient_lines[array_rand($this->patient_lines)]
                    : $this->doctor_lines[array_rand($this->doctor_lines)],
                'read' => $is_last_two ? 0 : 1,
                'created_at' => $sent_at,
            ]);

            if ($inserted) {
                $count++;
                $wpdb->update($chats_table, [
                    'last_message_at' => $sent_at,
                    'updated_at' => $sent_at,
                ], ['ID' => $chat_id]);
            }
        }

        return $count;
    }
}

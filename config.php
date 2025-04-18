<?php

namespace HospitalManager;

class Config {
    // WebSocket/SSE Configuration
    const SSE_ENDPOINT = '/wp-json/hospital-manager/v1/events';
    const SSE_RETRY_INTERVAL = 3000; // 3 seconds
    
    // Message Queue Configuration
    const MESSAGE_TTL = 86400; // 24 hours
    const MAX_QUEUE_SIZE = 1000;
    
    // Notification Settings
    const NOTIFICATION_TYPES = [
        'chat' => 'New message',
        'appointment' => 'Appointment update',
        'lab_results' => 'Lab results available'
    ];
}

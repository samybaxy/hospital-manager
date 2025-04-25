<?php

namespace HospitalManager\Tests\Helpers;

class Debugger
{
    /**
     * Print debug information to a file
     */
    public static function log($message, $data = null)
    {
        $log_file = dirname(__FILE__, 3) . '/debug-test.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $log_message = "[{$timestamp}] {$message}\n";
        
        if ($data !== null) {
            $formatted_data = is_array($data) || is_object($data) 
                ? print_r($data, true) 
                : $data;
            $log_message .= $formatted_data . "\n";
        }
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}

<?php

namespace HospitalManager\Tests\Helpers;

class Debugger
{
    /**
     * Print debug information to a file
     */
    public static function log($message, $data = null)
    {
        // Outside the plugin: a debug.log in the plugin folder is downloadable
        // from the web, and these dumps contain (test) patient records.
        $log_file = sys_get_temp_dir() . '/hospital-manager-tests.log';
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

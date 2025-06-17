<?php

namespace HospitalManager\Services;

/**
 * Validation Service
 * 
 * Centralized validation logic to reduce redundancy across services
 */
class ValidationService extends BaseService
{
    /**
     * Validate patient data
     * 
     * @param array $data Patient data to validate
     * @param bool $is_update Whether this is an update operation
     * @return array Array of validation errors (empty if valid)
     */
    public static function validatePatientData($data, $is_update = false)
    {
        $errors = [];
        
        // Required fields for creation
        if (!$is_update) {
            $required_fields = ['first_name', 'last_name', 'phone', 'gender'];
            $errors = array_merge($errors, self::validateRequiredFields($data, $required_fields));
        }
        
        // Phone validation
        if (!empty($data['phone'])) {
            $phone_error = self::validatePhone($data['phone']);
            if ($phone_error) {
                $errors[] = $phone_error;
            }
        }
        
        // Email validation
        if (!empty($data['email'])) {
            $email_error = self::validateEmail($data['email']);
            if ($email_error) {
                $errors[] = $email_error;
            }
        }
        
        // Age validation
        if (isset($data['age'])) {
            $age_error = self::validateNumeric($data['age'], 'age');
            if ($age_error) {
                $errors[] = $age_error;
            }
        }
        
        // Gender validation
        if (!empty($data['gender'])) {
            if (!in_array(strtolower($data['gender']), ['male', 'female', 'other', 'm', 'f'])) {
                $errors[] = "Invalid value for gender. Expected 'Male', 'Female', 'Other', 'M', or 'F'";
            }
        }
        
        // Bio data JSON validation
        if (!empty($data['bio_data']) && is_string($data['bio_data'])) {
            if (self::safeJsonDecode($data['bio_data']) === null) {
                $errors[] = "Invalid JSON format for bio_data";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate appointment data
     * 
     * @param array $data Appointment data to validate
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateAppointmentData($data)
    {
        $errors = [];
        
        $required_fields = ['patient_id', 'doctor_id', 'appointment_date', 'appointment_time'];
        $errors = array_merge($errors, self::validateRequiredFields($data, $required_fields));
        
        // Validate patient_id
        if (!empty($data['patient_id'])) {
            $patient_error = self::validateNumeric($data['patient_id'], 'patient_id');
            if ($patient_error) {
                $errors[] = $patient_error;
            }
        }
        
        // Validate doctor_id
        if (!empty($data['doctor_id'])) {
            $doctor_error = self::validateNumeric($data['doctor_id'], 'doctor_id');
            if ($doctor_error) {
                $errors[] = $doctor_error;
            }
        }
        
        // Validate appointment date
        if (!empty($data['appointment_date'])) {
            if (!self::validateDate($data['appointment_date'])) {
                $errors[] = "Invalid appointment date format. Expected YYYY-MM-DD";
            }
        }
        
        // Validate appointment time
        if (!empty($data['appointment_time'])) {
            if (!self::validateTime($data['appointment_time'])) {
                $errors[] = "Invalid appointment time format. Expected HH:MM";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate inventory item data
     * 
     * @param array $data Inventory data to validate
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateInventoryData($data)
    {
        $errors = [];
        
        $required_fields = ['name', 'category', 'quantity', 'unit'];
        $errors = array_merge($errors, self::validateRequiredFields($data, $required_fields));
        
        // Validate quantity
        if (isset($data['quantity'])) {
            $quantity_error = self::validateNumeric($data['quantity'], 'quantity');
            if ($quantity_error) {
                $errors[] = $quantity_error;
            } elseif ($data['quantity'] < 0) {
                $errors[] = "Quantity cannot be negative";
            }
        }
        
        // Validate reorder level
        if (isset($data['reorder_level'])) {
            $reorder_error = self::validateNumeric($data['reorder_level'], 'reorder_level');
            if ($reorder_error) {
                $errors[] = $reorder_error;
            } elseif ($data['reorder_level'] < 0) {
                $errors[] = "Reorder level cannot be negative";
            }
        }
        
        // Validate cost
        if (isset($data['cost'])) {
            if (!is_numeric($data['cost']) || $data['cost'] < 0) {
                $errors[] = "Cost must be a positive number";
            }
        }
        
        // Validate expiry date
        if (!empty($data['expiry_date'])) {
            if (!self::validateDate($data['expiry_date'])) {
                $errors[] = "Invalid expiry date format. Expected YYYY-MM-DD";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate notification data
     * 
     * @param array $data Notification data to validate
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateNotificationData($data)
    {
        $errors = [];
        
        $required_fields = ['user_id', 'type', 'title', 'message'];
        $errors = array_merge($errors, self::validateRequiredFields($data, $required_fields));
        
        // Validate user_id
        if (!empty($data['user_id'])) {
            $user_error = self::validateNumeric($data['user_id'], 'user_id');
            if ($user_error) {
                $errors[] = $user_error;
            }
        }
        
        // Validate notification type
        if (!empty($data['type'])) {
            $allowed_types = ['info', 'warning', 'error', 'success', 'appointment', 'lab_result', 'medication'];
            if (!in_array($data['type'], $allowed_types)) {
                $errors[] = "Invalid notification type. Allowed types: " . implode(', ', $allowed_types);
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate date format (YYYY-MM-DD)
     * 
     * @param string $date Date string to validate
     * @return bool Whether date is valid
     */
    public static function validateDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate time format (HH:MM)
     * 
     * @param string $time Time string to validate
     * @return bool Whether time is valid
     */
    public static function validateTime($time)
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
    
    /**
     * Validate datetime format (YYYY-MM-DD HH:MM:SS)
     * 
     * @param string $datetime Datetime string to validate
     * @return bool Whether datetime is valid
     */
    public static function validateDateTime($datetime)
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $d && $d->format('Y-m-d H:i:s') === $datetime;
    }
    
    /**
     * Validate password strength
     * 
     * @param string $password Password to validate
     * @return array Array of validation errors (empty if valid)
     */
    public static function validatePassword($password)
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file File data from $_FILES
     * @param array $allowed_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateFileUpload($file, $allowed_types = [], $max_size = 2097152)
    {
        $errors = [];
        
        if (!isset($file['error']) || is_array($file['error'])) {
            $errors[] = "Invalid file upload";
            return $errors;
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = "No file was uploaded";
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "File size exceeds the maximum allowed size";
                break;
            default:
                $errors[] = "Unknown file upload error";
                break;
        }
        
        if (!empty($errors)) {
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = "File size exceeds the maximum allowed size of " . ($max_size / 1024 / 1024) . "MB";
        }
        
        // Check file type
        if (!empty($allowed_types)) {
            $file_type = mime_content_type($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
            }
        }
        
        return $errors;
    }
}

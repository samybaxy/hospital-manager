<?php

namespace HospitalManager\Helpers;

/**
 * Debug-only logging for payloads that may contain patient or medical data
 * (request params, search terms, records, API bodies).
 *
 * Off unless explicitly switched on, in wp-config.php:
 *     define('HOSPITAL_MANAGER_LOG_PAYLOADS', true);
 * or with add_filter('hospital_manager_log_api_payloads', '__return_true').
 *
 * (It used to follow WP_DEBUG, so any site with debugging on wrote patient
 * names, allergies and results from every API request into the log.)
 *
 * Credentials, tokens, nonces and emails are redacted even when enabled.
 * For messages that carry no data, call error_log() directly.
 */
class DebugLog
{
    /**
     * Keys whose values are never written to the log, at any nesting depth.
     */
    const REDACTED_KEYS = [
        'password',
        'user_password',
        'new_password',
        'current_password',
        'token',
        'refresh_token',
        'nonce',
        'fresh_nonce',
        'email',
        'user_email',
    ];

    /**
     * Whether payloads may be logged.
     *
     * @return bool
     */
    public static function enabled()
    {
        $enabled = defined('HOSPITAL_MANAGER_LOG_PAYLOADS') && HOSPITAL_MANAGER_LOG_PAYLOADS;

        return (bool) apply_filters('hospital_manager_log_api_payloads', $enabled);
    }

    /**
     * Log "$message: <redacted JSON of $data>" — only when payload logging is enabled.
     *
     * @param string $message
     * @param mixed  $data Arrays, objects or models
     * @return bool Whether anything was logged
     */
    public static function payload($message, $data)
    {
        if (!self::enabled()) {
            return false;
        }

        error_log($message . ': ' . wp_json_encode(self::redact(self::normalize($data))));

        return true;
    }

    /**
     * Log a plain diagnostic line (IDs, filters, counts) only when debug logging
     * is enabled. Use for per-request tracing that shouldn't fill production logs.
     *
     * @param string $message
     * @return bool Whether anything was logged
     */
    public static function line($message)
    {
        if (!self::enabled()) {
            return false;
        }
        error_log($message);
        return true;
    }

    /**
     * Convert models/objects to arrays so they can be redacted and encoded.
     * (Models keep their data in protected attributes; json_encode() gives "{}".)
     *
     * @param mixed $data
     * @return mixed
     */
    public static function normalize($data)
    {
        if ($data instanceof \HospitalManager\Models\BaseModel) {
            $data = $data->getAttributes();
        }

        if (is_array($data) || is_object($data)) {
            return json_decode(wp_json_encode($data), true);
        }

        return $data;
    }

    /**
     * Recursively replace sensitive values with "[REDACTED]".
     *
     * @param mixed $data
     * @return mixed
     */
    public static function redact($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }
}

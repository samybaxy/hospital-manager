<?php

class CreateHospitalTables
{
    public static function up()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Patients table
        $sql_patients = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_patients (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            hmo_id bigint(20),
            hmo_designated_id varchar(100) UNIQUE,
            phone_number varchar(20),
            age int,
            sex ENUM('Male', 'Female', 'Other'),
            bio_data JSON,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY hmo_id (hmo_id)
        ) $charset_collate;";

        // Doctors table
        $sql_doctors = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_doctors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            phone_number varchar(20),
            photo bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // HMOs table
        $sql_hmos = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_hmos (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Visitations table
        $sql_visitations = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_visitations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            patient_id bigint(20) NOT NULL,
            doctor_id bigint(20) NOT NULL,
            date datetime NOT NULL,
            medical_history text,
            diagnosis text,
            treatment text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id)
        ) $charset_collate;";

        // Lab Investigations table
        $sql_lab_investigations = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_lab_investigations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visitation_id bigint(20) NOT NULL,
            lab_tech_id bigint(20) NOT NULL,
            results text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY visitation_id (visitation_id),
            KEY lab_tech_id (lab_tech_id)
        ) $charset_collate;";

        // Radiological Exams table
        $sql_radiological_exams = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_radiological_exams (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visitation_id bigint(20) NOT NULL,
            tech_id bigint(20) NOT NULL,
            results text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY visitation_id (visitation_id),
            KEY tech_id (tech_id)
        ) $charset_collate;";

        // Audit Logs table
        $sql_audit_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_audit_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) NOT NULL,
            changes JSON,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY entity_type_id (entity_type, entity_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_patients);
        dbDelta($sql_doctors);
        dbDelta($sql_hmos);
        dbDelta($sql_visitations);
        dbDelta($sql_lab_investigations);
        dbDelta($sql_radiological_exams);
        dbDelta($sql_audit_logs);
    }

    public static function down()
    {
        global $wpdb;
        
        $tables = [
            'hm_radiological_exams',
            'hm_lab_investigations',
            'hm_visitations',
            'hm_hmos',
            'hm_doctors',
            'hm_patients'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
        }
    }
}
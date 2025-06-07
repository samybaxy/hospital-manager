<?php

class CreateHospitalTables
{
    public static function up()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Patients table
        $sql_patients = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_patients (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            hmo_id bigint(20),
            hmo_designated_id varchar(100) UNIQUE,
            phone varchar(20),
            age int,
            gender ENUM('Male', 'Female'),
            marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Separated'),
            address text,
            city varchar(100),
            `state` varchar(100),
            bio_data JSON,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY user_id (user_id),
            KEY hmo_id (hmo_id)
        ) $charset_collate;";

        // Doctors table
        $sql_doctors = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_doctors (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            phone varchar(20),
            specialty varchar(100),
            status varchar(20) DEFAULT 'active',
            office varchar(50),
            board_certification varchar(255),
            education varchar(255),
            years_experience int,
            license_number varchar(100),
            appointment_availability JSON,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY user_id (user_id)
        ) $charset_collate;";

        // HMOs table
        $sql_hmos = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_hmos (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID)
        ) $charset_collate;";

        // Visitations table
        $sql_visitations = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_visitations (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            patient_id bigint(20) NOT NULL,
            doctor_id bigint(20) NOT NULL,
            appointment_id bigint(20) DEFAULT NULL,
            date date NOT NULL,
            time time NOT NULL,
            medical_history text,
            diagnosis text,
            treatment text,
            complaint text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY appointment_id (appointment_id)
        ) $charset_collate;";

        // Lab Investigations table
        $sql_lab_investigations = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_lab_investigations (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            visitation_id bigint(20) NOT NULL,
            patient_id bigint(20) NOT NULL,
            doctor_id bigint(20) NOT NULL,
            lab_tech_id bigint(20) NOT NULL,
            test_type varchar(100) NOT NULL,
            sample_type varchar(100) NULL,
            request_notes text NULL,
            lab_notes text NULL,
            test_results JSON NULL,
            flags JSON NULL,
            is_abnormal tinyint(1) DEFAULT 0,
            is_critical tinyint(1) DEFAULT 0,
            status ENUM('requested', 'sample_collected', 'in_progress', 'completed', 'verified', 'cancelled') NOT NULL DEFAULT 'requested',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY visitation_id (visitation_id),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY lab_tech_id (lab_tech_id),
            KEY status (status),
            KEY is_critical (is_critical),
            KEY is_abnormal (is_abnormal)
        ) $charset_collate;";

        // Laboratory Categories table
        $sql_lab_categories = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_lab_categories (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            display_order int(11) DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            UNIQUE KEY name (name)
        ) $charset_collate;";

        // Test Definitions table - FIXED foreign key reference
        $sql_lab_test_definitions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_lab_test_definitions (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            category_id bigint(20) NOT NULL,
            code varchar(50) NOT NULL,
            name varchar(100) NOT NULL,
            description text,
            sample_type varchar(100),
            container varchar(100),
            sample_volume varchar(50),
            turnaround_time varchar(100),
            test_parameters JSON NOT NULL,
            specimen_requirements text,
            preparation_instructions text,
            methodology varchar(255),
            cost decimal(10,2),
            is_panel tinyint(1) DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            UNIQUE KEY code (code),
            KEY category_id (category_id),
            KEY name (name),
            KEY is_panel (is_panel),
            KEY status (status),
            CONSTRAINT fk_category FOREIGN KEY (category_id) REFERENCES {$wpdb->prefix}hm_lab_categories(ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Radiological Exams table
        $sql_radiological_exams = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_radiological_exams (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            visitation_id bigint(20) NOT NULL,
            tech_id bigint(20) NOT NULL,
            results text,
            exam_type varchar(30) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY visitation_id (visitation_id),
            KEY tech_id (tech_id)
        ) $charset_collate;";

        // Audit Logs table
        $sql_audit_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_audit_logs (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) NOT NULL,
            changes JSON,
            details JSON,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY user_id (user_id),
            KEY entity_type_id (entity_type, entity_id)
        ) $charset_collate;";

        // Notifications table
        $sql_notifications = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_notifications (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            reference_id bigint(20) DEFAULT NULL,
            `read` tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (ID),
            KEY user_id (user_id),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Appointments table
        $sql_appointments = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_appointments (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            patient_id bigint(20) NOT NULL,
            doctor_id bigint(20) NOT NULL,
            appointment_date date NOT NULL,
            appointment_time time NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            reason text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (ID),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY appointment_date (appointment_date),
            KEY status (status)
        ) $charset_collate;";

        // Create chats table first
        $sql_chats = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_chats (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            doctor_id bigint(20) NOT NULL,
            patient_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            last_message_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (ID),
            UNIQUE KEY doctor_patient (doctor_id, patient_id),
            KEY doctor_id (doctor_id),
            KEY patient_id (patient_id)
        ) $charset_collate;";

        // Create messages table
        $sql_messages = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_chat_messages (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            chat_id bigint(20) NOT NULL,
            sender_id bigint(20) NOT NULL,
            receiver_id bigint(20) NOT NULL,
            message text NOT NULL,
            `read` tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (ID),
            KEY chat_id (chat_id),
            KEY sender_id (sender_id),
            KEY receiver_id (receiver_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Medical Reports table
        $sql_medical_reports = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_medical_reports (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            patient_id bigint(20) NOT NULL,
            doctor_id bigint(20) NOT NULL,
            visitation_id bigint(20) NOT NULL,
            report_content text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (ID),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY visitation_id (visitation_id)
        ) $charset_collate;";

        // Inventory table
        $sql_inventory = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_inventory (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            item_name varchar(255) NOT NULL,
            category varchar(100) NOT NULL DEFAULT 'Supplies',
            quantity int NOT NULL DEFAULT 0,
            unit varchar(50) NOT NULL DEFAULT 'units',
            reorder_level int NOT NULL DEFAULT 10,
            expiry_date date NULL,
            location varchar(255) NULL,
            cost decimal(10,2) NULL,
            status varchar(20) NOT NULL DEFAULT 'In Stock',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY category (category),
            KEY status (status),
            KEY expiry_date (expiry_date),
            KEY location (location),
            INDEX idx_quantity_reorder (quantity, reorder_level)
        ) $charset_collate;";

        // Inventory transactions table for tracking movements and changes
        $sql_inventory_transactions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_inventory_transactions (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            inventory_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            transaction_type ENUM('stock_in', 'stock_out', 'adjustment', 'transfer', 'expired', 'damaged', 'returned') NOT NULL,
            quantity_changed int NOT NULL,
            previous_quantity int NOT NULL,
            new_quantity int NOT NULL,
            reference_number varchar(100) NULL,
            notes text NULL,
            location_from varchar(255) NULL,
            location_to varchar(255) NULL,
            supplier_id bigint(20) NULL,
            batch_number varchar(100) NULL,
            expiry_date date NULL,
            cost_per_unit decimal(10,2) NULL,
            total_cost decimal(10,2) NULL,
            status varchar(20) NOT NULL DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY inventory_id (inventory_id),
            KEY user_id (user_id),
            KEY transaction_type (transaction_type),
            KEY reference_number (reference_number),
            KEY created_at (created_at),
            INDEX idx_inventory_date (inventory_id, created_at)
        ) $charset_collate;";

        // Inventory alerts table for system notifications
        $sql_inventory_alerts = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_inventory_alerts (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            inventory_id bigint(20) NOT NULL,
            alert_type ENUM('low_stock', 'out_of_stock', 'expired', 'expiring_soon', 'critical_level', 'reorder_point') NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
            title varchar(255) NOT NULL,
            message text NOT NULL,
            threshold_value int NULL,
            current_value int NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            acknowledged_at datetime NULL,
            acknowledged_by bigint(20) NULL,
            resolved_at datetime NULL,
            resolved_by bigint(20) NULL,
            next_check_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY inventory_id (inventory_id),
            KEY alert_type (alert_type),
            KEY severity (severity),
            KEY is_active (is_active),
            KEY acknowledged_by (acknowledged_by),
            KEY resolved_by (resolved_by),
            KEY created_at (created_at),
            INDEX idx_active_alerts (is_active, alert_type, severity)
        ) $charset_collate;";

        // Inventory suppliers table for supplier management
        $sql_inventory_suppliers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_inventory_suppliers (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            contact_person varchar(255) NULL,
            email varchar(255) NULL,
            phone varchar(20) NULL,
            address text NULL,
            city varchar(100) NULL,
            state varchar(100) NULL,
            country varchar(100) NULL,
            postal_code varchar(20) NULL,
            tax_id varchar(100) NULL,
            payment_terms varchar(255) NULL,
            delivery_time_days int NULL,
            minimum_order_amount decimal(10,2) NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            notes text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY name (name),
            KEY is_active (is_active),
            KEY email (email)
        ) $charset_collate;";

        // Inventory reorder suggestions table
        $sql_inventory_reorders = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_inventory_reorders (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            inventory_id bigint(20) NOT NULL,
            supplier_id bigint(20) NULL,
            suggested_quantity int NOT NULL,
            current_quantity int NOT NULL,
            reorder_level int NOT NULL,
            max_stock_level int NULL,
            priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
            status ENUM('pending', 'approved', 'ordered', 'received', 'cancelled') NOT NULL DEFAULT 'pending',
            estimated_cost decimal(10,2) NULL,
            order_reference varchar(100) NULL,
            expected_delivery_date date NULL,
            notes text NULL,
            created_by bigint(20) NOT NULL,
            approved_by bigint(20) NULL,
            approved_at datetime NULL,
            ordered_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY inventory_id (inventory_id),
            KEY supplier_id (supplier_id),
            KEY status (status),
            KEY priority (priority),
            KEY created_by (created_by),
            KEY approved_by (approved_by),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_patients);
        dbDelta($sql_doctors);
        dbDelta($sql_hmos);
        dbDelta($sql_visitations);
        dbDelta($sql_lab_categories);
        dbDelta($sql_lab_test_definitions);
        dbDelta($sql_lab_investigations);
        dbDelta($sql_radiological_exams);
        dbDelta($sql_audit_logs);
        dbDelta($sql_notifications);
        dbDelta($sql_appointments);
        dbDelta($sql_chats);
        dbDelta($sql_messages);
        dbDelta($sql_medical_reports);
        dbDelta($sql_inventory);
        dbDelta($sql_inventory_transactions);
        dbDelta($sql_inventory_alerts);
        dbDelta($sql_inventory_suppliers);
        dbDelta($sql_inventory_reorders);
    }

    public static function down()
    {
        global $wpdb;
        
        // Drop tables in correct order to handle foreign key constraints
        // Child tables first, then parent tables
        $tables = [
            'hm_inventory_reorders',
            'hm_inventory_alerts',
            'hm_inventory_transactions',
            'hm_inventory_suppliers',
            'hm_inventory',
            'hm_chat_messages',
            'hm_chats',
            'hm_medical_reports',
            'hm_radiological_exams',
            'hm_lab_investigations',
            'hm_lab_test_definitions',  // Drop this before hm_lab_categories due to foreign key
            'hm_lab_categories',        // Drop this after hm_lab_test_definitions
            'hm_visitations',
            'hm_appointments',
            'hm_notifications',
            'hm_audit_logs',
            'hm_patients',
            'hm_doctors',
            'hm_hmos'
        ];

        // Disable foreign key checks to avoid constraint issues
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            $result = $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
            if ($result === false) {
                error_log("Hospital Manager: Failed to drop table {$wpdb->prefix}$table: " . $wpdb->last_error);
            }
        }
        
        // Re-enable foreign key checks
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
    }
}
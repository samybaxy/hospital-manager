# Fix wpdb references in all seeder files

# Search and replace patterns to fix common wpdb issues:
# Replace $this->wpdb with global $wpdb usage
# Replace faker usage with basic PHP functions where needed

# Files to check:
# - PatientSeeder.php  
# - DoctorSeeder.php
# - VisitationSeeder.php
# - LabInvestigationSeeder.php
# - RadiologicalExamSeeder.php
# - AuditLogSeeder.php
# - NotificationSeeder.php
# - ChatSeeder.php
# - MedicalReportSeeder.php

# The main issues are:
# 1. Using $this->wpdb instead of global $wpdb
# 2. Using $this->faker when faker might be null
# 3. Missing proper error handling for database operations
# Hospital Manager Data Seeder System

This document provides instructions for using the Hospital Manager data seeder system to populate your database with test data.

## Overview

The Hospital Manager plugin includes a robust data seeding system that can generate realistic test data for all entities in the system including:

- Users with appropriate roles
- Patients and their medical records
- Doctors and their specialties
- Appointments (past and future)
- Patient visitations with diagnoses and treatments
- Lab investigation records
- Medical reports
- System notifications
- Chat conversations between doctors and patients
- Audit log records

## Prerequisites

Before running the seeders, ensure that you have:

1. Activated the Hospital Manager plugin
2. Set up your WordPress database
3. Installed all required dependencies via Composer

## Running the Seeders

You have three options for running the seeders:

### Option 1: Using Composer

```bash
# Run all seeders
cd /path/to/wp-content/plugins/hospital-manager
composer seed

# Run specific seeders
composer seed:roles
composer seed:users
composer seed:patients
# etc.

# Reset/clear all seeded data
composer seed:reset       # Using the seeder system
composer db:reset         # Alternative reliable reset method
```

### Option 2: Using PHP directly

```bash
# Run all seeders
cd /path/to/wp-content/plugins/hospital-manager
php database/run-seeders.php all

# Run specific seeders
php database/run-seeders.php roles
php database/run-seeders.php users
# etc.

# Show help
php database/run-seeders.php help
```

### Option 3: Using WP-CLI

If you have WP-CLI installed:

```bash
# Run all seeders
wp hospital-manager seed all

# Run specific seeders
wp hospital-manager seed roles
wp hospital-manager seed users
# etc.

# See available seeders
wp hospital-manager seed:list

# Clean database before seeding
wp hospital-manager seed all --fresh
```

## Seeder Ordering and Dependencies

When running the seeders, they should generally be executed in the following order:

1. `RoleSeeder` - Creates roles and permissions
2. `UserSeeder` - Creates WordPress users with appropriate roles
3. `HMOSeeder` - Creates health management organizations
4. `PatientSeeder` - Creates patient records linked to WordPress users
5. `DoctorSeeder` - Creates doctor records linked to WordPress users
6. `AppointmentSeeder` - Creates appointments between patients and doctors
7. `VisitationSeeder` - Creates patient visitation records
8. `LabInvestigationSeeder` - Creates lab test records
9. `MedicalReportSeeder` - Creates medical reports
10. `NotificationSeeder` - Creates system notifications
11. `ChatSeeder` - Creates chat conversations
12. `AuditLogSeeder` - Creates audit log records

When you run `DatabaseSeeder` or use the `all` option, the seeders will be executed in this order automatically.

## Demo Users

The seeders create the following demo users with known credentials:

| Role         | Username      | Password  | Email                    |
|-------------|---------------|-----------|--------------------------|
| Administrator| admin_demo    | password | admin_demo@example.com   |
| Doctor      | doctor_demo   | password | doctor_demo@example.com  |
| Patient     | patient_demo  | password | patient_demo@example.com |
| Receptionist| reception_demo| password | reception_demo@example.com |
| Lab Tech    | lab_demo      | password | lab_demo@example.com     |
| Desk Officer| desk_demo     | password | desk_demo@example.com    |

## Customizing the Seeders

Each seeder can be customized by editing the corresponding file in the `database/seeders` directory. You can adjust:

- The number of records to create
- The specific data being generated
- The probability distributions for various data fields

## Resetting Seeded Data

If you need to remove all data created by the seeders, you can use the following commands:

### Using Composer

```bash
# Reset all seeded data using the seeder system
composer seed:reset

# Alternative reset method (more reliable)
composer db:reset
```

### Using PHP directly

```bash
# Reset all seeded data
php database/reset-data.php
```

### Using WP-CLI

```bash
# Reset all seeded data
wp hospital-manager seed:reset
```

The reset process will:
- Truncate all Hospital Manager database tables
- Remove all users created by the seeders
- Clear plugin-related user meta data

This is useful for:
- Starting with a clean slate before re-seeding
- Cleaning up test data before deployment
- Resolving data issues caused by incomplete seeders

## Troubleshooting

If you encounter issues running the seeders:

1. **Database Connection Issues**: Ensure your WordPress database connection is working properly.
2. **Dependency Issues**: Make sure you've run the seeders in the correct order.
3. **Permission Issues**: Check that your web server has appropriate permissions.
4. **Memory Limits**: For large datasets, you might need to increase PHP memory limits.

If you're seeing PHP errors, check the WordPress debug.log file for details.

## Contributing

To extend or improve the seeder system:

1. Create new seeder classes in the `database/seeders` directory
2. Update `DatabaseSeeder` to include your new seeders
3. Add appropriate composer scripts in composer.json
4. Update documentation to include your new seeders

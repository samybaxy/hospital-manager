# Hospital Manager Database Seeders

This component populates the Hospital Manager WordPress plugin database with fake data for testing purposes. It creates a comprehensive set of test data for all entities in the system including users, patients, doctors, appointments, and medical records.

## Available Seeders

- **RoleSeeder**: Initialize user roles and capabilities
- **UserSeeder**: Generate WordPress users with appropriate roles (including demo users)
- **HMOSeeder**: Populate health management organizations
- **PatientSeeder**: Generate patient records linked to WordPress users
- **DoctorSeeder**: Generate doctor records linked to WordPress users
- **AppointmentSeeder**: Generate past and future appointments
- **VisitationSeeder**: Generate patient visit records
- **LabInvestigationSeeder**: Generate lab test records
- **MedicalReportSeeder**: Generate medical reports
- **NotificationSeeder**: Generate system notifications
- **ChatSeeder**: Generate chat conversations between doctors and patients
- **AuditLogSeeder**: Generate audit trail records

## Usage

### Using Composer

You can run the seeders using Composer commands:

```bash
# Run all seeders
composer seed

# Run specific seeders
composer seed:roles
composer seed:users
composer seed:patients
composer seed:doctors
composer seed:appointments
composer seed:visitations
composer seed:lab-investigations
composer seed:medical-reports
composer seed:notifications
composer seed:chats
composer seed:audit-logs
composer seed:reset      # Clear all seeded data
```

### Using PHP directly

You can also run the seeders using the PHP script:

```bash
# Run all seeders
php database/seeders.php all

# Run specific seeders
php database/seeders.php roles
php database/seeders.php users
# etc.

# Show help
php database/seeders.php help
```

### Using WP-CLI

If you have WP-CLI installed, you can use it to run the seeders:

```bash
# Run all seeders
wp hospital-manager seed all

# Run specific seeders
wp hospital-manager seed roles
wp hospital-manager seed users
# etc.

# Show available seeders
wp hospital-manager seed:list

# Truncate tables before seeding
wp hospital-manager seed all --fresh
```

## Demo Users

The seeders create one demo user per role, all with the password `demo1234`:

| Role              | Username                 | Email                         |
|-------------------|--------------------------|-------------------------------|
| Administrator     | demo_administrator       | administrator@example.com     |
| Doctor            | demo_doctor              | doctor@example.com            |
| Patient           | demo_patient             | patient@example.com           |
| Lab Technician    | demo_lab_tech            | lab_tech@example.com          |
| Nurse             | demo_hospital_nurse      | hospital_nurse@example.com    |
| Hospital Staff    | demo_hospital_staff      | hospital_staff@example.com    |
| Pharmacy Staff    | demo_pharmacy_staff      | pharmacy_staff@example.com    |
| Inventory Manager | demo_inventory_manager   | inventory_manager@example.com |
| Developer         | demo_developer           | developer@example.com         |

`DemoDataSeeder` links `demo_doctor` and `demo_patient` (shared appointments, visits,
labs, reports and a chat) so both dashboards have data. All other seeded users use the
password `hospital123`.

## Rebuilding Clinical Data

To regenerate appointments, visits, labs, radiology, reports, chats, notifications and
audit logs for the **existing** users/patients/doctors (logins and record IDs stay the
same):

```bash
php database/seeders.php clinical      # or: composer seed:clinical
```

This empties those tables first (anything entered through the app in them is lost) and
clears the plugin's query caches. If there's no system `php`, use `bin/local-php.sh`
instead of `php` (see the main README).

## Data Volume

By default, the seeders create:

- 1 demo user for each role
- 5-10 additional users per role
- 15-20 HMOs
- 30-50 patients
- 10-15 doctors
- 50-100 appointments
- 30-50 visitations
- 30-50 lab investigations
- 20-30 medical reports
- 100+ notifications
- 20-30 chat conversations with messages
- 200+ audit log entries

You can modify these numbers by editing the respective seeder files.

## Clearing Seeded Data

If you need to remove all the seeded data from your database, you can use the reset command:

```bash
# Using Composer
composer seed:reset

# Using direct PHP script
php database/reset-data.php

# Using Composer alternative (more reliable)
composer db:reset
```

The reset process will:
- Truncate all Hospital Manager database tables
- Remove all users created by the seeders
- Clear plugin-related user meta data

This is useful when you want to start with a clean slate before re-seeding the database.

# Suggested Seeder Execution Order

For proper database seeding with dependencies, run the seeders in this order:

1. **UserSeeder**
   - Creates users with appropriate roles
   - Must run first since all other records depend on users

2. **HMOSeeder**
   - Creates health management organizations
   - Required for patient registration

3. **PatientSeeder** 
   - Creates patient records for users with 'patient' role
   - Depends on HMO records for some patients

4. **DoctorSeeder**
   - Creates doctor records for users with 'doctor' role
   - Sets up specialties, availability, etc.

5. **InventorySuppliersSeeder**
   - Creates inventory supplier records
   - Required before creating inventory items

6. **InventorySeeder**
   - Creates inventory items
   - Required before transactions

7. **InventoryTransactionsSeeder** 
   - Creates inventory transaction history
   - Depends on inventory items and suppliers

8. **InventoryAlertsSeeder**
   - Creates inventory alerts
   - Depends on inventory items

9. **InventoryReordersSeeder**
   - Creates inventory reorder requests
   - Depends on inventory items and suppliers

10. **AppointmentSeeder**
    - Creates appointments between patients and doctors
    - Depends on patient and doctor records

11. **VisitationSeeder**
    - Creates patient visitation records
    - Depends on patient and doctor records

12. **LabInvestigationSeeder**
    - Creates laboratory investigation records
    - Depends on patient and doctor records

13. **RadiologicalExamSeeder**
    - Creates radiological examination records
    - Depends on patient and doctor records

14. **NotificationSeeder**
    - Creates notification records
    - Depends on user records

15. **ChatSeeder**
    - Creates chat messages between users
    - Depends on user records

16. **MedicalReportSeeder**
    - Creates medical reports
    - Depends on patient and doctor records

17. **AuditLogSeeder**
    - Creates audit log entries for various actions
    - Depends on all other records

# Hospital Manager API Endpoints Documentation

This document provides a comprehensive list of all REST API endpoints available in the Hospital Manager WordPress plugin.

**Base URL**: `/wp-json/hospital-manager/v1`

## Authentication Endpoints

### GET `/auth/me`
- **Description**: Get current authenticated user information
- **Permission**: Public (handles authentication internally)
- **Methods**: GET
- **Returns**: User data, authentication status, and fresh nonce

### GET `/auth/debug`
- **Description**: Debug authentication for troubleshooting
- **Permission**: Public
- **Methods**: GET
- **Returns**: Authentication debug information

### POST `/auth/login`
- **Description**: Authenticate user login
- **Permission**: Public
- **Methods**: POST
- **Parameters**:
  - `username` (required): User login name or email address
  - `password` (required): User password  
  - `remember` (optional): Whether to remember the user session
- **Returns**: Authentication result and user data

### POST `/auth/logout`
- **Description**: Log out current user
- **Permission**: Authenticated users
- **Methods**: POST
- **Returns**: Success status

### POST `/auth/reset-password`
- **Description**: Request password reset
- **Permission**: Unauthenticated users only
- **Methods**: POST
- **Parameters**:
  - `email` (required): User email address
- **Returns**: Reset request confirmation

### POST `/auth/reset-password/confirm`
- **Description**: Confirm password reset with token
- **Permission**: Unauthenticated users only
- **Methods**: POST
- **Parameters**:
  - `token` (required): Reset token
  - `new_password` (required): New password
- **Returns**: Password reset confirmation

### POST `/auth/refresh`
- **Description**: Refresh authentication token
- **Permission**: Public (validates internally)
- **Methods**: POST
- **Returns**: Refreshed token

## Patient Management Endpoints

### GET `/patients`
- **Description**: Get list of all patients
- **Permission**: `view_patients`
- **Methods**: GET
- **Parameters**: Pagination and filtering options
- **Returns**: List of patients

### POST `/patients`
- **Description**: Create new patient
- **Permission**: `manage_patient_records`
- **Methods**: POST
- **Parameters**: Patient data
- **Returns**: Created patient data

### GET `/patients/{ID}`
- **Description**: Get specific patient by ID
- **Permission**: `view_patients`
- **Methods**: GET
- **Returns**: Patient data

### PUT `/patients/{ID}`
- **Description**: Update patient information
- **Permission**: `manage_patient_records`
- **Methods**: PUT
- **Parameters**: Updated patient data
- **Returns**: Updated patient data

### DELETE `/patients/{ID}`
- **Description**: Delete patient record
- **Permission**: `manage_patient_records`
- **Methods**: DELETE
- **Returns**: Deletion confirmation

### GET `/patients/search`
- **Description**: Search patients by criteria
- **Permission**: `view_patients`
- **Methods**: GET
- **Parameters**: Search criteria
- **Returns**: Matching patients

### GET `/patients/me`
- **Description**: Get current user's patient record
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: User's patient data

### GET `/patients/{ID}/visitations`
- **Description**: Get patient's visitation history
- **Permission**: `view_patients`
- **Methods**: GET
- **Returns**: List of patient visitations

### GET `/patient/profile`
- **Description**: Get current patient's profile (deprecated - use `/profile` instead)
- **Permission**: Patient role
- **Methods**: GET
- **Returns**: Patient profile data

### PUT `/patient/profile`
- **Description**: Update current patient's profile (deprecated - use `/profile` instead)
- **Permission**: Patient role
- **Methods**: PUT
- **Returns**: Updated profile data

## Doctor Management Endpoints

### GET `/doctors`
- **Description**: Get list of all doctors
- **Permission**: Public
- **Methods**: GET
- **Returns**: List of doctors

### POST `/doctors`
- **Description**: Create new doctor
- **Permission**: Doctor management permission
- **Methods**: POST
- **Parameters**: Doctor data
- **Returns**: Created doctor data

### GET `/doctors/specialties`
- **Description**: Get list of doctor specialties
- **Permission**: Public
- **Methods**: GET
- **Returns**: List of medical specialties

### GET `/doctors/{ID}`
- **Description**: Get specific doctor by ID
- **Permission**: Public
- **Methods**: GET
- **Returns**: Doctor data

### PUT `/doctors/{ID}`
- **Description**: Update doctor information
- **Permission**: Doctor management permission
- **Methods**: PUT
- **Returns**: Updated doctor data

### DELETE `/doctors/{ID}`
- **Description**: Delete doctor record
- **Permission**: Doctor management permission
- **Methods**: DELETE
- **Returns**: Deletion confirmation

### GET `/doctors/{ID}/patients`
- **Description**: Get patients assigned to specific doctor
- **Permission**: Public
- **Methods**: GET
- **Returns**: List of doctor's patients

### GET `/doctor/profile`
- **Description**: Get current doctor's profile (deprecated - use `/profile` instead)
- **Permission**: Doctor role
- **Methods**: GET
- **Returns**: Doctor profile data

### PUT `/doctor/profile`
- **Description**: Update current doctor's profile (deprecated - use `/profile` instead)
- **Permission**: Doctor role
- **Methods**: PUT
- **Returns**: Updated profile data

## Appointment Management Endpoints

### GET `/appointments`
- **Description**: Get list of appointments
- **Permission**: Authenticated users
- **Methods**: GET
- **Parameters**: Filtering options (doctor_id, patient_id, date range)
- **Returns**: List of appointments

### POST `/appointments`
- **Description**: Create new appointment
- **Permission**: Authenticated users
- **Methods**: POST
- **Parameters**: Appointment data
- **Returns**: Created appointment data

### GET `/appointments/book/{ID}`
- **Description**: Get booking data for specific doctor
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: Doctor availability and booking information

### GET `/appointments/availability`
- **Description**: Get doctor availability for appointments
- **Permission**: Authenticated users
- **Methods**: GET
- **Parameters**: doctor_id, date range
- **Returns**: Available time slots

### GET `/appointments/stats`
- **Description**: Get appointment statistics
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: Appointment statistics and metrics

### GET `/appointments/{ID}`
- **Description**: Get specific appointment by ID
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: Appointment data

### PUT `/appointments/{ID}`
- **Description**: Update appointment information
- **Permission**: Authenticated users
- **Methods**: PUT
- **Parameters**: Updated appointment data
- **Returns**: Updated appointment data

## Visitation Management Endpoints

### GET `/visitations`
- **Description**: Get list of patient visitations
- **Permission**: `view_patient_records`
- **Methods**: GET
- **Parameters**: Filtering options
- **Returns**: List of visitations

### POST `/visitations`
- **Description**: Create new patient visitation
- **Permission**: `add_visitation`
- **Methods**: POST
- **Parameters**: Visitation data
- **Returns**: Created visitation data

### GET `/visitations/{id}`
- **Description**: Get specific visitation by ID
- **Permission**: `view_patient_records`
- **Methods**: GET
- **Returns**: Visitation data

### PUT `/visitations/{id}`
- **Description**: Update visitation information
- **Permission**: `edit_visitation`
- **Methods**: PUT
- **Returns**: Updated visitation data

### DELETE `/visitations/{id}`
- **Description**: Delete visitation record
- **Permission**: `delete_visitation`
- **Methods**: DELETE
- **Returns**: Deletion confirmation

## Lab Investigation Endpoints

### GET `/lab-investigations`
- **Description**: Get list of lab investigations
- **Permission**: `view_patient_records`
- **Methods**: GET
- **Parameters**: patient_id, lab_tech_id, doctor_id, status, date range
- **Returns**: List of lab investigations

### POST `/lab-investigations`
- **Description**: Create new lab investigation
- **Permission**: `manage_lab_investigations`
- **Methods**: POST
- **Parameters**: Investigation data
- **Returns**: Created investigation data

### GET `/lab-investigations/{ID}`
- **Description**: Get specific lab investigation
- **Permission**: `view_patient_records`
- **Methods**: GET
- **Returns**: Investigation data

### PUT `/lab-investigations/{ID}`
- **Description**: Update lab investigation
- **Permission**: `update_lab_results`
- **Methods**: PUT
- **Returns**: Updated investigation data

### DELETE `/lab-investigations/{ID}`
- **Description**: Delete lab investigation
- **Permission**: `manage_lab_investigations`
- **Methods**: DELETE
- **Returns**: Deletion confirmation

### PUT `/lab-investigations/{ID}/results`
- **Description**: Update lab investigation results
- **Permission**: `update_lab_results`
- **Methods**: PUT
- **Parameters**: Test results data
- **Returns**: Updated results

### GET `/lab-investigations/pending`
- **Description**: Get pending lab investigations
- **Permission**: `view_patient_records`
- **Methods**: GET
- **Returns**: List of pending investigations

### GET `/lab-categories`
- **Description**: Get lab test categories
- **Permission**: `view_patient_records`
- **Methods**: GET
- **Returns**: List of lab categories

### GET `/lab-test-definitions`
- **Description**: Get lab test definitions
- **Permission**: `view_patient_records`
- **Methods**: GET
- **Returns**: List of test definitions

## Chat & Communication Endpoints

### GET `/chats`
- **Description**: Get user's chat conversations
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: List of chat conversations

### GET `/chats/{ID}/messages`
- **Description**: Get messages from specific chat
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: Chat messages

### POST `/chats/{ID}/messages`
- **Description**: Send message to chat
- **Permission**: Authenticated users
- **Methods**: POST
- **Parameters**: Message content
- **Returns**: Sent message data

### POST `/chats/start`
- **Description**: Start new chat conversation
- **Permission**: Patient role
- **Methods**: POST
- **Parameters**: Chat participants
- **Returns**: New chat data

### PUT `/chats/{ID}/read`
- **Description**: Mark chat messages as read
- **Permission**: Authenticated users
- **Methods**: PUT
- **Returns**: Update confirmation

## Notification Endpoints

### GET `/notifications`
- **Description**: Get user notifications
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: List of notifications

### GET `/notifications/unread`
- **Description**: Get count of unread notifications
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: Unread notification count

### PUT `/notifications/{ID}/read`
- **Description**: Mark notification as read
- **Permission**: Authenticated users
- **Methods**: PUT
- **Returns**: Update confirmation

## Dashboard & Statistics Endpoints

### GET `/dashboard`
- **Description**: Get dashboard data based on user role
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: Role-specific dashboard data

### GET `/dashboard/stats`
- **Description**: Get dashboard statistics
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: Statistical data

### GET `/stats`
- **Description**: Get hospital statistics (admin only)
- **Permission**: Administrator role
- **Methods**: GET
- **Returns**: Hospital-wide statistics

## Inventory Management Endpoints

### GET `/inventory`
- **Description**: Get inventory items
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Parameters**: Filtering and pagination options
- **Returns**: List of inventory items

### POST `/inventory`
- **Description**: Create new inventory item
- **Permission**: `canEditInventory`
- **Methods**: POST
- **Parameters**: Item data
- **Returns**: Created item data

### GET `/inventory/{ID}`
- **Description**: Get specific inventory item
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Returns**: Item data

### PUT `/inventory/{ID}`
- **Description**: Update inventory item
- **Permission**: `canEditInventory`
- **Methods**: PUT
- **Returns**: Updated item data

### DELETE `/inventory/{ID}`
- **Description**: Delete inventory item
- **Permission**: `canDeleteInventory`
- **Methods**: DELETE
- **Returns**: Deletion confirmation

### GET `/inventory/critical`
- **Description**: Get critical inventory items (low stock)
- **Permission**: `canViewCriticalItems`
- **Methods**: GET
- **Returns**: Critical items list

### GET `/inventory/expiring`
- **Description**: Get expiring inventory items
- **Permission**: `canViewCriticalItems`
- **Methods**: GET
- **Parameters**: days (days ahead to check)
- **Returns**: Expiring items list

### GET `/inventory/summary`
- **Description**: Get inventory summary statistics
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Returns**: Inventory summary data

### GET `/inventory/categories`
- **Description**: Get inventory category summary
- **Permission**: `canViewReports`
- **Methods**: GET
- **Returns**: Category summary data

### PUT `/inventory/{ID}/status`
- **Description**: Update inventory item status
- **Permission**: `canEditInventory`
- **Methods**: PUT
- **Returns**: Updated status

### PUT `/inventory/bulk`
- **Description**: Perform bulk operations on inventory items
- **Permission**: `canPerformBulkOperations`
- **Methods**: PUT
- **Parameters**: action, items array
- **Returns**: Bulk operation results

### GET `/inventory/export`
- **Description**: Export inventory data
- **Permission**: `canExportInventory`
- **Methods**: GET
- **Parameters**: format (csv, json)
- **Returns**: Exported data

### GET `/inventory/permissions`
- **Description**: Get user inventory permissions
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: User permission data

### GET `/inventory/transactions`
- **Description**: Get inventory transactions
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Returns**: Transaction history

### POST `/inventory/transactions`
- **Description**: Create inventory transaction
- **Permission**: `canEditInventory`
- **Methods**: POST
- **Parameters**: Transaction data
- **Returns**: Created transaction

### GET `/inventory/alerts`
- **Description**: Get inventory alerts
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Parameters**: type, severity, status, limit
- **Returns**: Alert list

### PUT `/inventory/alerts/{ID}/acknowledge`
- **Description**: Acknowledge inventory alert
- **Permission**: `canEditInventory`
- **Methods**: PUT
- **Returns**: Acknowledgment confirmation

### PUT `/inventory/alerts/{ID}/resolve`
- **Description**: Resolve inventory alert
- **Permission**: `canEditInventory`
- **Methods**: PUT
- **Returns**: Resolution confirmation

### GET `/inventory/suppliers`
- **Description**: Get inventory suppliers
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Parameters**: search, status filters
- **Returns**: Supplier list

### POST `/inventory/suppliers`
- **Description**: Create new supplier
- **Permission**: `canEditInventory`
- **Methods**: POST
- **Parameters**: Supplier data
- **Returns**: Created supplier data

### GET `/inventory/suppliers/{ID}`
- **Description**: Get specific supplier
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Returns**: Supplier data

### PUT `/inventory/suppliers/{ID}`
- **Description**: Update supplier information
- **Permission**: `canEditInventory`
- **Methods**: PUT
- **Returns**: Updated supplier data

### DELETE `/inventory/suppliers/{ID}`
- **Description**: Delete supplier
- **Permission**: `canDeleteInventory`
- **Methods**: DELETE
- **Returns**: Deletion confirmation

### GET `/inventory/reorders`
- **Description**: Get inventory reorders
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Parameters**: status, priority, item_id filters
- **Returns**: Reorder list

### POST `/inventory/reorders`
- **Description**: Create inventory reorder
- **Permission**: `canEditInventory`
- **Methods**: POST
- **Parameters**: Reorder data
- **Returns**: Created reorder data

### PUT `/inventory/reorders/{ID}/approve`
- **Description**: Approve inventory reorder
- **Permission**: `canEditInventory`
- **Methods**: PUT
- **Returns**: Approval confirmation

### PUT `/inventory/reorders/{ID}/complete`
- **Description**: Complete inventory reorder
- **Permission**: `canEditInventory`
- **Methods**: PUT
- **Parameters**: received_quantity, notes
- **Returns**: Completion confirmation

### GET `/inventory/dashboard`
- **Description**: Get inventory dashboard data
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Returns**: Dashboard metrics

### POST `/inventory/alerts/generate`
- **Description**: Generate automatic inventory alerts
- **Permission**: `canEditInventory`
- **Methods**: POST
- **Returns**: Generated alerts

### GET `/inventory/reorders/suggestions`
- **Description**: Get reorder suggestions
- **Permission**: `canViewInventory`
- **Methods**: GET
- **Parameters**: Pagination options
- **Returns**: Reorder suggestions

## Profile Management Endpoints

### GET `/profile`
- **Description**: Get current user's profile (unified endpoint)
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: User profile data

### PUT `/profile`
- **Description**: Update current user's profile (unified endpoint)
- **Permission**: Authenticated users
- **Methods**: PUT
- **Parameters**: Profile data
- **Returns**: Updated profile data

## User Management Endpoints

### POST `/user/change-password`
- **Description**: Change user password
- **Permission**: Authenticated users
- **Methods**: POST
- **Parameters**: current_password, new_password, confirm_password
- **Returns**: Password change confirmation

## HMO Management Endpoints

### GET `/hmos`
- **Description**: Get list of Health Maintenance Organizations
- **Permission**: `view_patients`
- **Methods**: GET
- **Returns**: List of HMOs

## Audit & Logging Endpoints

### GET `/audit-logs`
- **Description**: Get audit logs
- **Permission**: `view_audit_log`
- **Methods**: GET
- **Returns**: Audit log entries

### GET `/audit-logs/patient/{ID}`
- **Description**: Get audit logs for specific patient
- **Permission**: `view_audit_log` or `doctor` role
- **Methods**: GET
- **Returns**: Patient-specific audit logs

## Access Control Endpoints

### GET `/access`
- **Description**: Get user route access permissions
- **Permission**: Authenticated users
- **Methods**: GET
- **Returns**: User's route access permissions

---

## Common Response Format

All endpoints return responses in the following format:

```json
{
  "success": true|false,
  "data": {}, // Response data
  "message": "Response message",
  "errors": [] // Error details if any
}
```

## Authentication

Most endpoints require authentication. Include the following headers:

- `X-WP-Nonce`: WordPress REST API nonce
- `Authorization`: Bearer token (if using JWT)

## Error Codes

Common HTTP status codes used:

- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `500`: Internal Server Error

## Pagination

List endpoints support pagination with these parameters:

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 10)
- `orderby`: Field to order by
- `order`: Sort order (asc/desc)

---

*This documentation is auto-generated from the Hospital Manager plugin source code.*

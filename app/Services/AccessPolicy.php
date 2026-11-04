<?php
/**
 * Role-to-permission map for endpoints that go through
 * BaseController::check_permission() (patients, visitations, lab investigations,
 * HMOs).
 *
 * Why this exists: the capabilities those endpoints check (view_patient_records,
 * manage_lab_investigations, ...) were never granted to any role, so every check
 * failed and a fallback then let *any* hospital role through, including patients,
 * who could list every patient. Access is now decided here, in one place.
 *
 * The map follows the intent declared in RoleService::getRolePermissions():
 *   access_patients      -> who may look patients up
 *   create/edit/delete_patients, add/edit_visitation, add/edit_lab_results
 *   access_visitations / access_lab_dashboard -> who may read clinical records
 *
 * A user is allowed when ANY of these hold:
 *   1. the capability was granted to them individually (a site admin can give one
 *      user extra access without editing code). Capabilities stored on a *role*
 *      are ignored here: roles get their access from MAP only. (The live doctor
 *      role stores edit_visitation, which used to override "own visits only".);
 *   2. one of their roles is listed for the capability below;
 *   3. the capability allows patients their OWN records, they are a patient, and
 *      the request is about their own record (see ownsRequestedRecord()).
 */

namespace HospitalManager\Services;

use HospitalManager\Models\Patient;

class AccessPolicy
{
    /** Patients may use this capability for their own records only. */
    const OWN = 'patient:own';

    /** Doctors may use this capability only for visits where they are the visit's doctor. */
    const DOCTOR_OWN = 'doctor:own';

    /**
     * capability => roles allowed (plus self::OWN where patients get their own records).
     *
     * Note: the "developer" role is the desk officer (RoleService::ROLE_DESK_OFFICER).
     */
    const MAP = [
        // Look patients up: list, search, view, visit history, HMO list for forms
        'view_patients' => [
            'administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff',
            'lab_tech', 'pharmacy_staff', self::OWN,
        ],
        'create_patients' => ['administrator', 'developer', 'doctor', 'hospital_staff'],
        'edit_patients'   => ['administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff'],
        'delete_patients' => ['administrator'],

        // Read clinical records: visitations, lab investigations, lab reference data
        'view_patient_records' => [
            'administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff',
            'lab_tech', self::OWN,
        ],

        // Visitations
        'add_visitation'    => ['administrator', self::DOCTOR_OWN, 'hospital_nurse'], // doctors: recorded under themselves
        'edit_visitation'   => ['administrator', self::DOCTOR_OWN, 'hospital_nurse'], // doctors: their own visits
        'delete_visitation' => ['administrator'],

        // Lab investigations: requesting/cancelling tests vs entering results
        'manage_lab_investigations' => ['administrator', 'doctor'],
        'update_lab_results'        => ['administrator', 'doctor', 'lab_tech'],

        // Appointments (AppointmentController applies the "own" limits: doctors see
        // and update their own appointments, patients book, see and cancel their own)
        'view_appointments'     => ['administrator', 'developer', 'hospital_nurse', 'hospital_staff', self::DOCTOR_OWN, self::OWN],
        'schedule_appointments' => ['administrator', 'developer', 'hospital_nurse', 'hospital_staff', 'doctor', self::OWN],
        'manage_appointments'   => ['administrator', 'developer', 'hospital_nurse', 'hospital_staff', self::DOCTOR_OWN],

        // Hospital-wide dashboard figures (counts, recent activity, everyone's upcoming appointments)
        'view_hospital_stats' => ['administrator', 'developer', 'doctor', 'hospital_nurse', 'hospital_staff'],

        // Doctor directory administration
        'manage_doctors' => ['administrator'],
    ];

    /** hm_patients.ID for a WordPress user, or null. */
    public static function patientRecordId($user_id)
    {
        $patient = Patient::findByUserId($user_id);
        return $patient ? (int) $patient->ID : null;
    }

    /**
     * Whether one of $user's roles is listed for $capability with full access
     * (not limited to their own records), or it was granted to them individually.
     */
    public static function allowsAll($user, $capability)
    {
        if (!$user || !$user->exists()) {
            return false;
        }
        return self::grantedToUser($user, $capability)
            || (bool) array_intersect((array) $user->roles, self::MAP[$capability] ?? []);
    }

    /** Whether $user may use $capability for their own records as a patient. */
    public static function allowsOwnPatient($user, $capability)
    {
        return $user && in_array('patient', (array) $user->roles, true) && self::patientsOwnRecords($capability);
    }

    /** Whether $user may use $capability for their own records as a doctor. */
    public static function allowsOwnDoctor($user, $capability)
    {
        return $user && in_array('doctor', (array) $user->roles, true)
            && in_array(self::DOCTOR_OWN, self::MAP[$capability] ?? [], true);
    }

    /**
     * Decide whether the current (signed-in) user may perform $capability for $request.
     *
     * @param \WP_REST_Request $request
     * @param string           $capability
     * @return true|\WP_Error
     */
    public static function authorize($request, $capability)
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return new \WP_Error('rest_forbidden', 'You must be logged in to access this endpoint.', ['status' => 401]);
        }

        if (empty($capability)) {
            return true;
        }

        if (self::grantedToUser($user, $capability)) {
            return true;
        }

        $allowed = self::MAP[$capability] ?? [];
        $roles = (array) $user->roles;

        if (array_intersect($roles, $allowed)) {
            return true;
        }

        if (in_array(self::OWN, $allowed, true) && in_array('patient', $roles, true)
            && self::ownsRequestedRecord($request, $user->ID)) {
            return true;
        }

        if (in_array(self::DOCTOR_OWN, $allowed, true) && in_array('doctor', $roles, true)
            && self::doctorOwnsRequestedVisit($request, $user->ID)) {
            return true;
        }

        return new \WP_Error(
            'rest_forbidden',
            'You do not have permission to access this resource.',
            ['status' => 403]
        );
    }

    /**
     * Whether $user may use $capability in general (explicit grant or role).
     * Own-record access for patients is per request and not included.
     */
    public static function allowsUser($user, $capability)
    {
        if (!$user || !$user->exists()) {
            return false;
        }
        if (self::grantedToUser($user, $capability)) {
            return true;
        }
        $allowed = self::MAP[$capability] ?? [];
        if (array_intersect((array) $user->roles, $allowed)) {
            return true;
        }
        // Doctors limited to their own records can still use the capability
        return in_array(self::DOCTOR_OWN, $allowed, true) && in_array('doctor', (array) $user->roles, true);
    }

    /**
     * True when $user may use $capability only on their own records as a doctor
     * (no role or explicit grant gives them all records).
     */
    public static function doctorOwnOnly($user, $capability)
    {
        if (!$user || !$user->exists() || self::grantedToUser($user, $capability)) {
            return false;
        }
        $allowed = self::MAP[$capability] ?? [];
        return !array_intersect((array) $user->roles, $allowed)
            && in_array(self::DOCTOR_OWN, $allowed, true)
            && in_array('doctor', (array) $user->roles, true);
    }

    /**
     * Was $capability granted to this user individually (not through a role)?
     */
    private static function grantedToUser($user, $capability)
    {
        return !empty($user->caps[$capability]);
    }

    /**
     * Read a visit's owner straight from the database. Access decisions must not
     * use the model cache: a stale entry could judge a visit by its old owner.
     */
    private static function visitColumn($visit_id, $column)
    {
        global $wpdb;
        $column = $column === 'doctor_id' ? 'doctor_id' : 'patient_id';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT {$column} FROM {$wpdb->prefix}hm_visitations WHERE ID = %d",
            $visit_id
        ));
    }

    /** hm_doctors.ID for a WordPress user, or null. */
    public static function doctorRecordId($user_id)
    {
        $doctor = \HospitalManager\Models\Doctor::findByUserId($user_id);
        return $doctor ? (int) $doctor->ID : null;
    }

    /**
     * Menu and button flags for GET /access, derived from MAP so the frontend
     * shows exactly what the API will allow. Each flag => what it unlocks.
     */
    public static function accessFlags($user)
    {
        $is_patient = $user && in_array('patient', (array) $user->roles, true);
        $records = self::allowsUser($user, 'view_patient_records') || $is_patient; // patients: own

        return [
            'patients'                   => self::allowsUser($user, 'view_patients'),   // patient list/search menu
            'visitations'                => $records,
            'lab_dashboard'              => $records,
            'create_patients'            => self::allowsUser($user, 'create_patients'),
            'edit_patients'              => self::allowsUser($user, 'edit_patients'),
            'delete_patients'            => self::allowsUser($user, 'delete_patients'),
            'add_visitation'             => self::allowsUser($user, 'add_visitation'),
            // true = new visits are recorded under this doctor (doctor_id in /access)
            'add_own_visits_only'        => self::doctorOwnOnly($user, 'add_visitation'),
            'edit_visitation'            => self::allowsUser($user, 'edit_visitation'),
            // true = only visits where visit.doctor_id is this user's doctor record (see doctor_id in /access)
            'edit_own_visits_only'       => self::doctorOwnOnly($user, 'edit_visitation'),
            'delete_visitation'          => self::allowsUser($user, 'delete_visitation'),
            'request_lab_investigations' => self::allowsUser($user, 'manage_lab_investigations'),
            'delete_lab_results'         => self::allowsUser($user, 'manage_lab_investigations'),
            'add_lab_results'            => self::allowsUser($user, 'update_lab_results'),
            'edit_lab_results'           => self::allowsUser($user, 'update_lab_results'),
        ];
    }

    /** Roles allowed for a capability (for the frontend, docs and tests). */
    public static function rolesFor($capability)
    {
        return array_values(array_diff(self::MAP[$capability] ?? [], [self::OWN, self::DOCTOR_OWN]));
    }

    /** Whether patients may use $capability for their own records. */
    public static function patientsOwnRecords($capability)
    {
        return in_array(self::OWN, self::MAP[$capability] ?? [], true);
    }

    /**
     * Doctors limited to their own visits:
     *  - POST /visitations: the new visit must be recorded under them (an empty
     *    doctor_id is filled in by VisitationController::create_visitation());
     *  - PUT /visitations/{id}: only visits where they are the visit's doctor.
     * Handing a visit to another doctor (doctor_id in the body) is refused.
     * Viewing and adding to any patient's history is unaffected.
     */
    private static function doctorOwnsRequestedVisit($request, $user_id)
    {
        $route = preg_replace('#^/hospital-manager/v1#', '', (string) $request->get_route());

        $own_doctor_id = self::doctorRecordId($user_id); // record ID, not the WordPress user ID
        if (!$own_doctor_id) {
            return false;
        }

        $new_doctor = $request->get_param('doctor_id');
        $other_doctor = $new_doctor !== null && $new_doctor !== '' && (int) $new_doctor !== $own_doctor_id;

        if ($route === '/visitations' && strtoupper($request->get_method()) === 'POST') {
            return !$other_doctor;
        }

        if (!preg_match('#^/visitations/(\d+)$#', $route, $m)) {
            return false;
        }

        $owner = self::visitColumn((int) $m[1], 'doctor_id');
        if ($owner === null || (int) $owner !== $own_doctor_id) {
            return false;
        }

        return !$other_doctor;
    }

    /**
     * Is this request about the signed-in patient's own record?
     *
     * Only routes whose handlers are known to be safe for a patient are listed;
     * anything else is refused.
     */
    private static function ownsRequestedRecord($request, $user_id)
    {
        $patient = Patient::findByUserId($user_id);
        if (!$patient) {
            return false;
        }
        $own_id = (int) $patient->ID;

        $route = preg_replace('#^/hospital-manager/v1#', '', (string) $request->get_route());
        $method = strtoupper($request->get_method());
        if ($method !== 'GET') {
            return false; // patients only ever read through this policy
        }

        // Their own patient record and visit history
        if (preg_match('#^/patients/(\d+)(/visitations)?$#', $route, $m)) {
            return (int) $m[1] === $own_id;
        }

        // One visit: must be theirs
        if (preg_match('#^/visitations/(\d+)$#', $route, $m)) {
            $owner = self::visitColumn((int) $m[1], 'patient_id');
            return $owner !== null && (int) $owner === $own_id;
        }

        // Lists the handlers already restrict to the patient's own records
        // (VisitationService::getVisitations, LabInvestigationController), and
        // lab reference data (categories, test definitions) that isn't personal.
        $scoped = [
            '#^/visitations$#',
            '#^/lab-investigations$#',
            '#^/lab-investigations/pending$#',
            '#^/lab-investigations/\d+$#',
            '#^/lab-categories$#',
            '#^/lab-test-definitions(/\d+)?$#',
        ];
        foreach ($scoped as $pattern) {
            if (preg_match($pattern, $route)) {
                return true;
            }
        }

        return false;
    }
}

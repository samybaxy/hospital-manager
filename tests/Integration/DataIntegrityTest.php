<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\BaseModel;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\Inventory;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;
use HospitalManager\Services\PatientService;

/**
 * Regression tests for data-corruption / silent-failure bugs:
 *   1. Numeric-looking strings were written with '%d' — phones lost their leading 0
 *      ("08012345678" -> 8012345678), IDs lost leading zeros, decimals were truncated.
 *   2. Cache invalidation deleted transients with raw SQL, bypassing the object cache,
 *      so e.g. Patient::count() kept returning a stale value.
 *   3. PatientService::createPatient() without an email inserted user_id = NULL
 *      (NOT NULL column) and failed with a raw SQL error.
 *   4. Models without their own save() (Visitation, LabInvestigation) fell through to
 *      WPMVC's PostModel::save() (wp_posts) and silently discarded edits.
 */
class DataIntegrityTest extends TestCase
{
    // ---------------------------------------------------------------- 1. formats

    public function test_sql_format_is_based_on_php_type_not_appearance()
    {
        $this->assertSame('%s', BaseModel::sqlFormat('08012345678'));
        $this->assertSame('%s', BaseModel::sqlFormat('000123'));
        $this->assertSame('%s', BaseModel::sqlFormat('55.64'));
        $this->assertSame('%d', BaseModel::sqlFormat(42));
        $this->assertSame('%f', BaseModel::sqlFormat(1.5));
    }

    public function test_patient_phone_keeps_leading_zero_on_create_and_save()
    {
        $patient = $this->createTestPatient(['phone' => '08031234567']);
        $this->assertSame('08031234567', $this->column('hm_patients', 'phone', $patient->ID));

        $patient->update(['phone' => '07061234567']);
        $this->assertSame('07061234567', $this->column('hm_patients', 'phone', $patient->ID));
    }

    public function test_ten_digit_local_number_gets_its_trunk_zero_back()
    {
        $patient = $this->createTestPatient();
        $patient->update(['phone' => '8091234567']);

        $this->assertSame('08091234567', $this->column('hm_patients', 'phone', $patient->ID));
    }

    public function test_international_numbers_are_not_altered()
    {
        $patient = $this->createTestPatient();
        $patient->update(['phone' => '+2348031234567']);

        $this->assertSame('+2348031234567', $this->column('hm_patients', 'phone', $patient->ID));
    }

    public function test_doctor_phone_keeps_leading_zero()
    {
        $doctor = $this->createTestDoctor(['phone' => '08055550101']);

        $this->assertSame('08055550101', $this->column('hm_doctors', 'phone', $doctor->ID));
    }

    public function test_hmo_member_id_keeps_leading_zeros()
    {
        $patient = $this->createTestPatient(['hmo_designated_id' => '000123']);

        $this->assertSame('000123', $this->column('hm_patients', 'hmo_designated_id', $patient->ID));
    }

    public function test_decimal_values_are_not_truncated()
    {
        $item = Inventory::create([
            'item_name' => 'Paracetamol 500mg',
            'category' => 'Medication',
            'quantity' => 10,
            'unit' => 'tablets',
            'reorder_level' => 5,
            'cost' => '55.64',
        ]);
        $id = is_object($item) ? $item->ID : $item;

        $this->assertSame('55.64', $this->column('hm_inventory', 'cost', $id));
    }

    // ---------------------------------------------------------------- 2. cache

    public function test_patient_count_reflects_new_and_deleted_patients()
    {
        $before = (int) Patient::count();

        $patient = $this->createTestPatient(['first_name' => 'CountMe']);
        $this->assertSame($before + 1, (int) Patient::count(), 'count() after create');

        Patient::find($patient->ID)->delete();
        $this->assertSame($before, (int) Patient::count(), 'count() after delete');
    }

    public function test_count_returns_an_integer_even_when_cached()
    {
        Patient::count();             // primes the cache
        $this->assertIsInt(Patient::count());
    }

    public function test_model_cache_invalidation_works_for_any_model()
    {
        $before = (int) Doctor::count();
        $this->createTestDoctor();

        $this->assertSame($before + 1, (int) Doctor::count());
    }

    // ---------------------------------------------------------------- 3. createPatient

    public function test_patient_without_email_is_created_without_a_portal_login()
    {
        $users_before = count_users()['total_users'];

        $patient = PatientService::createPatient([
            'first_name' => 'Walkin',
            'last_name' => 'Patient',
            'phone' => '08077770000',
            'gender' => 'Female',
        ]);

        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertSame('0', (string) $this->column('hm_patients', 'user_id', $patient->ID), 'user_id 0 = no portal account');
        $this->assertSame($users_before, count_users()['total_users'], 'No WordPress user should be created');
    }

    public function test_patient_with_email_gets_a_linked_patient_login()
    {
        $patient = PatientService::createPatient([
            'first_name' => 'Portal',
            'last_name' => 'Patient',
            'phone' => '08077770001',
            'gender' => 'Male',
            'email' => 'portal.patient@example.com',
        ]);

        $user = get_user_by('email', 'portal.patient@example.com');
        $this->assertNotFalse($user);
        $this->assertContains('patient', $user->roles);
        $this->assertEquals($user->ID, $this->column('hm_patients', 'user_id', $patient->ID));
    }

    public function test_patient_with_an_already_registered_email_is_rejected()
    {
        $this->factory->user->create(['user_email' => 'taken@example.com']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('A user with this email address already exists');

        PatientService::createPatient([
            'first_name' => 'Taken',
            'last_name' => 'Email',
            'phone' => '08077770002',
            'gender' => 'Male',
            'email' => 'taken@example.com',
        ]);
    }

    // ---------------------------------------------------------------- 4. save()

    public function test_visitation_save_persists_edits_and_new_records()
    {
        $patient = $this->createTestPatient();
        $doctor = $this->createTestDoctor();
        $appointment = $this->createTestAppointment($patient->ID, $doctor->ID);

        $visit = new Visitation([
            'patient_id' => $patient->ID,
            'doctor_id' => $doctor->ID,
            'appointment_id' => $appointment->ID, // not in $fillable, but a real column
            'date' => current_time('Y-m-d'),
            'time' => '10:00:00',
            'diagnosis' => 'Initial',
        ]);
        $visit->doctor_name = 'Joined column, must be ignored';
        $this->assertTrue($visit->save(), 'insert via save()');
        $this->assertNotEmpty($visit->ID);
        $this->assertEquals($appointment->ID, $this->column('hm_visitations', 'appointment_id', $visit->ID));

        $loaded = Visitation::find($visit->ID);
        $loaded->diagnosis = 'Revised';
        $this->assertTrue($loaded->save(), 'update via save()');
        $this->assertSame('Revised', $this->column('hm_visitations', 'diagnosis', $visit->ID));
    }

    public function test_lab_investigation_save_persists_results()
    {
        $patient = $this->createTestPatient();
        $doctor = $this->createTestDoctor();
        $visit = $this->createTestVisitation(['patient_id' => $patient->ID, 'doctor_id' => $doctor->ID]);
        $lab = $this->createTestLabInvestigation([
            'visitation_id' => $visit->ID,
            'patient_id' => $patient->ID,
            'doctor_id' => $doctor->ID,
            'lab_tech_id' => $this->createUserWithRole('lab_tech'),
        ]);

        $loaded = LabInvestigation::find($lab->ID);
        $loaded->status = 'completed';
        $loaded->test_results = ['parameters' => [['name' => 'Hemoglobin', 'value' => 14.2]]];
        $this->assertTrue($loaded->save());

        $this->assertSame('completed', $this->column('hm_lab_investigations', 'status', $lab->ID));
        $stored = json_decode($this->column('hm_lab_investigations', 'test_results', $lab->ID), true);
        $this->assertSame(14.2, $stored['parameters'][0]['value']);
    }

    private function column($table, $column, $id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT `{$column}` FROM {$wpdb->prefix}{$table} WHERE ID = %d", $id));
    }
}

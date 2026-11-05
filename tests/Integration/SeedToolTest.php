<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Database\Seeders\DatabaseSeeder;
use HospitalManager\Database\Seeders\SeedTool;
use HospitalManager\Tests\TestCase;

require_once dirname(__DIR__, 2) . '/database/seeders/SeedTool.php';

/**
 * Tools > Hospital Manager Seed creates accounts with published passwords, so the
 * refusals matter most. (A real end-to-end run creates 160+ users, which is too slow
 * for this suite; SeededPatientRelationshipsTest covers what the seeders produce.)
 */
class SeedToolTest extends TestCase
{
    public function test_seeding_is_refused_on_production_sites()
    {
        $this->assertFalse(SeedTool::isAllowed('production'));
        foreach (['staging', 'development', 'local'] as $environment) {
            $this->assertTrue(SeedTool::isAllowed($environment), $environment);
        }
    }

    public function test_a_blocked_run_changes_nothing()
    {
        // The test suite itself reports the production environment
        $this->assertSame('production', wp_get_environment_type());
        delete_option(SeedTool::STATE_OPTION);
        $users = count_users()['total_users'];

        $result = SeedTool::advance();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('production', $result['log']);
        $this->assertFalse(get_option(SeedTool::STATE_OPTION));
        $this->assertSame($users, count_users()['total_users']);
    }

    public function test_existing_patients_count_as_hospital_data()
    {
        $this->assertFalse(SeedTool::hasHospitalData());
        $this->createTestPatient();
        $this->assertTrue(SeedTool::hasHospitalData());
    }

    public function test_only_one_step_runs_at_a_time()
    {
        SeedTool::releaseLock();
        $this->assertTrue(SeedTool::acquireLock());
        $this->assertFalse(SeedTool::acquireLock(), 'a live lock blocks a second request');

        update_option(SeedTool::LOCK_OPTION, time() - SeedTool::LOCK_TTL - 1, false);
        $this->assertTrue(SeedTool::acquireLock(), 'a lock left by a killed request expires');

        SeedTool::releaseLock();
    }

    public function test_every_step_has_a_seeder_file_and_runs_in_dependency_order()
    {
        $steps = SeedTool::steps();

        $this->assertSame(DatabaseSeeder::SEEDERS, $steps);
        foreach ($steps as $class) {
            $this->assertTrue(class_exists($class), $class);
        }
        $position = array_flip(array_map(function ($class) {
            return basename(str_replace('\\', '/', $class));
        }, $steps));
        $this->assertLessThan($position['PatientSeeder'], $position['UserSeeder']);
        $this->assertLessThan($position['AppointmentSeeder'], $position['DoctorSeeder']);
        $this->assertLessThan($position['VisitationSeeder'], $position['AppointmentSeeder']);
    }
}

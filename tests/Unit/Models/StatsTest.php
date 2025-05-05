<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Stats;
use HospitalManager\Models\Doctor;

class StatsTest extends TestCase
{
    /**
     * @var array Test patients
     */
    protected $patients = [];
    
    /**
     * @var Doctor Test doctor
     */
    protected $doctor;
    
    /**
     * @var array Test HMOs
     */
    protected $hmos = [];
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create a test doctor
        $doctor_user_id = $this->createUserWithRole('doctor');
        $this->doctor = $this->createTestDoctor([
            'user_id' => $doctor_user_id
        ]);
        
        // Create test HMOs
        $this->hmos[] = $this->createTestHMO(['name' => 'Premium Health']);
        $this->hmos[] = $this->createTestHMO(['name' => 'National Insurance']);
        $this->hmos[] = $this->createTestHMO(['name' => 'Corporate Healthcare']);
        
        // Create test patients with different HMOs
        for ($i = 0; $i < 5; $i++) {
            $user_id = $this->createUserWithRole('patient');
            $hmo_index = $i % 3; // Distribute across the 3 HMOs
            $this->patients[] = $this->createTestPatient([
                'user_id' => $user_id,
                'hmo_id' => $this->hmos[$hmo_index]->id
            ]);
        }
        
        // Create visitations on different dates
        $dates = [
            date('Y-m-d', strtotime('-25 days')),
            date('Y-m-d', strtotime('-20 days')),
            date('Y-m-d', strtotime('-15 days')),
            date('Y-m-d', strtotime('-10 days')),
            date('Y-m-d', strtotime('-5 days')),
            date('Y-m-d', strtotime('-2 days')),
            date('Y-m-d')
        ];
        
        foreach ($dates as $index => $date) {
            // Create multiple visitations on some dates
            $count = $index % 3 + 1;
            for ($i = 0; $i < $count; $i++) {
                $patient_index = ($index + $i) % count($this->patients);
                $this->createTestVisitation([
                    'patient_id' => $this->patients[$patient_index]->id,
                    'doctor_id' => $this->doctor->id,
                    'date' => $date
                ]);
            }
        }
    }
    
    /**
     * Test getting visitation trend
     */
    public function testGetVisitationTrend()
    {
        $trend = Stats::getVisitationTrend();
        
        $this->assertNotEmpty($trend);
        
        // Check that we have data for the days we created visitations
        $found_dates = [];
        $total_count = 0;
        
        foreach ($trend as $data_point) {
            $found_dates[] = $data_point->date;
            $total_count += $data_point->count;
            
            // Ensure count is a positive number
            $this->assertGreaterThan(0, $data_point->count);
        }
        
        // Check that we have the expected dates in the results
        $this->assertContains(date('Y-m-d', strtotime('-5 days')), $found_dates);
        $this->assertContains(date('Y-m-d'), $found_dates);
        
        // Ensure total visits matches what we created
        $this->assertGreaterThanOrEqual(12, $total_count); // Sum of (index % 3 + 1) for 7 dates
    }

    /**
     * Test getting patient distribution by HMO
     */
    public function testGetPatientsByHMO()
    {
        $distribution = Stats::getPatientsByHMO();
        
        $this->assertNotEmpty($distribution);
        
        // Verify we have data for all 3 HMOs
        $this->assertCount(3, $distribution);
        
        // Create a map of HMO id to patient count
        $hmo_counts = [];
        foreach ($distribution as $data) {
            // The name field is actually the hmo_id
            $hmo_counts[$data->name] = $data->value;
        }
        
        // Verify counts - note that name field is actually the HMO ID
        $this->assertEquals(2, $hmo_counts[$this->hmos[0]->id]); // HMO 0 should have 2 patients (index 0 and 3)
        $this->assertEquals(2, $hmo_counts[$this->hmos[1]->id]); // HMO 1 should have 2 patients (index 1 and 4)
        $this->assertEquals(1, $hmo_counts[$this->hmos[2]->id]); // HMO 2 should have 1 patient (index 2)
    }

    /**
     * Test when no data exists
     */
    public function testNoData()
    {
        // Delete all visitations
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}hm_visitations");
        
        // Test empty trend data
        $trend = Stats::getVisitationTrend();
        $this->assertEmpty($trend);
        
        // Delete all patients
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}hm_patients");
        
        // Test empty HMO distribution
        $distribution = Stats::getPatientsByHMO();
        $this->assertEmpty($distribution);
    }
}

<?php

namespace HospitalManager\Tests\Unit;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\Visitation;
use HospitalManager\Models\Chat;
use HospitalManager\Models\ChatMessage;
use HospitalManager\Models\AuditLog;
use HospitalManager\Models\Notification;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\RadiologicalExam;
use HospitalManager\Models\HMO;

/**
 * Tests the TestCase helper methods to ensure they work correctly
 */
class TestCaseTest extends TestCase
{
    /**
     * Test user creation with role
     */
    public function testCreateUserWithRole()
    {
        $user_id = $this->createUserWithRole('doctor');
        $user = get_user_by('id', $user_id);
        
        $this->assertNotEmpty($user);
        $this->assertEquals($user_id, $user->ID);
        $this->assertTrue(user_can($user_id, 'read'));
        $this->assertTrue(in_array('doctor', $user->roles));
    }
    
    /**
     * Test patient creation utility
     */
    public function testCreateTestPatient()
    {
        $patient = $this->createTestPatient([
            'first_name' => 'Test',
            'last_name' => 'PatientHelper'
        ]);
        
        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertEquals('Test', $patient->first_name);
        $this->assertEquals('PatientHelper', $patient->last_name);
        $this->assertNotEmpty($patient->id);
    }
    
    /**
     * Test doctor creation utility
     */
    public function testCreateTestDoctor()
    {
        $doctor = $this->createTestDoctor([
            'first_name' => 'Test',
            'last_name' => 'DoctorHelper'
        ]);
        
        $this->assertInstanceOf(Doctor::class, $doctor);
        $this->assertEquals('Test', $doctor->first_name);
        $this->assertEquals('DoctorHelper', $doctor->last_name);
        $this->assertNotEmpty($doctor->id);
    }
    
    /**
     * Test appointment creation utility
     */
    public function testCreateTestAppointment()
    {
        $patient = $this->createTestPatient();
        $doctor = $this->createTestDoctor();
        
        $appointment = $this->createTestAppointment([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'scheduled'
        ]);
        
        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertEquals($patient->id, $appointment->patient_id);
        $this->assertEquals($doctor->id, $appointment->doctor_id);
        $this->assertEquals('scheduled', $appointment->status);
    }
    
    /**
     * Test visitation creation utility
     */
    public function testCreateTestVisitation()
    {
        $patient = $this->createTestPatient();
        $doctor = $this->createTestDoctor();
        
        $visitation = $this->createTestVisitation([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id
        ]);
        
        $this->assertInstanceOf(Visitation::class, $visitation);
        $this->assertEquals($patient->id, $visitation->patient_id);
        $this->assertEquals($doctor->id, $visitation->doctor_id);
    }
    
    /**
     * Test chat creation utility
     */
    public function testCreateTestChat()
    {
        $doctor_id = $this->createUserWithRole('doctor');
        $patient_id = $this->createUserWithRole('patient');
        
        $chat = $this->createTestChat([
            'doctor_id' => $doctor_id,
            'patient_id' => $patient_id
        ]);
        
        $this->assertInstanceOf(Chat::class, $chat);
        $this->assertEquals($doctor_id, $chat->doctor_id);
        $this->assertEquals($patient_id, $chat->patient_id);
    }
    
    /**
     * Test chat message creation utility
     */
    public function testCreateTestChatMessage()
    {
        $doctor_id = $this->createUserWithRole('doctor');
        $patient_id = $this->createUserWithRole('patient');
        
        $chat = $this->createTestChat([
            'doctor_id' => $doctor_id,
            'patient_id' => $patient_id
        ]);
        
        $message = $this->createTestChatMessage([
            'chat_id' => $chat->id,
            'sender_id' => $doctor_id,
            'receiver_id' => $patient_id,
            'message' => 'Test message from helper'
        ]);
        
        $this->assertInstanceOf(ChatMessage::class, $message);
        $this->assertEquals($chat->id, $message->chat_id);
        $this->assertEquals($doctor_id, $message->sender_id);
        $this->assertEquals('Test message from helper', $message->message);
    }
    
    /**
     * Test notification creation utility
     */
    public function testCreateTestNotification()
    {
        $user_id = $this->createUserWithRole('patient');
        
        $notification = $this->createTestNotification([
            'user_id' => $user_id,
            'type' => 'test_type',
            'title' => 'Test Notification',
            'message' => 'This is a test notification from helper'
        ]);
        
        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($user_id, $notification->user_id);
        $this->assertEquals('test_type', $notification->type);
        $this->assertEquals('Test Notification', $notification->title);
    }
    
    /**
     * Test lab investigation creation utility
     */
    public function testCreateTestLabInvestigation()
    {
        $patient = $this->createTestPatient();
        $doctor = $this->createTestDoctor();
        $visitation = $this->createTestVisitation([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id
        ]);
        
        $lab = $this->createTestLabInvestigation([
            'visitation_id' => $visitation->id,
            'patient_id' => $patient->id,
            'requested_by' => $doctor->id,
            'test_type' => 'Blood Test Helper'
        ]);
        
        $this->assertInstanceOf(LabInvestigation::class, $lab);
        $this->assertEquals($visitation->id, $lab->visitation_id);
        $this->assertEquals($patient->id, $lab->patient_id);
        $this->assertEquals('Blood Test Helper', $lab->test_type);
    }
    
    /**
     * Test radiological exam creation utility
     */
    public function testCreateTestRadiologicalExam()
    {
        $patient = $this->createTestPatient();
        $doctor = $this->createTestDoctor();
        $tech_id = $this->createUserWithRole('lab_tech');
        $visitation = $this->createTestVisitation([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id
        ]);
        
        $exam = $this->createTestRadiologicalExam([
            'visitation_id' => $visitation->id,
            'tech_id' => $tech_id,
            'results' => 'Test results from helper'
        ]);
        
        $this->assertInstanceOf(RadiologicalExam::class, $exam);
        $this->assertEquals($visitation->id, $exam->visitation_id);
        $this->assertEquals($tech_id, $exam->tech_id);
        $this->assertEquals('Test results from helper', $exam->results);
    }
    
    /**
     * Test HMO creation utility
     */
    public function testCreateTestHMO()
    {
        $hmo = $this->createTestHMO([
            'name' => 'Test HMO Provider'
        ]);
        
        $this->assertInstanceOf(HMO::class, $hmo);
        $this->assertEquals('Test HMO Provider', $hmo->name);
    }
    
    /**
     * Test audit log creation utility
     */
    public function testCreateTestAuditLog()
    {
        $user_id = $this->createUserWithRole('doctor');
        
        $log = $this->createTestAuditLog([
            'user_id' => $user_id,
            'action' => 'test_action',
            'entity_type' => 'test_entity',
            'entity_id' => 123
        ]);
        
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals($user_id, $log->user_id);
        $this->assertEquals('test_action', $log->action);
        $this->assertEquals('test_entity', $log->entity_type);
        $this->assertEquals(123, $log->entity_id);
    }
}

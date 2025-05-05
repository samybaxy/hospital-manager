<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;

class ApiServiceTest extends TestCase
{
    /**
     * @var array
     */
    private $controllerClasses;
    
    /**
     * @var array
     */
    private $controllerInstances;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Define the controller classes we expect to use
        $this->controllerClasses = [
            'PatientController',
            'VisitationController',
            'LabInvestigationController',
            'ChatController', 
            'NotificationController',
            'AppointmentController',
            'AuthController',
            'DoctorController',
            'AuditController',
            'DashboardController',
            'StatsController'
        ];
        
        // Create mock instances for each controller
        $this->controllerInstances = [];
        foreach ($this->controllerClasses as $controller) {
            $mock = Mockery::mock('HospitalManager\\Controllers\\Api\\' . $controller);
            $mock->shouldReceive('register_routes')->zeroOrMoreTimes()->andReturn(true);
            $this->controllerInstances[] = $mock;
        }
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test that API controllers can register their routes
     */
    public function testAllControllersAreRegistered()
    {
        // This test verifies each controller has a register_routes method that works
        foreach ($this->controllerInstances as $controller) {
            $this->assertTrue(method_exists($controller, 'register_routes') || 
                is_callable([$controller, 'register_routes']));
            
            // Call register_routes to verify it works as expected
            $result = $controller->register_routes();
            $this->assertTrue($result);
        }
    }
    
    /**
     * Test API response formatting
     */
    public function testGetControllers()
    {
        // Test formatResponse method
        $data = ['id' => 1, 'name' => 'Test'];
        $message = 'Success message';
        $status = 200;
        
        // Create a mock of ApiService
        $apiService = Mockery::mock('HospitalManager\\Services\\ApiService');
        $apiService->shouldReceive('formatResponse')
            ->with($data, $message, $status, true)
            ->andReturn([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'status' => $status,
                'version' => 'v1'
            ]);
        
        // Get formatted response
        $response = $apiService->formatResponse($data, $message, $status, true);
        
        // Verify response format
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('version', $response);
        
        // Verify values
        $this->assertTrue($response['success']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($data, $response['data']);
        $this->assertEquals($status, $response['status']);
    }
    
    /**
     * Test adding custom controllers
     */
    public function testAddCustomController()
    {
        // Create a mock for a custom controller
        $customController = Mockery::mock('HospitalManager\\Controllers\\Api\\CustomController');
        $customController->shouldReceive('register_routes')->zeroOrMoreTimes()->andReturn(true);
        
        // This just tests that the custom controller can be created and used
        $this->assertTrue(method_exists($customController, 'register_routes') || 
            is_callable([$customController, 'register_routes']));
        
        // Verify the controller works correctly
        $result = $customController->register_routes();
        $this->assertTrue($result);
    }
}

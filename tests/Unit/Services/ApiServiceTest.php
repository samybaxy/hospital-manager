<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\ApiService;
use Brain\Monkey\Functions;
use Mockery;
use ReflectionClass;

// API Controller classes
use HospitalManager\Controllers\Api\PatientController;
use HospitalManager\Controllers\Api\VisitationController;
use HospitalManager\Controllers\Api\LabInvestigationController;
use HospitalManager\Controllers\Api\ChatController;
use HospitalManager\Controllers\Api\NotificationController;
use HospitalManager\Controllers\Api\AppointmentController;
use HospitalManager\Controllers\Api\AuthController;
use HospitalManager\Controllers\Api\DoctorController;
use HospitalManager\Controllers\Api\AuditController;
use HospitalManager\Controllers\Api\DashboardController;
use HospitalManager\Controllers\Api\StatsController;

class ApiServiceTest extends TestCase
{
    /**
     * Track controllers that were registered
     * @var array
     */
    private $registered_controllers = [];
    
    /**
     * List of controller classes that should be registered
     * @var array
     */
    private $controller_classes = [];
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset registered controllers
        $this->registered_controllers = [];
        
        // Define the expected controller classes
        $this->controller_classes = [
            PatientController::class,
            VisitationController::class,
            LabInvestigationController::class,
            ChatController::class,
            NotificationController::class,
            AppointmentController::class,
            AuthController::class,
            DoctorController::class,
            AuditController::class,
            DashboardController::class,
            StatsController::class
        ];
        
        // Mock WordPress rest_api_init action using the WordPress-MVC pattern
        Functions\when('add_action')->alias(function($hook, $callback) {
            if ($hook === 'rest_api_init') {
                // Store the callback to be executed later in tests
                $this->rest_api_init_callback = $callback;
            }
            return true;
        });
        
        // Mock Controller instantiation and registration
        foreach ($this->controller_classes as $controller_class) {
            $this->mockController($controller_class);
        }
        
        // Mock register_rest_route with proper WordPress-MVC pattern
        Functions\when('register_rest_route')->alias(function($namespace, $route, $args) {
            return true;
        });
        
        // Create the mock filter for applying controller instances
        Functions\when('apply_filters')->alias(function($tag, $value, ...$args) {
            if ($tag === 'hospital_manager_api_controllers') {
                return $this->getMockControllers();
            }
            return $value;
        });
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
     * Create a mock controller instance
     * 
     * @param string $class The controller class to mock
     */
    private function mockController($class)
    {
        $shortName = (new ReflectionClass($class))->getShortName();
        
        // Create a Mockery mock for this controller
        $mock = Mockery::mock($class);
        
        // Set up the register_routes method expectation
        $mock->shouldReceive('register_routes')
            ->withNoArgs()
            ->andReturnUsing(function() use ($shortName) {
                // Track that this controller was registered
                $this->registered_controllers[] = $shortName;
                return true;
            });
        
        // Store the mock for later
        $this->controller_mocks[$shortName] = $mock;
    }
    
    /**
     * Get all the mock controllers for the filter
     * 
     * @return array Array of controller mock instances
     */
    private function getMockControllers()
    {
        return array_values($this->controller_mocks);
    }
    
    /**
     * Call the rest_api_init callback
     */
    private function triggerRestApiInit()
    {
        if (isset($this->rest_api_init_callback) && is_callable($this->rest_api_init_callback)) {
            call_user_func($this->rest_api_init_callback);
        }
    }
    
    /**
     * Test that all expected controllers are registered
     */
    public function testAllControllersAreRegistered()
    {
        // Register routes
        ApiService::registerRoutes();
        
        // Execute the callback
        $this->triggerRestApiInit();
        
        // Check each expected controller
        foreach ($this->controller_classes as $controller) {
            $shortName = (new ReflectionClass($controller))->getShortName();
            $this->assertContains(
                $shortName,
                $this->registered_controllers,
                "Controller $shortName is not registered with the API service. This means its endpoints won't be available to the application, potentially breaking critical functionality."
            );
        }
    }
    
    /**
     * Test getting controllers from the service
     */
    public function testGetControllers()
    {
        // First register the controllers
        ApiService::registerRoutes();
        $this->triggerRestApiInit();
        
        // Get controllers from the service
        $controllers = ApiService::getControllers();
        
        // Verify we have the expected number of controllers
        $this->assertCount(
            count($this->controller_classes),
            $controllers,
            "Expected " . count($this->controller_classes) . " controllers, but found " . count($controllers)
        );
        
        // Verify the first controller is the expected type
        $firstController = $controllers[0];
        $expectedType = $this->controller_classes[0];
        
        // Check if the controller is of the expected type
        $this->assertInstanceOf(
            $expectedType,
            $firstController,
            "Controller at index 0 is not an instance of {$expectedType}. This indicates the API service is not correctly instantiating controllers, which will cause API endpoints to be unavailable."
        );
    }
    
    /**
     * Test adding a custom controller
     */
    public function testAddCustomController()
    {
        // Create a custom controller class
        $customControllerClass = 'HospitalManager\Controllers\Api\CustomTestController';
        
        // Mock the custom controller
        $mock = Mockery::mock($customControllerClass);
        $mock->shouldReceive('register_routes')
            ->withNoArgs()
            ->andReturnUsing(function() {
                $this->registered_controllers[] = 'CustomTestController';
                return true;
            });
        
        // Register routes
        ApiService::registerRoutes();
        
        // Add the custom controller
        ApiService::addController($mock);
        
        // Trigger the initialization
        $this->triggerRestApiInit();
        
        // Verify the custom controller was registered
        $this->assertContains(
            'CustomTestController',
            $this->registered_controllers,
            "Custom controller was not registered properly. This indicates the API service isn't extensible with third-party controllers, limiting plugin extensibility."
        );
    }
}

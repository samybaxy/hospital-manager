<?php

namespace HospitalManager\Tests\Integration\Controllers;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\AdminController;
use Mockery;

class AdminControllerTest extends TestCase
{
    /**
     * @var AdminController
     */
    protected $controller;

    /**
     * @var int
     */
    protected $admin_id;

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create an admin user
        $this->admin_id = $this->createUserWithRole('administrator');
        wp_set_current_user($this->admin_id);
        
        // Initialize the controller
        $this->controller = new AdminController();
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
     * Test that the admin menu is registered
     */
    public function testAdminMenuRegistration()
    {
        // Get the global filter array to check registered hooks
        global $wp_filter;
        
        // Check if admin_menu has the controller's registerAdminMenu method
        $has_admin_menu = false;
        if (isset($wp_filter['admin_menu'])) {
            foreach ($wp_filter['admin_menu'] as $priority => $callbacks) {
                foreach ($callbacks as $cb) {
                    if (is_array($cb['function']) && 
                        is_object($cb['function'][0]) && 
                        get_class($cb['function'][0]) === AdminController::class && 
                        $cb['function'][1] === 'registerAdminMenu') {
                        $has_admin_menu = true;
                        break 2;
                    }
                }
            }
        }
        
        $this->assertTrue($has_admin_menu, 'AdminController::registerAdminMenu not hooked to admin_menu');
        
        // Check if admin_enqueue_scripts has the controller's enqueueAssets method
        $has_admin_enqueue = false;
        if (isset($wp_filter['admin_enqueue_scripts'])) {
            foreach ($wp_filter['admin_enqueue_scripts'] as $priority => $callbacks) {
                foreach ($callbacks as $cb) {
                    if (is_array($cb['function']) && 
                        is_object($cb['function'][0]) && 
                        get_class($cb['function'][0]) === AdminController::class && 
                        $cb['function'][1] === 'enqueueAssets') {
                        $has_admin_enqueue = true;
                        break 2;
                    }
                }
            }
        }
        
        $this->assertTrue($has_admin_enqueue, 'AdminController::enqueueAssets not hooked to admin_enqueue_scripts');
    }

    /**
     * Test the admin menu registration method
     * 
     * This test verifies that the method runs without errors and checks the code structure.
     */
    public function testRegisterAdminMenu()
    {
        // Get the reflected method to examine its implementation
        $reflectionMethod = new \ReflectionMethod(AdminController::class, 'registerAdminMenu');
        
        // Get method contents
        $fileName = $reflectionMethod->getFileName();
        $startLine = $reflectionMethod->getStartLine();
        $endLine = $reflectionMethod->getEndLine();
        
        // Read the file content
        $fileContent = file($fileName);
        $methodContent = implode('', array_slice($fileContent, $startLine - 1, $endLine - $startLine + 1));
        
        // Verify the method contains the expected components
        $this->assertStringContainsString('add_menu_page', $methodContent, 'Method should call add_menu_page');
        $this->assertStringContainsString('manage_options', $methodContent, 'Method should use manage_options capability');
        $this->assertStringContainsString('hospital-manager', $methodContent, 'Method should use the correct menu slug');
        $this->assertStringContainsString('dashicons-hospital', $methodContent, 'Method should use the hospital dashicon');
        
        // Call the method to ensure it doesn't throw an exception
        $this->controller->registerAdminMenu();
        $this->assertTrue(true, 'Method executed without errors');
    }

    /**
     * Test asset enqueuing for the admin page
     *
     * This test verifies that the method runs without errors and contains the necessary code.
     */
    public function testEnqueueAssets()
    {
        // Get the reflected method to examine its implementation
        $reflectionMethod = new \ReflectionMethod(AdminController::class, 'enqueueAssets');
        
        // Get method contents
        $fileName = $reflectionMethod->getFileName();
        $startLine = $reflectionMethod->getStartLine();
        $endLine = $reflectionMethod->getEndLine();
        
        // Read the file content
        $fileContent = file($fileName);
        $methodContent = implode('', array_slice($fileContent, $startLine - 1, $endLine - $startLine + 1));
        
        // Verify the method contains the expected components
        $this->assertStringContainsString('wp_enqueue_style', $methodContent, 'Method should call wp_enqueue_style');
        $this->assertStringContainsString('wp_enqueue_script', $methodContent, 'Method should call wp_enqueue_script');
        $this->assertStringContainsString('wp_localize_script', $methodContent, 'Method should call wp_localize_script');
        $this->assertStringContainsString('hospital-manager-admin', $methodContent, 'Method should enqueue admin styles');
        $this->assertStringContainsString('hospital-manager-app', $methodContent, 'Method should enqueue app script');
        
        // Test with a non-matching hook (should not cause errors)
        $this->controller->enqueueAssets('different_page');
        
        // Test with the correct hook (should not throw an error)
        $this->controller->enqueueAssets('toplevel_page_hospital-manager');
        
        $this->assertTrue(true, 'Method executed without errors');
    }

    /**
     * Test rendering the admin page
     */
    public function testRenderAdminPage()
    {
        // Create a simple mock for the view
        $mock_view = new class {
            public $rendered = false;
            public $template = null;
            
            public function render($template) {
                $this->rendered = true;
                $this->template = $template;
            }
        };
        
        // Set the mock view in the controller
        $reflection = new \ReflectionProperty(AdminController::class, 'view');
        $reflection->setAccessible(true);
        $reflection->setValue($this->controller, $mock_view);
        
        // Call the method
        $this->controller->renderAdminPage();
        
        // Verify it was called with the correct template
        $this->assertTrue($mock_view->rendered, 'The render method was not called');
        $this->assertEquals('admin.index', $mock_view->template, 'Wrong template was rendered');
    }
}

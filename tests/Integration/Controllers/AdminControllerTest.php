<?php

namespace HospitalManager\Tests\Integration\Controllers;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\AdminController;

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
     * Test that the admin menu is registered
     */
    public function testAdminMenuRegistration()
    {
        // Capture actions that would be added
        $actions_added = [];
        
        // Mock the add_action function
        global $wp_filter;
        $original_wp_filter = $wp_filter;
        $wp_filter = [];
        
        // Override add_action to record calls
        global $test_actions;
        $test_actions = [];
        
        function test_add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
            global $test_actions;
            $test_actions[] = [
                'hook' => $hook,
                'callback' => $callback,
                'priority' => $priority,
                'accepted_args' => $accepted_args
            ];
        }
        
        // Create a new controller to trigger the constructor
        $reflection = new \ReflectionClass(AdminController::class);
        $constructor = $reflection->getMethod('__construct');
        
        // Verify that the admin_menu action is registered
        $this->assertTrue(in_array('admin_menu', array_column($test_actions, 'hook')));
        
        // Verify that the admin_enqueue_scripts action is registered
        $this->assertTrue(in_array('admin_enqueue_scripts', array_column($test_actions, 'hook')));
        
        // Restore the original wp_filter
        $wp_filter = $original_wp_filter;
    }

    /**
     * Test the admin menu registration method
     */
    public function testRegisterAdminMenu()
    {
        // Mock add_menu_page function
        global $test_menu_pages;
        $test_menu_pages = [];
        
        function test_add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position) {
            global $test_menu_pages;
            $test_menu_pages[] = [
                'page_title' => $page_title,
                'menu_title' => $menu_title,
                'capability' => $capability,
                'menu_slug' => $menu_slug,
                'function' => $function,
                'icon_url' => $icon_url,
                'position' => $position
            ];
            return 'hook_suffix';
        }
        
        // Call the method
        $this->controller->registerAdminMenu();
        
        // Verify the menu page was added correctly
        $this->assertNotEmpty($test_menu_pages);
        $this->assertEquals('hospital-manager', $test_menu_pages[0]['menu_slug']);
        $this->assertEquals('manage_options', $test_menu_pages[0]['capability']);
        $this->assertEquals('dashicons-hospital', $test_menu_pages[0]['icon_url']);
        $this->assertEquals(30, $test_menu_pages[0]['position']);
    }

    /**
     * Test asset enqueuing for the admin page
     */
    public function testEnqueueAssets()
    {
        // Setup mock for wp_enqueue_style and wp_enqueue_script
        global $test_enqueued_styles, $test_enqueued_scripts, $test_localized_scripts;
        $test_enqueued_styles = [];
        $test_enqueued_scripts = [];
        $test_localized_scripts = [];
        
        function test_wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
            global $test_enqueued_styles;
            $test_enqueued_styles[] = [
                'handle' => $handle,
                'src' => $src,
                'deps' => $deps,
                'ver' => $ver,
                'media' => $media
            ];
        }
        
        function test_wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
            global $test_enqueued_scripts;
            $test_enqueued_scripts[] = [
                'handle' => $handle,
                'src' => $src,
                'deps' => $deps,
                'ver' => $ver,
                'in_footer' => $in_footer
            ];
        }
        
        function test_wp_localize_script($handle, $object_name, $l10n) {
            global $test_localized_scripts;
            $test_localized_scripts[] = [
                'handle' => $handle,
                'object_name' => $object_name,
                'l10n' => $l10n
            ];
        }
        
        // Test with a non-matching hook (should not enqueue assets)
        $this->controller->enqueueAssets('different_page');
        $this->assertEmpty($test_enqueued_styles);
        $this->assertEmpty($test_enqueued_scripts);
        
        // Test with the correct hook
        $this->controller->enqueueAssets('toplevel_page_hospital-manager');
        
        // Verify the style was enqueued
        $this->assertNotEmpty($test_enqueued_styles);
        $this->assertEquals('hospital-manager-admin', $test_enqueued_styles[0]['handle']);
        
        // Verify the script was enqueued
        $this->assertNotEmpty($test_enqueued_scripts);
        $this->assertEquals('hospital-manager-app', $test_enqueued_scripts[0]['handle']);
        $this->assertEquals(['wp-element'], $test_enqueued_scripts[0]['deps']);
        $this->assertEquals('1.0.0', $test_enqueued_scripts[0]['ver']);
        $this->assertTrue($test_enqueued_scripts[0]['in_footer']);
        
        // Verify the script was localized
        $this->assertNotEmpty($test_localized_scripts);
        $this->assertEquals('hospital-manager-app', $test_localized_scripts[0]['handle']);
        $this->assertEquals('hospitalManagerData', $test_localized_scripts[0]['object_name']);
    }

    /**
     * Test rendering the admin page
     */
    public function testRenderAdminPage()
    {
        // Mock the view render method
        $mock_view = $this->getMockBuilder('WPMVC\MVC\View')
            ->disableOriginalConstructor()
            ->getMock();
        
        // Set expectations for the render method
        $mock_view->expects($this->once())
            ->method('render')
            ->with($this->equalTo('admin.index'));
        
        // Set the mock view in the controller
        $reflection = new \ReflectionProperty(AdminController::class, 'view');
        $reflection->setAccessible(true);
        $reflection->setValue($this->controller, $mock_view);
        
        // Call the method
        $this->controller->renderAdminPage();
    }
}

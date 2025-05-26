<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use WP_REST_Request;
use WP_REST_Server;

class AuthControllerTest extends TestCase
{
    /**
     * @var \WP_REST_Server
     */
    protected $server;

    /**
     * @var string
     */
    protected $namespace = 'hospital-manager/v1';

    /**
     * @var array
     */
    protected $test_users = [];
    
    /**
     * @var string Username for the test doctor
     */
    protected $doctor_username;
    
    /**
     * @var string Password for the test doctor
     */
    protected $doctor_password;
    
    /**
     * @var int User ID for login test user
     */
    protected $login_test_user_id;

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init');
        
        // Create test users with different roles
        $this->test_users['doctor'] = $this->createUserWithRole('doctor');
        $this->test_users['patient'] = $this->createUserWithRole('patient');
        $this->test_users['admin'] = $this->createUserWithRole('administrator');
        
        // Create user credentials for login tests
        add_user_meta($this->test_users['doctor'], 'nickname', 'testdoctor');
        
        $this->doctor_username = 'doctor_' . rand(1000, 9999);
        $this->doctor_password = 'SecurePassword123!';
        
        // Create a dedicated user for login testing
        $this->login_test_user_id = wp_create_user(
            $this->doctor_username, 
            $this->doctor_password, 
            'doctor_' . rand(1000, 9999) . '@example.com'
        );
        $login_user = new \WP_User($this->login_test_user_id);
        $login_user->set_role('doctor');
    }

    /**
     * Test user can login with valid credentials
     */
    public function testLoginWithValidCredentials()
    {
        // Ensure no user is logged in
        wp_set_current_user(0);
        
        // Prepare login data
        $login_data = [
            'username' => $this->doctor_username,
            'password' => $this->doctor_password
        ];
        
        // Create request to login
        $request = new WP_REST_Request('POST', "/{$this->namespace}/auth/login");
        $request->set_body_params($login_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Verify the response indicates successful login
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('user', $data['data']);
        $this->assertEquals($this->login_test_user_id, $data['data']['user']['ID']);
        $this->assertArrayHasKey('role', $data['data']['user']);
        
        // Verify user is now logged in
        $this->assertTrue(is_user_logged_in());
    }

    /**
     * Test login fails with invalid credentials
     */
    public function testLoginWithInvalidCredentials()
    {
        // Ensure no user is logged in
        wp_set_current_user(0);
        
        // Prepare invalid login data
        $login_data = [
            'username' => $this->doctor_username,
            'password' => 'WrongPassword123!'
        ];
        
        // Create request to login
        $request = new WP_REST_Request('POST', "/{$this->namespace}/auth/login");
        $request->set_body_params($login_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(401, $response->get_status());
        
        // Verify the response indicates failed login
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('invalid', strtolower($data['message']));
        
        // Verify user is still not logged in
        $this->assertFalse(is_user_logged_in());
    }

    /**
     * Test logged in user cannot access login endpoint
     */
    public function testLoggedInUserCannotLogin()
    {
        // Set current user
        wp_set_current_user($this->test_users['doctor']);
        
        // Prepare login data
        $login_data = [
            'username' => $this->doctor_username,
            'password' => $this->doctor_password
        ];
        
        // Create request to login
        $request = new WP_REST_Request('POST', "/{$this->namespace}/auth/login");
        $request->set_body_params($login_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden as user is already logged in
        $this->assertEquals(403, $response->get_status());
    }

    /**
     * Test getting current user data
     */
    public function testGetCurrentUser()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get current user
        $request = new WP_REST_Request('GET', "/{$this->namespace}/auth/me");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Verify the response contains correct user data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($this->test_users['doctor'], $data['user']['ID']);
        $this->assertArrayHasKey('role', $data['user']);
    }

    /**
     * Test unauthenticated user cannot access current user data
     */
    public function testUnauthenticatedUserCannotGetCurrentUser()
    {
        // Ensure no user is logged in
        wp_set_current_user(0);
        
        // Create request to get current user
        $request = new WP_REST_Request('GET', "/{$this->namespace}/auth/me");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be unauthorized
        $this->assertEquals(401, $response->get_status());
    }

    /**
     * Test user can logout
     */
    public function testLogout()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Verify user is logged in
        $this->assertTrue(is_user_logged_in());
        
        // Create request to logout
        $request = new WP_REST_Request('POST', "/{$this->namespace}/auth/logout");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Verify the response indicates successful logout
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        
        // Note: In an HTTP test environment, we can't verify the actual session/cookie logout
        // but we can verify the endpoint returns success
    }

    /**
     * Test unauthenticated user cannot access logout endpoint
     */
    public function testUnauthenticatedUserCannotLogout()
    {
        // Ensure no user is logged in
        wp_set_current_user(0);
        
        // Create request to logout
        $request = new WP_REST_Request('POST', "/{$this->namespace}/auth/logout");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be unauthorized
        $this->assertEquals(401, $response->get_status());
    }
}

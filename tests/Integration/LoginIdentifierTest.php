<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Controllers\Api\AuthController;

/**
 * POST /auth/login through the real AuthController (the older AuthControllerTest
 * only exercises tests/Mocks/Api/AuthMockRestApi.php).
 *
 * The login screen accepts a username or an email address and sends it as
 * `username`; the backend passes it to wp_signon(), which resolves either.
 */
class LoginIdentifierTest extends TestCase
{
    const PASSWORD = 'correct-horse-9';

    private $user_id;
    private $original_remote_addr;

    public function setUp(): void
    {
        parent::setUp();

        $this->user_id = $this->factory->user->create([
            'user_login' => 'dr_amaka',
            'user_email' => 'amaka.obi@example.com',
            'user_pass'  => self::PASSWORD,
            'role'       => 'doctor',
        ]);

        wp_set_current_user(0);
        // A per-test IP keeps the login rate limit from leaking between tests.
        $this->original_remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand(1, 250);
        delete_transient('login_attempts_' . $_SERVER['REMOTE_ADDR']);

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
        $GLOBALS['wp_rest_server'] = new \Spy_REST_Server();
        (new AuthController())->register_routes();
    }

    public function tearDown(): void
    {
        delete_transient('login_attempts_' . $_SERVER['REMOTE_ADDR']);
        if ($this->original_remote_addr === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
        }
        wp_set_current_user(0);
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    private function login($identifier, $password = self::PASSWORD)
    {
        wp_set_current_user(0);
        $request = new \WP_REST_Request('POST', '/hospital-manager/v1/auth/login');
        $request->set_body_params([
            'username' => $identifier,
            'password' => $password,
            'nonce'    => wp_create_nonce('wp_rest'),
        ]);
        // wp_signon() sets auth cookies; headers are already sent under PHPUnit.
        return @rest_get_server()->dispatch($request);
    }

    public function test_login_with_username()
    {
        $response = $this->login('dr_amaka');

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $data = $response->get_data();
        $this->assertTrue($data['authenticated']);
        $this->assertEquals($this->user_id, $data['user']['ID']);
        $this->assertSame('amaka.obi@example.com', $data['user']['email']);
        $this->assertSame($this->user_id, get_current_user_id());
    }

    public function test_login_with_email()
    {
        $response = $this->login('amaka.obi@example.com');

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertTrue($response->get_data()['authenticated']);
        $this->assertEquals($this->user_id, $response->get_data()['user']['ID']);
    }

    public function test_surrounding_whitespace_is_ignored()
    {
        $this->assertSame(200, $this->login('  dr_amaka  ')->get_status());
        $this->assertSame(200, $this->login(' amaka.obi@example.com ')->get_status());
    }

    public function test_wrong_password_with_username_is_rejected()
    {
        $response = $this->login('dr_amaka', 'wrong-password-1');

        $this->assertSame(401, $response->get_status());
        $this->assertFalse($response->get_data()['authenticated']);
        $this->assertSame('incorrect_password', $response->get_data()['code']);
        $this->assertSame(0, get_current_user_id());
    }

    public function test_wrong_password_with_email_is_rejected()
    {
        $response = $this->login('amaka.obi@example.com', 'wrong-password-1');

        $this->assertSame(401, $response->get_status());
        $this->assertSame('incorrect_password', $response->get_data()['code']);
    }

    public function test_unknown_username_message_mentions_username_or_email()
    {
        $response = $this->login('no_such_user');

        $this->assertSame(401, $response->get_status());
        $this->assertSame('invalid_username', $response->get_data()['code']);
        $this->assertStringContainsString('username or email', $response->get_data()['message']);
    }

    public function test_unknown_email_is_rejected()
    {
        $response = $this->login('nobody@example.com');

        $this->assertSame(401, $response->get_status());
        $this->assertSame('invalid_email', $response->get_data()['code']);
        $this->assertStringContainsString('username or email', $response->get_data()['message']);
    }

    public function test_malformed_email_is_rejected_before_authentication()
    {
        $response = $this->login('amaka@');

        $this->assertSame(400, $response->get_status());
        $this->assertSame('invalid_email', $response->get_data()['code']);
    }

    public function test_failed_username_logins_count_toward_the_rate_limit()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(401, $this->login('dr_amaka', 'wrong-password-1')->get_status());
        }

        // Even the right password is refused once the limit is reached.
        $response = $this->login('dr_amaka');
        $this->assertSame(429, $response->get_status());
        $this->assertSame('rate_limited', $response->get_data()['code']);
    }

    public function test_successful_login_clears_failed_attempts()
    {
        $this->login('dr_amaka', 'wrong-password-1');
        $this->login('dr_amaka', 'wrong-password-1');
        $this->assertSame(200, $this->login('amaka.obi@example.com')->get_status());

        $this->assertFalse(get_transient('login_attempts_' . $_SERVER['REMOTE_ADDR']));
    }

    public function test_missing_nonce_is_rejected()
    {
        $request = new \WP_REST_Request('POST', '/hospital-manager/v1/auth/login');
        $request->set_body_params(['username' => 'dr_amaka', 'password' => self::PASSWORD]);
        $response = rest_get_server()->dispatch($request);

        $this->assertSame(403, $response->get_status());
        $this->assertSame('csrf_failed', $response->get_data()['code']);
    }
}

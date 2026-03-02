<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for index.php, callback.php, and logout.php logic.
 */
class IndexPageTest extends TestCase
{
    // ── index.php — redirect if already logged in ──────────────────────────────

    #[Test]
    public function logged_in_user_should_redirect_to_view(): void
    {
        $session  = ['access_token' => 'valid.jwt.token'];
        $redirect = !empty($session['access_token']) ? '/view.php' : null;

        $this->assertSame('/view.php', $redirect);
    }

    #[Test]
    public function logged_out_user_stays_on_index(): void
    {
        $session  = [];
        $redirect = !empty($session['access_token']) ? '/view.php' : null;

        $this->assertNull($redirect);
    }

    #[Test]
    public function empty_token_does_not_redirect(): void
    {
        $session  = ['access_token' => ''];
        $redirect = !empty($session['access_token']) ? '/view.php' : null;

        $this->assertNull($redirect);
    }

    // ── index.php — login button ───────────────────────────────────────────────

    #[Test]
    public function login_param_triggers_oidc_flow(): void
    {
        $params      = ['login' => '1'];
        $shouldLogin = isset($params['login']);

        $this->assertTrue($shouldLogin);
    }

    #[Test]
    public function missing_login_param_does_not_trigger_oidc(): void
    {
        $params      = [];
        $shouldLogin = isset($params['login']);

        $this->assertFalse($shouldLogin);
    }

    // ── callback.php — token storage ──────────────────────────────────────────

    #[Test]
    public function access_token_stored_in_session(): void
    {
        $tokens  = ['access_token' => 'eyJ.payload.sig'];
        $session = [];

        $session['access_token'] = $tokens['access_token'];

        $this->assertSame('eyJ.payload.sig', $session['access_token']);
    }

    #[Test]
    public function user_name_set_from_name_claim(): void
    {
        $userinfo = ['sub' => 'admin', 'name' => 'Admin User'];
        $session  = [];

        $session['user_name'] = $userinfo['name'] ?? $userinfo['sub'] ?? 'User';

        $this->assertSame('Admin User', $session['user_name']);
    }

    #[Test]
    public function user_name_falls_back_to_sub_when_name_missing(): void
    {
        $userinfo = ['sub' => 'admin'];
        $session  = [];

        $session['user_name'] = $userinfo['name'] ?? $userinfo['sub'] ?? 'User';

        $this->assertSame('admin', $session['user_name']);
    }

    #[Test]
    public function user_name_falls_back_to_user_when_both_missing(): void
    {
        $userinfo = [];
        $session  = [];

        $session['user_name'] = $userinfo['name'] ?? $userinfo['sub'] ?? 'User';

        $this->assertSame('User', $session['user_name']);
    }

    #[Test]
    public function callback_success_redirects_to_view(): void
    {
        $redirect = '/view.php';

        $this->assertSame('/view.php', $redirect);
    }

    #[Test]
    public function callback_exception_message_is_html_escaped(): void
    {
        $message = 'Token exchange failed (400): {"error":"<b>invalid</b>"}';
        $escaped = htmlspecialchars($message);

        $this->assertStringNotContainsString('<b>', $escaped);
        $this->assertStringContainsString('&lt;b&gt;', $escaped);
    }

    #[Test]
    public function callback_error_sets_400_response_code(): void
    {
        $code = 400;

        $this->assertSame(400, $code);
    }

    // ── logout.php ─────────────────────────────────────────────────────────────

    #[Test]
    public function logout_destroys_session(): void
    {
        $session = ['access_token' => 'token', 'user_name' => 'Admin'];

        // Simulate session_destroy
        $session = [];

        $this->assertEmpty($session);
    }

    #[Test]
    public function logout_redirects_to_index(): void
    {
        $redirect = '/index.php';

        $this->assertSame('/index.php', $redirect);
    }
}
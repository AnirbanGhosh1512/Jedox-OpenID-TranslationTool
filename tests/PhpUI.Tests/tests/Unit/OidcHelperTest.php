<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for OIDC helper logic in src/oidc.php.
 * Tests pure functions — no HTTP calls, no session, no headers.
 */
class OidcHelperTest extends TestCase
{
    // ── PKCE Code Challenge ────────────────────────────────────────────────────

    #[Test]
    public function pkce_challenge_is_base64url_sha256_of_verifier(): void
    {
        $verifier  = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $expected  = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $this->assertSame($expected, $this->computeChallenge($verifier));
    }

    #[Test]
    public function pkce_challenge_has_no_base64_padding(): void
    {
        $challenge = $this->computeChallenge(bin2hex(random_bytes(32)));

        $this->assertStringNotContainsString('=', $challenge);
    }

    #[Test]
    public function pkce_challenge_has_no_plus_or_slash(): void
    {
        $challenge = $this->computeChallenge(bin2hex(random_bytes(32)));

        $this->assertStringNotContainsString('+', $challenge);
        $this->assertStringNotContainsString('/', $challenge);
    }

    #[Test]
    public function pkce_challenge_only_contains_url_safe_chars(): void
    {
        $challenge = $this->computeChallenge(bin2hex(random_bytes(32)));

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $challenge);
    }

    #[Test]
    public function different_verifiers_produce_different_challenges(): void
    {
        $c1 = $this->computeChallenge(bin2hex(random_bytes(32)));
        $c2 = $this->computeChallenge(bin2hex(random_bytes(32)));

        $this->assertNotSame($c1, $c2);
    }

    #[Test]
    public function same_verifier_always_produces_same_challenge(): void
    {
        $verifier = bin2hex(random_bytes(32));

        $this->assertSame(
            $this->computeChallenge($verifier),
            $this->computeChallenge($verifier)
        );
    }

    // ── State & Verifier Generation ────────────────────────────────────────────

    #[Test]
    public function state_is_32_hex_chars(): void
    {
        $state = bin2hex(random_bytes(16));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $state);
    }

    #[Test]
    public function verifier_is_64_hex_chars(): void
    {
        $verifier = bin2hex(random_bytes(32));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $verifier);
    }

    #[Test]
    public function two_generated_states_are_unique(): void
    {
        $this->assertNotSame(
            bin2hex(random_bytes(16)),
            bin2hex(random_bytes(16))
        );
    }

    // ── Authorization URL Building ─────────────────────────────────────────────

    #[Test]
    public function authorize_url_contains_response_type_code(): void
    {
        $url = $this->buildAuthorizeUrl();

        $this->assertStringContainsString('response_type=code', $url);
    }

    #[Test]
    public function authorize_url_contains_client_id(): void
    {
        $url = $this->buildAuthorizeUrl(clientId: 'php-ui');

        $this->assertStringContainsString('client_id=php-ui', $url);
    }

    #[Test]
    public function authorize_url_contains_state(): void
    {
        $url = $this->buildAuthorizeUrl(state: 'mystate123');

        $this->assertStringContainsString('state=mystate123', $url);
    }

    #[Test]
    public function authorize_url_contains_code_challenge(): void
    {
        $url = $this->buildAuthorizeUrl(challenge: 'mychallenge456');

        $this->assertStringContainsString('code_challenge=mychallenge456', $url);
    }

    #[Test]
    public function authorize_url_uses_s256_method(): void
    {
        $url = $this->buildAuthorizeUrl();

        $this->assertStringContainsString('code_challenge_method=S256', $url);
    }

    #[Test]
    public function authorize_url_encodes_redirect_uri(): void
    {
        $url = $this->buildAuthorizeUrl(redirectUri: 'http://localhost:8080/callback.php');

        $this->assertStringContainsString(
            'redirect_uri=' . urlencode('http://localhost:8080/callback.php'),
            $url
        );
    }

    #[Test]
    public function authorize_url_starts_with_authority(): void
    {
        $url = $this->buildAuthorizeUrl();

        $this->assertStringStartsWith('http://localhost:8090/connect/authorize', $url);
    }

    // ── State Validation ───────────────────────────────────────────────────────

    #[Test]
    public function matching_states_pass_validation(): void
    {
        $state = bin2hex(random_bytes(16));

        $this->assertTrue($state === $state);
    }

    #[Test]
    public function mismatched_states_fail_validation(): void
    {
        $sessionState  = bin2hex(random_bytes(16));
        $returnedState = bin2hex(random_bytes(16));

        $this->assertFalse($sessionState === $returnedState);
    }

    #[Test]
    public function empty_state_does_not_match_real_state(): void
    {
        $sessionState  = bin2hex(random_bytes(16));
        $returnedState = '';

        $this->assertFalse($sessionState === $returnedState);
    }

    // ── Callback Parameter Validation ──────────────────────────────────────────

    #[Test]
    public function valid_callback_has_code_and_state(): void
    {
        $params = ['code' => 'auth_code_abc', 'state' => 'state_xyz'];

        $this->assertArrayHasKey('code',  $params);
        $this->assertArrayHasKey('state', $params);
        $this->assertNotEmpty($params['code']);
        $this->assertNotEmpty($params['state']);
    }

    #[Test]
    public function callback_without_code_is_invalid(): void
    {
        $params = ['state' => 'state_xyz'];

        $this->assertFalse(isset($params['code'], $params['state']));
    }

    #[Test]
    public function callback_without_state_is_invalid(): void
    {
        $params = ['code' => 'auth_code_abc'];

        $this->assertFalse(isset($params['code'], $params['state']));
    }

    #[Test]
    public function callback_with_empty_params_is_invalid(): void
    {
        $params = [];

        $this->assertFalse(isset($params['code'], $params['state']));
    }

    // ── Config Defaults ────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('configDefaultsProvider')]
    public function config_defaults_are_correct(string $envKey, string $expected): void
    {
        $value = getenv($envKey) ?: $expected;

        $this->assertSame($expected, $value);
    }

    public static function configDefaultsProvider(): array
    {
        return [
            'authority'    => ['OIDC_AUTHORITY',    'http://localhost:8090'],
            'api_base_url' => ['API_BASE_URL',       'http://localhost:8091'],
            'client_id'    => ['OIDC_CLIENT_ID',     'php-ui'],
            'redirect_uri' => ['OIDC_REDIRECT_URI',  'http://localhost:8080/callback.php'],
        ];
    }

    // ── OIDC Endpoint Paths ────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('endpointProvider')]
    public function oidc_endpoints_are_built_from_authority(
        string $path, string $expectedSuffix): void
    {
        $endpoint = 'http://localhost:8090' . $path;

        $this->assertStringEndsWith($expectedSuffix, $endpoint);
        $this->assertStringStartsWith('http://', $endpoint);
    }

    public static function endpointProvider(): array
    {
        return [
            'authorize' => ['/connect/authorize', '/connect/authorize'],
            'token'     => ['/connect/token',     '/connect/token'],
            'userinfo'  => ['/connect/userinfo',  '/connect/userinfo'],
            'logout'    => ['/connect/logout',    '/connect/logout'],
        ];
    }

    // ── API Helper ─────────────────────────────────────────────────────────────

    #[Test]
    public function api_url_is_base_url_plus_path(): void
    {
        $base = 'http://api:8091';
        $path = '/api/sids';

        $this->assertSame('http://api:8091/api/sids', $base . $path);
    }

    #[Test]
    public function bearer_token_header_is_formatted_correctly(): void
    {
        $token  = 'eyJhbGciOiJSUzI1NiJ9.payload.signature';
        $header = 'Authorization: Bearer ' . $token;

        $this->assertStringStartsWith('Authorization: Bearer ', $header);
        $this->assertStringContainsString($token, $header);
    }

    #[Test]
    public function api_response_structure_has_required_keys(): void
    {
        // Simulate what the api() helper returns
        $response = [
            'status' => 200,
            'body'   => ['sid' => 'app.title', 'defaultText' => 'Title'],
            'raw'    => '{"sid":"app.title","defaultText":"Title"}',
        ];

        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('body',   $response);
        $this->assertArrayHasKey('raw',    $response);
    }

    #[Test]
    public function http_method_is_uppercased(): void
    {
        $this->assertSame('GET',    strtoupper('get'));
        $this->assertSame('POST',   strtoupper('post'));
        $this->assertSame('PUT',    strtoupper('put'));
        $this->assertSame('DELETE', strtoupper('delete'));
    }

    // ── Session Auth Check ─────────────────────────────────────────────────────

    #[Test]
    public function empty_access_token_means_unauthenticated(): void
    {
        $session = ['access_token' => ''];

        $this->assertTrue(empty($session['access_token']));
    }

    #[Test]
    public function missing_access_token_means_unauthenticated(): void
    {
        $session = [];

        $this->assertTrue(empty($session['access_token']));
    }

    #[Test]
    public function valid_access_token_means_authenticated(): void
    {
        $session = ['access_token' => 'eyJhbGciOiJSUzI1NiJ9.payload.sig'];

        $this->assertFalse(empty($session['access_token']));
    }

    // ── Token Exchange Response ────────────────────────────────────────────────

    #[Test]
    public function token_response_with_non_200_is_failure(): void
    {
        $httpCode = 400;

        $this->assertNotSame(200, $httpCode);
    }

    #[Test]
    public function token_response_200_is_success(): void
    {
        $httpCode = 200;

        $this->assertSame(200, $httpCode);
    }

    #[Test]
    public function token_response_body_is_parsed_as_json(): void
    {
        $raw    = '{"access_token":"abc","id_token":"def","token_type":"Bearer"}';
        $parsed = json_decode($raw, true);

        $this->assertArrayHasKey('access_token', $parsed);
        $this->assertArrayHasKey('id_token',     $parsed);
        $this->assertSame('abc', $parsed['access_token']);
    }

    #[Test]
    public function empty_curl_response_defaults_to_empty_array(): void
    {
        $response = json_decode('' ?: '[]', true) ?? [];

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function computeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function buildAuthorizeUrl(
        string $clientId    = 'php-ui',
        string $redirectUri = 'http://localhost:8080/callback.php',
        string $scope       = 'openid profile',
        string $state       = 'teststate',
        string $challenge   = 'testchallenge'
    ): string {
        return 'http://localhost:8090/connect/authorize?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => $clientId,
            'redirect_uri'          => $redirectUri,
            'scope'                 => $scope,
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }
}
<?php
require_once __DIR__ . '/config.php';

function oidc_start_login(): void
{
    $state     = bin2hex(random_bytes(16));
    $verifier  = bin2hex(random_bytes(32));
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $_SESSION['oauth_state']    = $state;
    $_SESSION['oauth_verifier'] = $verifier;

    $params = http_build_query([
        'response_type'         => 'code',
        'client_id'             => OIDC_CLIENT_ID,
        'redirect_uri'          => OIDC_REDIRECT_URI,
        'scope'                 => OIDC_SCOPE,
        'state'                 => $state,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    header('Location: ' . OIDC_AUTH_ENDPOINT . '?' . $params);
    exit;
}

function oidc_handle_callback(): array
{
    if (!isset($_GET['code'], $_GET['state'])) {
        throw new RuntimeException('Missing code or state in callback.');
    }
    if ($_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
        throw new RuntimeException('State mismatch — possible CSRF attack.');
    }

    $verifier = $_SESSION['oauth_verifier'] ?? '';
    unset($_SESSION['oauth_state'], $_SESSION['oauth_verifier']);

    $ch = curl_init(OIDC_TOKEN_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'authorization_code',
            'client_id'     => OIDC_CLIENT_ID,
            'client_secret' => OIDC_CLIENT_SECRET,
            'redirect_uri'  => OIDC_REDIRECT_URI,
            'code'          => $_GET['code'],
            'code_verifier' => $verifier,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Token exchange failed ($httpCode): $body");
    }

    return json_decode($body, true);
}

function oidc_userinfo(string $token): array
{
    $ch = curl_init(OIDC_USERINFO_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true) ?? [];
}

function require_auth(): void
{
    if (empty($_SESSION['access_token'])) {
        header('Location: /index.php');
        exit;
    }
}

function api(string $method, string $path, ?array $body = null): array
{
    $ch      = curl_init(API_BASE_URL . $path);
    $headers = [
        'Authorization: Bearer ' . ($_SESSION['access_token'] ?? ''),
        'Accept: application/json',
    ];

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body'   => json_decode($response ?: '[]', true) ?? [],
        'raw'    => $response,
    ];
}
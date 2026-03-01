<?php
session_start();
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/oidc.php';

if (!empty($_SESSION['access_token'])) {
    header('Location: /view.php');
    exit;
}

if (isset($_GET['login'])) {
    oidc_start_login();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Translation Tool – Login</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/index.css">
</head>
<body class="login-page d-flex align-items-center justify-content-center text-white">

<div class="login-card text-center shadow-lg">

    <div class="display-4 mb-2">🌐</div>

    <h1 class="fw-bold">Translation Tool</h1>
    <p class="login-subtitle mb-4">
        Manage localization strings across languages
    </p>

    <a href="?login=1" class="btn btn-primary btn-lg px-4 mb-4">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" class="me-2">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10 17 15 12 10 7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
        Sign in with OIDC
    </a>

    <div class="row g-2 text-start small">
        <div class="col-6">
            <div class="feature-box">
                <strong>🗂️ Manage SIDs</strong><br>
                <span class="text-secondary">Create, edit, delete keys</span>
            </div>
        </div>
        <div class="col-6">
            <div class="feature-box">
                <strong>🌍 Multi-language</strong><br>
                <span class="text-secondary">Any IETF locale</span>
            </div>
        </div>
        <div class="col-6">
            <div class="feature-box">
                <strong>🔄 Fallback</strong><br>
                <span class="text-secondary">Falls back to en-US</span>
            </div>
        </div>
        <div class="col-6">
            <div class="feature-box">
                <strong>🔒 Secure</strong><br>
                <span class="text-secondary">OpenID Connect</span>
            </div>
        </div>
    </div>

</div>
</body>
</html>
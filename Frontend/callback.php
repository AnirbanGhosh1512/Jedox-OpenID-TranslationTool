<?php
session_start();
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/oidc.php';

try {
    $tokens   = oidc_handle_callback();
    $userinfo = oidc_userinfo($tokens['access_token']);

    $_SESSION['access_token'] = $tokens['access_token'];
    $_SESSION['id_token']     = $tokens['id_token'] ?? '';
    $_SESSION['user_name']    = $userinfo['name'] ?? $userinfo['sub'] ?? 'User';
    $_SESSION['user_email']   = $userinfo['email'] ?? '';

    header('Location: /view.php');
    exit;

} catch (Throwable $e) {
    http_response_code(400);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><title>Login Error</title>
<style>
    body { font-family: sans-serif; background:#0f172a; color:#e2e8f0;
           display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .box { background:rgba(220,38,38,.1); border:1px solid rgba(220,38,38,.3);
           border-radius:12px; padding:32px 40px; max-width:500px; }
    h2 { color:#f87171; margin-bottom:12px; }
    a  { color:#667eea; }
</style>
</head>
<body>
<div class="box">
    <h2>⚠️ Login Error</h2>
    <p><?= htmlspecialchars($e->getMessage()) ?></p>
    <p style="margin-top:16px"><a href="/">← Back to login</a></p>
</div>
</body>
</html>
<?php } ?>
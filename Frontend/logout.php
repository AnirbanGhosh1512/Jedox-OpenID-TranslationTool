<?php
session_start();
$idToken = $_SESSION['id_token'] ?? '';
session_destroy();

$params = http_build_query([
    'post_logout_redirect_uri' => 'http://localhost:8080/index.php',
    'id_token_hint'            => $idToken,
]);

header('Location: http://localhost:8090/connect/logout?' . $params);
exit;
<?php
define('OIDC_CLIENT_ID',     getenv('OIDC_CLIENT_ID')     ?: 'php-ui');
define('OIDC_CLIENT_SECRET', getenv('OIDC_CLIENT_SECRET') ?: 'php-secret');
define('OIDC_REDIRECT_URI',  getenv('OIDC_REDIRECT_URI')  ?: 'http://localhost:8080/callback.php');
define('API_BASE_URL',       getenv('API_BASE_URL')       ?: 'http://localhost:8091');

// For browser redirects — must be localhost
define('OIDC_AUTHORITY',     getenv('OIDC_AUTHORITY')     ?: 'http://localhost:8090');

// For server-side calls (PHP → IdP) — must use internal Docker hostname
define('OIDC_INTERNAL',      getenv('OIDC_INTERNAL')      ?: 'http://idp:8090');

define('OIDC_AUTH_ENDPOINT',  OIDC_AUTHORITY . '/connect/authorize');  // browser redirect
define('OIDC_TOKEN_ENDPOINT', OIDC_INTERNAL  . '/connect/token');      // server-to-server
define('OIDC_USERINFO_URL',   OIDC_INTERNAL  . '/connect/userinfo');   // server-to-server
define('OIDC_LOGOUT_URL',     OIDC_AUTHORITY . '/connect/logout');     // browser redirect
define('OIDC_SCOPE',          'openid');
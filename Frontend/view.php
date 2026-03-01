<?php
session_start();
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/oidc.php';
require_auth();

$lang   = $_GET['lang'] ?? 'en-US';
$result = api('GET', '/api/sids/view?lang=' . urlencode($lang));
$sids   = $result['status'] === 200 ? $result['body'] : [];
$error  = $result['status'] !== 200 ? 'API error (HTTP ' . $result['status'] . ')' : null;

$languages = [
    'en-US' => '🇺🇸 English (US)',
    'de-DE' => '🇩🇪 German',
    'fr-FR' => '🇫🇷 French',
    'es-ES' => '🇪🇸 Spanish',
    'it-IT' => '🇮🇹 Italian',
    'ja-JP' => '🇯🇵 Japanese',
    'zh-CN' => '🇨🇳 Chinese (Simplified)',
    'pt-BR' => '🇧🇷 Portuguese (Brazil)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Translation Tool</title>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/view.css">
</head>
<body>
<header>
    <div class="logo">🌐 Translation Tool</div>
    <div class="user">
        <span>👤 <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></span>
        <a href="/logout.php" class="btn-out">Sign out</a>
    </div>
</header>
<main>
    <div class="toolbar">
        <h1>Translations</h1>
        <div class="controls">
            <form method="get">
                <select name="lang" onchange="this.form.submit()">
                    <?php foreach ($languages as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $lang === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="/edit.php?lang=<?= urlencode($lang) ?>" class="btn-new">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="3">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                New SID
            </a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="table-wrap">
        <?php if (empty($sids)): ?>
        <div class="empty">
            <p style="font-size:2rem">📭</p>
            <p>No SIDs yet. Create your first one!</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>SID</th>
                    <th>Text (<?= htmlspecialchars($lang) ?>)</th>
                    <th>Default (en-US)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sids as $row): ?>
                <tr class="row" title="Double-click to edit"
                    ondblclick="location.href='/edit.php?sid=<?= urlencode($row['sid']) ?>&lang=<?= urlencode($lang) ?>'">
                    <td><span class="sid-tag"><?= htmlspecialchars($row['sid']) ?></span></td>
                    <td>
                        <?= htmlspecialchars($row['text']) ?>
                        <?php if ($row['hasFallback'] && $lang !== 'en-US'): ?>
                        <span class="fallback">fallback</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#64748b"><?= htmlspecialchars($row['defaultText']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <p class="hint">💡 Double-click any row to edit its translation.</p>
</main>
</body>
</html>
<?php
session_start();
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/oidc.php';
require_auth();

$sid    = $_GET['sid'] ?? '';
$langId = $_GET['lang'] ?? 'de-DE';
$isNew  = $sid === '';
$msg    = '';
$error  = '';

$sidData = null;
if (!$isNew) {
    $r = api('GET', '/api/sids/' . urlencode($sid));
    if ($r['status'] === 200) {
        $sidData = $r['body'];
    } else {
        $error = 'SID not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $newSid  = trim($_POST['sid'] ?? '');
        $defText = trim($_POST['default_text'] ?? '');
        $tLang   = trim($_POST['lang_id'] ?? '');
        $tText   = trim($_POST['translation_text'] ?? '');

        $body = ['sid' => $newSid, 'defaultText' => $defText, 'translations' => []];

        // Add current language as a translation (so it shows without fallback)
        if ($langId !== 'en-US') {
            $body['translations'][] = ['langId' => $langId, 'text' => $defText];
        }

        if ($tLang && $tText) {
            $body['translations'][] = ['langId' => $tLang, 'text' => $tText];
        }

        $r = api('POST', '/api/sids', $body);
        if ($r['status'] === 201) {
            header('Location: /view.php?lang=' . urlencode($tLang ?: $langId));
            exit;
        }
        $error = 'Could not create SID: ' . ($r['raw'] ?? '');

    } elseif ($action === 'save') {
        $tLang = trim($_POST['lang_id'] ?? '');
        $tText = trim($_POST['translation_text'] ?? '');

        $r = api('PUT',
            '/api/sids/' . urlencode($sid) . '/translations/' . urlencode($tLang),
            ['text' => $tText]);

        if ($r['status'] === 204) {
            $msg    = 'Translation saved.';
            $langId = $tLang;
            $sidData = api('GET', '/api/sids/' . urlencode($sid))['body'];
        } else {
            $error = 'Could not save translation.';
        }

    } elseif ($action === 'delete') {
        $r = api('DELETE', '/api/sids/' . urlencode($sid));
        if ($r['status'] === 204) {
            header('Location: /view.php');
            exit;
        }
        $error = 'Could not delete SID.';
    }
}

$currentTranslation = '';
if ($sidData) {
    foreach ($sidData['translations'] ?? [] as $t) {
        if ($t['langId'] === $langId) {
            $currentTranslation = $t['text'];
            break;
        }
    }
}

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
    <title><?= $isNew ? 'New SID' : 'Edit: ' . htmlspecialchars($sid) ?> – Translation Tool</title>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/edit.css">
</head>
<body>
<header>
    <div class="logo">🌐 Translation Tool</div>
    <a href="/view.php?lang=<?= urlencode($langId) ?>" class="back">← Back to list</a>
</header>
<main>
    <h1><?= $isNew ? '➕ New SID' : '✏️ Edit: ' . htmlspecialchars($sid) ?></h1>

    <?php if ($msg):   ?><div class="msg">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($isNew): ?>
    <div class="card">
        <p class="card-label">New String Identifier</p>
        <form method="post">
            <input type="hidden" name="action" value="create"/>
            <div class="fg">
                <label>SID *</label>
                <input type="text" name="sid" placeholder="e.g. app.welcome" required
                       value="<?= htmlspecialchars($_POST['sid'] ?? '') ?>"/>
            </div>
            <div class="fg">
                <label>Default Text (<?= htmlspecialchars($langId) ?>) *</label>
                <textarea name="default_text" required
                    placeholder="English fallback text"><?= htmlspecialchars($_POST['default_text'] ?? '') ?></textarea>
            </div>
            <div class="fg">
                <label>Initial Translation (optional)</label>
                <select name="lang_id" style="margin-bottom:8px">
                    <option value="">— none —</option>
                    <?php foreach ($languages as $code => $label): ?>
                        <?php if ($code !== $langId): ?>  <!-- exclude current language -->
                        <option value="<?= $code ?>"
                            <?= ($_POST['lang_id'] ?? '') === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                     <?php endif; ?>
                     <?php endforeach; ?>
                </select>
                <textarea name="translation_text"
                    placeholder="Leave empty to skip"><?= htmlspecialchars($_POST['translation_text'] ?? '') ?></textarea>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">✅ Create SID</button>
                <a href="/view.php" class="btn btn-ghost">✖ Discard</a>
            </div>
        </form>
    </div>

    <?php elseif ($sidData): ?>
    <div class="card">
        <p class="card-label">Identifier</p>
        <div class="fg">
            <label>SID</label>
            <div class="sid-pill"><?= htmlspecialchars($sidData['sid']) ?></div>
        </div>
        <div class="fg" style="margin-bottom:0">
            <label>Default Text (en-US) — read only</label>
            <textarea readonly><?= htmlspecialchars($sidData['defaultText']) ?></textarea>
        </div>
    </div>

    <div class="card">
        <p class="card-label">Edit Translation</p>
        <form method="post">
            <input type="hidden" name="action" value="save"/>
            <div class="fg">
                <label>Language</label>
                <select name="lang_id" onchange="this.form.submit()">
                    <?php foreach ($languages as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $langId === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Translation Text</label>
                <textarea name="translation_text" rows="4"
                    placeholder="Enter translation…"><?= htmlspecialchars($currentTranslation) ?></textarea>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">✅ Save</button>
                <a href="/view.php?lang=<?= urlencode($langId) ?>" class="btn btn-ghost">✖ Discard</a>
            </div>
        </form>
    </div>

    <?php if (!empty($sidData['translations'])): ?>
    <div class="card">
        <p class="card-label">All Translations (<?= count($sidData['translations']) ?>)</p>
        <div class="trans-list">
            <?php foreach ($sidData['translations'] as $t): ?>
            <div class="trans-row">
                <span class="trans-lang"><?= htmlspecialchars($t['langId']) ?></span>
                <span class="trans-text"><?= htmlspecialchars($t['text']) ?></span>
                <a href="?sid=<?= urlencode($sid) ?>&lang=<?= urlencode($t['langId']) ?>"
                   class="trans-edit">Edit</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card" style="border-color:rgba(220,38,38,0.2)">
        <p class="card-label" style="color:#f87171">Danger Zone</p>
        <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:16px">
            Deletes this SID and all <?= count($sidData['translations'] ?? []) ?> translation(s) permanently.
        </p>
        <form method="post"
              onsubmit="return confirm('Delete SID &quot;<?= htmlspecialchars(addslashes($sid)) ?>&quot;?\nThis cannot be undone.')">
            <input type="hidden" name="action" value="delete"/>
            <button type="submit" class="btn btn-danger">🗑️ Delete SID</button>
        </form>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
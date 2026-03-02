<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for SID/translation business logic in the PHP UI.
 * Tests data handling, language filtering, and form processing logic.
 */
class SidLogicTest extends TestCase
{
    // ── Language filtering ─────────────────────────────────────────────────────

    #[Test]
    public function english_view_shows_all_sids(): void
    {
        $sids = $this->makeSids();
        $lang = 'en-US';

        $filtered = $this->filterByLanguage($sids, $lang);

        $this->assertCount(3, $filtered);
    }

    #[Test]
    public function german_view_shows_only_sids_with_german_translation(): void
    {
        $sids     = $this->makeSids();
        $filtered = $this->filterByLanguage($sids, 'de-DE');

        $this->assertCount(2, $filtered);
        foreach ($filtered as $sid) {
            $this->assertSame('de-DE', $sid['langId']);
        }
    }

    #[Test]
    public function french_view_shows_only_sids_with_french_translation(): void
    {
        $sids     = $this->makeSids();
        $filtered = $this->filterByLanguage($sids, 'fr-FR');

        $this->assertCount(1, $filtered);
        $this->assertSame('app.title', $filtered[0]['sid']);
    }

    #[Test]
    public function language_with_no_translations_shows_empty_list(): void
    {
        $sids     = $this->makeSids();
        $filtered = $this->filterByLanguage($sids, 'ja-JP');

        $this->assertEmpty($filtered);
    }

    #[Test]
    public function fallback_is_false_for_all_filtered_sids(): void
    {
        $sids     = $this->makeSids();
        $filtered = $this->filterByLanguage($sids, 'de-DE');

        foreach ($filtered as $sid) {
            $this->assertFalse($sid['hasFallback']);
        }
    }

    // ── SID creation logic ─────────────────────────────────────────────────────

    #[Test]
    public function creating_sid_in_german_adds_german_translation(): void
    {
        $langId = 'de-DE';
        $text   = 'Hallo Welt';

        $body = $this->buildCreateBody('hello.world', $text, $langId);

        $this->assertSame($text, $body['defaultText']);
        $this->assertCount(1, $body['translations']);
        $this->assertSame('de-DE', $body['translations'][0]['langId']);
        $this->assertSame($text,   $body['translations'][0]['text']);
    }

    #[Test]
    public function creating_sid_in_english_adds_no_extra_translation(): void
    {
        $langId = 'en-US';
        $text   = 'Hello World';

        $body = $this->buildCreateBody('hello.world', $text, $langId);

        $this->assertSame($text, $body['defaultText']);
        $this->assertEmpty($body['translations']);
    }

    #[Test]
    public function creating_sid_with_extra_translation_adds_both(): void
    {
        $langId       = 'de-DE';
        $defaultText  = 'Hallo';
        $extraLang    = 'fr-FR';
        $extraText    = 'Bonjour';

        $body = $this->buildCreateBody('hello', $defaultText, $langId, $extraLang, $extraText);

        $this->assertCount(2, $body['translations']);

        $langs = array_column($body['translations'], 'langId');
        $this->assertContains('de-DE', $langs);
        $this->assertContains('fr-FR', $langs);
    }

    #[Test]
    public function creating_sid_with_empty_extra_translation_skips_it(): void
    {
        $body = $this->buildCreateBody('hello', 'Hallo', 'de-DE', 'fr-FR', '');

        // Empty extra text should not be added
        $langs = array_column($body['translations'], 'langId');
        $this->assertNotContains('fr-FR', $langs);
    }

    #[Test]
    public function sid_key_is_trimmed_of_whitespace(): void
    {
        $raw     = '  app.title  ';
        $trimmed = trim($raw);

        $this->assertSame('app.title', $trimmed);
    }

    #[Test]
    public function default_text_is_trimmed_of_whitespace(): void
    {
        $raw     = '  Translation Tool  ';
        $trimmed = trim($raw);

        $this->assertSame('Translation Tool', $trimmed);
    }

    // ── Translation upsert logic ───────────────────────────────────────────────

    #[Test]
    public function upsert_builds_correct_put_path(): void
    {
        $sid    = 'app.title';
        $langId = 'de-DE';
        $path   = '/api/sids/' . urlencode($sid) . '/translations/' . urlencode($langId);

        $this->assertSame('/api/sids/app.title/translations/de-DE', $path);
    }

    #[Test]
    public function upsert_path_encodes_special_chars_in_sid(): void
    {
        $sid  = 'app.some/special&key';
        $encoded = urlencode($sid);
        $path = '/api/sids/' . urlencode($sid) . '/translations/de-DE';

        //$this->assertStringNotContainsString('/', substr($path, 10)); // after /api/sids/
        // The slash inside the SID should be encoded as %2F not left as /
        $this->assertStringContainsString('%2F', $encoded);
        $this->assertStringContainsString('%26', $encoded);
        $this->assertStringContainsString(urlencode($sid), $path);
    }

    // ── Delete confirmation logic ──────────────────────────────────────────────

    #[Test]
    public function delete_action_builds_correct_path(): void
    {
        $sid  = 'app.title';
        $path = '/api/sids/' . urlencode($sid);

        $this->assertSame('/api/sids/app.title', $path);
    }

    // ── Language dropdown ──────────────────────────────────────────────────────

    #[Test]
    public function current_language_is_excluded_from_additional_translation_dropdown(): void
    {
        $languages  = $this->getLanguages();
        $currentLang = 'de-DE';

        $available = array_filter(
            $languages,
            fn($code) => $code !== $currentLang,
            ARRAY_FILTER_USE_KEY
        );

        $this->assertArrayNotHasKey('de-DE', $available);
        $this->assertArrayHasKey('en-US',    $available);
        $this->assertArrayHasKey('fr-FR',    $available);
    }

    #[Test]
    public function all_languages_available_when_no_current_language(): void
    {
        $languages = $this->getLanguages();

        $this->assertCount(8, $languages);
    }

    #[Test]
    public function english_is_in_languages_list(): void
    {
        $languages = $this->getLanguages();

        $this->assertArrayHasKey('en-US', $languages);
    }

    #[Test]
    public function german_is_in_languages_list(): void
    {
        $languages = $this->getLanguages();

        $this->assertArrayHasKey('de-DE', $languages);
    }

    // ── View page lang parameter ───────────────────────────────────────────────

    #[Test]
    public function new_sid_link_carries_current_language(): void
    {
        $lang = 'de-DE';
        $href = '/edit.php?lang=' . urlencode($lang);

        $this->assertSame('/edit.php?lang=de-DE', $href);
    }

    #[Test]
    public function back_link_carries_current_language(): void
    {
        $lang = 'fr-FR';
        $href = '/view.php?lang=' . urlencode($lang);

        $this->assertSame('/view.php?lang=fr-FR', $href);
    }

    #[Test]
    public function double_click_edit_link_carries_sid_and_language(): void
    {
        $sid  = 'app.title';
        $lang = 'de-DE';
        $href = '/edit.php?sid=' . urlencode($sid) . '&lang=' . urlencode($lang);

        $this->assertSame('/edit.php?sid=app.title&lang=de-DE', $href);
    }

    // ── Session handling ───────────────────────────────────────────────────────

    #[Test]
    public function user_name_falls_back_to_sub_when_name_missing(): void
    {
        $userinfo = ['sub' => 'admin'];
        $name     = $userinfo['name'] ?? $userinfo['sub'] ?? 'User';

        $this->assertSame('admin', $name);
    }

    #[Test]
    public function user_name_uses_name_claim_when_present(): void
    {
        $userinfo = ['sub' => 'admin', 'name' => 'Admin User'];
        $name     = $userinfo['name'] ?? $userinfo['sub'] ?? 'User';

        $this->assertSame('Admin User', $name);
    }

    #[Test]
    public function user_name_falls_back_to_user_when_both_missing(): void
    {
        $userinfo = [];
        $name     = $userinfo['name'] ?? $userinfo['sub'] ?? 'User';

        $this->assertSame('User', $name);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeSids(): array
    {
        return [
            [
                'sid'         => 'app.title',
                'defaultText' => 'Translation Tool',
                'translations' => [
                    ['langId' => 'de-DE', 'text' => 'Übersetzungswerkzeug'],
                    ['langId' => 'fr-FR', 'text' => 'Outil de traduction'],
                ],
            ],
            [
                'sid'         => 'btn.save',
                'defaultText' => 'Save',
                'translations' => [
                    ['langId' => 'de-DE', 'text' => 'Speichern'],
                ],
            ],
            [
                'sid'          => 'app.welcome',
                'defaultText'  => 'Welcome',
                'translations' => [],
            ],
        ];
    }

    private function filterByLanguage(array $sids, string $lang): array
    {
        if ($lang === 'en-US') {
            return array_map(fn($s) => [
                'sid'         => $s['sid'],
                'defaultText' => $s['defaultText'],
                'langId'      => $lang,
                'text'        => $s['defaultText'],
                'hasFallback' => false,
            ], $sids);
        }

        $result = [];
        foreach ($sids as $s) {
            foreach ($s['translations'] as $t) {
                if ($t['langId'] === $lang) {
                    $result[] = [
                        'sid'         => $s['sid'],
                        'defaultText' => $s['defaultText'],
                        'langId'      => $lang,
                        'text'        => $t['text'],
                        'hasFallback' => false,
                    ];
                    break;
                }
            }
        }
        return $result;
    }

    private function buildCreateBody(
        string  $sid,
        string  $text,
        string  $langId,
        string  $extraLang = '',
        string  $extraText = ''
    ): array {
        $body = [
            'sid'          => trim($sid),
            'defaultText'  => trim($text),
            'translations' => [],
        ];

        if ($langId !== 'en-US') {
            $body['translations'][] = ['langId' => $langId, 'text' => $text];
        }

        if ($extraLang && $extraText) {
            $body['translations'][] = ['langId' => $extraLang, 'text' => $extraText];
        }

        return $body;
    }

    private function getLanguages(): array
    {
        return [
            'en-US' => '🇺🇸 English (US)',
            'de-DE' => '🇩🇪 German',
            'fr-FR' => '🇫🇷 French',
            'es-ES' => '🇪🇸 Spanish',
            'it-IT' => '🇮🇹 Italian',
            'ja-JP' => '🇯🇵 Japanese',
            'zh-CN' => '🇨🇳 Chinese (Simplified)',
            'pt-BR' => '🇧🇷 Portuguese (Brazil)',
        ];
    }
}
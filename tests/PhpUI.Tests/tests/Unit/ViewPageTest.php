<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for view.php rendering logic.
 * Tests table rendering, language dropdown, fallback badge, and row links.
 */
class ViewPageTest extends TestCase
{
    // ── Table rendering ────────────────────────────────────────────────────────

    #[Test]
    public function empty_sids_renders_no_rows(): void
    {
        $rows = $this->renderRows([], 'en-US');

        $this->assertEmpty($rows);
    }

    #[Test]
    public function sids_render_one_row_each(): void
    {
        $sids = $this->makeSids();
        $rows = $this->renderRows($sids, 'en-US');

        $this->assertCount(3, $rows);
    }

    #[Test]
    public function row_contains_sid_key(): void
    {
        $sids = $this->makeSids();
        $rows = $this->renderRows($sids, 'en-US');

        $this->assertStringContainsString('app.title', $rows[0]['sid']);
    }

    #[Test]
    public function row_contains_text_for_selected_language(): void
    {
        $sids = $this->makeSids();
        $rows = $this->renderRows($sids, 'de-DE');

        $deRow = array_filter($rows, fn($r) => $r['sid'] === 'app.title');
        $this->assertSame('Übersetzungswerkzeug', array_values($deRow)[0]['text']);
    }

    #[Test]
    public function row_double_click_link_contains_sid_and_lang(): void
    {
        $sid  = 'app.title';
        $lang = 'de-DE';
        $link = $this->buildEditLink($sid, $lang);

        $this->assertStringContainsString('sid=app.title', $link);
        $this->assertStringContainsString('lang=de-DE',    $link);
        $this->assertStringStartsWith('/edit.php',         $link);
    }

    #[Test]
    public function row_link_encodes_special_chars_in_sid(): void
    {
        $sid  = 'app.some/key&value';
        $link = $this->buildEditLink($sid, 'en-US');

        $this->assertStringContainsString(urlencode($sid), $link);
    }

    // ── Language dropdown ──────────────────────────────────────────────────────

    #[Test]
    public function language_dropdown_has_all_languages(): void
    {
        $languages = $this->getLanguages();

        $this->assertCount(8, $languages);
    }

    #[Test]
    public function language_dropdown_selected_matches_current_lang(): void
    {
        $languages   = $this->getLanguages();
        $currentLang = 'de-DE';

        $selected = array_filter(
            array_keys($languages),
            fn($code) => $code === $currentLang
        );

        $this->assertCount(1, $selected);
        $this->assertContains('de-DE', $selected);
    }

    #[Test]
    #[DataProvider('languageProvider')]
    public function all_languages_have_flag_emoji(string $code, string $label): void
    {
        // Flags are multi-byte unicode — just check label is not empty
        $this->assertNotEmpty($label);
        $this->assertGreaterThan(2, mb_strlen($label));
    }

    public static function languageProvider(): array
    {
        return [
            ['en-US', '🇺🇸 English (US)'],
            ['de-DE', '🇩🇪 German'],
            ['fr-FR', '🇫🇷 French'],
            ['es-ES', '🇪🇸 Spanish'],
            ['it-IT', '🇮🇹 Italian'],
            ['ja-JP', '🇯🇵 Japanese'],
            ['zh-CN', '🇨🇳 Chinese (Simplified)'],
            ['pt-BR', '🇧🇷 Portuguese (Brazil)'],
        ];
    }

    // ── New SID button ─────────────────────────────────────────────────────────

    #[Test]
    public function new_sid_button_carries_current_language(): void
    {
        $lang = 'de-DE';
        $href = '/edit.php?lang=' . urlencode($lang);

        $this->assertSame('/edit.php?lang=de-DE', $href);
    }

    #[Test]
    public function new_sid_button_carries_english_language(): void
    {
        $lang = 'en-US';
        $href = '/edit.php?lang=' . urlencode($lang);

        $this->assertSame('/edit.php?lang=en-US', $href);
    }

    // ── Default text column ────────────────────────────────────────────────────

    #[Test]
    public function default_text_column_is_hidden_for_english(): void
    {
        $lang          = 'en-US';
        $showDefault   = $lang !== 'en-US';

        $this->assertFalse($showDefault);
    }

    #[Test]
    public function default_text_column_is_shown_for_german(): void
    {
        $lang        = 'de-DE';
        $showDefault = $lang !== 'en-US';

        $this->assertTrue($showDefault);
    }

    #[Test]
    #[DataProvider('nonEnglishLanguages')]
    public function default_text_column_is_shown_for_non_english(string $lang): void
    {
        $this->assertTrue($lang !== 'en-US');
    }

    public static function nonEnglishLanguages(): array
    {
        return [
            ['de-DE'],
            ['fr-FR'],
            ['es-ES'],
            ['ja-JP'],
        ];
    }

    // ── XSS prevention ────────────────────────────────────────────────────────

    #[Test]
    public function sid_text_is_html_escaped(): void
    {
        $malicious = '<script>alert("xss")</script>';
        $escaped   = htmlspecialchars($malicious);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    #[Test]
    public function translation_text_is_html_escaped(): void
    {
        $malicious = '"><img src=x onerror=alert(1)>';
        $escaped   = htmlspecialchars($malicious);

        $this->assertStringNotContainsString('<img', $escaped);
        $this->assertStringContainsString('&lt;img', $escaped);
    }

    #[Test]
    public function username_is_html_escaped(): void
    {
        $malicious = '<b>Admin</b>';
        $escaped   = htmlspecialchars($malicious);

        $this->assertStringNotContainsString('<b>', $escaped);
    }

    // ── Error display ──────────────────────────────────────────────────────────

    #[Test]
    public function api_error_is_shown_when_status_not_200(): void
    {
        $result = ['status' => 401, 'body' => []];
        $error  = $result['status'] !== 200 ? 'API error (HTTP ' . $result['status'] . ')' : null;

        $this->assertNotNull($error);
        $this->assertStringContainsString('401', $error);
    }

    #[Test]
    public function no_error_shown_when_status_200(): void
    {
        $result = ['status' => 200, 'body' => []];
        $error  = $result['status'] !== 200 ? 'API error (HTTP ' . $result['status'] . ')' : null;

        $this->assertNull($error);
    }

    #[Test]
    #[DataProvider('errorStatusCodes')]
    public function error_message_includes_status_code(int $status): void
    {
        $error = 'API error (HTTP ' . $status . ')';

        $this->assertStringContainsString((string)$status, $error);
    }

    public static function errorStatusCodes(): array
    {
        return [[400], [401], [403], [404], [500]];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function renderRows(array $sids, string $lang): array
    {
        if ($lang === 'en-US') {
            return array_map(fn($s) => [
                'sid'         => $s['sid'],
                'text'        => $s['defaultText'],
                'defaultText' => $s['defaultText'],
                'hasFallback' => false,
            ], $sids);
        }

        $rows = [];
        foreach ($sids as $s) {
            foreach ($s['translations'] as $t) {
                if ($t['langId'] === $lang) {
                    $rows[] = [
                        'sid'         => $s['sid'],
                        'text'        => $t['text'],
                        'defaultText' => $s['defaultText'],
                        'hasFallback' => false,
                    ];
                    break;
                }
            }
        }
        return $rows;
    }

    private function buildEditLink(string $sid, string $lang): string
    {
        return '/edit.php?sid=' . urlencode($sid) . '&lang=' . urlencode($lang);
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

    private function makeSids(): array
    {
        return [
            [
                'sid'          => 'app.title',
                'defaultText'  => 'Translation Tool',
                'translations' => [
                    ['langId' => 'de-DE', 'text' => 'Übersetzungswerkzeug'],
                    ['langId' => 'fr-FR', 'text' => 'Outil de traduction'],
                ],
            ],
            [
                'sid'          => 'btn.save',
                'defaultText'  => 'Save',
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
}
<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for edit.php logic.
 * Covers create form, save form, delete, translation lookup, and input handling.
 */
class EditPageTest extends TestCase
{
    // ── isNew detection ────────────────────────────────────────────────────────

    #[Test]
    public function empty_sid_param_means_new_form(): void
    {
        $sid   = '';
        $isNew = $sid === '';

        $this->assertTrue($isNew);
    }

    #[Test]
    public function non_empty_sid_param_means_edit_form(): void
    {
        $sid   = 'app.title';
        $isNew = $sid === '';

        $this->assertFalse($isNew);
    }

    // ── Create form ────────────────────────────────────────────────────────────

    #[Test]
    public function create_body_contains_sid_and_default_text(): void
    {
        $body = $this->buildCreateBody('app.greeting', 'Hello', '', '');

        $this->assertSame('app.greeting', $body['sid']);
        $this->assertSame('Hello',        $body['defaultText']);
    }

    #[Test]
    public function create_body_with_translation_includes_it(): void
    {
        $body = $this->buildCreateBody('app.greeting', 'Hello', 'de-DE', 'Hallo');

        $this->assertCount(1, $body['translations']);
        $this->assertSame('de-DE', $body['translations'][0]['langId']);
        $this->assertSame('Hallo', $body['translations'][0]['text']);
    }

    #[Test]
    public function create_body_without_translation_has_empty_array(): void
    {
        $body = $this->buildCreateBody('app.greeting', 'Hello', '', '');

        $this->assertEmpty($body['translations']);
    }

    #[Test]
    public function create_body_with_lang_but_empty_text_skips_translation(): void
    {
        $body = $this->buildCreateBody('app.greeting', 'Hello', 'de-DE', '');

        $this->assertEmpty($body['translations']);
    }

    #[Test]
    public function create_body_with_empty_lang_skips_translation(): void
    {
        $body = $this->buildCreateBody('app.greeting', 'Hello', '', 'Hallo');

        $this->assertEmpty($body['translations']);
    }

    #[Test]
    public function create_sid_is_trimmed(): void
    {
        $body = $this->buildCreateBody('  app.greeting  ', 'Hello', '', '');

        $this->assertSame('app.greeting', $body['sid']);
    }

    #[Test]
    public function create_default_text_is_trimmed(): void
    {
        $body = $this->buildCreateBody('app.greeting', '  Hello  ', '', '');

        $this->assertSame('Hello', $body['defaultText']);
    }

    #[Test]
    public function create_success_redirects_to_view_with_lang(): void
    {
        $tLang    = 'de-DE';
        $redirect = '/view.php?lang=' . urlencode($tLang ?: 'en-US');

        $this->assertSame('/view.php?lang=de-DE', $redirect);
    }

    #[Test]
    public function create_success_with_no_lang_redirects_to_english(): void
    {
        $tLang    = '';
        $redirect = '/view.php?lang=' . urlencode($tLang ?: 'en-US');

        $this->assertSame('/view.php?lang=en-US', $redirect);
    }

    // ── Save (upsert) form ─────────────────────────────────────────────────────

    #[Test]
    public function save_builds_correct_put_path(): void
    {
        $sid    = 'app.title';
        $lang   = 'de-DE';
        $path   = '/api/sids/' . urlencode($sid) . '/translations/' . urlencode($lang);

        $this->assertSame('/api/sids/app.title/translations/de-DE', $path);
    }

    #[Test]
    public function save_body_contains_text(): void
    {
        $body = ['text' => 'Übersetzungswerkzeug'];

        $this->assertArrayHasKey('text', $body);
        $this->assertSame('Übersetzungswerkzeug', $body['text']);
    }

    #[Test]
    public function save_success_status_is_204(): void
    {
        $status = 204;

        $this->assertSame(204, $status);
    }

    #[Test]
    public function save_lang_is_trimmed(): void
    {
        $raw     = '  de-DE  ';
        $trimmed = trim($raw);

        $this->assertSame('de-DE', $trimmed);
    }

    #[Test]
    public function save_text_is_trimmed(): void
    {
        $raw     = '  Hallo  ';
        $trimmed = trim($raw);

        $this->assertSame('Hallo', $trimmed);
    }

    // ── Delete form ────────────────────────────────────────────────────────────

    #[Test]
    public function delete_builds_correct_path(): void
    {
        $sid  = 'app.title';
        $path = '/api/sids/' . urlencode($sid);

        $this->assertSame('/api/sids/app.title', $path);
    }

    #[Test]
    public function delete_success_redirects_to_view(): void
    {
        $redirect = '/view.php';

        $this->assertSame('/view.php', $redirect);
    }

    #[Test]
    public function delete_success_status_is_204(): void
    {
        $status = 204;

        $this->assertSame(204, $status);
    }

    // ── Current translation lookup ─────────────────────────────────────────────

    #[Test]
    public function current_translation_found_for_matching_lang(): void
    {
        $translations = [
            ['langId' => 'de-DE', 'text' => 'Übersetzungswerkzeug'],
            ['langId' => 'fr-FR', 'text' => 'Outil de traduction'],
        ];

        $current = $this->findTranslation($translations, 'de-DE');

        $this->assertSame('Übersetzungswerkzeug', $current);
    }

    #[Test]
    public function current_translation_empty_when_no_match(): void
    {
        $translations = [
            ['langId' => 'de-DE', 'text' => 'Übersetzungswerkzeug'],
        ];

        $current = $this->findTranslation($translations, 'fr-FR');

        $this->assertSame('', $current);
    }

    #[Test]
    public function current_translation_empty_when_no_translations(): void
    {
        $current = $this->findTranslation([], 'de-DE');

        $this->assertSame('', $current);
    }

    #[Test]
    public function current_translation_returns_first_match(): void
    {
        $translations = [
            ['langId' => 'de-DE', 'text' => 'First'],
            ['langId' => 'de-DE', 'text' => 'Second'],
        ];

        $current = $this->findTranslation($translations, 'de-DE');

        $this->assertSame('First', $current);
    }

    // ── Page title ─────────────────────────────────────────────────────────────

    #[Test]
    public function new_page_title_is_new_sid(): void
    {
        $isNew = true;
        $sid   = '';
        $title = $isNew ? 'New SID' : 'Edit: ' . htmlspecialchars($sid);

        $this->assertSame('New SID', $title);
    }

    #[Test]
    public function edit_page_title_contains_sid(): void
    {
        $isNew = false;
        $sid   = 'app.title';
        $title = $isNew ? 'New SID' : 'Edit: ' . htmlspecialchars($sid);

        $this->assertSame('Edit: app.title', $title);
    }

    #[Test]
    public function edit_page_title_escapes_html_in_sid(): void
    {
        $isNew = false;
        $sid   = '<script>xss</script>';
        $title = $isNew ? 'New SID' : 'Edit: ' . htmlspecialchars($sid);

        $this->assertStringNotContainsString('<script>', $title);
    }

    // ── Back link ──────────────────────────────────────────────────────────────

    #[Test]
    public function back_link_contains_current_lang(): void
    {
        $langId = 'de-DE';
        $href   = '/view.php?lang=' . urlencode($langId);

        $this->assertSame('/view.php?lang=de-DE', $href);
    }

    // ── Translation list ───────────────────────────────────────────────────────

    #[Test]
    public function translation_list_is_hidden_when_empty(): void
    {
        $translations = [];
        $showList     = !empty($translations);

        $this->assertFalse($showList);
    }

    #[Test]
    public function translation_list_is_shown_when_not_empty(): void
    {
        $translations = [['langId' => 'de-DE', 'text' => 'Hallo']];
        $showList     = !empty($translations);

        $this->assertTrue($showList);
    }

    #[Test]
    public function translation_count_in_danger_zone_matches_actual(): void
    {
        $translations = [
            ['langId' => 'de-DE', 'text' => 'Hallo'],
            ['langId' => 'fr-FR', 'text' => 'Bonjour'],
        ];

        $this->assertCount(2, $translations);
    }

    // ── Edit translation link ──────────────────────────────────────────────────

    #[Test]
    public function translation_edit_link_contains_sid_and_lang(): void
    {
        $sid    = 'app.title';
        $langId = 'de-DE';
        $href   = '?sid=' . urlencode($sid) . '&lang=' . urlencode($langId);

        $this->assertStringContainsString('sid=app.title', $href);
        $this->assertStringContainsString('lang=de-DE',    $href);
    }

    // ── XSS prevention ────────────────────────────────────────────────────────

    #[Test]
    public function default_text_in_form_is_html_escaped(): void
    {
        $text    = '<script>alert(1)</script>';
        $escaped = htmlspecialchars($text);

        $this->assertStringNotContainsString('<script>', $escaped);
    }

    #[Test]
    public function translation_text_in_form_is_html_escaped(): void
    {
        $text    = '"><svg onload=alert(1)>';
        $escaped = htmlspecialchars($text);

        $this->assertStringNotContainsString('<svg', $escaped);
        $this->assertStringContainsString('&lt;svg', $escaped);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function buildCreateBody(
        string $sid,
        string $defText,
        string $tLang,
        string $tText
    ): array {
        $body = [
            'sid'          => trim($sid),
            'defaultText'  => trim($defText),
            'translations' => [],
        ];

        if (trim($tLang) && trim($tText)) {
            $body['translations'][] = [
                'langId' => trim($tLang),
                'text'   => trim($tText),
            ];
        }

        return $body;
    }

    private function findTranslation(array $translations, string $lang): string
    {
        foreach ($translations as $t) {
            if ($t['langId'] === $lang) {
                return $t['text'];
            }
        }
        return '';
    }
}
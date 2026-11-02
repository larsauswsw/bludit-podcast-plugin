<?php

/** Roadmap 014: Oberfläche in der Sitesprache (Deutsch, Englisch), Regel 1 und 4. */
class LanguageTest extends PodcastTestCase
{
    private const DIR = PATH_PLUGINS . 'podcast' . DS . 'languages' . DS;

    /** Sprachdatei als Array. */
    private function file(string $name): array
    {
        $data = json_decode((string) file_get_contents(self::DIR . $name), true);
        $this->assertIsArray($data, $name . ': ' . json_last_error_msg());
        return $data;
    }

    /** Plugin neu erzeugen, nachdem die Sitesprache geändert wurde. */
    private function useLanguage(string $code): void
    {
        $GLOBALS['site']->lang = $code;
        $this->plugin = new Podcast();
    }

    /** Platzhalter {name} eines Textes, sortiert. */
    private function placeholders(string $text): array
    {
        preg_match_all('/\{([a-z]+)\}/', $text, $m);
        sort($m[1]);
        return $m[1];
    }

    public function testLanguageFilesAreValid(): void
    {
        foreach (['en.json', 'de_DE.json', 'de_AT.json', 'de_CH.json'] as $name) {
            $data = $this->file($name);
            $this->assertIsString($data['plugin-data']['name'] ?? null, $name);
            $this->assertIsString($data['plugin-data']['description'] ?? null, $name);
            $this->assertNotEmpty($data['podcast-texts'] ?? null, $name);
            foreach ($data['podcast-texts'] as $key => $text) {
                $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/D', $key, $name);
                $this->assertIsString($text, $name . ': ' . $key);
                $this->assertNotSame('', trim($text), $name . ': ' . $key);
            }
        }
    }

    public function testGermanAndEnglishHaveTheSameTextsAndPlaceholders(): void
    {
        $en = $this->file('en.json')['podcast-texts'];
        $de = $this->file('de_DE.json')['podcast-texts'];
        $this->assertSame(array_keys($en), array_keys($de), 'gleiche Schlüssel in gleicher Reihenfolge');
        foreach ($en as $key => $text) {
            $this->assertSame($this->placeholders($text), $this->placeholders($de[$key]), $key);
        }
    }

    /** de_AT ist eine Kopie, de_CH schreibt ss statt ß (wie Bludits eigene Sprachdateien). */
    public function testGermanVariantsFollowGermanFile(): void
    {
        $de = (string) file_get_contents(self::DIR . 'de_DE.json');
        $this->assertSame($de, file_get_contents(self::DIR . 'de_AT.json'), 'de_AT.json: cp de_DE.json de_AT.json');
        $this->assertSame(str_replace('ß', 'ss', $de), file_get_contents(self::DIR . 'de_CH.json'),
            'de_CH.json: sed \'s/ß/ss/g\' de_DE.json > de_CH.json');
    }

    /** Jeder Schlüssel im Code steht in en.json, und jeder Text in en.json wird verwendet. */
    public function testEveryKeyIsDefinedAndUsed(): void
    {
        $php  = (string) file_get_contents(PATH_PLUGINS . 'podcast' . DS . 'plugin.php');
        $js   = (string) file_get_contents(PATH_PLUGINS . 'podcast' . DS . 'js' . DS . 'podcast-admin.js');
        $used = [];
        preg_match_all('/->(?:text|html)\(\s*\'([a-z0-9-]+)\'/', $php, $m);
        $used = array_merge($used, $m[1]);
        preg_match_all('/uploadText\([^,()]+,\s*\'([a-z0-9-]+)\'/', $php, $m);
        foreach ($m[1] as $key) {
            $used[] = 'image-' . $key;
            $used[] = 'audio-' . $key;
        }
        preg_match_all('/\bt\(\s*\'([a-z0-9-]+)\'/', $js, $m);
        foreach ($m[1] as $key) {
            $used[] = 'js-' . $key;
        }
        $used    = array_unique($used);
        $defined = array_keys($this->file('en.json')['podcast-texts']);

        $this->assertSame([], array_values(array_diff($used, $defined)), 'im Code verwendet, aber nicht in en.json');
        $this->assertSame([], array_values(array_diff($defined, $used)), 'in en.json, aber nirgends verwendet');
    }

    public function testGermanCategoryNamesCoverAppleList(): void
    {
        $names = [];
        foreach ((new ReflectionClassConstant(Podcast::class, 'APPLE_CATEGORIES'))->getValue() as $category => $subs) {
            $names = array_merge($names, [$category], $subs);
        }
        $german = $this->file('de_DE.json')['podcast-apple-categories'];
        $this->assertEqualsCanonicalizing(array_unique($names), array_keys($german));
        $this->assertSame('Kunst', $german['Arts']);
        $this->assertArrayNotHasKey('podcast-apple-categories', $this->file('en.json'), 'Englisch: Namen aus dem Code');
    }

    public function testTextsFollowSiteLanguage(): void
    {
        $this->assertSame('Kein Schlüssel angegeben', $this->call('handleDeleteEpisode', '')['error']);
        $this->assertSame('Folge 7', $this->call('text', 'episode-n', ['n' => 7]));

        $this->useLanguage('en');
        $this->assertSame('No key given', $this->call('handleDeleteEpisode', '')['error']);
        $this->assertSame('Episode 7', $this->call('text', 'episode-n', ['n' => 7]));

        $this->useLanguage('de_CH');
        $this->assertSame('Max. Upload-Grösse (MB)', $this->call('text', 'max-upload'));
    }

    /** Sprachen ohne eigene Datei (wie bei Bludit) und ungültige Codes: Englisch. */
    public function testOtherLanguagesFallBackToEnglish(): void
    {
        foreach (['fr_FR', '../../../tmp/x', 'de_DE.json', '', 'DE'] as $code) {
            $this->useLanguage($code);
            $this->assertSame('Channel settings', $this->call('text', 'channel-settings'), $code);
            $this->assertSame('Arts', $this->call('categoryName', 'Arts'), $code);
        }
        $this->useLanguage('de_DE');
        $this->assertSame('unbekannter-schluessel', $this->call('text', 'unbekannter-schluessel'));
    }

    /** Nur Zeichenketten aus der Sprachdatei übernehmen (Regel 1). */
    public function testLanguageFileKeepsOnlyStrings(): void
    {
        $path = $this->tempFile(json_encode(['podcast-texts' => ['a' => 'A', 'b' => ['x'], 'c' => 3, 7 => 'sieben'],
            'podcast-apple-categories' => ['Arts' => 'Kunst', 'News' => null]]));
        $this->assertSame(['texts' => ['a' => 'A'], 'categories' => ['Arts' => 'Kunst']],
            $this->call('readLanguageFile', $path));
        $this->assertSame(['texts' => [], 'categories' => []], $this->call('readLanguageFile', $this->tempFile('{kein json')));
        $this->assertSame(['texts' => [], 'categories' => []], $this->call('readLanguageFile', self::DIR . 'fehlt.json'));
    }

    /** Regel 4: Text und Werte escaped, nur Markup aus dem Code bleibt HTML. */
    public function testHtmlEscapesTextAndValues(): void
    {
        $this->assertSame('Aktionen für &lt;b&gt;Tom &amp; &quot;Jerry&quot;&lt;/b&gt;',
            $this->call('html', 'actions-for', ['title' => '<b>Tom & "Jerry"</b>']));
        $this->assertSame('Apple Podcasts &amp; Verzeichnisse', $this->call('html', 'group-directories'));
        $this->assertStringContainsString('(<code>podcast:locked</code>)',
            $this->call('html', 'feed-locked-tip', [], ['tag' => '<code>podcast:locked</code>']));
        // Ersetzt wird nur einmal: ein Platzhalter im eingesetzten Wert bleibt Text
        $this->assertSame('Aktionen für {title}', $this->call('html', 'actions-for', ['title' => '{title}']));
    }

    public function testCategorySelectShowsSiteLanguage(): void
    {
        $german = $this->call('categorySelect');
        $this->assertStringContainsString('>Kunst (Arts)</option>', $german);
        $this->assertStringContainsString('>Kunst → Mode und Schönheit (Fashion &amp; Beauty)</option>', $german);
        $this->assertStringContainsString('>Bitte wählen …</option>', $german);

        $this->useLanguage('en');
        $english = $this->call('categorySelect');
        $this->assertStringContainsString('<option value="Arts">Arts</option>', $english);
        $this->assertStringContainsString('>Arts → Fashion &amp; Beauty</option>', $english);
        $this->assertStringNotContainsString('Kunst', $english);
        // Nach englischem Namen sortiert: Arts vor Business vor Comedy
        $this->assertLessThan(strpos($english, 'label="Business"'), strpos($english, 'label="Arts"'));
        $this->assertLessThan(strpos($english, 'label="Comedy"'), strpos($english, 'label="Business"'));
    }

    public function testNumbersUseDecimalSeparatorOfLanguage(): void
    {
        $this->assertSame('0,5', $this->call('formatMB', 524288));
        $this->assertSame('2', $this->call('formatMB', 2097152));
        $this->useLanguage('en');
        $this->assertSame('0.5', $this->call('formatMB', 524288));
    }

    /** Texte für das Skript: nur js-Schlüssel ohne Präfix, JSON nach Regel 4. */
    public function testAdminScriptPassesTextsAsJson(): void
    {
        $this->useLanguage('en');
        $html = $this->call('adminScript', 'https://example.org/blog/admin/plugin/podcast?podcast_api=1&x=<y>');
        $this->assertSame(1, preg_match('/var PODCAST_TEXTS = (\{.*?\}); var PODCAST_API_URL = (".*?");/', $html, $m));
        $texts = json_decode($m[1], true);
        $this->assertSame('New episode', $texts['new-episode']);
        $this->assertSame('.', $texts['decimal-separator']);
        $this->assertArrayNotHasKey('channel-settings', $texts);
        $this->assertSame('https://example.org/blog/admin/plugin/podcast?podcast_api=1&x=<y>', json_decode($m[2]));
        $this->assertStringNotContainsString('<y>', $html);
        $this->assertStringNotContainsString('&x', $html);
        $this->assertStringContainsString('js/podcast-admin.js?version=', $html);
        // Ohne API-Adresse (Kanaleinstellungen) nur die Texte
        $this->assertStringNotContainsString('PODCAST_API_URL', $this->call('adminScript'));
    }
}

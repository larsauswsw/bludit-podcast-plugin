<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/BluditClient.php';

/**
 * Integrationstest gegen eine frisch installierte Bludit-Instanz (Roadmap 036,
 * Teil 2). Läuft nur mit BLUDIT_URL und BLUDIT_ADMIN_PASSWORD, siehe
 * .github/workflows/ci.yml. Die Tests bauen aufeinander auf (@depends).
 */
class PluginIntegrationTest extends TestCase
{
    /** @var BluditClient|null */
    private static $admin;

    private static function url(): string
    {
        return (string) getenv('BLUDIT_URL');
    }

    protected function setUp(): void
    {
        if (self::url() === '' || (string) getenv('BLUDIT_ADMIN_PASSWORD') === '') {
            $this->markTestSkipped('BLUDIT_URL und BLUDIT_ADMIN_PASSWORD nicht gesetzt.');
        }
    }

    private static function mp3(): string
    {
        return "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\xFF\xFB\x90\x64" . str_repeat("\x00", 413), 20);
    }

    private static function file(string $content, string $name, string $mime): CURLFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'podcast-upload-');
        file_put_contents($path, $content);
        return new CURLFile($path, $mime, $name);
    }

    public function testLoginAndActivatePlugin(): void
    {
        self::$admin = new BluditClient(self::url());
        self::$admin->login('admin', (string) getenv('BLUDIT_ADMIN_PASSWORD'));
        self::$admin->get('/admin/install-plugin/Podcast');
        $this->assertStringContainsString('configure-plugin/Podcast', (string) self::$admin->location);
        $html = self::$admin->get('/admin/configure-plugin/Podcast');
        $this->assertStringContainsString('Apple Podcasts &amp; Verzeichnisse', $html);
    }

    /** @depends testLoginAndActivatePlugin */
    public function testSaveChannelSettings(): void
    {
        $admin = self::$admin;
        $token = BluditClient::token($admin->get('/admin/configure-plugin/Podcast'));
        $admin->post('/admin/configure-plugin/Podcast', [
            'tokenCSRF' => $token, 'save' => '',
            'feedTitle' => 'Test & "Podcast"', 'feedDesc' => 'Beschreibung des Testpodcasts.',
            'author' => 'Testautor', 'email' => 'owner@example.org', 'feedLang' => 'de-de',
            'cover' => 'https://example.org/cover.jpg', 'copyright' => '© 2026 Test',
            'category' => 'Kids & Family > Parenting', 'showType' => 'serial', 'explicit' => 'no',
            'feedLocked' => 'no', 'feedSlug' => 'podcast.xml', 'feedUrl' => '', 'siteUrl' => '',
        ]);
        $html = $admin->get('/admin/configure-plugin/Podcast');
        $this->assertStringContainsString('value="Test &amp; &quot;Podcast&quot;"', $html, 'genau einmal escaped');
        $this->assertMatchesRegularExpression('/value="Kids &amp; Family &gt; Parenting" selected/', $html);
        $this->assertMatchesRegularExpression('/id="podcast-guidDisplay" value="[0-9a-f-]{36}"/', $html);
    }

    /** @depends testSaveChannelSettings */
    public function testCreateEpisodesViaApi(): string
    {
        $admin  = self::$admin;
        $result = $admin->api([
            'podcast_api_action' => 'save_episode', 'key' => '', 'title' => 'Folge 1 & "Start"',
            'content' => '<p>Erste <strong>Folge</strong> mit <a href="/ueber" onclick="x()">Link</a>.</p><script>alert(1)</script>',
            'episode' => '1', 'season' => '1', 'type' => 'full', 'explicit' => 'no', 'duration' => '00:10:00',
            'date' => '2026-10-01T10:00', 'status' => 'published', 'image_url' => '', 'audio_url' => '',
            'audio_file' => self::file(self::mp3(), 'folge1.mp3', 'audio/mpeg'),
        ]);
        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $this->assertArrayNotHasKey('warning', $result);

        // Externe Adresse ins interne Netz: gespeichert, aber ohne Abfrage (Hinweis)
        $second = $admin->api([
            'podcast_api_action' => 'save_episode', 'key' => '', 'title' => 'Folge 2',
            'content' => 'Zweite Folge.', 'episode' => '2', 'season' => '1', 'type' => 'full', 'explicit' => 'no',
            'duration' => '', 'date' => '2026-10-02T10:00', 'status' => 'published', 'image_url' => '',
            'audio_url' => 'http://127.0.0.1/folge2.mp3',
        ]);
        $this->assertTrue($second['success'] ?? false, json_encode($second));
        $this->assertStringContainsString('internes Netz', (string) ($second['warning'] ?? ''));
        return (string) $result['key'];
    }

    /** @depends testCreateEpisodesViaApi */
    public function testFeedIsValidForApple(string $key): void
    {
        $xml = (new BluditClient(self::url()))->get('/podcast.xml');
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'Feed ist wohlgeformtes XML');
        $x = new DOMXPath($doc);
        $x->registerNamespace('itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
        $x->registerNamespace('podcast', 'https://podcastindex.org/namespace/1.0');
        $x->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
        $x->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
        $value = function (string $query) use ($x): string {
            $node = $x->query($query)->item(0);
            return $node ? trim($node->nodeValue) : '';
        };

        $this->assertSame('Test & "Podcast"', $value('/rss/channel/title'));
        $this->assertSame('Kids & Family', $value('/rss/channel/itunes:category/@text'));
        $this->assertSame('Parenting', $value('/rss/channel/itunes:category/itunes:category/@text'));
        $this->assertSame('serial', $value('/rss/channel/itunes:type'));
        $this->assertSame('false', $value('/rss/channel/itunes:explicit'));
        $this->assertSame('https://example.org/cover.jpg', $value('/rss/channel/itunes:image/@href'));
        $this->assertSame('https://example.org/cover.jpg', $value('/rss/channel/image/url'));
        $this->assertSame('© 2026 Test', $value('/rss/channel/copyright'));
        $this->assertStringEndsWith('/podcast.xml', $value('/rss/channel/atom:link[@rel="self"]/@href'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $value('/rss/channel/podcast:guid'));
        $this->assertSame('no', $value('/rss/channel/podcast:locked'));
        $this->assertSame('owner@example.org', $value('/rss/channel/podcast:locked/@owner'));

        $item = '/rss/channel/item[title=\'Folge 1 & "Start"\']';
        $this->assertSame((string) strlen(self::mp3()), $value($item . '/enclosure/@length'));
        $this->assertSame('audio/mpeg', $value($item . '/enclosure/@type'));
        $this->assertSame('false', $value($item . '/itunes:explicit'));
        $notes = $value($item . '/content:encoded');
        $this->assertStringContainsString('<strong>Folge</strong>', $notes);
        $this->assertStringNotContainsString('onclick', $notes);
        $this->assertStringNotContainsString('<script', $notes);
        $this->assertSame(0, $x->query('//itunes:summary')->length);
        $this->assertSame('0', $value('/rss/channel/item[title="Folge 2"]/enclosure/@length'));
    }

    /** @depends testCreateEpisodesViaApi */
    public function testEpisodePageHasPlayerAndDiscoveryLink(string $key): void
    {
        $html = (new BluditClient(self::url()))->get('/' . $key);
        $this->assertStringContainsString('podlove-episode-player', $html);
        $this->assertMatchesRegularExpression('#<link rel="alternate" type="application/rss\+xml" title="Test &amp; &quot;Podcast&quot;" href="[^"]+/podcast\.xml">#', $html);
    }

    /** @depends testCreateEpisodesViaApi */
    public function testApiRejectsUnsafeInput(string $key): void
    {
        $admin = self::$admin;
        $base  = ['podcast_api_action' => 'save_episode', 'content' => 'Text', 'date' => '2026-10-03T10:00',
            'status' => 'published', 'audio_url' => 'https://example.org/a.mp3', 'image_url' => '', 'type' => 'full'];

        $this->assertSame('Titel ist erforderlich', $admin->api($base + ['key' => '', 'title' => ['Array']])['error'] ?? null);
        $this->assertSame('Episode nicht gefunden', $admin->api($base + ['key' => '../../databases/users', 'title' => 'X'])['error'] ?? null);
        $this->assertSame('Episode nicht gefunden',
            $admin->api(['podcast_api_action' => 'delete_episode', 'episode_key' => '../' . $key])['error'] ?? null);

        // Eine normale Bludit-Seite (ohne Tag podcast) ist keine Episode
        preg_match_all('#edit-content/([a-z0-9-]+)#', $admin->get('/admin/content'), $m);
        $foreign = array_values(array_diff(array_unique($m[1]), [$key, 'folge-2']));
        $this->assertNotEmpty($foreign, 'Bludit legt bei der Installation Beispielseiten an');
        $this->assertSame('Seite gehört nicht zum Podcast', $admin->api($base + ['key' => $foreign[0], 'title' => 'X'])['error'] ?? null);
        $this->assertSame('Seite gehört nicht zum Podcast',
            $admin->api(['podcast_api_action' => 'delete_episode', 'episode_key' => $foreign[0]])['error'] ?? null);

        // Upload mit falschem Inhalt
        $upload = $admin->api(['podcast_api_action' => 'save_episode', 'key' => '', 'title' => 'Böse', 'content' => 'x',
            'date' => '2026-10-03T10:00', 'status' => 'draft', 'audio_url' => '', 'image_url' => '', 'type' => 'full',
            'audio_file' => self::file('<?php echo "x"; ?>', 'evil.mp3', 'audio/mpeg')]);
        $this->assertStringContainsString('passt inhaltlich nicht', (string) ($upload['error'] ?? ''));

        $this->assertSame('Unbekannte Aktion', $admin->api(['podcast_api_action' => 'drop_everything'])['error'] ?? null);
    }

    /** @depends testApiRejectsUnsafeInput */
    public function testEditorRights(): void
    {
        $admin = self::$admin;
        // Zufälliges Passwort für das Wegwerf-Konto (kein fester Wert im Repository)
        $secret = 'r-' . bin2hex(random_bytes(8));
        $token  = BluditClient::token($admin->get('/admin/new-user'));
        $admin->post('/admin/new-user', ['tokenCSRF' => $token, 'new_username' => 'redakteur',
            'new_' . 'password' => $secret, 'confirm_' . 'password' => $secret,
            'role' => 'editor', 'email' => 'redakteur@example.org']);

        $editor = new BluditClient(self::url());
        $editor->login('redakteur', $secret);
        $editor->get('/admin/configure-plugin/Podcast');
        $this->assertTrue($editor->redirected(), 'Kanaleinstellungen nur für Admins');

        $cover = $editor->api(['podcast_api_action' => 'upload_cover',
            'cover_file' => self::file(str_repeat('x', 10), 'c.png', 'image/png')]);
        $this->assertSame('Das Cover dürfen nur Admins ändern.', $cover['error'] ?? null);

        $saved = $editor->api(['podcast_api_action' => 'save_episode', 'key' => '', 'title' => 'Vom Redakteur',
            'content' => 'Text', 'date' => '2026-10-04T10:00', 'status' => 'draft', 'image_url' => '',
            'audio_url' => 'https://example.org/r.mp3', 'type' => 'full']);
        $this->assertArrayHasKey('success', $saved, 'Redakteure dürfen Episoden anlegen');
    }

    /** Ohne CSRF-Token meldet Bludit ab; der Aufruf verändert nichts. @depends testEditorRights */
    public function testApiWithoutCsrfTokenEndsSession(): void
    {
        $admin  = self::$admin;
        $result = $admin->api(['podcast_api_action' => 'save_episode', 'key' => '', 'title' => 'Ohne Token',
            'content' => 'x', 'date' => '2026-10-05T10:00', 'status' => 'published', 'image_url' => '',
            'audio_url' => 'https://example.org/t.mp3', 'type' => 'full'], false);
        $this->assertArrayNotHasKey('success', $result);
        $this->assertTrue($admin->redirected());
        $this->assertStringContainsString('login', (string) $admin->location);

        // Sitzung beendet: Bludit zeigt statt der Episodenliste das Anmeldeformular
        $page = $admin->get('/admin/plugin/podcast');
        $this->assertStringContainsString('name="password"', $page);
        $this->assertStringNotContainsString('podcast-episode-list', $page);
        $feed = (new BluditClient(self::url()))->get('/podcast.xml');
        $this->assertStringNotContainsString('Ohne Token', $feed);
    }
}

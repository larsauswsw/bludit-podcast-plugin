<?php

/** Regel 1 und 4: Eingaben prüfen, Ausgaben genau einmal escapen. */
class EscapingAndInputTest extends PodcastTestCase
{
    public function testXmlEscapesOnceAndReplacesInvalidCharacters(): void
    {
        $this->assertSame('Tom &amp; Jerry &quot;Folge&quot; &lt;1&gt; &#039;x&#039;',
            $this->call('xml', 'Tom & Jerry "Folge" <1> \'x\''));
        $this->assertStringNotContainsString("\x01", $this->call('xml', "a\x01b"));
        $this->assertSame('a' . "\u{FFFD}" . 'b', $this->call('xml', "a\xC3b"));
    }

    public function testSettingReturnsDecodedValue(): void
    {
        $this->setting('feedTitle', 'Tom & Jerry "Folge"');
        $this->assertSame('Tom & Jerry "Folge"', $this->call('setting', 'feedTitle'));
    }

    public function testPostStringRejectsArraysAndTrims(): void
    {
        $post = ['title' => '  Folge  ', 'list' => ['a', 'b'], 'num' => 5];
        $this->assertSame('Folge', $this->call('postString', $post, 'title'));
        $this->assertSame('', $this->call('postString', $post, 'list'));
        $this->assertSame('', $this->call('postString', $post, 'num'));
        $this->assertSame('', $this->call('postString', $post, 'missing'));
    }

    /** @dataProvider urlProvider */
    public function testIsHttpUrl(string $url, bool $expected): void
    {
        $this->assertSame($expected, $this->call('isHttpUrl', $url));
    }

    public function urlProvider(): array
    {
        return [
            ['https://example.org/a.mp3', true],
            ['http://example.org', true],
            ['HTTPS://EXAMPLE.ORG/', true],
            ['javascript:alert(1)', false],
            ['data:text/html,x', false],
            ['ftp://example.org/a', false],
            ['//example.org/a', false],
            ['/relative/path', false],
            ['', false],
        ];
    }

    /** @dataProvider durationProvider */
    public function testIsValidDuration($value, bool $expected): void
    {
        $this->assertSame($expected, $this->call('isValidDuration', $value));
    }

    public function durationProvider(): array
    {
        return [
            ['3600', true], ['45:30', true], ['01:02:03', true], ['123:59:59', true],
            ['45:99', false], ['1:2', false], ['abc', false], ['', false], ["01:02:03\n", false], [['1'], false],
        ];
    }

    public function testSanitizeFilename(): void
    {
        $this->assertSame('folge-1-ubung', $this->call('sanitizeFilename', 'Folge 1 Übung'));
        $this->assertSame('etcpasswd', $this->call('sanitizeFilename', '../../etc/passwd'));
        $this->assertSame('upload', $this->call('sanitizeFilename', '***'));
    }

    public function testAudioMimeType(): void
    {
        $this->assertSame('audio/mpeg', $this->call('audioMimeType', 'mp3'));
        $this->assertSame('audio/x-m4a', $this->call('audioMimeType', 'm4a'));
    }

    public function testNormalizeMetaRejectsInvalidValues(): void
    {
        $meta = $this->call('normalizeMeta', [
            'audio_url'       => 'javascript:alert(1)',
            'audio_length'    => '-5',
            'itunes_duration' => '45:99',
            'itunes_episode'  => '3',
            'itunes_season'   => ['1'],
            'itunes_type'     => 'evil',
            'itunes_explicit' => 'maybe',
            'itunes_image'    => 'https://example.org/b.jpg',
        ]);
        $this->assertSame('', $meta['audio_url']);
        $this->assertSame('0', $meta['audio_length']);
        $this->assertSame('', $meta['itunes_duration']);
        $this->assertSame('3', $meta['itunes_episode']);
        $this->assertSame('', $meta['itunes_season']);
        $this->assertSame('full', $meta['itunes_type']);
        $this->assertSame('no', $meta['itunes_explicit']);
        $this->assertSame('https://example.org/b.jpg', $meta['itunes_image']);
    }

    /** @dataProvider dateProvider */
    public function testEpisodeDate($value, ?string $expected): void
    {
        $this->assertSame($expected, $this->call('episodeDate', $value));
    }

    public function dateProvider(): array
    {
        return [
            ['2026-11-02T06:00', '2026-11-02 06:00:00'],
            ['2026-11-02T06:00:30', '2026-11-02 06:00:30'],
            ['2026-03-29T02:30', '2026-03-29 02:30:00'],
            ['2026-02-30T10:00', null],
            ['2026-11-02 06:00', null],
            ['2026-11-02T25:00', null],
            ['', null],
            [['2026-11-02T06:00'], null],
        ];
    }

    public function testEnumSettingsFallBackToDefaults(): void
    {
        $this->setting('playerMode', 'evil');
        $this->setting('playerPosition', 'middle');
        $this->setting('showType', 'other');
        $this->setting('feedLocked', 'maybe');
        $this->assertSame('podlove', $this->call('playerMode'));
        $this->assertSame('top', $this->call('playerPosition'));
        $this->assertSame('episodic', $this->call('showType'));
        $this->assertFalse($this->call('feedLocked'));
    }
}

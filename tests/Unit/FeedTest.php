<?php

/** Feed-Adresse, Kategorien, GUID, Shownotes (Roadmap 016, 017, 019). */
class FeedTest extends PodcastTestCase
{
    public function testFeedUrlFallsBackToLocalAddress(): void
    {
        $this->assertSame('https://example.org/blog/podcast.xml', $this->call('feedUrl'));
        $this->setting('feedSlug', '/feeds/mein podcast.xml');
        $this->assertSame('https://example.org/blog/feeds/mein%20podcast.xml', $this->call('localFeedUrl'));
    }

    public function testFeedUrlOverrideOnlyWithHttp(): void
    {
        $this->setting('feedUrl', 'https://cdn.example.com/feed.xml');
        $this->assertSame('https://cdn.example.com/feed.xml', $this->call('feedUrl'));
        $this->setting('feedUrl', 'javascript:alert(1)');
        $this->assertSame('https://example.org/blog/podcast.xml', $this->call('feedUrl'));
    }

    public function testSiteAndCoverUrlOnlyWithHttp(): void
    {
        $this->setting('siteUrl', 'ftp://example.org');
        $this->assertSame(DOMAIN_BASE, $this->call('siteUrl'));
        $this->setting('cover', 'javascript:alert(1)');
        $this->assertSame('', $this->call('coverUrl'));
        $this->setting('cover', 'https://example.org/c.jpg');
        $this->assertSame('https://example.org/c.jpg', $this->call('coverUrl'));
    }

    public function testAppleCategoryListMatchesApple(): void
    {
        $reflection = new ReflectionClassConstant(Podcast::class, 'APPLE_CATEGORIES');
        $categories = $reflection->getValue();
        $this->assertCount(19, $categories);
        $this->assertSame(91, array_sum(array_map(function ($c) { return count($c[1]); }, $categories)));
    }

    /** @dataProvider categoryProvider */
    public function testAppleCategoryAcceptsOnlyListedValues(string $stored, ?array $expected): void
    {
        $this->setting('category', $stored);
        $this->assertSame($expected, $this->call('appleCategory'));
    }

    public function categoryProvider(): array
    {
        return [
            ['Society & Culture', ['Society & Culture', '']],
            ['sports > tennis', ['Sports', 'Tennis']],
            ['Kids & Family > Parenting', ['Kids & Family', 'Parenting']],
            ['Gesellschaft', null],
            ['Sports > Curling', null],
            ['', null],
        ];
    }

    public function testCategorySelectEscapesAndPreselects(): void
    {
        $this->setting('category', 'Kids & Family > Parenting');
        $html = $this->call('categorySelect');
        $this->assertStringContainsString('value="Kids &amp; Family &gt; Parenting" selected', $html);
        $this->setting('category', '<script>alert(1)</script>');
        $html = $this->call('categorySelect');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testUuidV5MatchesPodcastingSpecExample(): void
    {
        $this->assertSame('917393e3-1b1e-5cef-ace4-edaa54e1f810',
            $this->call('uuidV5', 'ead4c236-bf58-58c6-a2c6-a6b28d128cb6', 'mp3s.nashownotes.com/pc20rss.xml'));
    }

    public function testPodcastGuidIsStoredOnceAndStable(): void
    {
        $first = $this->call('podcastGuid');
        $this->call('ensurePodcastGuid');
        $this->setting('feedUrl', 'https://cdn.example.com/moved.xml');
        $this->assertSame($first, $this->call('podcastGuid'));
    }

    public function testShownotesKeepWhitelistAndDropEverythingElse(): void
    {
        $html = '<p onclick="x()">Tom &amp; Jerry <strong>live</strong> <a href="/ueber" style="c">Über</a>, '
            . '<a href="javascript:alert(1)">böse</a> <a href="mailto:a@b.de">Mail</a></p>'
            . '<script>alert(1)</script><iframe src="https://evil"></iframe><!-- c -->'
            . '<div><span>Eingepackt</span><img src="x" onerror="x()"></div><ul><li>Eins</li></ul>';
        $notes = $this->call('shownotesHtml', $html);
        $this->assertStringContainsString('<p>Tom &amp; Jerry <strong>live</strong>', $notes);
        $this->assertStringContainsString('<a href="https://example.org/ueber">Über</a>', $notes);
        $this->assertStringContainsString('<a href="mailto:a@b.de">Mail</a>', $notes);
        $this->assertStringContainsString('böse', $notes);
        $this->assertStringContainsString('<ul><li>Eins</li></ul>', $notes);
        foreach (['javascript:', 'onclick', 'onerror', 'style=', '<script', 'alert(1)', '<iframe', '<img', '<div', '<span', '<!--'] as $bad) {
            $this->assertStringNotContainsString($bad, $notes, $bad);
        }
    }

    public function testCdataSplitsTerminatorAndRemovesControlCharacters(): void
    {
        $xml = '<x>' . $this->call('cdata', "a ]]> b\x01 c") . '</x>';
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $this->assertSame('a ]]> b c', $doc->documentElement->textContent);
    }

    public function testEpisodeTextSummaryDropsScriptsAndLimitsLength(): void
    {
        $this->assertSame('a b', $this->call('episodeTextSummary', '<p>a</p><script>alert(1)</script><p>b</p>'));
        $text = $this->call('episodeTextSummary', '<p>' . str_repeat('Wort &amp; ', 1200) . '</p>');
        $this->assertLessThanOrEqual(4000, mb_strlen($text));
        $this->assertStringEndsWith('…', $text);
        $this->assertDoesNotMatchRegularExpression('/&[a-z]*…$/', $text);
    }
}

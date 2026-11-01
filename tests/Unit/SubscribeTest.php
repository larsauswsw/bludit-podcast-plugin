<?php

/** Abo-Button: Links zu Diensten und App-Liste (Roadmap 017, #79). */
class SubscribeTest extends PodcastTestCase
{
    /** @dataProvider serviceLinkProvider */
    public function testServiceIdFromLink(string $field, string $link, string $expected): void
    {
        $this->setting($field, $link);
        $this->assertSame($expected, $this->call('serviceId', $field), $link);
    }

    public function serviceLinkProvider(): array
    {
        return [
            ['linkApple', 'https://podcasts.apple.com/de/podcast/bewusst-papa/id1234567890', 'id1234567890'],
            ['linkApple', 'https://podcasts.apple.com/us/podcast/x/id1234?i=1000', 'id1234'],
            ['linkApple', 'https://evil.example/podcast/id1', ''],
            ['linkApple', 'http://podcasts.apple.com/podcast/id1', ''],
            ['linkApple', 'https://podcasts.apple.com.evil.example/podcast/id1', ''],
            ['linkSpotify', 'https://open.spotify.com/show/4rOoJ6Egrf8K2IrywzwOMk?si=abc', '4rOoJ6Egrf8K2IrywzwOMk'],
            ['linkSpotify', 'https://open.spotify.com/episode/4rOoJ6Egrf8K2IrywzwOMk', ''],
            ['linkPocketCasts', 'https://pca.st/s7qtomm0', 's7qtomm0'],
            ['linkPocketCasts', 'https://pca.st/itunes/1325018583', 'itunes/1325018583'],
            ['linkPocketCasts', 'https://pca.st/../x', ''],
            ['linkDeezer', 'https://www.deezer.com/de/show/123456', '123456'],
            ['linkDeezer', 'https://deezer.page.link/abc', ''],
            ['linkCastbox', 'https://castbox.fm/channel/Mein-Podcast-id1234567?country=de', 'id1234567'],
        ];
    }

    public function testClientsWithoutServiceLinks(): void
    {
        $clients = $this->call('subscribeClients', 'https://example.org/blog/podcast.xml');
        $ids = array_column($clients, 'id');
        $this->assertContains('overcast', $ids);
        $this->assertContains('antenna-pod', $ids);
        $this->assertNotContains('apple-podcasts', $ids);
        $this->assertNotContains('pocket-casts', $ids);
        $this->assertSame(['id' => 'rss', 'service' => 'https://example.org/blog/podcast.xml'], end($clients));
    }

    public function testAppleLinkAddsCastroAndPocketCasts(): void
    {
        $this->setting('linkApple', 'https://podcasts.apple.com/de/podcast/x/id42');
        $clients = $this->call('subscribeClients', 'https://example.org/blog/podcast.xml');
        $this->assertContains(['id' => 'apple-podcasts', 'service' => 'id42'], $clients);
        $this->assertContains(['id' => 'pocket-casts', 'service' => 'itunes/42'], $clients);
        $this->assertContains(['id' => 'castro', 'service' => 'id42'], $clients);
    }
}

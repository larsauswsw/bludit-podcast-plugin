<?php

/** Regel 8: Dateigröße externer Audio-URLs nur von öffentlichen Zielen (Roadmap 016). */
class OutboundRequestTest extends PodcastTestCase
{
    /** @dataProvider ipProvider */
    public function testIsPublicIp(string $ip, bool $expected): void
    {
        $this->assertSame($expected, $this->call('isPublicIp', $ip), $ip);
    }

    public function ipProvider(): array
    {
        return [
            ['8.8.8.8', true],
            ['93.184.216.34', true],
            ['2606:4700:4700::1111', true],
            ['127.0.0.1', false],
            ['10.1.2.3', false],
            ['172.16.0.1', false],
            ['192.168.1.1', false],
            ['169.254.169.254', false],
            ['100.64.0.1', false],
            ['0.0.0.0', false],
            ['224.0.0.1', false],
            ['255.255.255.255', false],
            ['::1', false],
            ['::', false],
            ['fc00::1', false],
            ['fd12:3456::1', false],
            ['fe80::1', false],
            ['::ffff:127.0.0.1', false],
            ['64:ff9b::7f00:1', false],
            ['2002:7f00:1::1', false],
            ['2001:db8::1', false],
            ['not-an-ip', false],
        ];
    }

    public function testIpInRange(): void
    {
        $this->assertTrue($this->call('ipInRange', '100.127.255.255', '100.64.0.0/10'));
        $this->assertFalse($this->call('ipInRange', '100.128.0.0', '100.64.0.0/10'));
        $this->assertTrue($this->call('ipInRange', 'fe80::abcd', 'fe80::/10'));
        $this->assertFalse($this->call('ipInRange', '10.0.0.1', 'fe80::/10'));
    }

    /** @dataProvider blockedTargetProvider */
    public function testPublicTargetRejectsUnsafeAddresses(string $url): void
    {
        $result = $this->call('publicTarget', $url);
        $this->assertArrayHasKey('error', $result, $url);
    }

    public function blockedTargetProvider(): array
    {
        return [
            ['http://127.0.0.1/a.mp3'],
            ['http://169.254.169.254/latest/meta-data'],
            ['http://10.0.0.1/a.mp3'],
            ['http://[::1]/a.mp3'],
            ['http://[::ffff:127.0.0.1]/a.mp3'],
            ['http://localhost/a.mp3'],
            ['http://2130706433/a.mp3'],
            ['http://0x7f.1/a.mp3'],
            ['http://example.com:8080/a.mp3'],
            ['http://user:pw@example.com/a.mp3'],
            ['ftp://example.com/a.mp3'],
            ['file:///etc/passwd'],
            ['javascript:alert(1)'],
        ];
    }

    public function testPublicTargetAcceptsPublicIpLiteral(): void
    {
        $this->assertSame(['host' => '8.8.8.8', 'port' => 443, 'ip' => '8.8.8.8'],
            $this->call('publicTarget', 'https://8.8.8.8/a.mp3'));
    }

    public function testRemoteAudioLengthDoesNotContactInternalTargets(): void
    {
        $result = $this->call('remoteAudioLength', 'http://127.0.0.1/a.mp3');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('internes Netz', $result['error']);
    }

    /** @dataProvider locationProvider */
    public function testResolveLocation(string $base, string $location, string $expected): void
    {
        $this->assertSame($expected, $this->call('resolveLocation', $base, $location));
    }

    public function locationProvider(): array
    {
        return [
            ['https://a.example/x/y.mp3', 'https://b.example/z.mp3', 'https://b.example/z.mp3'],
            ['https://a.example/x/y.mp3', '//b.example/z.mp3', 'https://b.example/z.mp3'],
            ['https://a.example/x/y.mp3', '/z.mp3', 'https://a.example/z.mp3'],
            ['https://a.example/x/y.mp3', 'z.mp3', 'https://a.example/x/z.mp3'],
            ['https://a.example/x/y.mp3', 'file:///etc/passwd', 'file:///etc/passwd'],
        ];
    }
}

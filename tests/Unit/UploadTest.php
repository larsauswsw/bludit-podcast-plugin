<?php

/** Regel 2 und 6: Upload-Prüfung, Cover-Vorgaben, lokale Pfade (Roadmap 011, 022). */
class UploadTest extends PodcastTestCase
{
    private function upload(string $content, string $name): array
    {
        $path = $this->tempFile($content);
        return ['name' => $name, 'tmp_name' => $path, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK];
    }

    private function mp3(): string
    {
        return "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\xFF\xFB\x90\x64" . str_repeat("\x00", 413), 20);
    }

    public function testValidUploadsPass(): void
    {
        $this->assertSame('mp3', $this->call('validateUpload', $this->upload($this->mp3(), 'folge.mp3'), 'audio'));
        $this->assertSame('png', $this->call('validateUpload', $this->upload($this->pngHeader(10, 10), 'bild.PNG'), 'image'));
    }

    /** @dataProvider rejectedUploadProvider */
    public function testRejectedUploads(string $type, string $kind, string $name): void
    {
        $content = $kind === 'png' ? $this->pngHeader(10, 10) : ($kind === 'mp3' ? $this->mp3() : '<?php echo 1; ?>');
        $this->expectException(RuntimeException::class);
        $this->call('validateUpload', $this->upload($content, $name), $type);
    }

    public function rejectedUploadProvider(): array
    {
        return [
            'PHP als MP3'         => ['audio', 'php', 'evil.mp3'],
            'Bild als MP3'        => ['audio', 'png', 'bild.mp3'],
            'MP3 als Bild'        => ['image', 'mp3', 'folge.png'],
            'SVG'                 => ['image', 'php', 'bild.svg'],
            'PHP-Endung'          => ['image', 'png', 'shell.php'],
            'doppelte Endung'     => ['image', 'php', 'bild.png.php'],
            'Audio ohne Endung'   => ['audio', 'mp3', 'folge'],
        ];
    }

    public function testUploadLargerThanSettingIsRejected(): void
    {
        $this->setting('maxUploadMB', '1');
        $file = $this->upload($this->mp3(), 'folge.mp3');
        $file['size'] = 2 * 1024 * 1024;
        $this->expectException(RuntimeException::class);
        $this->call('validateUpload', $file, 'audio');
    }

    public function testUploadWithArrayFieldsIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->call('validateUpload', ['name' => ['a.mp3'], 'tmp_name' => ['x'], 'size' => [1], 'error' => [0]], 'audio');
    }

    /** @dataProvider coverProvider */
    public function testCoverProblems(string $kind, int $width, int $height, int $channels, string $expected): void
    {
        $content = $kind === 'png' ? $this->pngHeader($width, $height) : $this->jpegHeader($width, $height, $channels);
        $problems = $this->call('coverProblems', $this->tempFile($content));
        $this->assertSame($expected, implode(' ', $problems));
    }

    public function coverProvider(): array
    {
        return [
            'passt'       => ['png', 1400, 1400, 3, ''],
            'Höchstmaß'   => ['jpg', 3000, 3000, 3, ''],
            'rechteckig'  => ['png', 1600, 1200, 3, 'Es ist nicht quadratisch (1600 × 1200 px).'],
            'zu klein'    => ['png', 800, 800, 3, 'Es ist zu klein (800 × 800 px).'],
            'zu groß'     => ['jpg', 3200, 3200, 3, 'Es ist zu groß (3200 × 3200 px).'],
            'CMYK'        => ['jpg', 1400, 1400, 4, 'Es ist im CMYK-Farbraum gespeichert, Apple verlangt RGB.'],
        ];
    }

    public function testCoverProblemsForUnreadableFile(): void
    {
        $this->assertSame(['Die Bildgröße ließ sich nicht lesen.'], $this->call('coverProblems', $this->tempFile('kein Bild')));
        $this->assertSame(['Die Bildgröße ließ sich nicht lesen.'], $this->call('coverProblems', '/nicht/vorhanden.png'));
    }

    public function testLocalPathsOnlyInsideUploadFolders(): void
    {
        $images = PATH_UPLOADS . 'podcast' . DS . 'images' . DS;
        $audio  = PATH_UPLOADS . 'podcast' . DS . 'audio' . DS;
        @mkdir($images, 0755, true);
        @mkdir($audio, 0755, true);
        file_put_contents($images . 'cover-abc123.png', $this->pngHeader(1400, 1400));
        file_put_contents($audio . 'folge-abc123.mp3', $this->mp3());

        $this->assertSame(realpath($images . 'cover-abc123.png'),
            $this->call('localImagePath', DOMAIN_UPLOADS . 'podcast/images/cover-abc123.png'));
        $this->assertSame(strlen($this->mp3()),
            $this->call('localAudioLength', DOMAIN_UPLOADS . 'podcast/audio/folge-abc123.mp3'));

        foreach (['../../../etc/passwd', '..%2F..%2Fx.png', 'cover-abc123.png?x=1', 'sub/cover.png', 'cover.php'] as $name) {
            $this->assertSame('', $this->call('localImagePath', DOMAIN_UPLOADS . 'podcast/images/' . $name), $name);
            $this->assertSame(0, $this->call('localAudioLength', DOMAIN_UPLOADS . 'podcast/audio/' . $name), $name);
        }
        $this->assertSame('', $this->call('localImagePath', 'https://evil.example/podcast/images/cover-abc123.png'));
    }

    public function testCoverUploadOnlyForAdmins(): void
    {
        $GLOBALS['testRole'] = 'editor';
        $result = $this->call('handleCoverUpload', []);
        $this->assertSame(['error' => 'Das Cover dürfen nur Admins ändern.'], $result);
    }
}

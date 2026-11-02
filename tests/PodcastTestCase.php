<?php

use PHPUnit\Framework\TestCase;

/**
 * Basis für Unit-Tests: frisches Plugin je Test, Aufruf privater Methoden per
 * Reflection, Einstellungen so gespeichert wie von Bludit (HTML-escaped).
 */
abstract class PodcastTestCase extends TestCase
{
    /** @var Podcast */
    protected $plugin;

    protected function setUp(): void
    {
        $GLOBALS['testRole'] = 'admin';
        $GLOBALS['site']->lang = 'de_DE';
        Log::$messages = [];
        $this->plugin = new Podcast();
    }

    /** Ruft eine private Methode des Plugins auf. */
    protected function call(string $method, ...$args)
    {
        $reflection = new ReflectionMethod(Podcast::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->plugin, $args);
    }

    /** Setzt eine Einstellung wie Plugin::post() (escaped gespeichert). */
    protected function setting(string $field, $value): void
    {
        $this->plugin->db[$field] = is_string($value) ? Sanitize::html($value) : $value;
    }

    /** Bildkopf (PNG, nur IHDR), reicht getimagesize() für Breite/Höhe. */
    protected function pngHeader(int $width, int $height): string
    {
        $ihdr = pack('NN', $width, $height) . "\x08\x02\x00\x00\x00";
        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
    }

    /** Bildkopf (JPEG mit SOF0), $channels 3 = RGB, 4 = CMYK. */
    protected function jpegHeader(int $width, int $height, int $channels = 3): string
    {
        $components = '';
        for ($i = 1; $i <= $channels; $i++) {
            $components .= chr($i) . "\x11\x00";
        }
        $sof = "\x08" . pack('nn', $height, $width) . chr($channels) . $components;
        return "\xFF\xD8\xFF\xC0" . pack('n', strlen($sof) + 2) . $sof . "\xFF\xD9";
    }

    /** Schreibt Inhalt in eine temporäre Datei und liefert den Pfad. */
    protected function tempFile(string $content, string $suffix = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'podcast-test-') . $suffix;
        file_put_contents($path, $content);
        return $path;
    }
}

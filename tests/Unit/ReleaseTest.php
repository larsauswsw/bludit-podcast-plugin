<?php

use PHPUnit\Framework\TestCase;

/**
 * Roadmap 038: metadata.json und CHANGELOG.md passen zusammen, so wie
 * scripts/release.sh sie erwartet. Vor einem Release: Version noch ohne
 * Datum und ohne eigenen Abschnitt, Änderungen unter „Unreleased“. Danach:
 * der oberste Abschnitt ist die Version mit dem releaseDate.
 */
class ReleaseTest extends TestCase
{
    private function metadata(): array
    {
        $data = json_decode((string) file_get_contents(PATH_PLUGINS . 'podcast' . DS . 'metadata.json'), true);
        $this->assertIsArray($data, 'metadata.json ist gültiges JSON');
        return $data;
    }

    /** Überschriften „## [...]“ des CHANGELOG in Reihenfolge. */
    private function headings(): array
    {
        $text = (string) file_get_contents(dirname(PATH_PLUGINS) . DS . 'CHANGELOG.md');
        preg_match_all('/^## \[([^\]]+)\](?: - (\S+))?[ \t]*$/m', $text, $m, PREG_SET_ORDER);
        return array_map(function ($h) { return [$h[1], $h[2] ?? '']; }, $m);
    }

    public function testMetadataHasVersionAndCompatibility(): void
    {
        $meta = $this->metadata();
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/D', $meta['version'] ?? null);
        $this->assertMatchesRegularExpression('/^(?:\d{4}-\d{2}-\d{2})?$/D', $meta['releaseDate'] ?? null);
        $this->assertMatchesRegularExpression('/^3\.\d+(?:,3\.\d+)*$/D', $meta['compatible'] ?? null);
        $this->assertIsString($meta['license'] ?? null);
    }

    public function testChangelogMatchesMetadata(): void
    {
        $meta     = $this->metadata();
        $headings = $this->headings();
        $this->assertNotEmpty($headings, 'CHANGELOG.md mit Abschnitten');
        $this->assertSame(['Unreleased', ''], $headings[0], 'oben steht „## [Unreleased]“');

        $versions = array_slice($headings, 1);
        foreach ($versions as [$version, $date]) {
            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/D', $version);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/D', $date, $version . ' mit Datum');
        }

        if ($meta['releaseDate'] === '') {
            // Noch nicht veröffentlicht: kein Abschnitt für diese Version
            $this->assertNotContains($meta['version'], array_column($versions, 0));
        } else {
            $this->assertSame([$meta['version'], $meta['releaseDate']], $versions[0] ?? null,
                'oberste Version = metadata.json (version, releaseDate)');
        }
    }
}

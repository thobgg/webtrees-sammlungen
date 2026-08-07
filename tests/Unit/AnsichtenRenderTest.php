<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rendert jede Ansicht in jeder Sprache und stellt sicher, dass dabei nichts
 * wirft. Deckt die Luecke, an der v1.2.6 gescheitert ist: ein uebersetzter Text
 * mit Platzhalter ohne Argument liess `sprintf()` werfen und riss jede Galerie
 * mit sich. Unit-Tests und statische Analyse pruefen Struktur, nicht Laufzeit.
 *
 * Laeuft als eigener Prozess, weil die I18N-Attrappe sonst mit der echten
 * webtrees-Klasse kollidiert.
 */
final class AnsichtenRenderTest extends TestCase
{
    private static function wurzel(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<array{string}>
     */
    public static function sprachen(): array
    {
        $sprachen = [];

        foreach (glob(self::wurzel() . '/resources/lang/*.mo') ?: [] as $datei) {
            $sprachen[] = [basename($datei, '.mo')];
        }

        return $sprachen;
    }

    #[DataProvider('sprachen')]
    public function testAlleAnsichtenRendernOhneFehler(string $sprache): void
    {
        $skript = self::wurzel() . '/tests/ansichten-rendern.php';
        self::assertFileExists($skript);

        $befehl = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($skript),
            escapeshellarg(self::wurzel()),
            escapeshellarg($sprache)
        );

        exec($befehl, $ausgabe, $code);

        self::assertSame(
            0,
            $code,
            "Rendern in Sprache '{$sprache}' fehlgeschlagen:\n" . implode("\n", $ausgabe)
        );
    }
}

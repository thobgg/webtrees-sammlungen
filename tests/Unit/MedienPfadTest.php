<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sammlungen\Service\MedienPfad;

/**
 * Regression zu Issue #23, gemeldet von @ro-la.
 *
 * Das Modul setzte den Medienpfad an zwoelf Stellen selbst zusammen, aus der
 * Konstante `Webtrees::DATA_DIR` und der Baum-Einstellung. Die Konstante ist
 * aber nur die Vorgabe fuer die Website-Einstellung `INDEX_DIRECTORY`, die man
 * verschieben darf - das ist der empfohlene Weg, die Originaldateien aus der
 * Reichweite der Adresszeile zu nehmen. Wer das tat, bei dem fand das Modul
 * nichts.
 *
 * Geprueft wird hier das Zusammensetzen, denn dort sitzen die Fallstricke:
 * Schraegstriche am Ende, doppelte Trenner, Windows-Schreibweise.
 */
final class MedienPfadTest extends TestCase
{
    /**
     * Der wichtigste Fall: bei unveraenderten Einstellungen muss derselbe Pfad
     * herauskommen wie vorher. Sonst waere die Reparatur ein Umbau.
     */
    public function testVorgabeErgibtDenBisherigenPfad(): void
    {
        self::assertSame('data/media/', MedienPfad::zusammensetzen('data/', 'media/'));
    }

    /** Nutzer tragen den Pfad von Hand ein - mit oder ohne Schraegstrich. */
    public function testSchraegstricheWerdenVereinheitlicht(): void
    {
        $erwartet = '/var/archiv/media/';

        self::assertSame($erwartet, MedienPfad::zusammensetzen('/var/archiv', 'media'));
        self::assertSame($erwartet, MedienPfad::zusammensetzen('/var/archiv/', '/media/'));
        self::assertSame($erwartet, MedienPfad::zusammensetzen('/var/archiv//', 'media//'));
    }

    /** Ein Datenverzeichnis ausserhalb des Webverzeichnisses ist der Anlass. */
    public function testAbsoluterPfadAusserhalbDerInstallation(): void
    {
        self::assertSame(
            '/srv/geheim/bilder/',
            MedienPfad::zusammensetzen('/srv/geheim', 'bilder')
        );
    }

    /** Windows schreibt Backslashes; die Pruefungen im Modul vergleichen Vorwaertsschraege. */
    public function testWindowsSchreibweise(): void
    {
        self::assertSame('C:/webtrees/data/media/', MedienPfad::zusammensetzen('C:\\webtrees\\data', 'media'));
    }

    /** Leerer Medienordner: dann ist das Datenverzeichnis selbst die Wurzel. */
    public function testLeererMedienordner(): void
    {
        self::assertSame('data/', MedienPfad::zusammensetzen('data/', ''));
    }

    /**
     * Der eigentliche Waechter: nirgends im Modul darf der Medienpfad wieder
     * aus der Konstante zusammengesetzt werden.
     */
    public function testKeineFesteAnnahmeMehrImQuelltext(): void
    {
        $wurzel   = dirname(__DIR__, 2) . '/src';
        $treffer  = [];
        $dateien  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($wurzel));

        foreach ($dateien as $datei) {
            if ($datei->getExtension() !== 'php' || $datei->getFilename() === 'MedienPfad.php') {
                continue;
            }
            $inhalt = (string) file_get_contents($datei->getPathname());
            if (str_contains($inhalt, 'DATA_DIR')) {
                $treffer[] = $datei->getFilename();
            }
        }

        self::assertSame([], $treffer, 'Der Medienpfad wird wieder fest angenommen');
    }
}

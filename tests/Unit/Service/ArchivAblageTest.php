<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sammlungen\Service\ArchivAblage;

/**
 * Was vom Dateinamen eines Uploads uebrig bleibt, und wie eine Datei heisst, deren Name schon belegt ist. Beides
 * ohne Dateisystem pruefbar - genau die Stellen, an denen ein Upload sonst den Ordner wechselt oder etwas
 * ueberschreibt.
 */
#[CoversClass(ArchivAblage::class)]
final class ArchivAblageTest extends TestCase
{
    /** @return array<string,array{string,string}> */
    public static function namen(): array
    {
        return [
            'schlicht'            => ['hof-1920.jpg', 'hof-1920.jpg'],
            'Pfad wird gekappt'   => ['../../etc/passwd.jpg', 'passwd.jpg'],
            'Windows-Pfad'        => ['C:\\Bilder\\oma.jpg', 'oma.jpg'],
            'Doppelpunkt'         => ['12:30 Uhr.jpg', '12-30 Uhr.jpg'],
            'versteckt'           => ['.htaccess.jpg', 'htaccess.jpg'],
            'Punkt am Ende'       => ['bild.jpg.', 'bild.jpg'],
            'Steuerzeichen'       => ["bi\x00ld\n.jpg", 'bild.jpg'],
            'Umlaute bleiben'     => ['Großmutter Käthe.jpg', 'Großmutter Käthe.jpg'],
            'nur Muell'           => ['...', ''],
        ];
    }

    #[DataProvider('namen')]
    public function testDateinameBereinigen(string $roh, string $erwartet): void
    {
        self::assertSame($erwartet, ArchivAblage::dateinameBereinigen($roh));
    }

    public function testFreierNameBleibtWennFrei(): void
    {
        self::assertSame('Archiv/foto.jpg', ArchivAblage::freierName(static fn (string $p): bool => false, 'Archiv/', 'foto.jpg'));
    }

    public function testFreierNameZaehltHoch(): void
    {
        $belegt = ['foto.jpg', 'foto-2.jpg', 'foto-3.jpg'];

        self::assertSame('foto-4.jpg', ArchivAblage::freierName(static fn (string $p): bool => in_array($p, $belegt, true), '', 'foto.jpg'));
    }

    public function testFreierNameOhneEndung(): void
    {
        self::assertSame('notiz-2', ArchivAblage::freierName(static fn (string $p): bool => $p === 'notiz', '', 'notiz'));
    }

    public function testIstBild(): void
    {
        self::assertTrue(ArchivAblage::istBild('Ordner/Bild.JPG'));
        self::assertFalse(ArchivAblage::istBild('Ordner/urkunde.pdf'));
    }
}

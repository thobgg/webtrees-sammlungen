<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sammlungen\Service\JpegXmp;

/**
 * Schritt 0 der Postkarten-Aufgabe: das JPG ist das Original, und die
 * Bilddaten duerfen beim Schreiben von Metadaten nie neu kodiert werden.
 *
 * Der fruehere Weg (new Imagick + writeImage) hat genau das getan. Diese
 * Tests halten fest, dass der Segmentschreiber nur das XMP-Segment
 * austauscht: die Scan-Daten ab SOS sind vorher und nachher Byte fuer Byte
 * gleich, alle anderen Segmente bleiben, und das Ergebnis ist weiterhin ein
 * lesbares JPEG.
 */
#[CoversClass(JpegXmp::class)]
final class JpegXmpTest extends TestCase
{
    private string $datei;

    protected function setUp(): void
    {
        if (!function_exists('imagejpeg')) {
            self::markTestSkipped('GD fehlt - kein Testbild.');
        }

        $this->datei = tempnam(sys_get_temp_dir(), 'pk') . '.jpg';

        $bild = imagecreatetruecolor(320, 200);
        for ($x = 0; $x < 320; $x += 8) {
            $farbe = imagecolorallocate($bild, ($x * 3) % 256, ($x * 7) % 256, ($x * 11) % 256);
            imagefilledrectangle($bild, $x, 0, $x + 7, 200, $farbe);
        }
        imagejpeg($bild, $this->datei, 95);
    }

    protected function tearDown(): void
    {
        @unlink($this->datei);
        @unlink(substr($this->datei, 0, -4));
    }

    private static function paket(string $inhalt): string
    {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . $inhalt
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    }

    /** Segmente vor SOS als Liste [marker, daten] - zum Vergleich von Kopf-Zustaenden. */
    private static function segmente(string $datei): array
    {
        $roh = (string) file_get_contents($datei);
        $pos = 2;
        $liste = [];

        while ($pos < strlen($roh)) {
            $marker = ord($roh[$pos + 1]);
            if ($marker === 0xDA) {
                break;
            }
            $laenge = unpack('n', substr($roh, $pos + 2, 2))[1];
            $liste[] = [$marker, substr($roh, $pos + 4, $laenge - 2)];
            $pos += 2 + $laenge;
        }

        return $liste;
    }

    public function testBilddatenBleibenByteFuerByteGleich(): void
    {
        $vorher = JpegXmp::bilddaten($this->datei);
        self::assertNotSame('', $vorher);

        JpegXmp::schreibe($this->datei, self::paket('<dc:identifier>PK_0001</dc:identifier>'));

        self::assertSame($vorher, JpegXmp::bilddaten($this->datei));
        self::assertSame(md5($vorher), md5(JpegXmp::bilddaten($this->datei)));
    }

    public function testErgebnisIstWeiterEinLesbaresJpeg(): void
    {
        JpegXmp::schreibe($this->datei, self::paket('<dc:identifier>PK_0001</dc:identifier>'));

        $bild = @imagecreatefromjpeg($this->datei);
        self::assertNotFalse($bild);
        self::assertSame(320, imagesx($bild));
        self::assertSame(200, imagesy($bild));

        $masse = getimagesize($this->datei);
        self::assertNotFalse($masse);
        self::assertSame('image/jpeg', $masse['mime']);
    }

    public function testXmpWirdGelesenUndBeimZweitenSchreibenErsetztNichtErgaenzt(): void
    {
        self::assertSame('', JpegXmp::lies($this->datei));

        JpegXmp::schreibe($this->datei, self::paket('<dc:identifier>erstes</dc:identifier>'));
        self::assertStringContainsString('erstes', JpegXmp::lies($this->datei));

        JpegXmp::schreibe($this->datei, self::paket('<dc:identifier>zweites</dc:identifier>'));
        $gelesen = JpegXmp::lies($this->datei);

        self::assertStringContainsString('zweites', $gelesen);
        self::assertStringNotContainsString('erstes', $gelesen);

        // Genau ein XMP-Segment in der Datei.
        $xmpSegmente = array_filter(
            self::segmente($this->datei),
            static fn (array $s): bool => $s[0] === 0xE1 && str_starts_with($s[1], JpegXmp::XMP_KOPF)
        );
        self::assertCount(1, $xmpSegmente);
    }

    public function testAlleAnderenSegmenteBleibenErhalten(): void
    {
        $vorher = self::segmente($this->datei);
        self::assertNotEmpty($vorher);

        JpegXmp::schreibe($this->datei, self::paket('<dc:identifier>PK_0001</dc:identifier>'));

        $ohneXmp = array_values(array_filter(
            self::segmente($this->datei),
            static fn (array $s): bool => !($s[0] === 0xE1 && str_starts_with($s[1], JpegXmp::XMP_KOPF))
        ));

        self::assertSame($vorher, $ohneXmp);
    }

    public function testXmpSteHtVorDenTabellenNichtDahinter(): void
    {
        JpegXmp::schreibe($this->datei, self::paket('<dc:identifier>PK_0001</dc:identifier>'));

        $marker = array_map(static fn (array $s): int => $s[0], self::segmente($this->datei));
        $xmp    = array_search(0xE1, $marker, true);
        $dqt    = array_search(0xDB, $marker, true);

        self::assertNotFalse($xmp);
        self::assertNotFalse($dqt);
        self::assertLessThan($dqt, $xmp, 'APP1/XMP muss vor der ersten Quantisierungstabelle liegen.');
    }

    public function testDateigroesseAendertSichNurUmDasSegment(): void
    {
        $vorher = filesize($this->datei);
        $paket  = self::paket('<dc:identifier>PK_0001</dc:identifier>');

        JpegXmp::schreibe($this->datei, $paket);
        clearstatcache();

        // 2 Marker + 2 Laenge + Kopf + Paket
        self::assertSame($vorher + 4 + strlen(JpegXmp::XMP_KOPF) + strlen($paket), filesize($this->datei));
    }

    public function testKeinJpegWirdAbgewiesenUndBleibtUnveraendert(): void
    {
        file_put_contents($this->datei, 'kein Bild');

        $this->expectException(\RuntimeException::class);
        try {
            JpegXmp::schreibe($this->datei, self::paket(''));
        } finally {
            self::assertSame('kein Bild', file_get_contents($this->datei));
        }
    }

    public function testZuGrossesPaketWirdAbgewiesen(): void
    {
        $this->expectException(\RuntimeException::class);
        JpegXmp::schreibe($this->datei, str_repeat('x', 70000));
    }

    public function testKeineHilfsdateiBleibtLiegen(): void
    {
        $dir    = dirname($this->datei);
        $vorher = glob($dir . '/.xmp-*') ?: [];

        JpegXmp::schreibe($this->datei, self::paket('<dc:identifier>PK_0001</dc:identifier>'));

        self::assertSame($vorher, glob($dir . '/.xmp-*') ?: []);
    }
}

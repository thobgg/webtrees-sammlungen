<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sammlungen\Service\ExifService;

#[CoversClass(ExifService::class)]
final class ExifServiceTest extends TestCase
{
    private ExifService $service;

    protected function setUp(): void
    {
        // ExifService hat keinen Konstruktor mit Abhängigkeiten – direkt instanziierbar.
        $this->service = new ExifService();
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $m = new ReflectionMethod(ExifService::class, $method);

        return $m->invoke($this->service, ...$args);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function anzeigeProvider(): array
    {
        return [
            'volles Datum'              => ['1985-07-23', '23.07.1985'],
            'EXIF-Doppelpunkt-Format'   => ['1985:07:23', '23.07.1985'],
            'Tag unbekannt (00)'        => ['1985-07-00', '1985'],
            'Monat+Tag unbekannt (00)'  => ['2020:00:00', '2020'],
            'Monat unbekannt (00)'      => ['2000-00-15', '2000'],
            'Silvester'                 => ['1900-12-31', '31.12.1900'],
            'nur Jahr'                  => ['1850', '1850'],
            'leer'                      => ['', ''],
            'unparsbar bleibt unveraendert' => [' kein Datum', ' kein Datum'],
        ];
    }

    #[DataProvider('anzeigeProvider')]
    public function testFormatiereDatumAnzeige(string $iso, string $erwartet): void
    {
        self::assertSame($erwartet, $this->call('formatiereDatumAnzeige', $iso));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function exifProvider(): array
    {
        return [
            'volles Datum' => ['1985-07-23', '1985:07:23 00:00:00'],
            'nur Jahr'     => ['1850', '1850:01:01 00:00:00'],
            'leer'         => ['', ''],
        ];
    }

    #[DataProvider('exifProvider')]
    public function testFormatiereDatumExif(string $iso, string $erwartet): void
    {
        self::assertSame($erwartet, $this->call('formatiereDatumExif', $iso));
    }

    public function testBaueXmpPacketEnthaeltAlleFelder(): void
    {
        $xmp = $this->call(
            'baueXmpPacket',
            'Hochzeit in Stuttgart',
            '1985-07-23',
            ['Max Mustermann', 'Erika Musterfrau'],
            ['Hochzeit', 'Familie'],
        );

        self::assertIsString($xmp);
        self::assertStringContainsString('Hochzeit in Stuttgart', $xmp);
        self::assertStringContainsString('<xmp:CreateDate>1985-07-23</xmp:CreateDate>', $xmp);
        self::assertStringContainsString('Max Mustermann', $xmp);
        self::assertStringContainsString('Erika Musterfrau', $xmp);
        self::assertStringContainsString('<iptcExt:PersonInImage>', $xmp);
        self::assertStringContainsString('<dc:subject>', $xmp);
    }

    public function testBaueXmpPacketEntkommtSonderzeichen(): void
    {
        $xmp = $this->call('baueXmpPacket', 'Müller & <Sohn>', '', [], []);

        // XML-Sonderzeichen müssen maskiert sein – kein rohes < oder & im Wert.
        self::assertStringContainsString('Müller &amp; &lt;Sohn&gt;', $xmp);
        self::assertStringNotContainsString('<Sohn>', $xmp);
    }

    public function testBaueXmpPacketMitKopplungUndUnsicheremDatum(): void
    {
        $xmp = $this->call('baueXmpPacket', 'Motiv: Kirche', '1912-05-12', [], [], true, 'PK_0001', 'PK_0001_R.jpg');

        self::assertStringContainsString('<dc:identifier>PK_0001</dc:identifier>', $xmp);
        self::assertStringContainsString('<dc:relation><rdf:Bag><rdf:li>PK_0001_R.jpg</rdf:li></rdf:Bag></dc:relation>', $xmp);
        self::assertStringContainsString('<sammlungen:DatumUnsicher>True</sammlungen:DatumUnsicher>', $xmp);
        self::assertStringContainsString('xmlns:sammlungen="' . ExifService::NS_SAMMLUNGEN . '"', $xmp);

        // Ohne Datum gibt es auch kein "unsicher".
        $ohne = $this->call('baueXmpPacket', '', '', [], [], true, '', '');
        self::assertStringNotContainsString('DatumUnsicher', $ohne);
        self::assertStringNotContainsString('<dc:identifier>', $ohne);
        self::assertStringNotContainsString('<dc:relation>', $ohne);
    }

    /**
     * Das Paket muss sich mit denselben XPath-Ausdruecken wieder lesen lassen,
     * mit denen leseMeta() arbeitet - sonst schreibt das Modul, was es selbst
     * nicht mehr findet.
     */
    public function testGeschriebenesPaketWirdWiederGelesen(): void
    {
        $xmp = $this->call(
            'baueXmpPacket',
            "Motiv: Kirche\nTranskription:\nGruss aus Kiel",
            '1912-05-12',
            ['Anna Bugge'],
            ['Kiel', 'Lichtdruck'],
            true,
            'PK_0001',
            'PK_0001_R.jpg'
        );

        $result = [
            'beschreibung' => '', 'datum' => '', 'datum_iso' => '', 'datum_unsicher' => false,
            'personen' => [], 'keywords' => [], 'identifier' => '', 'relation' => '',
        ];
        $m = new ReflectionMethod(ExifService::class, 'parseXmp');
        $m->invokeArgs($this->service, [$xmp, &$result]);

        self::assertSame("Motiv: Kirche\nTranskription:\nGruss aus Kiel", $result['beschreibung']);
        self::assertSame('1912-05-12', $result['datum_iso']);
        self::assertSame('12.05.1912', $result['datum']);
        self::assertTrue($result['datum_unsicher']);
        self::assertSame(['Anna Bugge'], $result['personen']);
        self::assertSame(['Kiel', 'Lichtdruck'], $result['keywords']);
        self::assertSame('PK_0001', $result['identifier']);
        self::assertSame('PK_0001_R.jpg', $result['relation']);
    }

    /**
     * Ohne Imagick liest leseMeta() ein JPEG ueber den eigenen Segmentleser -
     * und was schreibeMeta() geschrieben hat, kommt so zurueck. Laeuft nur
     * dort, wo Imagick fehlt (dann nimmt leseMeta den anderen Weg), und
     * braucht GD fuer das Testbild.
     */
    public function testJpegRundlaufOhneImagick(): void
    {
        if (class_exists('Imagick') || !function_exists('imagejpeg')) {
            self::markTestSkipped('Nur ohne Imagick und mit GD.');
        }

        $datei = tempnam(sys_get_temp_dir(), 'pk') . '.jpg';
        $bild  = imagecreatetruecolor(40, 30);
        imagejpeg($bild, $datei, 95);

        try {
            $xmp = $this->call('baueXmpPacket', 'Kirche', '1912', ['Anna'], ['Kiel'], false, 'PK_0001', 'PK_0001_R.jpg');
            \Sammlungen\Service\JpegXmp::schreibe($datei, $xmp);

            $meta = $this->service->leseMeta($datei);

            self::assertSame('Kirche', $meta['beschreibung']);
            self::assertSame('1912', $meta['datum_iso']);
            self::assertSame(['Anna'], $meta['personen']);
            self::assertSame('PK_0001', $meta['identifier']);
            self::assertSame(40, $meta['breite']);
            self::assertSame(30, $meta['hoehe']);
        } finally {
            @unlink($datei);
            @unlink(substr($datei, 0, -4));
        }
    }

    public function testBaueXmpPacketLaesstLeereFelderWeg(): void
    {
        $xmp = $this->call('baueXmpPacket', '', '', [], []);

        self::assertStringNotContainsString('<dc:description>', $xmp);
        self::assertStringNotContainsString('<xmp:CreateDate>', $xmp);
        self::assertStringNotContainsString('<iptcExt:PersonInImage>', $xmp);
        self::assertStringNotContainsString('<dc:subject>', $xmp);
    }
}

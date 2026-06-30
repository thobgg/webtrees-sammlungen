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

    public function testBaueXmpPacketLaesstLeereFelderWeg(): void
    {
        $xmp = $this->call('baueXmpPacket', '', '', [], []);

        self::assertStringNotContainsString('<dc:description>', $xmp);
        self::assertStringNotContainsString('<xmp:CreateDate>', $xmp);
        self::assertStringNotContainsString('<iptcExt:PersonInImage>', $xmp);
        self::assertStringNotContainsString('<dc:subject>', $xmp);
    }
}

<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sammlungen\Http\RequestHandlers\Api\Metadaten;

#[CoversClass(Metadaten::class)]
final class MetadatenTest extends TestCase
{
    /** @return array<string,array{string,bool}> */
    public static function daten(): array
    {
        return [
            'leer'          => ['', true],
            'Jahr'          => ['1928', true],
            'Monat'         => ['1928-06', true],
            'Tag'           => ['1928-06-14', true],
            'Monat 13'      => ['1928-13', false],
            'Tag 32'        => ['1928-06-32', false],
            'deutsch'       => ['14.06.1928', false],
            'Text'          => ['um 1928', false],
        ];
    }

    #[DataProvider('daten')]
    public function testDatum(string $datum, bool $gueltig): void
    {
        self::assertSame($gueltig, Metadaten::datumGueltig($datum));
    }

    public function testAusFormular(): void
    {
        $meta = Metadaten::ausFormular([
            'beschreibung' => '  Hochzeit von Heinrich und Anna ',
            'datum'        => '1928',
            'personen'     => 'Heinrich Falkenrath, Anna Falkenrath,, ',
            'keywords'     => '',
        ]);

        self::assertNotNull($meta);
        self::assertSame('Hochzeit von Heinrich und Anna', $meta->beschreibung);
        self::assertSame(['Heinrich Falkenrath', 'Anna Falkenrath'], $meta->personen);
        self::assertSame([], $meta->keywords);
        self::assertFalse($meta->leer());
    }

    public function testLeeresFormularIstLeer(): void
    {
        $meta = Metadaten::ausFormular([]);

        self::assertNotNull($meta);
        self::assertTrue($meta->leer());
    }

    public function testFalschesDatumLiefertNull(): void
    {
        self::assertNull(Metadaten::ausFormular(['datum' => 'gestern']));
    }
}

<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Sammlungen\Service\CollectionService;

#[CoversClass(CollectionService::class)]
final class CollectionServiceTest extends TestCase
{
    private CollectionService $service;

    protected function setUp(): void
    {
        // Konstruktor erwartet einen Cache-Service (DB-nah) – für die reinen
        // Validierungs-Helfer ohne Konstruktor instanziieren.
        $this->service = (new ReflectionClass(CollectionService::class))
            ->newInstanceWithoutConstructor();
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $m = new ReflectionMethod(CollectionService::class, $method);

        return $m->invoke($this->service, ...$args);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function farbeProvider(): array
    {
        return [
            'gueltig kleingeschrieben' => ['#a1b2c3', '#a1b2c3'],
            'gueltig wird normalisiert' => ['#AABBCC', '#aabbcc'],
            'ohne Raute → Fallback'     => ['aabbcc', '#6c757d'],
            'zu kurz → Fallback'        => ['#abc', '#6c757d'],
            'unsinn → Fallback'         => ['rot', '#6c757d'],
            'leer → Fallback'           => ['', '#6c757d'],
        ];
    }

    #[DataProvider('farbeProvider')]
    public function testValidiereHexFarbe(string $eingabe, string $erwartet): void
    {
        self::assertSame($erwartet, $this->call('validiereHexFarbe', $eingabe));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function gueltigeSlugsProvider(): array
    {
        return [
            'einfach'        => ['fotos'],
            'mit-bindestrich' => ['alte-fotos'],
            'mit_unterstrich' => ['album_1900'],
            'ziffern'        => ['2024'],
        ];
    }

    #[DataProvider('gueltigeSlugsProvider')]
    public function testValidiereSlugAkzeptiertGueltige(string $slug): void
    {
        $this->call('validiereSlug', $slug);
        // Kein Exception geworfen → gültig.
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ungueltigeSlugsProvider(): array
    {
        return [
            'leer'           => [''],
            'Grossbuchstabe' => ['Fotos'],
            'Leerzeichen'    => ['alte fotos'],
            'Sonderzeichen'  => ['fotos!'],
            'Umlaut'         => ['grüße'],
            'zu lang'        => [str_repeat('a', 81)],
        ];
    }

    #[DataProvider('ungueltigeSlugsProvider')]
    public function testValidiereSlugLehntUngueltigeAb(string $slug): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->call('validiereSlug', $slug);
    }

    public function testCastRowTypisiert(): void
    {
        $row = (object) [
            'id'          => '7',
            'gedcom_id'   => '3',
            'reihenfolge' => '2',
            'aktiv'       => '1',
            'beschreibung' => null,
            'ordner'      => null,
            'ansicht'     => null,
        ];

        $result = $this->call('castRow', $row);

        self::assertSame(7, $result->id);
        self::assertSame(3, $result->gedcom_id);
        self::assertSame(2, $result->reihenfolge);
        self::assertTrue($result->aktiv);
        self::assertNull($result->beschreibung);
        self::assertSame('foto', $result->ansicht); // Default bei null
    }
}

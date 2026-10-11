<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sammlungen\Http\RequestHandlers\Api\SammlungFormular;

#[CoversClass(SammlungFormular::class)]
final class SammlungFormularTest extends TestCase
{
    private const SYMBOLE   = ['folder', 'users', 'envelope'];
    private const ANSICHTEN = ['foto', 'raster', 'gemischt', 'dokument', 'postkarte'];
    private const ORDNER    = ['familie', 'familie/postkarten'];

    /** @param array<string,mixed> $body */
    private function lesen(array $body, ?array $alt = null): array
    {
        return SammlungFormular::lesen($body, $alt, self::SYMBOLE, self::ANSICHTEN, self::ORDNER);
    }

    public function testNeuMitVorgaben(): void
    {
        $r = $this->lesen(['name' => '  Hochzeiten ']);

        self::assertNull($r['fehler']);
        self::assertSame([
            'name' => 'Hochzeiten', 'beschreibung' => '', 'icon' => 'folder', 'farbe' => '#6c757d',
            'ansicht' => 'foto', 'ordner' => '', 'aktiv' => true, 'reihenfolge' => 0,
        ], $r['felder']);
    }

    public function testAendernBehaeltNichtGesendetes(): void
    {
        $alt = [
            'name' => 'Karten', 'beschreibung' => 'Alte', 'icon' => 'envelope', 'farbe' => '#112233',
            'ansicht' => 'postkarte', 'ordner' => 'familie/postkarten', 'aktiv' => false, 'reihenfolge' => 4,
        ];

        $r = $this->lesen(['beschreibung' => 'Neu', 'aktiv' => '1'], $alt);

        self::assertNull($r['fehler']);
        self::assertSame('Neu', $r['felder']['beschreibung']);
        self::assertTrue($r['felder']['aktiv']);
        self::assertSame('familie/postkarten', $r['felder']['ordner']);
        self::assertSame(4, $r['felder']['reihenfolge']);
    }

    public function testAltesSymbolUndAlterOrdnerDuerfenBleiben(): void
    {
        $alt = ['name' => 'X', 'icon' => 'fa-camera', 'ordner' => 'weg'];

        self::assertNull($this->lesen(['name' => 'Y'], $alt)['fehler']);
        self::assertSame('bad-icon', $this->lesen(['name' => 'Y', 'icon' => 'fa-camera'])['fehler']);
    }

    /** @return array<string,array{array<string,string>,string}> */
    public static function fehler(): array
    {
        return [
            'ohne Name'      => [['name' => ' '], 'missing-name'],
            'Farbe'          => [['name' => 'A', 'farbe' => 'rot'], 'bad-color'],
            'Symbol'         => [['name' => 'A', 'icon' => 'rocket'], 'bad-icon'],
            'Ansicht'        => [['name' => 'A', 'ansicht' => 'karussell'], 'bad-view'],
            'Ordner'         => [['name' => 'A', 'ordner' => 'gibtsnicht'], 'folder-not-found'],
            'Reihenfolge'    => [['name' => 'A', 'reihenfolge' => 'zwei'], 'bad-order'],
            'Reihenfolge -1' => [['name' => 'A', 'reihenfolge' => '-1'], 'bad-order'],
        ];
    }

    /** @param array<string,string> $body */
    #[DataProvider('fehler')]
    public function testFehler(array $body, string $code): void
    {
        self::assertSame($code, $this->lesen($body)['fehler']);
    }

    public function testOrdnerMitSchraegstrichenUndFarbeGross(): void
    {
        $r = $this->lesen(['name' => 'A', 'ordner' => '/familie/', 'farbe' => '#AABBCC']);

        self::assertNull($r['fehler']);
        self::assertSame('familie', $r['felder']['ordner']);
        self::assertSame('#aabbcc', $r['felder']['farbe']);
    }

    /** @return array<string,array{string,string}> */
    public static function slugs(): array
    {
        return [
            'einfach'   => ['Hochzeiten', 'hochzeiten'],
            'Umlaute'   => ['Großeltern Müller', 'grosseltern-mueller'],
            'Zeichen'   => ['  Fotos (1950–1960)! ', 'fotos-1950-1960'],
            'nur Zeichen' => ['???', 'sammlung'],
        ];
    }

    #[DataProvider('slugs')]
    public function testSlugAusName(string $name, string $slug): void
    {
        self::assertSame($slug, SammlungFormular::slugAusName($name));
    }

    public function testSlugHoechstens80Zeichen(): void
    {
        self::assertSame(80, strlen(SammlungFormular::slugAusName(str_repeat('a', 200))));
    }
}

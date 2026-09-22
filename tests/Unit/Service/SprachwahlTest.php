<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sammlungen\Service\Sprachwahl;

/**
 * Die App-Schnittstelle antwortet in der Sprache der App (?lang=…), sofern
 * die Installation sie anbietet. Die Zuordnung darf weder zu streng sein
 * ("de-DE" muss "de" finden) noch zu locker ("fr" darf nicht "de" werden).
 */
#[CoversClass(Sprachwahl::class)]
final class SprachwahlTest extends TestCase
{
    private const ANGEBOT = ['de', 'en-GB', 'en-US', 'nl', 'pt-BR'];

    /** @return array<string,array{string,string|null}> */
    public static function faelle(): array
    {
        return [
            'genau'                    => ['de', 'de'],
            'gross geschrieben'        => ['DE', 'de'],
            'mit Region auf Sprache'   => ['de-DE', 'de'],
            'nl-BE auf nl'             => ['nl-BE', 'nl'],
            'en bevorzugt en-US'       => ['en', 'en-US'],
            'en-GB genau'              => ['en-GB', 'en-GB'],
            'pt auf einzige Region'    => ['pt', 'pt-BR'],
            'unbekannt'                => ['fr', null],
            'leer'                     => ['', null],
            'nur Leerzeichen'          => ['  ', null],
        ];
    }

    #[DataProvider('faelle')]
    public function testFindetDiePassendeSprache(string $wunsch, ?string $erwartet): void
    {
        self::assertSame($erwartet, Sprachwahl::passend($wunsch, self::ANGEBOT));
    }

    public function testOhneAngebotKeinTreffer(): void
    {
        self::assertNull(Sprachwahl::passend('de', []));
    }
}

<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use Fisharebest\Webtrees\Module\AbstractModule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Sammlungen\SammlungenModule;
use Sammlungen\ViewModel\SammlungenViewModel;

/**
 * Regression zu Issue #14: die gespeicherten Einstellungen hatten keine Wirkung.
 */
#[CoversClass(SammlungenModule::class)]
final class EinstellungenTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AbstractModule::class)) {
            self::markTestSkipped('webtrees nicht erreichbar – Modulklasse nicht ladbar.');
        }
    }

    /**
     * @return list<array{mixed, int}>
     */
    public static function perPageWerte(): array
    {
        return [
            'gespeicherter Wert wird uebernommen' => ['25', 25],
            'Integer statt String'                => [100, 100],
            'unterhalb des Minimums'              => ['1', SammlungenModule::MIN_PER_PAGE],
            'oberhalb des Maximums'               => ['9999', SammlungenModule::MAX_PER_PAGE],
            'nicht gesetzt'                       => [null, SammlungenModule::DEFAULT_PER_PAGE],
            'leerer String'                       => ['', SammlungenModule::DEFAULT_PER_PAGE],
            'kein Skalar (POST-Array)'            => [['50'], SammlungenModule::DEFAULT_PER_PAGE],
        ];
    }

    #[DataProvider('perPageWerte')]
    public function testPerPageWirdNormalisiert(mixed $eingabe, int $erwartet): void
    {
        self::assertSame($erwartet, SammlungenModule::normalisierePerPage($eingabe));
    }

    /**
     * @return list<array{mixed, int}>
     */
    public static function cacheTtlWerte(): array
    {
        return [
            'gespeicherter Wert wird uebernommen' => ['1800', 1800],
            'unterhalb des Minimums'              => ['5', SammlungenModule::MIN_CACHE_TTL],
            'oberhalb des Maximums'               => ['999999', SammlungenModule::MAX_CACHE_TTL],
            'nicht gesetzt'                       => [null, SammlungenModule::DEFAULT_CACHE_TTL],
            'leerer String'                       => ['', SammlungenModule::DEFAULT_CACHE_TTL],
        ];
    }

    #[DataProvider('cacheTtlWerte')]
    public function testCacheTtlWirdNormalisiert(mixed $eingabe, int $erwartet): void
    {
        self::assertSame($erwartet, SammlungenModule::normalisiereCacheTtl($eingabe));
    }

    /**
     * Der Default muss selbst durch die Normalisierung kommen – sonst liefert
     * eine unkonfigurierte Installation einen Wert, den das Formular gar nicht
     * anbieten kann.
     */
    public function testDefaultsLiegenImGueltigenBereich(): void
    {
        self::assertSame(
            SammlungenModule::DEFAULT_PER_PAGE,
            SammlungenModule::normalisierePerPage(SammlungenModule::DEFAULT_PER_PAGE)
        );
        self::assertSame(
            SammlungenModule::DEFAULT_CACHE_TTL,
            SammlungenModule::normalisiereCacheTtl(SammlungenModule::DEFAULT_CACHE_TTL)
        );
    }

    /**
     * Kern von Issue #14a: "Eintraege pro Seite" wurde gespeichert, aber nie
     * gelesen – der ViewModel paginierte mit fest verdrahteten Konstanten.
     * Ohne die Modul-Abhaengigkeit kommt er an die Einstellung nicht heran.
     */
    public function testViewModelBeziehtDasModulFuerDieSeitengroesse(): void
    {
        $parameter = (new ReflectionClass(SammlungenViewModel::class))
            ->getConstructor()
            ?->getParameters() ?? [];

        $typen = array_map(
            static function (\ReflectionParameter $p): ?string {
                $typ = $p->getType();
                return $typ instanceof ReflectionNamedType ? $typ->getName() : null;
            },
            $parameter
        );

        self::assertContains(SammlungenModule::class, $typen);
    }

    /**
     * Die alten Konstanten duerfen nicht zurueckkehren – sie waren die Ursache
     * dafuer, dass die Einstellung wirkungslos blieb.
     */
    public function testViewModelHatKeineFestVerdrahteteSeitengroesse(): void
    {
        $konstanten = (new ReflectionClass(SammlungenViewModel::class))->getConstants();

        self::assertArrayNotHasKey('PER_SEITE_FOTO', $konstanten);
        self::assertArrayNotHasKey('PER_SEITE_DOKUMENT', $konstanten);
    }
}

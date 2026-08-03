<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use Fisharebest\Webtrees\Module\AbstractModule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sammlungen\Http\RequestHandlers\AdminConfig;
use Sammlungen\Http\RequestHandlers\AdminSammlungEdit;
use Sammlungen\Http\RequestHandlers\AdminSammlungFotos;
use Sammlungen\Http\RequestHandlers\AdminSammlungen;

/**
 * Regression zu Issue #14c: ViewResponseTrait rendert per Default mit
 * 'layouts/default', also der Besucheroberflaeche. Die Admin-Seiten bekamen
 * dadurch Kopfzeile und Navigation eines konkreten Baums – wer die
 * Einstellungen oeffnete, landete sichtbar im ersten Stammbaum statt im
 * Control Panel.
 */
final class AdminLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AbstractModule::class)) {
            self::markTestSkipped('webtrees nicht erreichbar – Handler nicht ladbar.');
        }
    }

    /**
     * @return list<array{class-string}>
     */
    public static function adminHandler(): array
    {
        return [
            [AdminConfig::class],
            [AdminSammlungen::class],
            [AdminSammlungEdit::class],
            [AdminSammlungFotos::class],
        ];
    }

    /**
     * Geprueft wird der Quelltext, nicht die fertige Instanz: die Handler
     * brauchen zum Bauen Dienste mit Datenbankzugriff, und der Wert laesst sich
     * nicht als Property-Default deklarieren – eine Neudeklaration mit
     * abweichendem Default kollidiert mit ViewResponseTrait::$layout und ist ein
     * fataler Fehler. Er muss also zur Laufzeit zugewiesen werden.
     *
     * @param class-string $handler
     */
    #[DataProvider('adminHandler')]
    public function testAdminHandlerRendernImControlPanel(string $handler): void
    {
        $datei = (new ReflectionClass($handler))->getFileName();

        self::assertIsString($datei);
        self::assertStringContainsString(
            "\$this->layout = 'layouts/administration';",
            (string) file_get_contents($datei),
            $handler . ' rendert noch mit der Besucheroberflaeche.'
        );
    }

    /**
     * Gegenprobe zur Zuweisung oben: als Property deklariert waere es ein
     * fataler Fehler beim Laden der Klasse.
     *
     * @param class-string $handler
     */
    #[DataProvider('adminHandler')]
    public function testAdminHandlerDeklarierenLayoutNichtNeu(string $handler): void
    {
        $defaults = (new ReflectionClass($handler))->getDefaultProperties();

        self::assertSame('layouts/default', $defaults['layout'] ?? null, $handler);
    }

    /**
     * Das Admin-Layout rendert bereits in <body class="container-lg">. Ein
     * zweiter Container in der View schachtelt die Seitenraender doppelt.
     */
    #[DataProvider('adminViews')]
    public function testAdminViewsSchachtelnKeinenZweitenContainer(string $view): void
    {
        $pfad = dirname(__DIR__, 2) . '/resources/views/' . $view;

        self::assertFileExists($pfad);
        self::assertStringNotContainsString('container-lg', (string) file_get_contents($pfad), $view);
    }

    /**
     * @return list<array{string}>
     */
    public static function adminViews(): array
    {
        return [
            ['admin-config.phtml'],
            ['admin-sammlungen.phtml'],
            ['admin-sammlung-edit.phtml'],
            ['admin-sammlung-fotos.phtml'],
        ];
    }
}

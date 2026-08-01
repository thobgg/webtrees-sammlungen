<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use Fisharebest\Webtrees\Module\AbstractModule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use Sammlungen\Http\RequestHandlers\AbstractArchivHandler;
use Sammlungen\SammlungenModule;

#[CoversClass(SammlungenModule::class)]
final class SammlungenModuleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AbstractModule::class)) {
            self::markTestSkipped('webtrees nicht erreichbar – Modulklasse nicht ladbar.');
        }
    }

    /**
     * Regression zu Issue #12: webtrees benennt Custom-Module erst nach dem
     * Erzeugen per setName(). Der DI-Container erzeugt fuer die RequestHandler
     * aber eine eigene Instanz und ruft setName() nie auf. Blieb name() dabei
     * leer, schrieb setPreference() einen leeren module_name nach
     * wt_module_setting und verletzte den Foreign Key auf wt_module – das
     * Speichern der Einstellungen war unbenutzbar.
     */
    public function testNameIstOhneSetNameGesetzt(): void
    {
        self::assertSame('_sammlungen_', (new SammlungenModule())->name());
    }

    /**
     * Ein spaeteres setName() durch webtrees darf den Namen nicht veraendern –
     * sonst haengen Instanz und gespeicherte Einstellungen auseinander.
     */
    public function testSetNameDurchWebtreesAendertDenNamenNicht(): void
    {
        $modul = new SammlungenModule();
        $modul->setName(SammlungenModule::MODULE_NAME);

        self::assertSame('_sammlungen_', $modul->name());
    }

    /**
     * ModuleService::customModules() vergibt den Namen als
     * '_' . Verzeichnisname . '_'. Der registrierte Name haengt also am Ordner,
     * nicht an der Konstanten – weichen beide ab, findet das Modul seine
     * gespeicherten Einstellungen nicht mehr.
     */
    public function testKonstanteEntsprichtDemVerzeichnisnamen(): void
    {
        $modulVerzeichnis = dirname(__DIR__, 2);

        if (basename(dirname($modulVerzeichnis)) !== 'modules_v4') {
            self::markTestSkipped('Checkout liegt nicht in modules_v4/ – Verzeichnisname nicht aussagekraeftig.');
        }

        self::assertSame('_' . basename($modulVerzeichnis) . '_', SammlungenModule::MODULE_NAME);
    }

    /**
     * Die Handler loesen ihre Views ueber eine eigene Kopie des Modulnamens auf
     * (AbstractArchivHandler::viewName()). Laeuft die auseinander, liefern die
     * Admin-Seiten einen View-not-found-Fehler.
     */
    public function testViewNamespaceDerHandlerPasstZumModulnamen(): void
    {
        $konstante = new ReflectionClassConstant(AbstractArchivHandler::class, 'MODULE_NAME');

        self::assertSame(SammlungenModule::MODULE_NAME, $konstante->getValue());
    }
}

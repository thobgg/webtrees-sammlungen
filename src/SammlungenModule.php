<?php

declare(strict_types=1);

namespace Sammlungen;

use Sammlungen\Http\RequestHandlers\AdminConfig;
use Sammlungen\Http\RequestHandlers\AdminSammlungFotos;
use Sammlungen\Http\RequestHandlers\AdminSammlungDelete;
use Sammlungen\Http\RequestHandlers\AdminSammlungEdit;
use Sammlungen\Http\RequestHandlers\AdminSammlungen;
use Sammlungen\Http\RequestHandlers\CacheClear;
use Sammlungen\Http\RequestHandlers\ExifSchreiben;
use Sammlungen\Http\RequestHandlers\MediaDateiUmbenennen;
use Sammlungen\Http\RequestHandlers\ModulAsset;
use Sammlungen\Http\RequestHandlers\SammlungAktivToggle;
use Sammlungen\Http\RequestHandlers\SammlungMediumToggle;
use Sammlungen\Http\RequestHandlers\MediaDateiServe;
use Sammlungen\Http\RequestHandlers\SammlungenPage;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Localization\Translation;
use Illuminate\Database\Schema\Blueprint;


class SammlungenModule extends AbstractModule implements
    ModuleCustomInterface,
    ModuleMenuInterface,
    ModuleConfigInterface,
    ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleMenuTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;

    public const MODULE_NAME = '_sammlungen_';
    public const SETTING_CACHE_TTL = 'cache_ttl';
    public const SETTING_PER_PAGE  = 'per_page';
    public const DEFAULT_CACHE_TTL = 900;
    public const DEFAULT_PER_PAGE  = 50;

    public function title(): string { return 'Sammlungen'; }
    public function description(): string { return 'Foto- und Dokumenten-Sammlungen mit EXIF-Anreicherung, Galerie und Lightbox.'; }
    public function customModuleAuthorName(): string { return 'Thomas Bugge'; }
    public function customModuleVersion(): string { return '1.0.8'; }
    public function customModuleLatestVersion(): string { return '1.0.8'; }
    public function customModuleSupportUrl(): string { return ''; }

    /**
     * Lädt die Übersetzungen aus resources/lang/<sprache>.mo.
     * Ohne diese Methode würde webtrees keine .mo-Datei des Moduls laden
     * und alle Texte blieben beim deutschen Quelltext.
     *
     * @return array<string,string>
     */
    public function customTranslations(string $language): array
    {
        $dir = $this->resourcesFolder() . 'lang/';

        // Exakten Sprachcode zuerst versuchen (de, nl), dann 2-Buchstaben-Fallback,
        // damit z. B. en-GB / en-US auf en.mo zurückfallen.
        foreach ([$language, substr($language, 0, 2)] as $code) {
            $file = $dir . $code . '.mo';
            if (file_exists($file)) {
                return (new Translation($file))->asArray();
            }
        }

        return [];
    }

    public function getConfigLink(): string
    {
        return route('sammlungen.admin.config');
    }

    public function boot(): void
    {
        $this->migrateDatabase();
        View::registerNamespace(self::MODULE_NAME, $this->resourcesFolder() . 'views/');

        $router = Registry::routeFactory()->routeMap();

        // ── Öffentliche Seiten (mit {tree}) ──────────────────────────
        $router->get('sammlungen.sammlungen',   '/tree/{tree}/archiv/sammlungen',        SammlungenPage::class);
        $router->get('sammlungen.media-datei',  '/tree/{tree}/archiv/media-datei',       MediaDateiServe::class);
        $router->get('sammlungen.exif-schreiben', '/tree/{tree}/archiv/exif-schreiben', ExifSchreiben::class)
               ->allows('POST');
        $router->get('sammlungen.datei-umbenennen', '/tree/{tree}/archiv/datei-umbenennen', MediaDateiUmbenennen::class)
               ->allows('POST');
        $router->get('sammlungen.sammlung-medium', '/tree/{tree}/archiv/sammlung-medium', SammlungMediumToggle::class)
               ->allows('POST');

        // ── Admin: globale Einstellungen (kein {tree} – baumübergreifend) ───
        // Statische Assets (JS/CSS aus modules_v4/sammlungen/resources/)
        $router->get('sammlungen.asset', '/archiv/asset', ModulAsset::class);

        $router->get('sammlungen.admin.config', '/archiv/admin/config', AdminConfig::class)
               ->allows('POST');

        $router->get('sammlungen.admin.cache-clear', '/archiv/admin/cache-clear', CacheClear::class)
               ->allows('POST');

        // ── Admin: Sammlungen (mit {tree} – sammlungen sind baumspezifisch) ─
        $router->get('sammlungen.admin.sammlungen', '/tree/{tree}/archiv/admin/sammlungen', AdminSammlungen::class);
        $router->get('sammlungen.admin.sammlung-fotos', '/tree/{tree}/archiv/admin/sammlung-fotos', AdminSammlungFotos::class);

        $router->get('sammlungen.admin.sammlungen.edit', '/tree/{tree}/archiv/admin/sammlungen/edit', AdminSammlungEdit::class)
               ->allows('POST');

        $router->get('sammlungen.admin.sammlungen.toggle-aktiv', '/tree/{tree}/archiv/admin/sammlungen/toggle-aktiv', SammlungAktivToggle::class)
               ->allows('POST');

        $router->get('sammlungen.admin.sammlungen.delete', '/tree/{tree}/archiv/admin/sammlungen/delete', AdminSammlungDelete::class)
               ->allows('POST');
    }

    public function defaultMenuOrder(): int { return 99; }

    public function headContent(): string
    {
        $path = $this->resourcesFolder() . 'archiv-icon.jpg';
        if (!file_exists($path) || !class_exists('Imagick')) {
            return '';
        }
        try {
            // Auf 40×40px skalieren und als PNG ausgeben – exakt wie andere Nav-Icons
            $im = new \Imagick($path);
            $im->thumbnailImage(50, 50, true, true);
            $im->setImageFormat('png');
            $b64 = base64_encode($im->getImageBlob());
            $im->destroy();
        } catch (\Throwable) {
            return '';
        }
        return '<style>'
            . '.menu-sammlungen .nav-link:before{'
            . 'content:url("data:image/png;base64,' . $b64 . '")}'
            . '</style>';
    }

    private function archivIconBase64(): string
    {
        $path = $this->resourcesFolder() . 'archiv-icon.jpg';
        if (!file_exists($path)) {
            return '';
        }
        return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($path));
    }

    public function getMenu(Tree $tree): ?Menu
    {
        // Menü nur für eingeloggte Mitglieder sichtbar
        if (!Auth::isMember($tree)) {
            return null;
        }

        return new Menu(
            I18N::translate('Sammlungen'),
            route('sammlungen.sammlungen', ['tree' => $tree->name()]),
            'menu-sammlungen',
            ['rel' => 'nofollow'],
        );
    }

    public function cacheTtl(): int
    {
        return max(60, (int) $this->getPreference(self::SETTING_CACHE_TTL, (string) self::DEFAULT_CACHE_TTL));
    }

    public function perPage(): int
    {
        return max(10, min(200, (int) $this->getPreference(self::SETTING_PER_PAGE, (string) self::DEFAULT_PER_PAGE)));
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    private function migrateDatabase(): void
    {
        $schema = DB::schema();

        // Migration v1.0.0 → v1.0.1: Tabellen umbenennen wenn alte Namen
        // (`familienarchiv_*`) vorhanden, neue (`sammlungen_*`) noch nicht.
        // Idempotent – läuft nur einmalig durch.
        $renamings = [
            'familienarchiv_collection'        => 'sammlungen_collection',
            'familienarchiv_collection_medium' => 'sammlungen_collection_medium',
            'familienarchiv_collection_pfad'   => 'sammlungen_collection_pfad',
        ];
        $didDdl = false;
        foreach ($renamings as $alt => $neu) {
            if ($schema->hasTable($alt) && !$schema->hasTable($neu)) {
                $schema->rename($alt, $neu);
                $didDdl = true;
            }
        }

        // RENAME TABLE ist DDL und commit-implizit – das beendet die von
        // webtrees' UseTransaction-Middleware aussen gestartete Transaktion.
        // Wir starten eine neue, damit das spätere Commit der Middleware nicht
        // mit "no active transaction" abbricht.
        if ($didDdl) {
            try {
                $pdo = DB::connection()->getPdo();
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                }
            } catch (\Throwable) {
                // Best effort – wenn's nicht klappt, schlimmstenfalls eine
                // Warnung beim ersten Request nach dem Upgrade. Funktional egal.
            }
        }

        // Tabelle 1: Sammlungs-Definitionen
        if (!$schema->hasTable('sammlungen_collection')) {
            $schema->create('sammlungen_collection', static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('gedcom_id')->unsigned();
                $table->string('slug', 80);
                $table->string('name', 255);
                $table->text('beschreibung')->nullable();
                $table->string('farbe', 7)->default('#6c757d');
                $table->string('icon', 40)->default('folder');
                $table->integer('reihenfolge')->default(0);
                $table->boolean('aktiv')->default(true);
                $table->timestamps();
                $table->unique(['gedcom_id', 'slug']);
            });
        }

        // ordner-Spalte nachrüsten
        if ($schema->hasTable('sammlungen_collection') && !$schema->hasColumn('sammlungen_collection', 'ordner')) {
            $schema->table('sammlungen_collection', static function (Blueprint $table): void {
                $table->string('ordner', 500)->nullable()->default(null)->after('beschreibung');
            });
        }

        // ansicht-Spalte nachrüsten: 'foto' | 'dokument'
        if ($schema->hasTable('sammlungen_collection') && !$schema->hasColumn('sammlungen_collection', 'ansicht')) {
            $schema->table('sammlungen_collection', static function (Blueprint $table): void {
                $table->string('ansicht', 20)->default('foto')->after('ordner');
            });
        }

        // Tabelle 2: Zuordnung Medium ↔ Sammlung (m_id, für importierte Medien)
        if (!$schema->hasTable('sammlungen_collection_medium')) {
            $schema->create('sammlungen_collection_medium', static function (Blueprint $table): void {
                $table->unsignedInteger('collection_id');
                $table->string('m_id', 20);
                $table->unsignedInteger('gedcom_id');
                $table->timestamps();

                $table->primary(['collection_id', 'm_id', 'gedcom_id']);
                $table->index(['gedcom_id', 'collection_id'], 'sml_col_med_gid_cid');
                $table->index(['gedcom_id', 'm_id'], 'sml_col_med_gid_mid');
            });
        }

        // Tabelle 3: Pfad-basierte Zuordnung (für alle Fotos, auch nicht-importierte)
        if (!$schema->hasTable('sammlungen_collection_pfad')) {
            $schema->create('sammlungen_collection_pfad', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('collection_id');
                $table->unsignedInteger('gedcom_id');
                $table->string('pfad', 500);        // relativer Pfad ab data/media/
                $table->string('m_id', 20)->nullable(); // optional: webtrees-Referenz
                $table->timestamps();

                $table->unique(['collection_id', 'gedcom_id', 'pfad'], 'sml_col_pfad_unique');
                $table->index(['gedcom_id', 'collection_id'], 'sml_col_pfad_gid_cid');
            });
        }
    }
}

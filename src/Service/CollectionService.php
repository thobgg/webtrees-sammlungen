<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use Sammlungen\Cache\ApcuCacheService;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Tree;

use function basename;
use function is_dir;
use function pathinfo;
use function str_replace;
use function strtolower;
use function usort;

use const PATHINFO_EXTENSION;

/**
 * Service für das CRUD verwalteter Sammlungen
 * und die Zuordnung von Medienobjekten zu Sammlungen.
 */
class CollectionService
{
    public function __construct(
        private readonly ApcuCacheService $cache
    ) {}

    // ---------------------------------------------------------------
    // Lesezugriff – Sammlungen
    // ---------------------------------------------------------------

    public function aktive(Tree $tree): array
    {
        $cacheKey = sprintf('collections:aktiv:%d', $tree->id());

        return $this->cache->remember($cacheKey, function () use ($tree): array {
            return DB::table('sammlungen_collection')
                ->where('gedcom_id', '=', $tree->id())
                ->where('aktiv', '=', 1)
                ->orderBy('reihenfolge')
                ->orderBy('name')
                ->get()
                ->map(fn (object $r) => $this->castRow($r))
                ->all();
        });
    }

    public function alle(Tree $tree): array
    {
        return DB::table('sammlungen_collection')
            ->where('gedcom_id', '=', $tree->id())
            ->orderBy('reihenfolge')
            ->orderBy('name')
            ->get()
            ->map(fn (object $r) => $this->castRow($r))
            ->all();
    }

    public function findeNachSlug(Tree $tree, string $slug): ?object
    {
        $row = DB::table('sammlungen_collection')
            ->where('gedcom_id', '=', $tree->id())
            ->where('slug', '=', $slug)
            ->first();

        return $row !== null ? $this->castRow($row) : null;
    }

    public function findeNachId(int $id): ?object
    {
        $row = DB::table('sammlungen_collection')
            ->where('id', '=', $id)
            ->first();

        return $row !== null ? $this->castRow($row) : null;
    }

    // ---------------------------------------------------------------
    // Schreibzugriff – Sammlungen
    // ---------------------------------------------------------------

    public function erstellen(
        Tree    $tree,
        string  $slug,
        string  $name,
        string  $beschreibung = '',
        string  $farbe        = '#6c757d',
        string  $icon         = 'folder',
        int     $reihenfolge  = 0,
        bool    $aktiv        = true,
        string  $ordner       = '',
        string  $ansicht      = 'foto',
    ): int {
        $this->validiereSlug($slug);

        if ($this->findeNachSlug($tree, $slug) !== null) {
            throw new \InvalidArgumentException(
                "Slug '{$slug}' ist im Baum '{$tree->name()}' bereits vergeben."
            );
        }

        $id = (int) DB::table('sammlungen_collection')->insertGetId([
            'gedcom_id'    => $tree->id(),
            'slug'         => $slug,
            'name'         => $name,
            'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
            'farbe'        => $this->validiereHexFarbe($farbe),
            'icon'         => $icon,
            'reihenfolge'  => $reihenfolge,
            'aktiv'        => $aktiv ? 1 : 0,
            'ordner'       => $ordner !== '' ? $ordner : null,
            'ansicht'      => in_array($ansicht, ['foto', 'raster', 'gemischt', 'dokument'], true) ? $ansicht : 'foto',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->cache->forget(sprintf('collections:aktiv:%d', $tree->id()));

        return $id;
    }

    public function aktualisieren(
        int     $id,
        string  $name,
        string  $beschreibung = '',
        string  $farbe        = '#6c757d',
        string  $icon         = 'folder',
        int     $reihenfolge  = 0,
        bool    $aktiv        = true,
        string  $ordner       = '',
        string  $ansicht      = 'foto',
    ): bool {
        $affected = DB::table('sammlungen_collection')
            ->where('id', '=', $id)
            ->update([
                'name'         => $name,
                'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
                'farbe'        => $this->validiereHexFarbe($farbe),
                'icon'         => $icon,
                'reihenfolge'  => $reihenfolge,
                'aktiv'        => $aktiv ? 1 : 0,
                'ordner'       => $ordner !== '' ? $ordner : null,
                'ansicht'      => in_array($ansicht, ['foto', 'raster', 'gemischt', 'dokument'], true) ? $ansicht : 'foto',
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

        $this->cache->flush();

        return $affected > 0;
    }

    public function setAktiv(int $id, bool $aktiv): bool
    {
        $affected = DB::table('sammlungen_collection')
            ->where('id', '=', $id)
            ->update([
                'aktiv'      => $aktiv ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $this->cache->flush();

        return $affected > 0;
    }

    public function loeschen(int $id): bool
    {
        // Zuerst alle Zuordnungen entfernen
        DB::table('sammlungen_collection_medium')
            ->where('collection_id', '=', $id)
            ->delete();

        $affected = DB::table('sammlungen_collection')
            ->where('id', '=', $id)
            ->delete();

        $this->cache->flush();

        return $affected > 0;
    }

    // ---------------------------------------------------------------
    // Pfad-basierte Zuordnung (für alle Fotos, auch nicht-importierte)
    // ---------------------------------------------------------------

    public function pfadZuordnen(Tree $tree, int $collectionId, string $pfad, ?string $mId = null): void
    {
        $exists = DB::table('sammlungen_collection_pfad')
            ->where('collection_id', '=', $collectionId)
            ->where('gedcom_id', '=', $tree->id())
            ->where('pfad', '=', $pfad)
            ->exists();

        if (!$exists) {
            DB::table('sammlungen_collection_pfad')->insert([
                'collection_id' => $collectionId,
                'gedcom_id'     => $tree->id(),
                'pfad'          => $pfad,
                'm_id'          => $mId,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        $this->cache->flush();
    }

    public function pfadEntfernen(Tree $tree, int $collectionId, string $pfad): void
    {
        DB::table('sammlungen_collection_pfad')
            ->where('collection_id', '=', $collectionId)
            ->where('gedcom_id', '=', $tree->id())
            ->where('pfad', '=', $pfad)
            ->delete();

        $this->cache->flush();
    }

    /** Gibt alle Pfade einer Sammlung zurück. */
    public function pfadeDerSammlung(Tree $tree, int $collectionId, int $offset = 0, int $limit = 48): array
    {
        $cacheKey = sprintf('collection_pfade:%d:%d:%d:%d', $tree->id(), $collectionId, $offset, $limit);

        return $this->cache->remember($cacheKey, function () use ($tree, $collectionId, $offset, $limit): array {
            return DB::table('sammlungen_collection_pfad')
                ->where('collection_id', '=', $collectionId)
                ->where('gedcom_id', '=', $tree->id())
                ->orderBy('pfad')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(fn (object $r) => [
                    'pfad' => (string) $r->pfad,
                    'm_id' => $r->m_id !== null ? (string) $r->m_id : null,
                ])
                ->values()
                ->all();
        }, 300);
    }

    public function anzahlPfadeSammlung(Tree $tree, int $collectionId): int
    {
        return (int) DB::table('sammlungen_collection_pfad')
            ->where('collection_id', '=', $collectionId)
            ->where('gedcom_id', '=', $tree->id())
            ->count();
    }

    /** Gibt die collection_ids zurück, in denen ein Pfad bereits enthalten ist. */
    public function sammlungenDesPfades(Tree $tree, string $pfad): array
    {
        return DB::table('sammlungen_collection_pfad')
            ->where('pfad', '=', $pfad)
            ->where('gedcom_id', '=', $tree->id())
            ->pluck('collection_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------
    // Zuordnung Medium ↔ Sammlung
    // ---------------------------------------------------------------

    /**
     * Ordnet ein Medienobjekt einer Sammlung zu.
     * Ist die Zuordnung bereits vorhanden, passiert nichts.
     */
    public function mediumZuordnen(Tree $tree, int $collectionId, string $mId): void
    {
        $exists = DB::table('sammlungen_collection_medium')
            ->where('collection_id', '=', $collectionId)
            ->where('m_id', '=', $mId)
            ->where('gedcom_id', '=', $tree->id())
            ->exists();

        if (!$exists) {
            DB::table('sammlungen_collection_medium')->insert([
                'collection_id' => $collectionId,
                'm_id'          => $mId,
                'gedcom_id'     => $tree->id(),
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        $this->cache->flush();
    }

    /**
     * Entfernt die Zuordnung eines Medienobjekts aus einer Sammlung.
     */
    public function mediumEntfernen(Tree $tree, int $collectionId, string $mId): void
    {
        DB::table('sammlungen_collection_medium')
            ->where('collection_id', '=', $collectionId)
            ->where('m_id', '=', $mId)
            ->where('gedcom_id', '=', $tree->id())
            ->delete();

        $this->cache->flush();
    }

    /**
     * Gibt alle m_ids (Media-Xrefs) einer Sammlung zurück.
     *
     * @return list<string>
     */
    public function medienDerSammlung(
        Tree $tree,
        int  $collectionId,
        int  $offset = 0,
        int  $limit  = 24
    ): array {
        $cacheKey = sprintf('collection_medien:%d:%d:%d:%d', $tree->id(), $collectionId, $offset, $limit);

        return $this->cache->remember($cacheKey, function () use ($tree, $collectionId, $offset, $limit): array {
            return DB::table('sammlungen_collection_medium')
                ->where('collection_id', '=', $collectionId)
                ->where('gedcom_id', '=', $tree->id())
                ->orderBy('created_at')
                ->offset($offset)
                ->limit($limit)
                ->pluck('m_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();
        }, 600);
    }

    /**
     * Anzahl der Medien in einer Sammlung.
     */
    public function anzahlMedien(Tree $tree, int $collectionId): int
    {
        return (int) DB::table('sammlungen_collection_medium')
            ->where('collection_id', '=', $collectionId)
            ->where('gedcom_id', '=', $tree->id())
            ->count();
    }

    /**
     * Gibt alle collection_ids zurück, in denen ein Medium enthalten ist.
     *
     * @return list<int>
     */
    public function sammlungenDesMedias(Tree $tree, string $mId): array
    {
        return DB::table('sammlungen_collection_medium')
            ->where('m_id', '=', $mId)
            ->where('gedcom_id', '=', $tree->id())
            ->pluck('collection_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------
    // Ordner-basierte Sammlungen
    // ---------------------------------------------------------------

    public function verfuegbareOrdner(Tree $tree): array
    {
        // Direkt vom Dateisystem lesen – zuverlässig, unabhängig von
        // ggf. falschen Windows-Pfaden in der DB (Ahnenblatt-Migration).
        $mediaBase = \Fisharebest\Webtrees\Webtrees::DATA_DIR
            . $tree->getPreference('MEDIA_DIRECTORY', 'media/');

        if (!is_dir($mediaBase)) {
            return [];
        }

        $ordner = [];

        foreach ($this->medienIterator($mediaBase, false) as $item) {
            if ($item->isDir()) {
                $rel = ltrim(str_replace(['\\', $mediaBase], ['/', ''], $item->getPathname()), '/');
                if ($rel !== '') {
                    $ordner[] = $rel;
                }
            }
        }

        sort($ordner);
        return $ordner;
    }

    /**
     * Erzeugt einen rekursiven Iterator der Synology-Systemordner ausblendet:
     * @eaDir (Thumbnails), @tmp, #recycle, versteckte Ordner (.)
     * Die Thumbnails in @eaDir können perspektivisch für eine Lightbox genutzt werden.
     */
    private function medienIterator(string $pfad, bool $nurDateien = true): \RecursiveIteratorIterator
    {
        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($pfad, \RecursiveDirectoryIterator::SKIP_DOTS),
            static function (\SplFileInfo $item): bool {
                $name = $item->getFilename();
                return !str_starts_with($name, '@')
                    && !str_starts_with($name, '.')
                    && !str_starts_with($name, '._')
                    && $name !== '#recycle'
                    && $name !== 'eaDir'
                    && $name !== 'AppleMark'
                    && $name !== 'Thumbs.db';  // Windows-Thumbnail-Cache
            }
        );
        return new \RecursiveIteratorIterator(
            $filter,
            $nurDateien
                ? \RecursiveIteratorIterator::LEAVES_ONLY
                : \RecursiveIteratorIterator::SELF_FIRST
        );
    }

    /** Prüft anhand des Dateisystems ob eine Sammlung überwiegend Bilddateien enthält (> 50%). */
    public function istBildsammlung(Tree $tree, string $ordner): bool
    {
        $mediaBase  = \Fisharebest\Webtrees\Webtrees::DATA_DIR
            . $tree->getPreference('MEDIA_DIRECTORY', 'media/');
        $ordnerPfad = $mediaBase . $ordner . '/';

        if (!is_dir($ordnerPfad)) {
            return false;
        }

        $bildFormate = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $gesamt      = 0;
        $bilder      = 0;

        foreach ($this->medienIterator($ordnerPfad) as $datei) {
            if ($datei->isFile()) {
                $gesamt++;
                $ext = strtolower(pathinfo($datei->getFilename(), PATHINFO_EXTENSION));
                if (in_array($ext, $bildFormate, true)) {
                    $bilder++;
                }
            }
        }

        return $gesamt > 0 && ($bilder / $gesamt) > 0.5;
    }

    /**
     * Liest EXIF-Datum und -Beschreibung aus einer Bilddatei.
     * Gibt ['datum' => 'TT.MM.JJJJ', 'datum_iso' => 'JJJJ-MM-TT', 'beschreibung' => '...'] zurück.
     *
     * @return array{datum:string,datum_iso:string,beschreibung:string}
     */
    public function exifDaten(string $fullPath): array
    {
        $result = ['datum' => '', 'datum_iso' => '', 'beschreibung' => ''];

        if (!function_exists('exif_read_data') || !is_file($fullPath)) {
            return $result;
        }

        try {
            $exif = @exif_read_data($fullPath, 'EXIF,IFD0', false);
            if (!is_array($exif)) {
                return $result;
            }

            // Datum: DateTimeOriginal bevorzugt, fallback DateTime
            $raw = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? '';
            if ($raw !== '' && preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $raw, $m)) {
                $result['datum_iso'] = "{$m[1]}-{$m[2]}-{$m[3]}";
                $result['datum']     = $m[3] === '00' || $m[2] === '00'
                    ? $m[1]
                    : "{$m[3]}.{$m[2]}.{$m[1]}";
            }

            // Beschreibung aus EXIF ImageDescription – Software-Artefakte ignorieren
            $desc = $exif['ImageDescription'] ?? '';
            if (is_string($desc) && trim($desc) !== '') {
                $desc = trim($desc);
                // Software-generierte Einträge filtern (Scanner-Software etc.)
                $ignorieren = ['LEAD Technologies', 'Adobe', 'OLYMPUS', 'NIKON', 'Canon'];
                $istSoftware = false;
                foreach ($ignorieren as $sw) {
                    if (stripos($desc, $sw) !== false) {
                        $istSoftware = true;
                        break;
                    }
                }
                if (!$istSoftware) {
                    $result['beschreibung'] = $desc;
                }
            }
        } catch (\Throwable) {
            // EXIF nicht lesbar – kein Fehler
        }

        return $result;
    }

    /**
     * Liefert alle mit Medienobjekten verknüpften Personennamen als Map [m_id => [namen]].
     * Batch-Query für mehrere m_ids auf einmal.
     *
     * @param  list<string>           $mIds
     * @return array<string, list<string>>
     */
    public function personenFuerMediaIds(Tree $tree, array $mIds): array
    {
        if ($mIds === []) {
            return [];
        }

        $rows = DB::table('link AS l')
            ->join('name AS n', function ($join): void {
                $join->on('n.n_id', '=', 'l.l_from')
                     ->on('n.n_file', '=', 'l.l_file')
                     ->where('n.n_num', '=', 0); // Primärname
            })
            ->where('l.l_file', '=', $tree->id())
            ->where('l.l_type', '=', 'OBJE')
            ->whereIn('l.l_to', $mIds)
            ->select(['l.l_to AS m_id', 'n.n_full AS name'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $mid  = (string) $row->m_id;
            // GEDCOM-Slashes entfernen: "Anna /Bugge/" → "Anna Bugge"
            $name = trim(str_replace('/', '', (string) $row->name));
            $map[$mid][] = $name;
        }

        return $map;
    }

    /**
     * Gibt webtrees-Metadaten für mehrere m_ids zurück:
     * Titel (TITL), Notiz (NOTE), Personen mit xref für Links.
     *
     * @param  list<string> $mIds
     * @return array<string, array{titel:string, notiz:string, personen:list<array{name:string, xref:string}>}>
     */
    public function webtreesDatenFuerMediaIds(Tree $tree, array $mIds): array
    {
        if ($mIds === []) {
            return [];
        }

        // Titel + GEDCOM-Text (für NOTE-Parsing)
        $medienRows = DB::table('media AS m')
            ->join('media_file AS mf', function ($join): void {
                $join->on('mf.m_id', '=', 'm.m_id')->on('mf.m_file', '=', 'm.m_file');
            })
            ->where('m.m_file', '=', $tree->id())
            ->whereIn('m.m_id', $mIds)
            ->select(['m.m_id', 'm.m_gedcom', 'mf.descriptive_title'])
            ->get();

        $result = [];
        foreach ($medienRows as $row) {
            $mid   = (string) $row->m_id;
            $notiz = '';
            if (preg_match('/^\d NOTE (.+)/m', (string) $row->m_gedcom, $m)) {
                $notiz = trim($m[1]);
            }
            $result[$mid] = [
                'titel'    => (string) ($row->descriptive_title ?? ''),
                'notiz'    => $notiz,
                'personen' => [],
            ];
        }

        // Personen mit xref (für Links zu Personenseiten)
        $personRows = DB::table('link AS l')
            ->join('name AS n', function ($join): void {
                $join->on('n.n_id', '=', 'l.l_from')
                     ->on('n.n_file', '=', 'l.l_file')
                     ->where('n.n_num', '=', 0);
            })
            ->where('l.l_file', '=', $tree->id())
            ->where('l.l_type', '=', 'OBJE')
            ->whereIn('l.l_to', $mIds)
            ->select(['l.l_to AS m_id', 'l.l_from AS i_id', 'n.n_full AS name'])
            ->get();

        foreach ($personRows as $row) {
            $mid  = (string) $row->m_id;
            $name = trim(str_replace('/', '', (string) $row->name));
            if (isset($result[$mid])) {
                $result[$mid]['personen'][] = [
                    'name' => $name,
                    'xref' => (string) $row->i_id,
                ];
            }
        }

        return $result;
    }

    /** Gibt Detaildaten (m_id, Titel, Dateiname, Format) für Listenansicht zurück. */
    public function medienDetailsInOrdner(
        Tree   $tree,
        string $ordner,
        int    $offset = 0,
        int    $limit  = 50
    ): array {
        return DB::table('media_file AS mf')
            ->join('media AS m', function ($join): void {
                $join->on('m.m_id', '=', 'mf.m_id')->on('m.m_file', '=', 'mf.m_file');
            })
            ->where('mf.m_file', '=', $tree->id())
            ->where('mf.multimedia_file_refn', 'LIKE', $ordner . '/%')
            ->select(['m.m_id', 'mf.multimedia_file_refn', 'mf.descriptive_title', 'mf.multimedia_format'])
            ->distinct()
            ->orderBy('mf.multimedia_file_refn')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (object $r): array => [
                'm_id'   => (string) $r->m_id,
                'titel'  => (string) ($r->descriptive_title ?? ''),
                'datei'  => basename((string) $r->multimedia_file_refn),
                'format' => strtolower((string) ($r->multimedia_format ?? '')),
            ])
            ->values()
            ->all();
    }

    public function anzahlInOrdner(Tree $tree, string $ordner): int
    {
        return (int) DB::table('media_file AS mf')
            ->join('media AS m', function ($join) {
                $join->on('m.m_id', '=', 'mf.m_id')->on('m.m_file', '=', 'mf.m_file');
            })
            ->where('mf.m_file', '=', $tree->id())
            ->where('mf.multimedia_file_refn', 'LIKE', $ordner . '/%')
            ->distinct()
            ->count('m.m_id');
    }

    public function medienInOrdner(Tree $tree, string $ordner, int $offset = 0, int $limit = 48): array
    {
        return DB::table('media_file AS mf')
            ->join('media AS m', function ($join) {
                $join->on('m.m_id', '=', 'mf.m_id')->on('m.m_file', '=', 'mf.m_file');
            })
            ->where('mf.m_file', '=', $tree->id())
            ->where('mf.multimedia_file_refn', 'LIKE', $ordner . '/%')
            ->groupBy('m.m_id')
            ->orderByRaw('MAX(' . DB::prefix('mf') . '.multimedia_file_refn)')
            ->offset($offset)
            ->limit($limit)
            ->pluck('m.m_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Liefert ALLE Dateien aus einem Ordner (Dateisystem + DB-Abgleich).
     * Nicht-importierte Dateien haben m_id=null.
     *
     * @return list<array{pfad:string, datei:string, format:string, m_id:string|null, titel:string}>
     */
    /** Zählt alle Dateien im Ordner vom Dateisystem (gecacht, 5 Min). */
    public function anzahlDateienImOrdner(Tree $tree, string $ordner): int
    {
        $cacheKey = sprintf('ordner_fs_count:%d:%s', $tree->id(), md5($ordner));

        return $this->cache->remember($cacheKey, function () use ($tree, $ordner): int {
            $mediaBase  = \Fisharebest\Webtrees\Webtrees::DATA_DIR
                . $tree->getPreference('MEDIA_DIRECTORY', 'media/');
            $ordnerPfad = $mediaBase . $ordner . '/';

            if (!is_dir($ordnerPfad)) {
                return 0;
            }

            $count = 0;
            foreach ($this->medienIterator($ordnerPfad) as $datei) {
                if ($datei->isFile()) {
                    $count++;
                }
            }
            return $count;
        }, 300);
    }

    public function alleDateienInOrdner(Tree $tree, string $ordner): array
    {
        $mediaBase = \Fisharebest\Webtrees\Webtrees::DATA_DIR
            . $tree->getPreference('MEDIA_DIRECTORY', 'media/');

        $ordnerPfad = $mediaBase . $ordner . '/';

        if (!is_dir($ordnerPfad)) {
            return [];
        }

        // Alle Dateien rekursiv – Synology-Systemordner (@eaDir etc.) ausgeblendet
        $dateisystemPfade = [];
        foreach ($this->medienIterator($ordnerPfad) as $datei) {
            if ($datei->isFile()) {
                // Relativer Pfad ab media-Basis (z.B. "Kirchenbücher-.../datei.pdf")
                $relativPfad = $ordner . '/' . ltrim(
                    str_replace(str_replace('\\', '/', $ordnerPfad), '', str_replace('\\', '/', $datei->getPathname())),
                    '/'
                );
                $dateisystemPfade[$relativPfad] = true;
            }
        }

        // DB-Einträge für diesen Ordner abrufen
        $dbEintraege = DB::table('media_file AS mf')
            ->join('media AS m', function ($join): void {
                $join->on('m.m_id', '=', 'mf.m_id')->on('m.m_file', '=', 'mf.m_file');
            })
            ->where('mf.m_file', '=', $tree->id())
            ->where('mf.multimedia_file_refn', 'LIKE', $ordner . '/%')
            ->select(['m.m_id', 'mf.multimedia_file_refn', 'mf.descriptive_title', 'mf.multimedia_format'])
            ->get()
            ->keyBy('multimedia_file_refn')
            ->all();

        // Zusammenführen: Dateisystem ist führend
        $ergebnis = [];
        foreach (array_keys($dateisystemPfade) as $pfad) {
            $db     = $dbEintraege[$pfad] ?? null;
            $ext    = strtolower(pathinfo($pfad, PATHINFO_EXTENSION));
            $ergebnis[] = [
                'pfad'   => $pfad,
                'datei'  => basename($pfad),
                'format' => $ext,
                'm_id'   => $db !== null ? (string) $db->m_id : null,
                'titel'  => $db !== null ? (string) ($db->descriptive_title ?? '') : '',
            ];
        }

        // Nach Pfad sortieren
        usort($ergebnis, fn ($a, $b) => strcmp($a['pfad'], $b['pfad']));

        return $ergebnis;
    }

    public function vorschauInOrdner(Tree $tree, string $ordner, int $n): array
    {
        return DB::table('media_file AS mf')
            ->join('media AS m', function ($join) {
                $join->on('m.m_id', '=', 'mf.m_id')->on('m.m_file', '=', 'mf.m_file');
            })
            ->where('mf.m_file', '=', $tree->id())
            ->where('mf.multimedia_file_refn', 'LIKE', $ordner . '/%')
            ->whereIn('mf.multimedia_format', ['jpg', 'jpeg', 'png', 'gif', 'webp'])
            ->groupBy('m.m_id')
            ->orderByRaw('MAX(' . DB::prefix('mf') . '.multimedia_file_refn) DESC')
            ->limit($n)
            ->pluck('m.m_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------
    // Validierung & Mapping
    // ---------------------------------------------------------------

    private function validiereSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9_-]{1,80}$/', $slug)) {
            throw new \InvalidArgumentException(
                "Slug '{$slug}' ist ungültig. Erlaubt: Kleinbuchstaben, Ziffern, - und _."
            );
        }
    }

    private function validiereHexFarbe(string $farbe): string
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $farbe)) {
            return '#6c757d';
        }

        return strtolower($farbe);
    }

    private function castRow(object $row): object
    {
        $row->id           = (int) $row->id;
        $row->gedcom_id    = (int) $row->gedcom_id;
        $row->reihenfolge  = (int) $row->reihenfolge;
        $row->aktiv        = (bool) $row->aktiv;
        $row->beschreibung = $row->beschreibung ?? null;
        $row->ordner       = $row->ordner ?? null;
        $row->ansicht      = $row->ansicht ?? 'foto';

        return $row;
    }
}

<?php

declare(strict_types=1);

namespace Sammlungen\Repository;

use Sammlungen\Cache\ApcuCacheService;
use Sammlungen\Dto\SammlungDto;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Query\Builder;

/**
 * Repository für Mediensammlungen.
 *
 * Gruppiert Medienobjekte nach `source_media_type` aus der
 * webtrees-Tabelle `media_file`.
 *
 * Liest aus:
 *   - `media_file`  (id, m_id, m_file, source_media_type, descriptive_title)
 *   - `media`       (m_id, m_file, m_gedcom)
 */
class SammlungenRepository
{
    /** Cache-TTL (20 Minuten) */
    private const CACHE_TTL = 1200;

    public function __construct(
        private readonly ApcuCacheService $cache
    ) {}

    // ---------------------------------------------------------------
    // Öffentliche API
    // ---------------------------------------------------------------

    /**
     * Gibt alle Sammlungen (= Medientyp-Gruppen) zurück,
     * absteigend nach Anzahl sortiert.
     *
     * @return list<SammlungDto>
     */
    public function alleSammlungen(Tree $tree): array
    {
        $cacheKey = sprintf('sammlungen:%d', $tree->id());

        return $this->cache->remember($cacheKey, function () use ($tree) {
            return $this->queryAlleSammlungen($tree);
        }, self::CACHE_TTL);
    }

    /**
     * Gibt eine einzelne Sammlung nach Medientyp zurück.
     * $typ = '' steht für Medien ohne Typangabe.
     */
    public function findeSammlung(Tree $tree, string $typ): ?SammlungDto
    {
        // Aus dem Gesamt-Cache holen statt extra Query
        $alle = $this->alleSammlungen($tree);

        foreach ($alle as $sammlung) {
            if ($sammlung->typ === $typ) {
                return $sammlung;
            }
        }

        return null;
    }

    /**
     * Gibt die Medien-Xrefs (m_id) einer Sammlung zurück
     * (für Detailansicht / Paginierung).
     *
     * @return list<string>
     */
    public function medienInSammlung(
        Tree   $tree,
        string $typ,
        int    $offset = 0,
        int    $limit  = 24
    ): array {
        $cacheKey = sprintf('sammlung_medien:%d:%s:%d:%d', $tree->id(), $typ, $offset, $limit);

        return $this->cache->remember($cacheKey, function () use ($tree, $typ, $offset, $limit) {
            return $this->queryMedienInSammlung($tree, $typ, $offset, $limit);
        }, self::CACHE_TTL);
    }

    // ---------------------------------------------------------------
    // Interne Queries
    // ---------------------------------------------------------------

    /** @return list<SammlungDto> */
    private function queryAlleSammlungen(Tree $tree): array
    {
        $rows = DB::table('media_file AS mf')
            ->join('media AS m', function ($join) use ($tree) {
                $join->on('m.m_id', '=', 'mf.m_id')
                     ->where('m.m_file', '=', $tree->id());
            })
            ->where('mf.m_file', '=', $tree->id())
            ->select('mf.source_media_type')
            ->selectRaw('COUNT(DISTINCT ' . DB::prefix('mf') . '.m_id) AS anzahl')
            // Drei neueste m_ids für Vorschau (subquery wäre aufwändiger → nachher holen)
            ->groupBy('mf.source_media_type')
            ->orderByDesc('anzahl')
            ->get();

        $dtos = [];
        foreach ($rows as $row) {
            $typ  = (string) ($row->source_media_type ?? '');
            $name = SammlungDto::typBezeichnung($typ);

            $vorschauXrefs = $this->queryVorschauXrefs($tree, $typ, 3);

            $dtos[] = new SammlungDto(
                typ:           $typ,
                name:          $name,
                anzahl:        (int) $row->anzahl,
                vorschauXrefs: $vorschauXrefs,
            );
        }

        return $dtos;
    }

    /**
     * @return list<string>
     */
    private function queryMedienInSammlung(
        Tree   $tree,
        string $typ,
        int    $offset,
        int    $limit
    ): array {
        return DB::table('media_file AS mf')
            ->where('mf.m_file', '=', $tree->id())
            ->where('mf.source_media_type', '=', $typ)
            ->select('mf.m_id')
            ->groupBy('mf.m_id')
            ->orderBy('mf.m_id')
            ->offset($offset)
            ->limit($limit)
            ->pluck('mf.m_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function queryVorschauXrefs(Tree $tree, string $typ, int $n): array
    {
        return DB::table('media_file AS mf')
            ->where('mf.m_file', '=', $tree->id())
            ->where('mf.source_media_type', '=', $typ)
            ->whereIn('mf.multimedia_format', ['jpg', 'jpeg', 'png', 'gif', 'webp'])
            ->select('mf.m_id')
            ->groupBy('mf.m_id')
            ->orderByDesc('mf.m_id')
            ->limit($n)
            ->pluck('mf.m_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------
    // Nicht-eingebundene Medien (kein OBJE-Link, nur lokale Dateien)
    // ---------------------------------------------------------------

    /**
     * Schraenkt eine Abfrage auf Medien ein, an denen weder eine Person noch
     * eine Familie haengt.
     *
     * Bis 1.3.3 genuegte hier irgendeine `OBJE`-Zeile. In webtrees darf die
     * aber genauso in einer Quelle, einer Notiz, einem Repositorium oder einem
     * Einreicher stehen - und wer seine Registerscans sauber an Quellen haengt,
     * bekam dadurch einen leeren freien Bestand gemeldet, obwohl an keiner
     * Person etwas hing. Genau diese Menge ist aber der Zweck der Ansicht:
     * "Teil des Familienarchivs ohne Verknuepfung im Stammbaum".
     */
    private function nurOhnePersonOderFamilie(Builder $query): Builder
    {
        return $query->whereNotExists(function (Builder $unter): void {
            $unter->from('link AS lnk')
                ->leftJoin('individuals AS i', function ($join): void {
                    $join->on('i.i_file', '=', 'lnk.l_file')
                         ->on('i.i_id', '=', 'lnk.l_from');
                })
                ->leftJoin('families AS f', function ($join): void {
                    $join->on('f.f_file', '=', 'lnk.l_file')
                         ->on('f.f_id', '=', 'lnk.l_from');
                })
                ->whereColumn('lnk.l_file', '=', 'm.m_file')
                ->whereColumn('lnk.l_to', '=', 'm.m_id')
                ->where('lnk.l_type', '=', 'OBJE')
                ->where(function (Builder $oder): void {
                    $oder->whereNotNull('i.i_id')->orWhereNotNull('f.f_id');
                })
                ->selectRaw('1');
        });
    }

    /**
     * Anzahl nicht-eingebundener Medien, gruppiert nach source_media_type.
     *
     * "Nicht eingebunden" bedeutet: es haengt weder eine Person noch eine
     * Familie daran. Verweise aus Quellen oder Notizen zaehlen nicht als
     * Einbindung in den Stammbaum. Externe URLs (http/https) bleiben aussen vor.
     *
     * @return array<string, int>  [typ => anzahl], absteigend nach anzahl
     */
    public function anzahlNachTypOhneVerknuepfung(Tree $tree): array
    {
        $cacheKey = sprintf('sammlungen_unverknuepft:%d', $tree->id());

        return $this->cache->remember($cacheKey, function () use ($tree): array {
            $basis = DB::table('media AS m')
                ->join('media_file AS mf', function ($join): void {
                    $join->on('mf.m_id', '=', 'm.m_id')
                         ->on('mf.m_file', '=', 'm.m_file');
                })
                ->where('m.m_file', '=', $tree->id())
                ->where('mf.multimedia_file_refn', 'NOT LIKE', 'http:%')
                ->where('mf.multimedia_file_refn', 'NOT LIKE', 'https:%');

            $rows = $this->nurOhnePersonOderFamilie($basis)
                ->select('mf.source_media_type')
                ->selectRaw('COUNT(DISTINCT ' . DB::prefix('m') . '.m_id) AS anzahl')
                ->groupBy('mf.source_media_type')
                ->orderByDesc('anzahl')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $typ          = (string) ($row->source_media_type ?? '');
                $result[$typ] = (int) $row->anzahl;
            }

            return $result;
        }, self::CACHE_TTL);
    }

    /**
     * m_ids nicht-eingebundener Medien, optional nach source_media_type gefiltert.
     * Leerer $typ = alle Typen.
     *
     * @return list<string>
     */
    public function medienOhneVerknuepfung(
        Tree   $tree,
        string $typ,
        int    $offset = 0,
        int    $limit  = 24
    ): array {
        $cacheKey = sprintf('sammlungen_unverknuepft_medien:%d:%s:%d:%d', $tree->id(), $typ, $offset, $limit);

        return $this->cache->remember($cacheKey, function () use ($tree, $typ, $offset, $limit): array {
            $query = $this->nurOhnePersonOderFamilie(
                DB::table('media AS m')
                    ->join('media_file AS mf', function ($join): void {
                        $join->on('mf.m_id', '=', 'm.m_id')
                             ->on('mf.m_file', '=', 'm.m_file');
                    })
                    ->where('m.m_file', '=', $tree->id())
                    ->where('mf.multimedia_file_refn', 'NOT LIKE', 'http:%')
                    ->where('mf.multimedia_file_refn', 'NOT LIKE', 'https:%')
            );

            if ($typ !== '') {
                $query->where('mf.source_media_type', '=', $typ);
            }

            return $query
                ->select('m.m_id')
                ->distinct()
                ->orderBy('m.m_id')
                ->offset($offset)
                ->limit($limit)
                ->pluck('m.m_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();
        }, self::CACHE_TTL);
    }

    /**
     * Vorschau-Xrefs (nur Bilder) für nicht-eingebundene Medien.
     * Leerer $typ = alle Typen.
     *
     * @return list<string>
     */
    public function queryVorschauOhneVerknuepfung(Tree $tree, string $typ, int $n): array
    {
        $query = $this->nurOhnePersonOderFamilie(
            DB::table('media AS m')
                ->join('media_file AS mf', function ($join): void {
                    $join->on('mf.m_id', '=', 'm.m_id')
                         ->on('mf.m_file', '=', 'm.m_file');
                })
                ->where('m.m_file', '=', $tree->id())
                ->where('mf.multimedia_file_refn', 'NOT LIKE', 'http:%')
                ->where('mf.multimedia_file_refn', 'NOT LIKE', 'https:%')
                ->whereIn('mf.multimedia_format', ['jpg', 'jpeg', 'png', 'gif', 'webp'])
        );

        if ($typ !== '') {
            $query->where('mf.source_media_type', '=', $typ);
        }

        return $query
            ->select('m.m_id')
            ->distinct()
            ->orderByDesc('m.m_id')
            ->limit($n)
            ->pluck('m.m_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }
}

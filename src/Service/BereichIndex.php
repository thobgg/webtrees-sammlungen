<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Tree;
use Sammlungen\Cache\ApcuCacheService;

use function array_chunk;
use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function filemtime;
use function implode;
use function in_array;
use function is_dir;
use function ltrim;
use function microtime;
use function pathinfo;
use function sort;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;

use const PATHINFO_EXTENSION;

/**
 * Welche Personen auf welchem Bild markiert sind - damit "alle Bilder, auf
 * denen I21 zu sehen ist" nicht bei jeder Anfrage jede Datei des Archivs
 * oeffnen muss.
 *
 * Die Angaben selbst stehen in den Bilddateien (siehe Bereiche); die Tabelle
 * ist nur ein Verzeichnis und laesst sich jederzeit neu aufbauen. Beim
 * Auffrischen wird jede Datei nur gelesen, wenn sich ihre Aenderungszeit
 * geaendert hat - auch Rahmen, die digiKam auf der NAS gesetzt hat, kommen
 * so hinein. Das Schreiben ueber die Schnittstelle traegt die Datei sofort
 * ein.
 *
 * Ein grosses Archiv beim ersten Mal ganz zu lesen kann dauern. Deshalb
 * arbeitet jedes Auffrischen nur eine begrenzte Zeit; was liegen bleibt,
 * kommt bei der naechsten Anfrage dran.
 */
class BereichIndex
{
    private const TABELLE = 'sammlungen_bereich_index';

    private const BILD_FORMATE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Hoechstens so lange liest ein Auffrischen Dateien (Sekunden). */
    private const ZEITBUDGET = 8.0;

    /** Wie oft der Ordner hoechstens durchgesehen wird (Sekunden). */
    private const PAUSE = 60;

    public function __construct(
        private readonly ExifService      $exifService,
        private readonly ApcuCacheService $cache,
    ) {}

    /**
     * Den Index mit dem Medienordner abgleichen.
     *
     * @return bool true: alles gelesen; false: Zeit abgelaufen, Rest beim naechsten Mal
     */
    public function auffrischen(Tree $tree): bool
    {
        $schluessel = 'bereichindex:' . $tree->id();

        // Kurz nach einem vollstaendigen Durchgang nicht gleich wieder.
        if ($this->cache->remember($schluessel, static fn (): bool => false, self::PAUSE)) {
            return true;
        }

        $vollstaendig = $this->durchgang($tree);

        if ($vollstaendig) {
            $this->cache->forget($schluessel);
            $this->cache->remember($schluessel, static fn (): bool => true, self::PAUSE);
        }

        return $vollstaendig;
    }

    /** Eine Datei neu eintragen - nach dem Schreiben ueber die Schnittstelle. */
    public function datei(Tree $tree, string $pfad): void
    {
        $voll = MedienPfad::wurzel($tree) . $pfad;
        clearstatcache(true, $voll);
        $mtime = @filemtime($voll);

        if ($mtime === false) {
            DB::table(self::TABELLE)->where('gedcom_id', '=', $tree->id())->where('pfad', '=', $pfad)->delete();

            return;
        }

        DB::table(self::TABELLE)->updateOrInsert(
            ['gedcom_id' => $tree->id(), 'pfad' => $pfad],
            ['mtime' => $mtime, 'xrefs' => $this->xrefsLesen($voll)]
        );
    }

    /**
     * Die Bilder, auf denen diese Person markiert ist, nach Pfad sortiert.
     *
     * @return list<string>
     */
    public function pfadeMitPerson(Tree $tree, string $xref): array
    {
        $zeilen = DB::table(self::TABELLE)
            ->where('gedcom_id', '=', $tree->id())
            ->where('xrefs', 'LIKE', '%|' . str_replace(['\\', '%'], ['\\\\', '\\%'], $xref) . '|%')
            ->pluck('xrefs', 'pfad');

        $pfade = [];

        // "_" ist in LIKE ein Platzhalter und in Kennungen erlaubt - hier genau pruefen.
        foreach ($zeilen as $pfad => $xrefs) {
            if (in_array($xref, explode('|', (string) $xrefs), true)) {
                $pfade[] = (string) $pfad;
            }
        }

        sort($pfade);

        return $pfade;
    }

    private function durchgang(Tree $tree): bool
    {
        $wurzel = MedienPfad::wurzel($tree);

        if (!is_dir($wurzel)) {
            return true;
        }

        $bekannt = DB::table(self::TABELLE)
            ->where('gedcom_id', '=', $tree->id())
            ->pluck('mtime', 'pfad')
            ->map(static fn ($m): int => (int) $m)
            ->all();

        $start   = microtime(true);
        $gesehen = [];
        $neu     = [];
        $fertig  = true;
        $laenge  = strlen($wurzel);

        foreach (CollectionService::medienIterator($wurzel) as $datei) {
            if (!$datei->isFile()) {
                continue;
            }

            $ext = strtolower(pathinfo($datei->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, self::BILD_FORMATE, true)) {
                continue;
            }

            $pfad = ltrim(str_replace('\\', '/', substr($datei->getPathname(), $laenge)), '/');
            $gesehen[$pfad] = true;
            $mtime = (int) $datei->getMTime();

            if (($bekannt[$pfad] ?? null) === $mtime) {
                continue;
            }

            if (microtime(true) - $start > self::ZEITBUDGET) {
                $fertig = false;
                continue;
            }

            $xrefs = $this->xrefsLesen($datei->getPathname());

            if (isset($bekannt[$pfad])) {
                DB::table(self::TABELLE)
                    ->where('gedcom_id', '=', $tree->id())
                    ->where('pfad', '=', $pfad)
                    ->update(['mtime' => $mtime, 'xrefs' => $xrefs]);
            } else {
                $neu[] = ['gedcom_id' => $tree->id(), 'pfad' => $pfad, 'mtime' => $mtime, 'xrefs' => $xrefs];
            }
        }

        foreach (array_chunk($neu, 200) as $block) {
            DB::table(self::TABELLE)->insert($block);
        }

        // Geloeschte und umbenannte Dateien austragen.
        $weg = array_values(array_filter(
            array_map('strval', array_keys($bekannt)),
            static fn (string $pfad): bool => !isset($gesehen[$pfad])
        ));

        foreach (array_chunk($weg, 500) as $block) {
            DB::table(self::TABELLE)->where('gedcom_id', '=', $tree->id())->whereIn('pfad', $block)->delete();
        }

        return $fertig;
    }

    private function xrefsLesen(string $voll): string
    {
        $xrefs = Bereiche::xrefs($this->exifService->leseMeta($voll)['bereiche']);

        return $xrefs === [] ? '' : '|' . implode('|', $xrefs) . '|';
    }
}

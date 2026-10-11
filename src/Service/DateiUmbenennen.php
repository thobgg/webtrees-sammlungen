<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\PendingChangesService;
use Fisharebest\Webtrees\Tree;

use function date;
use function dirname;
use function file_exists;
use function is_file;
use function pathinfo;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function realpath;
use function rename;
use function str_starts_with;
use function strtolower;
use function trim;

use const PATHINFO_EXTENSION;

/** Eine Datei, die sich nicht umbenennen laesst - mit dem Code, den die App liest. */
final class DateiUmbenennenException extends \RuntimeException
{
    public function __construct(public readonly string $fehlercode)
    {
        parent::__construct($fehlercode);
    }
}

/**
 * Benennt eine Datei im Medienordner um - im selben Ordner, mit derselben Endung.
 *
 * Mitgezogen wird alles, was den Pfad kennt: die Zuordnungen zu Sammlungen, das
 * Verzeichnis der Gesichter und, wenn die Datei zu einem Medienobjekt gehoert,
 * dessen FILE-Zeile im Stammbaum. Letzteres wie webtrees selbst beim Umbenennen
 * einer Mediendatei: Datensatz aendern und die Aenderung sofort annehmen, damit
 * Datei und Stammbaum nicht auseinanderlaufen.
 *
 * Die Endung bleibt, bis auf Gross- und Kleinschreibung: sonst wuerde aus einem
 * Bild mit einem Handgriff eine Datei, die der Server anders behandelt.
 */
final class DateiUmbenennen
{
    public function __construct(
        private readonly PendingChangesService $pendingChanges,
        private readonly BereichIndex          $bereichIndex,
        private readonly CollectionService     $collectionService,
    ) {}

    /**
     * @return string der neue Pfad ab Medienordner
     *
     * @throws DateiUmbenennenException
     */
    public function umbenennen(Tree $tree, string $altPfad, string $neuerName): string
    {
        $altPfad   = trim($altPfad);
        $neuerName = trim($neuerName);

        if ($altPfad === '') {
            throw new DateiUmbenennenException('missing-pfad');
        }

        $fehler = self::nameFehler($altPfad, $neuerName);

        if ($fehler !== null) {
            throw new DateiUmbenennenException($fehler);
        }

        $wurzel   = MedienPfad::wurzel($tree);
        $altVoll  = realpath($wurzel . $altPfad);
        $realBase = realpath($wurzel);

        if ($altVoll === false || $realBase === false || !str_starts_with($altVoll, $realBase) || !is_file($altVoll)) {
            throw new DateiUmbenennenException('file-not-found');
        }

        $neuPfad = self::neuerPfad($altPfad, $neuerName);

        if ($neuPfad === $altPfad) {
            return $altPfad;
        }

        $neuVoll = dirname($altVoll) . '/' . $neuerName;

        // Nur Gross-/Kleinschreibung geaendert: auf einem Dateisystem, das sie
        // nicht unterscheidet, ist "die neue Datei" die alte.
        if (file_exists($neuVoll) && strtolower($neuPfad) !== strtolower($altPfad)) {
            throw new DateiUmbenennenException('name-exists');
        }

        if (!@rename($altVoll, $neuVoll)) {
            throw new DateiUmbenennenException('rename-failed');
        }

        DB::table('sammlungen_collection_pfad')
            ->where('gedcom_id', '=', $tree->id())
            ->where('pfad', '=', $altPfad)
            ->update(['pfad' => $neuPfad, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->medienobjekteNachziehen($tree, $altPfad, $neuPfad);

        $this->bereichIndex->datei($tree, $altPfad);
        $this->bereichIndex->datei($tree, $neuPfad);
        $this->collectionService->cacheLeeren();

        return $neuPfad;
    }

    /**
     * Was am neuen Namen nicht stimmt, als Code - oder null.
     *
     *   bad-name           leer, mit Pfadtrenner, versteckt (".x"), Doppelpunkt oder Steuerzeichen
     *   extension-changed  andere Endung als die Datei (Gross-/Kleinschreibung zaehlt nicht)
     */
    public static function nameFehler(string $altPfad, string $neuerName): ?string
    {
        if ($neuerName === ''
            || preg_match('/[\/\\\\:\x00-\x1F\x7F]/', $neuerName) === 1
            || str_starts_with($neuerName, '.')
            || trim($neuerName, ' .') !== $neuerName
        ) {
            return 'bad-name';
        }

        if (pathinfo($neuerName, \PATHINFO_FILENAME) === '') {
            return 'bad-name';
        }

        $alt = strtolower(pathinfo($altPfad, PATHINFO_EXTENSION));
        $neu = strtolower(pathinfo($neuerName, PATHINFO_EXTENSION));

        return $alt === $neu ? null : 'extension-changed';
    }

    /** Gleicher Ordner, neuer Name. */
    public static function neuerPfad(string $altPfad, string $neuerName): string
    {
        $ordner = dirname($altPfad);

        return ($ordner === '.' || $ordner === '' ? '' : $ordner . '/') . $neuerName;
    }

    /**
     * Die FILE-Zeilen der Medienobjekte, die auf die alte Datei zeigen. Titel
     * und Format darunter bleiben, nur der Pfad wechselt.
     */
    private function medienobjekteNachziehen(Tree $tree, string $altPfad, string $neuPfad): void
    {
        $xrefs = DB::table('media_file')
            ->where('m_file', '=', $tree->id())
            ->where('multimedia_file_refn', '=', $altPfad)
            ->pluck('m_id')
            ->unique()
            ->all();

        foreach ($xrefs as $xref) {
            $media = Registry::mediaFactory()->make((string) $xref, $tree);

            if (!$media instanceof Media || !$media->canEdit()) {
                continue;
            }

            foreach ($media->facts(['FILE'], false, null, true) as $fact) {
                if (!$fact instanceof Fact || trim($fact->value()) !== $altPfad) {
                    continue;
                }

                $gedcom = (string) preg_replace(
                    '/^1 FILE ' . preg_quote($fact->value(), '/') . '$/m',
                    '1 FILE ' . $neuPfad,
                    $fact->gedcom(),
                    1
                );

                $media->updateFact($fact->id(), $gedcom, true);
            }

            $this->pendingChanges->acceptRecord($media);
        }

        // Falls webtrees den Datensatz nicht anfassen konnte: wenigstens die
        // Tabelle, aus der Galerie und Abfragen lesen.
        DB::table('media_file')
            ->where('m_file', '=', $tree->id())
            ->where('multimedia_file_refn', '=', $altPfad)
            ->update(['multimedia_file_refn' => $neuPfad]);
    }
}

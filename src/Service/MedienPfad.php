<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Webtrees;

use function rtrim;
use function str_replace;

/**
 * Wo die Mediendateien eines Stammbaums liegen.
 *
 * Das Modul hat den Pfad bisher an elf Stellen selbst zusammengesetzt, aus der
 * Konstante `Webtrees::DATA_DIR` und der Baum-Einstellung `MEDIA_DIRECTORY`.
 * Die Konstante ist aber nur die *Vorgabe* fuer die Website-Einstellung
 * `INDEX_DIRECTORY`; webtrees selbst liest immer diese Einstellung. Wer sein
 * Datenverzeichnis verschiebt - der empfohlene Weg, die Originale aus der
 * Reichweite der Adresszeile zu nehmen -, bei dem suchte das Modul weiter unter
 * `<webtrees>/data/` und fand nichts: leere Sammlungen, freier Bestand null,
 * Bilder 404 (Issue #23).
 *
 * Dass die Dateien im Ordner liegen duerfen, ohne dass der Webserver ihn
 * ausliefert, geht in Ordnung: webtrees streamt sie durch PHP, und dieses Modul
 * tut dasselbe.
 */
final class MedienPfad
{
    /**
     * Setzt Datenverzeichnis und Medienordner zusammen.
     *
     * Getrennt von der Einstellungs-Abfrage, weil hier die Fallstricke sitzen -
     * Schraegstriche am Ende, doppelte Trenner, Windows-Schreibweise - und weil
     * sich das ohne Datenbank pruefen laesst.
     */
    public static function zusammensetzen(string $datenverzeichnis, string $medienordner): string
    {
        $datenverzeichnis = str_replace('\\', '/', $datenverzeichnis);
        $medienordner     = str_replace('\\', '/', $medienordner);

        $basis = rtrim($datenverzeichnis, '/');
        $unter = trim($medienordner, '/');

        if ($unter === '') {
            return $basis . '/';
        }

        return $basis . '/' . $unter . '/';
    }

    /**
     * Das Datenverzeichnis der Website - dorthin legt das Modul auch seine
     * Sicherungen vor dem Schreiben von EXIF.
     */
    public static function datenverzeichnis(): string
    {
        $wert = Site::getPreference('INDEX_DIRECTORY');

        return rtrim(str_replace('\\', '/', $wert !== '' ? $wert : Webtrees::DATA_DIR), '/') . '/';
    }

    /** Der Medienordner dieses Stammbaums, so wie webtrees ihn bestimmt. */
    public static function wurzel(Tree $tree): string
    {
        return self::zusammensetzen(self::datenverzeichnis(), $tree->mediaFolder());
    }
}

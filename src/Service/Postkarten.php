<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use function array_values;
use function basename;
use function count;
use function dirname;
use function implode;
use function in_array;
use function pathinfo;
use function preg_match;
use function preg_split;
use function strcmp;
use function strtolower;
use function strtoupper;
use function trim;
use function usort;

use const PATHINFO_EXTENSION;

/**
 * Was eine Postkarte ausmacht: zwei Dateien, die zusammengehoeren, und eine
 * Beschreibung, die drei Dinge auf einmal sagt.
 *
 * Kopplung. Die Dateien heissen PK_0001_V.jpg (Vorderseite) und
 * PK_0001_R.jpg (Rueckseite); der Name vor dem _V/_R ist der Schluessel.
 * Eine Datei ohne Gegenstueck ist eine Einzelkarte. Zwei Dateien, die in
 * webtrees zu demselben Medienobjekt gehoeren (ein OBJE mit zwei FILE), sind
 * ebenfalls ein Paar, auch wenn die Namen es nicht sagen.
 *
 * Beschreibung. Der Editor hat ein Feld dafuer, und in dieses eine Feld
 * gehoeren Motiv, Transkription und Notiz. Sie stehen hintereinander, jeweils
 * mit einer festen Ueberschrift am Zeilenanfang - so bleibt die Beschreibung
 * in jedem anderen Programm lesbar, und das Modul kann sie trotzdem wieder
 * auseinandernehmen. Die Ueberschriften sind fest deutsch, weil sie in der
 * Datei liegen und nicht mit der Oberflaechensprache wechseln duerfen.
 */
final class Postkarten
{
    public const ANSICHT = 'postkarte';

    public const VORDERSEITE = 'V';
    public const RUECKSEITE  = 'R';

    /** Dateiname -> Schluessel + Seite. Endung gross oder klein, Seite gross oder klein. */
    private const MUSTER = '/^(.+)_([VvRr])\.jpe?g$/i';

    public const UEBERSCHRIFT_MOTIV         = 'Motiv';
    public const UEBERSCHRIFT_TRANSKRIPTION = 'Transkription';
    public const UEBERSCHRIFT_NOTIZ         = 'Notiz';

    private const BILD_FORMATE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // ---------------------------------------------------------------
    // Kopplung
    // ---------------------------------------------------------------

    /**
     * Schluessel und Seite aus dem Dateinamen, oder null wenn der Name dem
     * Muster nicht folgt.
     *
     * @return array{schluessel:string, seite:string}|null  seite: 'V' oder 'R'
     */
    public static function seite(string $datei): ?array
    {
        if (preg_match(self::MUSTER, basename($datei), $m) !== 1) {
            return null;
        }

        return ['schluessel' => $m[1], 'seite' => strtoupper($m[2])];
    }

    /** Dateiname der anderen Seite (PK_0001_V.jpg -> PK_0001_R.jpg), oder null. */
    public static function partnerName(string $datei): ?string
    {
        $name = basename($datei);

        if (preg_match(self::MUSTER, $name, $m) !== 1) {
            return null;
        }

        $andere = strtoupper($m[2]) === self::VORDERSEITE ? self::RUECKSEITE : self::VORDERSEITE;
        $endung = pathinfo($name, PATHINFO_EXTENSION);

        return $m[1] . '_' . $andere . '.' . $endung;
    }

    /** Sind zwei Pfade nach dem Namensmuster Vorder- und Rueckseite derselben Karte? */
    public static function sindPartner(string $pfadA, string $pfadB): bool
    {
        if (dirname($pfadA) !== dirname($pfadB)) {
            return false;
        }

        $a = self::seite($pfadA);
        $b = self::seite($pfadB);

        return $a !== null && $b !== null
            && $a['schluessel'] === $b['schluessel']
            && $a['seite'] !== $b['seite'];
    }

    /**
     * Fasst Dateien zu Karten zusammen.
     *
     * Erst nach dem Namensmuster, dann nach Medienobjekt: zwei Dateien mit
     * derselben m_id, die noch keinen Partner haben, werden ein Paar (die
     * im Pfad erste ist die Vorderseite). Was uebrig bleibt, ist eine
     * Einzelkarte. Nicht-Bilder werden uebergangen.
     *
     * Die Dateien werden unveraendert in die Karten uebernommen - wer sie
     * vorher mit EXIF anreichert, findet die Anreicherung in den Seiten wieder.
     *
     * @param list<array{pfad:string, datei:string, format:string, m_id:string|null}> $dateien
     * @return list<array{schluessel:string, vorderseite:array|null, rueckseite:array|null}>
     */
    public static function gruppieren(array $dateien): array
    {
        $bilder = array_values(array_filter(
            $dateien,
            static fn (array $d): bool => in_array(strtolower((string) $d['format']), self::BILD_FORMATE, true)
        ));

        usort($bilder, static fn (array $a, array $b): int => strcmp($a['pfad'], $b['pfad']));

        /** @var array<string, array{schluessel:string, vorderseite:array|null, rueckseite:array|null}> $karten */
        $karten = [];
        $einzeln = [];

        // 1. Namensmuster
        foreach ($bilder as $bild) {
            $seite = self::seite($bild['datei']);

            if ($seite === null) {
                $einzeln[] = $bild;
                continue;
            }

            $id = dirname($bild['pfad']) . '/' . $seite['schluessel'];

            $karten[$id] ??= ['schluessel' => $seite['schluessel'], 'vorderseite' => null, 'rueckseite' => null];

            $feld = $seite['seite'] === self::VORDERSEITE ? 'vorderseite' : 'rueckseite';

            if ($karten[$id][$feld] === null) {
                $karten[$id][$feld] = $bild;
            } else {
                // PK_0001_V.jpg und PK_0001_v.JPG: die zweite wird eine Einzelkarte.
                $einzeln[] = $bild;
            }
        }

        // 2. Medienobjekt mit mehreren Dateien
        $jeMedium = [];
        foreach ($einzeln as $i => $bild) {
            if ($bild['m_id'] !== null && $bild['m_id'] !== '') {
                $jeMedium[$bild['m_id']][] = $i;
            }
        }

        $vergeben = [];
        foreach ($jeMedium as $indizes) {
            if (count($indizes) < 2) {
                continue;
            }
            [$v, $r] = $indizes;
            $vorne = $einzeln[$v];
            $karten['m:' . $vorne['pfad']] = [
                'schluessel'  => pathinfo($vorne['datei'], \PATHINFO_FILENAME),
                'vorderseite' => $vorne,
                'rueckseite'  => $einzeln[$r],
            ];
            $vergeben[$v] = true;
            $vergeben[$r] = true;
        }

        foreach ($einzeln as $i => $bild) {
            if (isset($vergeben[$i])) {
                continue;
            }
            $karten['e:' . $bild['pfad']] = [
                'schluessel'  => pathinfo($bild['datei'], \PATHINFO_FILENAME),
                'vorderseite' => $bild,
                'rueckseite'  => null,
            ];
        }

        // Rueckseite ohne Vorderseite: die Rueckseite ist dann das, was man sieht.
        foreach ($karten as &$karte) {
            if ($karte['vorderseite'] === null) {
                $karte['vorderseite'] = $karte['rueckseite'];
                $karte['rueckseite']  = null;
            }
        }
        unset($karte);

        $liste = array_values($karten);
        usort($liste, static fn (array $a, array $b): int => strcmp($a['vorderseite']['pfad'], $b['vorderseite']['pfad']));

        return $liste;
    }

    // ---------------------------------------------------------------
    // Beschreibung mit Abschnitten
    // ---------------------------------------------------------------

    /**
     * Motiv, Transkription und Notiz zu einer Beschreibung. Leere Abschnitte
     * fallen weg; ist nur das Motiv da, steht es ohne Ueberschrift - eine
     * Postkarte mit einem Satz Beschreibung soll aussehen wie jedes andere Bild.
     */
    public static function beschreibung(string $motiv, string $transkription, string $notiz): string
    {
        $motiv         = trim($motiv);
        $transkription = trim($transkription);
        $notiz         = trim($notiz);

        if ($transkription === '' && $notiz === '') {
            return $motiv;
        }

        $teile = [];

        if ($motiv !== '') {
            $teile[] = self::UEBERSCHRIFT_MOTIV . ': ' . $motiv;
        }
        if ($transkription !== '') {
            $teile[] = self::UEBERSCHRIFT_TRANSKRIPTION . ":\n" . $transkription;
        }
        if ($notiz !== '') {
            $teile[] = self::UEBERSCHRIFT_NOTIZ . ': ' . $notiz;
        }

        return implode("\n", $teile);
    }

    /**
     * Umkehrung: eine Beschreibung in ihre Abschnitte. Text vor der ersten
     * Ueberschrift ist das Motiv. Ueberschriften werden ohne Ruecksicht auf
     * Gross- und Kleinschreibung erkannt, mit oder ohne Text in derselben Zeile.
     *
     * @return array{motiv:string, transkription:string, notiz:string}
     */
    public static function abschnitte(string $beschreibung): array
    {
        $felder = ['motiv' => [], 'transkription' => [], 'notiz' => []];
        $namen  = [
            strtolower(self::UEBERSCHRIFT_MOTIV)         => 'motiv',
            strtolower(self::UEBERSCHRIFT_TRANSKRIPTION) => 'transkription',
            strtolower(self::UEBERSCHRIFT_NOTIZ)         => 'notiz',
        ];

        $aktuell = 'motiv';

        foreach (preg_split('/\R/', $beschreibung) ?: [] as $zeile) {
            if (preg_match('/^\s*(Motiv|Transkription|Notiz)\s*:\s?(.*)$/iu', $zeile, $m) === 1) {
                $aktuell = $namen[strtolower($m[1])];
                if (trim($m[2]) !== '') {
                    $felder[$aktuell][] = $m[2];
                }
                continue;
            }

            $felder[$aktuell][] = $zeile;
        }

        return [
            'motiv'         => trim(implode("\n", $felder['motiv'])),
            'transkription' => trim(implode("\n", $felder['transkription'])),
            'notiz'         => trim(implode("\n", $felder['notiz'])),
        ];
    }

    /** Das Motiv allein - was als Bildunterschrift taugt. */
    public static function motiv(string $beschreibung): string
    {
        return self::abschnitte($beschreibung)['motiv'];
    }
}

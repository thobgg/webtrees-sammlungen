<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use function array_key_exists;
use function in_array;
use function is_numeric;
use function mb_strlen;
use function preg_match;
use function preg_replace;
use function strtolower;
use function strtr;
use function substr;
use function trim;

/**
 * Die Maske "Sammlung anlegen/bearbeiten", aus dem Formular einer App gelesen
 * und geprueft - ohne webtrees, damit die Regeln sich testen lassen.
 *
 * Wie bei api/exif gilt: Was nicht mitkommt, bleibt beim Bearbeiten, wie es
 * ist; beim Anlegen gelten die Vorgaben der Maske im Browser.
 */
final class SammlungFormular
{
    /** Vorgaben einer neuen Sammlung, wie in der Maske im Browser. */
    public const VORGABEN = [
        'name'         => '',
        'beschreibung' => '',
        'icon'         => 'folder',
        'farbe'        => '#6c757d',
        'ansicht'      => 'foto',
        'ordner'       => '',
        'aktiv'        => true,
        'reihenfolge'  => 0,
    ];

    /**
     * @param array<string,mixed>      $body       das Formular
     * @param array<string,mixed>|null $alt        die Felder der vorhandenen Sammlung, null beim Anlegen
     * @param list<string>             $symbole    erlaubte Symbole (ein vorhandenes darf bleiben)
     * @param list<string>             $ansichten  erlaubte Anzeigetypen
     * @param list<string>             $ordner     Ordner unter dem Medienordner des Baums
     * @return array{felder:array{name:string, beschreibung:string, icon:string, farbe:string, ansicht:string, ordner:string, aktiv:bool, reihenfolge:int}, fehler:string|null}
     */
    public static function lesen(array $body, ?array $alt, array $symbole, array $ansichten, array $ordner): array
    {
        $f = [...self::VORGABEN, ...($alt ?? [])];

        foreach (['name', 'beschreibung', 'icon', 'farbe', 'ansicht', 'ordner'] as $feld) {
            if (array_key_exists($feld, $body)) {
                $f[$feld] = trim((string) $body[$feld]);
            }
        }

        if (array_key_exists('aktiv', $body)) {
            $f['aktiv'] = in_array((string) $body['aktiv'], ['1', 'true', 'on'], true);
        }

        $fehler = null;

        if (array_key_exists('reihenfolge', $body)) {
            $r = trim((string) $body['reihenfolge']);

            if ($r === '' || !is_numeric($r) || (int) $r < 0 || (int) $r > 9999) {
                $fehler = 'bad-order';
            } else {
                $f['reihenfolge'] = (int) $r;
            }
        }

        $f['ordner'] = trim((string) $f['ordner'], '/');
        $f['farbe']  = strtolower((string) $f['farbe']);

        $fehler ??= match (true) {
            $f['name'] === ''                                              => 'missing-name',
            mb_strlen($f['name']) > 255                                    => 'bad-name',
            mb_strlen($f['beschreibung']) > 2000                           => 'bad-description',
            preg_match('/^#[0-9a-f]{6}$/', $f['farbe']) !== 1              => 'bad-color',
            !in_array($f['icon'], $symbole, true)
                && $f['icon'] !== ($alt['icon'] ?? null)                   => 'bad-icon',
            !in_array($f['ansicht'], $ansichten, true)                     => 'bad-view',
            $f['ordner'] !== '' && !in_array($f['ordner'], $ordner, true)
                && $f['ordner'] !== ($alt['ordner'] ?? null)               => 'folder-not-found',
            default                                                        => null,
        };

        return ['felder' => $f, 'fehler' => $fehler];
    }

    /**
     * Kennung aus dem Namen: Kleinbuchstaben, Ziffern, - und _, hoechstens 80
     * Zeichen. Umlaute werden umschrieben ("Großeltern" -> "grosseltern"), statt
     * wie im Browser zu Bindestrichen zu werden.
     */
    public static function slugAusName(string $name): string
    {
        $s = strtr($name, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'ó' => 'o', 'ñ' => 'n', 'ç' => 'c',
        ]);
        $s = strtolower((string) preg_replace('/[^a-z0-9_-]+/i', '-', $s));
        $s = trim(substr(trim($s, '-'), 0, 80), '-');

        return $s !== '' ? $s : 'sammlung';
    }
}

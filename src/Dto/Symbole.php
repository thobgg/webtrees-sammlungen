<?php

declare(strict_types=1);

namespace Sammlungen\Dto;

/**
 * Piktogramme, die dieses webtrees auch zeichnen kann.
 *
 * webtrees liefert kein vollstaendiges FontAwesome aus, sondern eine Auswahl -
 * genau die Symbole, die es selbst in `resources/views/icons/` benutzt. Ein
 * Name ausserhalb dieser Auswahl erscheint nicht etwa gar nicht, sondern als
 * gestrichelter Kreis mit Fragezeichen: der Platzhalter fuer "unbekanntes
 * Symbol". Am Geraet gemessen: `fa-folder` zeichnet, `fa-folder-open` nicht.
 *
 * Deshalb wird hier jeder Wunsch auf ein Symbol abgebildet, das vorhanden ist.
 * Das kostet Genauigkeit - Zeitung, Manuskript und Buch teilen sich eines -,
 * aber ein naeherungsweise passendes Symbol ist besser als ein Fragezeichen.
 * Und es haelt das Modul an das, was das jeweilige Theme zeichnen kann.
 */
final class Symbole
{
    /**
     * Was webtrees mitbringt, ausgelesen aus seinen eigenen Icon-Ansichten.
     *
     * @var list<string>
     */
    public const VERFUEGBAR = [
        'fa-address-card', 'fa-arrow-down', 'fa-arrow-left', 'fa-arrow-right',
        'fa-arrow-up', 'fa-arrows-alt-v', 'fa-ban', 'fa-bell', 'fa-calendar',
        'fa-caret-down', 'fa-caret-up', 'fa-check', 'fa-children', 'fa-compress',
        'fa-copy', 'fa-database', 'fa-download', 'fa-envelope',
        'fa-exclamation-triangle', 'fa-expand', 'fa-file', 'fa-file-alt',
        'fa-file-image', 'fa-folder', 'fa-grip-horizontal', 'fa-grip-lines',
        'fa-history', 'fa-info-circle', 'fa-keyboard', 'fa-language', 'fa-link',
        'fa-list', 'fa-lock', 'fa-magic', 'fa-map', 'fa-map-marker-alt',
        'fa-medkit', 'fa-paint-brush', 'fa-pause', 'fa-pencil-alt', 'fa-play',
        'fa-plus', 'fa-question-circle', 'fa-search', 'fa-search-location',
        'fa-search-minus', 'fa-search-plus', 'fa-server', 'fa-share-alt',
        'fa-sitemap', 'fa-sort-amount-down', 'fa-spinner', 'fa-step-forward',
        'fa-sync-alt', 'fa-tags', 'fa-th-list', 'fa-thumbtack', 'fa-times',
        'fa-trash-alt', 'fa-tree', 'fa-undo', 'fa-university', 'fa-unlink',
        'fa-upload', 'fa-user', 'fa-users', 'fa-venus', 'fa-wrench',
    ];

    /**
     * Ersatz fuer Namen, die das Modul frueher benutzt hat und die webtrees
     * nicht kennt. Die Zuordnung ist Geschmackssache, das Fragezeichen war es
     * nicht.
     *
     * @var array<string,string>
     */
    private const ERSATZ = [
        'fa-folder-open' => 'fa-folder',
        'fa-image'       => 'fa-file-image',
        'fa-images'      => 'fa-file-image',
        'fa-camera'      => 'fa-file-image',
        'fa-photo-film'  => 'fa-file-image',
        'fa-book'        => 'fa-file',
        'fa-file-lines'  => 'fa-file-alt',
        'fa-scroll'      => 'fa-file-alt',
        'fa-newspaper'   => 'fa-file-alt',
        'fa-id-card'     => 'fa-address-card',
        'fa-th'          => 'fa-th-list',
        'fa-layer-group' => 'fa-th-list',
        'fa-film'        => 'fa-play',
        'fa-video'       => 'fa-play',
        'fa-headphones'  => 'fa-play',
        'fa-monument'    => 'fa-map-marker-alt',
        'fa-cog'         => 'fa-wrench',
        'fa-sliders-h'   => 'fa-wrench',
        'fa-broom'       => 'fa-sync-alt',
        'fa-save'        => 'fa-check',
        'fa-trash'       => 'fa-trash-alt',
        'fa-edit'        => 'fa-pencil-alt',
        'fa-star'        => 'fa-thumbtack',
    ];

    /**
     * Liefert zu einem Wunschnamen ein Symbol, das gezeichnet werden kann.
     * Namen duerfen mit oder ohne `fa-` kommen - in der Verwaltung tragen
     * Nutzer sie von Hand ein.
     */
    public static function fa(string $wunsch): string
    {
        $name = strtolower(trim($wunsch));

        if ($name === '') {
            return 'fa-folder';
        }

        if (!str_starts_with($name, 'fa-')) {
            $name = 'fa-' . $name;
        }

        if (in_array($name, self::VERFUEGBAR, true)) {
            return $name;
        }

        return self::ERSATZ[$name] ?? 'fa-file';
    }
}

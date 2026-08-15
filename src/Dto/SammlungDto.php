<?php

declare(strict_types=1);

namespace Sammlungen\Dto;

use Fisharebest\Webtrees\Elements\SourceMediaType;
use Fisharebest\Webtrees\I18N;

/**
 * Unveränderliches Value-Object für eine Sammlung
 * (thematisch gruppierte Medienobjekte nach `source_media_type`).
 */
final class SammlungDto
{
    /**
     * Bezeichnungen der GEDCOM-Medientypen.
     *
     * Sie kommen aus webtrees selbst: dessen Liste ist vollständig - sie kennt
     * auch Gemälde, Wappen und Urkunden, die hier fehlten und dann roh als
     * "PAINTING" auf dem Schirm standen - und sie ist in jede Sprache
     * übersetzt, in der webtrees ausgeliefert wird. Die frühere Liste im Modul
     * war fest eingedeutscht und erschien auch in einer englischen oder
     * slowakischen Oberfläche auf Deutsch.
     *
     * @return array<string,string>  [typ => Bezeichnung], Schlüssel kleingeschrieben
     */
    public static function typBezeichnungen(): array
    {
        $werte = (new SourceMediaType(''))->values();

        $liste = [];
        foreach ($werte as $schluessel => $bezeichnung) {
            $liste[strtolower((string) $schluessel)] = (string) $bezeichnung;
        }

        // webtrees führt den leeren Typ ohne Bezeichnung - hier braucht die
        // Kachel eine.
        $liste[''] = I18N::translate('No type');

        return $liste;
    }

    /**
     * Bezeichnung eines einzelnen Typs; unbekannte Schlüssel kommen unverändert
     * zurück, damit ein exotischer GEDCOM-Wert sichtbar bleibt statt zu
     * verschwinden.
     */
    public static function typBezeichnung(string $typ): string
    {
        $typ = strtolower($typ);

        return self::typBezeichnungen()[$typ] ?? ucfirst($typ);
    }

    /** Zuordnung Medientyp → FontAwesome-Icon-Klasse */
    public const ICONS = [
        'audio'      => 'fa-play',
        'book'       => 'fa-file',
        'card'       => 'fa-address-card',
        'document'   => 'fa-file-alt',
        'electronic' => 'fa-file-alt',
        'fiche'      => 'fa-th-list',
        'film'       => 'fa-play',
        'magazine'   => 'fa-file-alt',
        'manuscript' => 'fa-file-alt',
        'map'        => 'fa-map',
        'newspaper'  => 'fa-file-alt',
        'photo'      => 'fa-file-image',
        'tombstone'  => 'fa-map-marker-alt',
        'video'      => 'fa-play',
        'other'      => 'fa-folder',
        ''           => 'fa-folder',
    ];

    public function __construct(
        /**
         * GEDCOM source_media_type-Schlüssel (z. B. „photo", „book").
         * Leerer String = Medien ohne Typenangabe.
         */
        public readonly string $typ,

        /** Anzeigename der Sammlung */
        public readonly string $name,

        /** Anzahl Medienobjekte in dieser Sammlung */
        public readonly int $anzahl,

        /**
         * Xrefs der neuesten Medienobjekte (maximal 3) für Vorschau.
         * @var list<string>
         */
        public readonly array $vorschauXrefs = [],
    ) {}

    /**
     * Gibt die FontAwesome-Icon-Klasse für diesen Typ zurück.
     */
    public function icon(): string
    {
        return self::ICONS[strtolower($this->typ)] ?? 'fa-folder';
    }

    /**
     * Gibt den URL-sicheren Kategorieschlüssel zurück.
     */
    public function slug(): string
    {
        return $this->typ === '' ? '_ohne_typ' : $this->typ;
    }
}

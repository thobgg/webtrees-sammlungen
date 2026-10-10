<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use DOMDocument;
use DOMElement;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function count;
use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function max;
use function min;
use function preg_match;
use function round;
use function rtrim;
use function strcasecmp;
use function trim;

/**
 * Bereiche auf einem Bild: Rechtecke um Gesichter (und was andere Programme
 * sonst markieren), wie sie im XMP der Datei stehen.
 *
 * Gespeichert wird im Standard "MWG Regions" (Metadata Working Group 2.0),
 * den auch digiKam, Lightroom und ExifTool lesen und schreiben. Dort steht
 * die MITTE des Rahmens in stArea:x/y, normiert auf 0-1. Nach aussen (an die
 * Apps, an die Lightbox) gibt das Modul die linke obere Ecke weiter - wer
 * Rechtecke zeichnet, braucht sie so. Die Umrechnung geschieht nur hier.
 *
 * Zu jedem Gesicht kann sammlungen:Xref die Person im Stammbaum nennen. Das
 * haengt das Bild NICHT an die Person; ein Medienobjekt entsteht nur, wenn
 * jemand es ausdruecklich anlegt.
 *
 * Ohne MWG-Bereiche werden die der Windows-Fotogalerie (MP:RegionInfo,
 * linke obere Ecke) gelesen. Geschrieben wird immer MWG.
 *
 * Ein Bereich: ['x', 'y', 'w', 'h' (links oben, normiert), 'name', 'xref' (oder null), 'typ'].
 */
final class Bereiche
{
    public const NS_MWG_RS = 'http://www.metadataworkinggroup.com/schemas/regions/';
    public const NS_STAREA = 'http://ns.adobe.com/xmp/sType/Area#';
    public const NS_STDIM  = 'http://ns.adobe.com/xap/1.0/sType/Dimensions#';

    private const NS_RDF   = XmpPaket::NS_RDF;
    private const NS_MP    = 'http://ns.microsoft.com/photo/1.2/';
    private const NS_MPRI  = 'http://ns.microsoft.com/photo/1.2/t/RegionInfo#';
    private const NS_MPREG = 'http://ns.microsoft.com/photo/1.2/t/Region#';

    public const GESICHT = 'Face';

    /** Wie webtrees Kennungen bildet: Buchstaben, Ziffern und wenige Zeichen. */
    private const XREF_MUSTER = '/^[A-Za-z0-9:_.-]{1,20}$/';

    /** Ein Bild mit hunderten Rahmen ist kein Gruppenbild mehr, sondern ein Fehler. */
    public const HOECHSTZAHL = 500;

    /**
     * Die Bereiche aus einem XMP-Paket.
     *
     * @return list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}>
     */
    public static function lesen(string $xmp): array
    {
        $xmp = trim(rtrim($xmp, "\0"));

        if ($xmp === '') {
            return [];
        }

        $doc     = new DOMDocument('1.0', 'UTF-8');
        $vorher  = libxml_use_internal_errors(true);
        $ok      = $doc->loadXML($xmp, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($vorher);

        if (!$ok || $doc->doctype !== null) {
            return [];
        }

        $mwg = self::mwgLesen($doc);

        return $mwg !== [] ? $mwg : self::microsoftLesen($doc);
    }

    /**
     * Die Bereiche aus der Anfrage einer App (JSON-Liste, links oben, normiert).
     * Nur Gesichter: andere Typen bleiben in der Datei, wie sie sind, und
     * werden deshalb nicht angenommen.
     *
     * @return list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}>|null
     *         null: kein gueltiges JSON, Werte ausserhalb 0-1, Kennung unbrauchbar
     */
    public static function ausJson(string $json): ?array
    {
        $daten = json_decode($json, true);

        if (!is_array($daten) || !array_is_list($daten) || count($daten) > self::HOECHSTZAHL) {
            return null;
        }

        $bereiche = [];

        foreach ($daten as $b) {
            if (!is_array($b)) {
                return null;
            }

            $typ = $b['typ'] ?? self::GESICHT;
            if (!is_string($typ)) {
                return null;
            }
            if (strcasecmp($typ, self::GESICHT) !== 0) {
                continue;
            }

            $zahlen = [];
            foreach (['x', 'y', 'w', 'h'] as $k) {
                if (!array_key_exists($k, $b) || !is_numeric($b[$k])) {
                    return null;
                }
                $zahlen[$k] = (float) $b[$k];
                if ($zahlen[$k] < 0.0 || $zahlen[$k] > 1.0) {
                    return null;
                }
            }

            // Ein Rahmen ohne Flaeche oder ueber den Rand hinaus ist ein Fehler
            // der App - ein Hauch Rundung (0.4351 + 0.5650) wird geduldet.
            if ($zahlen['w'] <= 0.0 || $zahlen['h'] <= 0.0
                || $zahlen['x'] + $zahlen['w'] > 1.001 || $zahlen['y'] + $zahlen['h'] > 1.001) {
                return null;
            }

            $name = $b['name'] ?? '';
            $xref = $b['xref'] ?? null;

            if (!is_string($name) || ($xref !== null && !is_string($xref))) {
                return null;
            }

            $xref = $xref !== null ? trim($xref) : '';
            if ($xref !== '' && preg_match(self::XREF_MUSTER, $xref) !== 1) {
                return null;
            }

            $bereiche[] = [
                'x'    => $zahlen['x'],
                'y'    => $zahlen['y'],
                'w'    => min($zahlen['w'], 1.0 - $zahlen['x']),
                'h'    => min($zahlen['h'], 1.0 - $zahlen['y']),
                'name' => trim($name),
                'xref' => $xref !== '' ? $xref : null,
                'typ'  => self::GESICHT,
            ];
        }

        return $bereiche;
    }

    /**
     * Die Kennungen der Personen in den Gesichtern - fuer den Index.
     *
     * @param list<array{xref:string|null, typ:string}> $bereiche
     * @return list<string>
     */
    public static function xrefs(array $bereiche): array
    {
        $xrefs = [];

        foreach ($bereiche as $b) {
            if ($b['xref'] !== null && strcasecmp($b['typ'], self::GESICHT) === 0) {
                $xrefs[$b['xref']] = true;
            }
        }

        return array_keys($xrefs);
    }

    // ---------------------------------------------------------------
    // MWG
    // ---------------------------------------------------------------

    /** @return list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}> */
    private static function mwgLesen(DOMDocument $doc): array
    {
        $bereiche = [];

        foreach ($doc->getElementsByTagNameNS(self::NS_MWG_RS, 'Regions') as $regions) {
            $liste = self::kind(self::ressource($regions), self::NS_MWG_RS, 'RegionList');
            if ($liste === null) {
                continue;
            }

            foreach (self::eintraege($liste) as $li) {
                $r    = self::ressource($li);
                $area = self::kind($r, self::NS_MWG_RS, 'Area');
                if ($area === null) {
                    continue;
                }
                $a = self::ressource($area);

                $einheit = self::wert($a, self::NS_STAREA, 'unit');
                if ($einheit !== null && $einheit !== '' && $einheit !== 'normalized') {
                    continue;
                }

                $mx = self::zahl(self::wert($a, self::NS_STAREA, 'x'));
                $my = self::zahl(self::wert($a, self::NS_STAREA, 'y'));
                $w  = self::zahl(self::wert($a, self::NS_STAREA, 'w')) ?? 0.0;
                $h  = self::zahl(self::wert($a, self::NS_STAREA, 'h')) ?? 0.0;

                if ($mx === null || $my === null) {
                    continue;
                }

                $xref = trim((string) self::wert($r, XmpPaket::NS_SAMMLUNGEN, 'Xref'));

                $bereiche[] = self::rahmen(
                    $mx - $w / 2,
                    $my - $h / 2,
                    $w,
                    $h,
                    (string) self::wert($r, self::NS_MWG_RS, 'Name'),
                    preg_match(self::XREF_MUSTER, $xref) === 1 ? $xref : null,
                    trim((string) self::wert($r, self::NS_MWG_RS, 'Type')),
                );
            }
        }

        return $bereiche;
    }

    // ---------------------------------------------------------------
    // Windows-Fotogalerie (nur lesen)
    // ---------------------------------------------------------------

    /** @return list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}> */
    private static function microsoftLesen(DOMDocument $doc): array
    {
        $bereiche = [];

        foreach ($doc->getElementsByTagNameNS(self::NS_MP, 'RegionInfo') as $info) {
            $liste = self::kind(self::ressource($info), self::NS_MPRI, 'Regions');
            if ($liste === null) {
                continue;
            }

            foreach (self::eintraege($liste) as $li) {
                $r     = self::ressource($li);
                $teile = explode(',', (string) self::wert($r, self::NS_MPREG, 'Rectangle'));

                if (count($teile) !== 4) {
                    continue;
                }

                [$x, $y, $w, $h] = [self::zahl($teile[0]), self::zahl($teile[1]), self::zahl($teile[2]), self::zahl($teile[3])];

                if ($x === null || $y === null || $w === null || $h === null) {
                    continue;
                }

                $bereiche[] = self::rahmen($x, $y, $w, $h, (string) self::wert($r, self::NS_MPREG, 'PersonDisplayName'), null, self::GESICHT);
            }
        }

        return $bereiche;
    }

    // ---------------------------------------------------------------
    // Hilfen
    // ---------------------------------------------------------------

    /** @return array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string} */
    private static function rahmen(float $x, float $y, float $w, float $h, string $name, ?string $xref, string $typ): array
    {
        // Auf das Bild beschneiden: Programme runden, und ein Rahmen am Rand
        // ragt dann um ein Tausendstel hinaus.
        $x = max(0.0, min(1.0, $x));
        $y = max(0.0, min(1.0, $y));

        return [
            'x'    => round($x, 6),
            'y'    => round($y, 6),
            'w'    => round(max(0.0, min($w, 1.0 - $x)), 6),
            'h'    => round(max(0.0, min($h, 1.0 - $y)), 6),
            'name' => trim($name),
            'xref' => $xref,
            'typ'  => $typ,
        ];
    }

    private static function zahl(?string $wert): ?float
    {
        $wert = trim((string) $wert);

        return is_numeric($wert) ? (float) $wert : null;
    }

    /**
     * Wo die Eigenschaften eines Knotens stehen: RDF erlaubt sie direkt am
     * Element (rdf:parseType="Resource") oder in einem rdf:Description darin.
     */
    public static function ressource(DOMElement $el): DOMElement
    {
        return self::kind($el, self::NS_RDF, 'Description') ?? $el;
    }

    /** Ein Wert als Attribut oder als Kindelement. */
    public static function wert(DOMElement $el, string $ns, string $name): ?string
    {
        if ($el->hasAttributeNS($ns, $name)) {
            return $el->getAttributeNS($ns, $name);
        }

        $kind = self::kind($el, $ns, $name);

        return $kind?->textContent;
    }

    public static function kind(DOMElement $el, string $ns, string $name): ?DOMElement
    {
        foreach ($el->childNodes as $k) {
            if ($k instanceof DOMElement && $k->namespaceURI === $ns && $k->localName === $name) {
                return $k;
            }
        }

        return null;
    }

    /**
     * Die rdf:li einer Liste (Bag oder Seq).
     *
     * @return list<DOMElement>
     */
    public static function eintraege(DOMElement $liste): array
    {
        $container = self::kind($liste, self::NS_RDF, 'Bag') ?? self::kind($liste, self::NS_RDF, 'Seq');

        if ($container === null) {
            return [];
        }

        $li = [];
        foreach ($container->childNodes as $k) {
            if ($k instanceof DOMElement && $k->namespaceURI === self::NS_RDF && $k->localName === 'li') {
                $li[] = $k;
            }
        }

        return $li;
    }
}

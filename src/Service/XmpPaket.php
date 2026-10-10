<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use DOMDocument;
use DOMElement;
use DOMNode;

use function array_key_exists;
use function in_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function rtrim;
use function sprintf;
use function strcasecmp;
use function trim;

/**
 * Baut das XMP-Paket, das das Modul in eine Bilddatei schreibt - und laesst
 * dabei alles stehen, was nicht ihm gehoert.
 *
 * Frueher wurde das Paket bei jedem Speichern neu aufgebaut, nur aus den
 * Feldern des Moduls. Was andere Programme ins XMP geschrieben hatten, war
 * danach weg: Gesichtsregionen aus digiKam, Bewertungen, Farbmarkierungen,
 * Stichwort-Hierarchien aus Lightroom, Beschreibungen in weiteren Sprachen.
 * Jetzt werden nur die Felder ersetzt, die das Modul bearbeitet:
 *
 *   dc:description (nur die Fassung x-default), xmp:CreateDate,
 *   sammlungen:DatumUnsicher, Iptc4xmpExt:PersonInImage, dc:subject,
 *   dc:identifier, dc:relation, und die Gesichter in mwg-rs:Regions
 *   (nur Bereiche vom Typ Face, siehe Bereiche)
 *
 * Ein Feld ohne Wert wird entfernt - leert jemand im Editor das Datum, ist es
 * danach auch in der Datei weg. Felder in Kurzschreibweise (als Attribut am
 * rdf:Description, wie manche Programme sie schreiben) werden ebenso erkannt.
 *
 * Ist das vorhandene Paket kein lesbares XML, wird nicht geschrieben: lieber
 * eine Fehlermeldung als ein Paket, das fremde Angaben stillschweigend
 * verschluckt.
 */
final class XmpPaket
{
    public const NS_RDF        = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    public const NS_DC         = 'http://purl.org/dc/elements/1.1/';
    public const NS_XMP        = 'http://ns.adobe.com/xap/1.0/';
    public const NS_IPTCEX     = 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/';
    public const NS_SAMMLUNGEN = 'https://github.com/thobgg/webtrees-sammlungen/ns/1.0/';

    private const NS_X   = 'adobe:ns:meta/';
    private const NS_XML = 'http://www.w3.org/XML/1998/namespace';

    /** Die Felder, die das Modul bearbeitet: Namensraum => lokale Namen. */
    private const EIGENE = [
        self::NS_XMP        => ['CreateDate'],
        self::NS_SAMMLUNGEN => ['DatumUnsicher'],
        self::NS_IPTCEX     => ['PersonInImage'],
        self::NS_DC         => ['subject', 'identifier', 'relation'],
    ];

    /** Schluessel von aendern() => das Feld im XMP (dc:description wird eigens behandelt). */
    private const FELDER = [
        'datum'         => [self::NS_XMP, 'CreateDate'],
        'datumUnsicher' => [self::NS_SAMMLUNGEN, 'DatumUnsicher'],
        'personen'      => [self::NS_IPTCEX, 'PersonInImage'],
        'keywords'      => [self::NS_DC, 'subject'],
        'identifier'    => [self::NS_DC, 'identifier'],
        'relation'      => [self::NS_DC, 'relation'],
    ];

    /** Bevorzugte Praefixe, falls das Paket noch keinen fuer den Namensraum kennt. */
    private const PRAEFIX = [
        self::NS_DC         => 'dc',
        self::NS_XMP        => 'xmp',
        self::NS_IPTCEX     => 'Iptc4xmpExt',
        self::NS_SAMMLUNGEN => 'sammlungen',
    ];

    /**
     * Alle Felder des Moduls auf einmal setzen - der Weg der Lightbox.
     *
     * @param string       $alt           das vorhandene Paket ('' = keines)
     * @param list<string> $personen
     * @param list<string> $keywords
     *
     * @throws \RuntimeException wenn das vorhandene Paket nicht lesbar ist
     */
    public static function mischen(
        string $alt,
        string $beschreibung,
        string $datumIso,
        array  $personen,
        array  $keywords,
        bool   $datumUnsicher = false,
        string $identifier = '',
        string $relation = '',
    ): string {
        return self::aendern($alt, [
            'beschreibung'  => $beschreibung,
            'datum'         => $datumIso,
            'datumUnsicher' => $datumUnsicher,
            'personen'      => $personen,
            'keywords'      => $keywords,
            'identifier'    => $identifier,
            'relation'      => $relation,
        ]);
    }

    /**
     * Nur die mitgegebenen Felder setzen; was fehlt, bleibt, wie es in der
     * Datei steht. Ein mitgegebenes leeres Feld leert.
     *
     * Schluessel: beschreibung, datum, datumUnsicher, personen, keywords,
     * identifier, relation, bereiche (Gesichter, siehe Bereiche). Ein leeres
     * Datum nimmt "Datum unsicher" mit. Neue Gesichter mit Namen ergaenzen
     * Iptc4xmpExt:PersonInImage, falls der Name dort fehlt - entfernt wird
     * daraus nie etwas, sonst verschwaende ein Name aus Programmen, die keine
     * Bereiche kennen, nur weil sein Rahmen geloescht wurde.
     *
     * @param array{beschreibung?:string, datum?:string, datumUnsicher?:bool, personen?:list<string>, keywords?:list<string>, identifier?:string, relation?:string, bereiche?:list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}>} $felder
     * @param int $breite  Pixelmasse des Bildes, fuer mwg-rs:AppliedToDimensions
     *
     * @throws \RuntimeException wenn das vorhandene Paket nicht lesbar ist
     */
    public static function aendern(string $alt, array $felder, int $breite = 0, int $hoehe = 0): string
    {
        $doc = self::laden($alt);
        $rdf = $doc->getElementsByTagNameNS(self::NS_RDF, 'RDF')->item(0);

        if (!$rdf instanceof DOMElement) {
            throw new \RuntimeException('Vorhandenes XMP ohne rdf:RDF - nicht überschrieben.');
        }

        $beschreibungen = [];
        foreach ($rdf->childNodes as $kind) {
            if ($kind instanceof DOMElement && $kind->namespaceURI === self::NS_RDF && $kind->localName === 'Description') {
                $beschreibungen[] = $kind;
            }
        }

        if ($beschreibungen === []) {
            $neu = $doc->createElementNS(self::NS_RDF, 'rdf:Description');
            $neu->setAttributeNS(self::NS_RDF, 'rdf:about', '');
            $rdf->appendChild($neu);
            $beschreibungen[] = $neu;
        }

        // Namen neuer Gesichter, die in PersonInImage noch fehlen. Vor dem
        // Entfernen lesen - danach waere die vorhandene Liste weg.
        if (isset($felder['bereiche'])) {
            $liste  = $felder['personen'] ?? self::personenLesen($beschreibungen);
            $fehlen = [];
            foreach ($felder['bereiche'] as $b) {
                if ($b['name'] !== '' && !in_array($b['name'], $liste, true) && !in_array($b['name'], $fehlen, true)) {
                    $fehlen[] = $b['name'];
                }
            }
            if ($fehlen !== [] || array_key_exists('personen', $felder)) {
                $felder['personen'] = [...$liste, ...$fehlen];
            }
        }

        // "Datum unsicher" gehoert zum Datum: wird das Datum geleert, geht es mit.
        if (($felder['datum'] ?? null) === '') {
            $felder['datumUnsicher'] = false;
        }

        // 1. Die mitgegebenen Felder entfernen - als Element wie als Attribut, in allen Beschreibungen.
        foreach ($beschreibungen as $d) {
            foreach (self::FELDER as $schluessel => [$ns, $name]) {
                if (!array_key_exists($schluessel, $felder)) {
                    continue;
                }
                if ($d->hasAttributeNS($ns, $name)) {
                    $d->removeAttributeNS($ns, $name);
                }
                foreach (self::kinder($d, $ns, $name) as $alterWert) {
                    $d->removeChild($alterWert);
                }
            }
            if (array_key_exists('beschreibung', $felder)) {
                self::beschreibungEntfernen($d);
            }
        }

        // 2. Neue Werte in die erste Beschreibung. Namensraeume, die das
        //    Paket noch nicht kennt, einmal dort anmelden - sonst traegt jedes
        //    neue Feld seine eigene xmlns-Angabe.
        $ziel = $beschreibungen[0];
        foreach (self::PRAEFIX as $ns => $wunsch) {
            self::anmelden($ziel, $ns, $wunsch);
        }
        $beschreibung = trim($felder['beschreibung'] ?? '');

        if ($beschreibung !== '') {
            self::beschreibungSetzen($doc, $beschreibungen, $beschreibung);
        }
        if (($felder['datum'] ?? '') !== '') {
            self::einfach($doc, $ziel, self::NS_XMP, 'CreateDate', $felder['datum']);
        }
        if ($felder['datumUnsicher'] ?? false) {
            self::einfach($doc, $ziel, self::NS_SAMMLUNGEN, 'DatumUnsicher', 'True');
        }
        if (($felder['personen'] ?? []) !== []) {
            self::liste($doc, $ziel, self::NS_IPTCEX, 'PersonInImage', $felder['personen']);
        }
        if (($felder['keywords'] ?? []) !== []) {
            self::liste($doc, $ziel, self::NS_DC, 'subject', $felder['keywords']);
        }
        if (($felder['identifier'] ?? '') !== '') {
            self::einfach($doc, $ziel, self::NS_DC, 'identifier', $felder['identifier']);
        }
        if (($felder['relation'] ?? '') !== '') {
            self::liste($doc, $ziel, self::NS_DC, 'relation', [$felder['relation']]);
        }
        if (isset($felder['bereiche'])) {
            self::bereicheSetzen($doc, $beschreibungen, $felder['bereiche'], $breite, $hoehe);
        }

        // Neu angelegte Elemente tragen ihre Namensraum-Angabe einzeln mit
        // (xmlns:rdf an jedem rdf:Bag). Gueltig, aber aufgeblaeht -
        // NSCLEAN entfernt die Doppelungen beim Neuladen.
        $sauber = new DOMDocument('1.0', 'UTF-8');
        $sauber->loadXML((string) $doc->saveXML($doc->documentElement), LIBXML_NSCLEAN | LIBXML_NONET);

        return "<?xpacket begin=\"\xEF\xBB\xBF\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n"
            . $sauber->saveXML($sauber->documentElement)
            . "\n<?xpacket end=\"w\"?>";
    }

    /**
     * PersonInImage, wie es im Paket steht.
     *
     * @param list<DOMElement> $beschreibungen
     * @return list<string>
     */
    private static function personenLesen(array $beschreibungen): array
    {
        $namen = [];

        foreach ($beschreibungen as $d) {
            foreach (self::kinder($d, self::NS_IPTCEX, 'PersonInImage') as $feld) {
                foreach (Bereiche::eintraege($feld) as $li) {
                    $name = trim($li->textContent);
                    if ($name !== '' && !in_array($name, $namen, true)) {
                        $namen[] = $name;
                    }
                }
            }
        }

        return $namen;
    }

    /**
     * Die Gesichter ersetzen (mwg-rs:Type = Face). Bereiche anderer Art
     * (Haustier, Fokus, Barcode) und fremde Felder in ihnen bleiben stehen.
     * Bleibt gar kein Bereich uebrig, verschwindet mwg-rs:Regions.
     *
     * @param list<DOMElement> $beschreibungen
     * @param list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}> $bereiche
     */
    private static function bereicheSetzen(DOMDocument $doc, array $beschreibungen, array $bereiche, int $breite, int $hoehe): void
    {
        $regions = null;
        $traeger = $beschreibungen[0];
        foreach ($beschreibungen as $d) {
            $regions = self::kinder($d, Bereiche::NS_MWG_RS, 'Regions')[0] ?? null;
            if ($regions !== null) {
                $traeger = $d;
                break;
            }
        }

        if ($regions === null && $bereiche === []) {
            return;
        }

        foreach ([Bereiche::NS_MWG_RS => 'mwg-rs', Bereiche::NS_STAREA => 'stArea', Bereiche::NS_STDIM => 'stDim'] as $ns => $wunsch) {
            self::anmelden($traeger, $ns, $wunsch);
        }
        $rdf = self::praefixFuer($traeger, self::NS_RDF);
        $mwg = self::praefixFuer($traeger, Bereiche::NS_MWG_RS);

        if ($regions === null) {
            $regions = $doc->createElementNS(Bereiche::NS_MWG_RS, $mwg . ':Regions');
            $regions->setAttributeNS(self::NS_RDF, $rdf . ':parseType', 'Resource');
            $traeger->appendChild($regions);
        }

        $res   = Bereiche::ressource($regions);
        $liste = Bereiche::kind($res, Bereiche::NS_MWG_RS, 'RegionList');
        if ($liste === null) {
            $liste = $doc->createElementNS(Bereiche::NS_MWG_RS, $mwg . ':RegionList');
            $res->appendChild($liste);
        }
        $bag = Bereiche::kind($liste, self::NS_RDF, 'Bag') ?? Bereiche::kind($liste, self::NS_RDF, 'Seq');
        if ($bag === null) {
            $bag = $doc->createElementNS(self::NS_RDF, $rdf . ':Bag');
            $liste->appendChild($bag);
        }

        // Alte Gesichter raus.
        foreach (Bereiche::eintraege($liste) as $li) {
            $typ = trim((string) Bereiche::wert(Bereiche::ressource($li), Bereiche::NS_MWG_RS, 'Type'));
            if (strcasecmp($typ, Bereiche::GESICHT) === 0) {
                $bag->removeChild($li);
            }
        }

        foreach ($bereiche as $b) {
            $bag->appendChild(self::bereichElement($doc, $traeger, $b));
        }

        if (Bereiche::eintraege($liste) === []) {
            $regions->parentNode?->removeChild($regions);

            return;
        }

        // Die Masse, auf die sich die normierten Werte beziehen. Stehen schon
        // welche da, bleiben sie: normierte Werte gelten unabhaengig davon,
        // und fremde Bereiche wurden dagegen gemessen.
        if ($breite > 0 && $hoehe > 0 && Bereiche::kind($res, Bereiche::NS_MWG_RS, 'AppliedToDimensions') === null) {
            $stDim = self::praefixFuer($traeger, Bereiche::NS_STDIM);
            $dim   = $doc->createElementNS(Bereiche::NS_MWG_RS, $mwg . ':AppliedToDimensions');
            $dim->setAttributeNS(Bereiche::NS_STDIM, $stDim . ':w', (string) $breite);
            $dim->setAttributeNS(Bereiche::NS_STDIM, $stDim . ':h', (string) $hoehe);
            $dim->setAttributeNS(Bereiche::NS_STDIM, $stDim . ':unit', 'pixel');
            $res->insertBefore($dim, $res->firstChild);
        }
    }

    /** @param array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string} $b */
    private static function bereichElement(DOMDocument $doc, DOMElement $traeger, array $b): DOMElement
    {
        $rdf    = self::praefixFuer($traeger, self::NS_RDF);
        $mwg    = self::praefixFuer($traeger, Bereiche::NS_MWG_RS);
        $stArea = self::praefixFuer($traeger, Bereiche::NS_STAREA);

        $li = $doc->createElementNS(self::NS_RDF, $rdf . ':li');
        $li->setAttributeNS(self::NS_RDF, $rdf . ':parseType', 'Resource');

        $typ = $doc->createElementNS(Bereiche::NS_MWG_RS, $mwg . ':Type');
        $typ->appendChild($doc->createTextNode(Bereiche::GESICHT));
        $li->appendChild($typ);

        if ($b['name'] !== '') {
            $name = $doc->createElementNS(Bereiche::NS_MWG_RS, $mwg . ':Name');
            $name->appendChild($doc->createTextNode($b['name']));
            $li->appendChild($name);
        }

        // MWG zaehlt von der Mitte des Rahmens aus.
        $area = $doc->createElementNS(Bereiche::NS_MWG_RS, $mwg . ':Area');
        $area->setAttributeNS(Bereiche::NS_STAREA, $stArea . ':x', self::dezimal($b['x'] + $b['w'] / 2));
        $area->setAttributeNS(Bereiche::NS_STAREA, $stArea . ':y', self::dezimal($b['y'] + $b['h'] / 2));
        $area->setAttributeNS(Bereiche::NS_STAREA, $stArea . ':w', self::dezimal($b['w']));
        $area->setAttributeNS(Bereiche::NS_STAREA, $stArea . ':h', self::dezimal($b['h']));
        $area->setAttributeNS(Bereiche::NS_STAREA, $stArea . ':unit', 'normalized');
        $li->appendChild($area);

        if ($b['xref'] !== null) {
            $xref = $doc->createElementNS(self::NS_SAMMLUNGEN, self::praefixFuer($traeger, self::NS_SAMMLUNGEN) . ':Xref');
            $xref->appendChild($doc->createTextNode($b['xref']));
            $li->appendChild($xref);
        }

        return $li;
    }

    /** Vier bis sechs Stellen reichen fuer jedes Foto; ohne Exponent und mit Punkt. */
    private static function dezimal(float $wert): string
    {
        $text = rtrim(rtrim(sprintf('%.6F', $wert), '0'), '.');

        return $text === '' || $text === '-0' ? '0' : $text;
    }

    /** Das vorhandene Paket als DOM, oder ein leeres Grundgeruest. */
    private static function laden(string $alt): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;

        // Pakete werden oft mit Nullbytes oder Leerzeichen auf feste Laenge gebracht.
        $alt = trim(rtrim($alt, "\0"));

        if ($alt === '') {
            $doc->loadXML(
                '<x:xmpmeta xmlns:x="' . self::NS_X . '">'
                . '<rdf:RDF xmlns:rdf="' . self::NS_RDF . '">'
                . '<rdf:Description rdf:about=""'
                . ' xmlns:dc="' . self::NS_DC . '"'
                . ' xmlns:xmp="' . self::NS_XMP . '"'
                . ' xmlns:Iptc4xmpExt="' . self::NS_IPTCEX . '"'
                . ' xmlns:sammlungen="' . self::NS_SAMMLUNGEN . '"/>'
                . '</rdf:RDF></x:xmpmeta>'
            );

            return $doc;
        }

        $vorher = libxml_use_internal_errors(true);
        // LIBXML_NONET: ein Paket aus einer fremden Datei darf nichts nachladen.
        $ok = $doc->loadXML($alt, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($vorher);

        if (!$ok || $doc->documentElement === null) {
            throw new \RuntimeException('Vorhandenes XMP ist kein lesbares XML - nicht überschrieben.');
        }

        // XMP kennt keine DTD. Steht doch eine drin, ist das Paket entweder
        // kaputt oder praepariert - Verweise auf ihre Entitaeten ergaeben
        // nach dem Neuschreiben ein ungueltiges Paket.
        if ($doc->doctype !== null) {
            throw new \RuntimeException('Vorhandenes XMP enthält eine DTD - nicht überschrieben.');
        }

        return $doc;
    }

    /** @return list<DOMElement> direkte Kinder mit diesem Namen */
    private static function kinder(DOMElement $eltern, string $ns, string $name): array
    {
        $treffer = [];
        foreach ($eltern->childNodes as $kind) {
            if ($kind instanceof DOMElement && $kind->namespaceURI === $ns && $kind->localName === $name) {
                $treffer[] = $kind;
            }
        }

        return $treffer;
    }

    /**
     * Aus dc:description nur die Fassung x-default entfernen. Fassungen in
     * anderen Sprachen bleiben; ist danach keine mehr uebrig, verschwindet
     * das ganze Feld.
     */
    private static function beschreibungEntfernen(DOMElement $d): void
    {
        if ($d->hasAttributeNS(self::NS_DC, 'description')) {
            $d->removeAttributeNS(self::NS_DC, 'description');
        }

        foreach (self::kinder($d, self::NS_DC, 'description') as $feld) {
            foreach (self::kinder($feld, self::NS_RDF, 'Alt') as $alt) {
                foreach (self::kinder($alt, self::NS_RDF, 'li') as $li) {
                    $sprache = $li->getAttributeNS(self::NS_XML, 'lang');
                    if ($sprache === '' || $sprache === 'x-default') {
                        $alt->removeChild($li);
                    }
                }
                if (self::kinder($alt, self::NS_RDF, 'li') === []) {
                    $feld->removeChild($alt);
                }
            }
            if (!self::hatElemente($feld)) {
                $d->removeChild($feld);
            }
        }
    }

    /**
     * Die Beschreibung als x-default setzen. Gibt es noch ein dc:description
     * mit anderen Sprachen, kommt sie dort an den Anfang - x-default soll
     * laut Spezifikation der erste Eintrag sein.
     *
     * @param list<DOMElement> $beschreibungen
     */
    private static function beschreibungSetzen(DOMDocument $doc, array $beschreibungen, string $text): void
    {
        $li = $doc->createElementNS(self::NS_RDF, self::praefixFuer($beschreibungen[0], self::NS_RDF) . ':li');
        $li->setAttributeNS(self::NS_XML, 'xml:lang', 'x-default');
        $li->appendChild($doc->createTextNode($text));

        foreach ($beschreibungen as $d) {
            foreach (self::kinder($d, self::NS_DC, 'description') as $feld) {
                foreach (self::kinder($feld, self::NS_RDF, 'Alt') as $alt) {
                    $alt->insertBefore($li, $alt->firstChild);

                    return;
                }
            }
        }

        $ziel = $beschreibungen[0];
        $feld = $doc->createElementNS(self::NS_DC, self::praefixFuer($ziel, self::NS_DC) . ':description');
        $alt  = $doc->createElementNS(self::NS_RDF, self::praefixFuer($ziel, self::NS_RDF) . ':Alt');
        $alt->appendChild($li);
        $feld->appendChild($alt);
        $ziel->appendChild($feld);
    }

    private static function einfach(DOMDocument $doc, DOMElement $ziel, string $ns, string $name, string $wert): void
    {
        $el = $doc->createElementNS($ns, self::praefixFuer($ziel, $ns) . ':' . $name);
        $el->appendChild($doc->createTextNode($wert));
        $ziel->appendChild($el);
    }

    /** @param list<string> $werte */
    private static function liste(DOMDocument $doc, DOMElement $ziel, string $ns, string $name, array $werte): void
    {
        $rdf = self::praefixFuer($ziel, self::NS_RDF);
        $el  = $doc->createElementNS($ns, self::praefixFuer($ziel, $ns) . ':' . $name);
        $bag = $doc->createElementNS(self::NS_RDF, $rdf . ':Bag');

        foreach ($werte as $wert) {
            $li = $doc->createElementNS(self::NS_RDF, $rdf . ':li');
            $li->appendChild($doc->createTextNode($wert));
            $bag->appendChild($li);
        }

        $el->appendChild($bag);
        $ziel->appendChild($el);
    }

    /**
     * Den Praefix, den das Paket fuer diesen Namensraum schon benutzt - sonst
     * unseren. Programme schreiben etwa "Iptc4xmpExt" oder "iptcExt"; beides
     * ist richtig, nur doppelt soll es nicht werden.
     */
    private static function praefixFuer(DOMNode $knoten, string $ns): string
    {
        $vorhanden = $knoten->lookupPrefix($ns);

        if ($vorhanden !== null && $vorhanden !== '') {
            return $vorhanden;
        }

        return self::PRAEFIX[$ns] ?? 'ns' . substr(md5($ns), 0, 4);
    }

    /** Meldet einen Namensraum am Element an, falls er dort noch unbekannt ist. */
    private static function anmelden(DOMElement $el, string $ns, string $wunsch): void
    {
        if ($el->lookupPrefix($ns) !== null) {
            return;
        }

        // Ist der Wunsch-Praefix schon fuer etwas anderes vergeben, einen eigenen nehmen.
        $praefix = $el->lookupNamespaceURI($wunsch) === null ? $wunsch : 'ns' . substr(md5($ns), 0, 4);
        $el->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $praefix, $ns);
    }

    private static function hatElemente(DOMElement $el): bool
    {
        foreach ($el->childNodes as $kind) {
            if ($kind instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }

    /** Wird ein Namensraum als eigenes Feld des Moduls behandelt? (fuer Tests und Lesen) */
    public static function istEigenesFeld(string $ns, string $name): bool
    {
        return in_array($name, self::EIGENE[$ns] ?? [], true)
            || ($ns === self::NS_DC && $name === 'description');
    }
}

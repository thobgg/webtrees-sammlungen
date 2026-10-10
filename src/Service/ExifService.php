<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Tree;

/**
 * Liest und schreibt EXIF/XMP-Metadaten in Bilddateien.
 *
 * Lesen: Imagick (pingImage, nur der Dateikopf); ohne Imagick bei JPEG der
 * eigene Segmentleser.
 *
 * Schreiben: bei JPEG wird nur das XMP-Segment ausgetauscht (JpegXmp), die
 * Bilddaten bleiben Byte fuer Byte erhalten. Der fruehere Weg ueber Imagick
 * hat das Bild dekodiert und mit writeImage() neu komprimiert - jedes
 * Speichern einer Beschreibung ein Generationsverlust. Fuer PNG, GIF und WebP
 * bleibt Imagick (PNG und GIF sind verlustfrei; WebP-Originale sind im Archiv
 * nicht vorgesehen).
 */
class ExifService
{
    private const BILD_FORMATE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // XMP-Namespaces
    private const NS_RDF    = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const NS_DC     = 'http://purl.org/dc/elements/1.1/';
    private const NS_XMP    = 'http://ns.adobe.com/xap/1.0/';
    private const NS_IPTCEX = 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/';

    /**
     * Eigener Namensraum fuer das, was kein Standardfeld hergibt. Bisher nur
     * "Datum unsicher" (Poststempel unleserlich, Jahr aus der Briefmarke
     * geschlossen): xmp:CreateDate muss ein sauberes Datum bleiben, und ein
     * Stichwort dafuer stuende in jeder Stichwortliste im Weg. Spaeter
     * kommen hier die Rollen Absender/Empfaenger dazu.
     */
    public const NS_SAMMLUNGEN = XmpPaket::NS_SAMMLUNGEN;

    /**
     * Liest XMP-Metadaten aus einer Bilddatei.
     *
     * @return array{beschreibung:string, datum:string, datum_iso:string, datum_unsicher:bool, datum_aus_exif:bool, personen:list<string>, keywords:list<string>, identifier:string, relation:string, bereiche:list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}>, breite:int, hoehe:int, groesse_kb:int}
     */
    public function leseMeta(string $fullPath): array
    {
        $result = [
            'beschreibung'   => '',
            'datum'          => '',
            'datum_iso'      => '',
            'datum_unsicher' => false,
            // true: das Datum stammt aus dem klassischen EXIF und nicht aus
            // dem XMP. Bei Scans ist das meist der Tag, an dem das Bild
            // gespeichert wurde (GIMP setzt DateTime), nicht der Tag der
            // Aufnahme - Postkarten uebernehmen so ein Datum nicht.
            'datum_aus_exif' => false,
            'personen'       => [],
            'keywords'       => [],
            'identifier'     => '',
            'relation'       => '',
            // Gesichter und andere markierte Bereiche (siehe Bereiche), links oben normiert.
            'bereiche'       => [],
            'breite'         => 0,
            'hoehe'          => 0,
            'groesse_kb'     => 0,
        ];

        if (!is_file($fullPath)) {
            return $result;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (!class_exists('Imagick') && !in_array($ext, ['jpg', 'jpeg'], true)) {
            return $result;
        }

        // Dateigröße (nur filesystem stat, sehr schnell)
        $result['groesse_kb'] = ($s = @filesize($fullPath)) ? (int) round($s / 1024) : 0;

        // Cache-Key: Pfad + Änderungsdatum → invalidiert automatisch nach EXIF-Schreiben
        $mtime    = @filemtime($fullPath) ?: 0;
        // "exif2": seit den Bereichen hat das Ergebnis ein Feld mehr.
        $cacheKey = 'exif2:' . md5($fullPath) . ':' . $mtime;

        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey, $ok);
            if ($ok && is_array($cached)) {
                // Dateigröße ist immer aktuell, Rest aus Cache
                $cached['groesse_kb'] = $result['groesse_kb'];
                return $cached + $result;
            }
        }

        try {
            $xmpRaw = '';

            if (class_exists('Imagick')) {
                // pingImage statt Konstruktor: der Konstruktor dekodiert die ganze
                // Datei. Bei einem 7-MB-Foto mit 4796 x 7731 Punkten sind das rund
                // hundert Megabyte Speicher und ein Zehntel Sekunde Rechenzeit -
                // fuer Angaben, die im Dateikopf stehen. Auf einer Galerieseite mit
                // 50 Bildern waren das gemessen ueber fuenf Sekunden, bevor das
                // erste Byte beim Browser ankam. Ping liest nur den Kopf samt
                // Profilen; Breite, Hoehe, EXIF und XMP bleiben verfuegbar.
                $imagick = new \Imagick();
                $imagick->pingImage($fullPath);
                $result['breite'] = $imagick->getImageWidth();
                $result['hoehe']  = $imagick->getImageHeight();

                // Klassisches EXIF (Kamera-Datum) – wird unten als Fallback verwendet
                $exifDateOriginal = '';
                try {
                    $exifDateOriginal = (string) $imagick->getImageProperty('exif:DateTimeOriginal');
                } catch (\Throwable) {}
                if ($exifDateOriginal === '') {
                    try {
                        $exifDateOriginal = (string) $imagick->getImageProperty('exif:DateTime');
                    } catch (\Throwable) {}
                }
                if ($exifDateOriginal !== '') {
                    // EXIF-Format: "YYYY:MM:DD HH:MM:SS" → ISO YYYY-MM-DD
                    if (preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $exifDateOriginal, $m)) {
                        $result['datum_iso']      = "$m[1]-$m[2]-$m[3]";
                        $result['datum']          = $this->formatiereDatumAnzeige($result['datum_iso']);
                        $result['datum_aus_exif'] = true;
                    }
                }

                $xmpRaw = $imagick->getImageProfile('xmp');
                $imagick->destroy();
            } else {
                // Ohne Imagick: Masse aus dem Dateikopf, XMP aus dem Segment.
                $masse = @getimagesize($fullPath);
                if ($masse !== false) {
                    $result['breite'] = (int) $masse[0];
                    $result['hoehe']  = (int) $masse[1];
                }
                $xmpRaw = JpegXmp::lies($fullPath);
            }

            if ($xmpRaw !== '') {
                $this->parseXmp($xmpRaw, $result);
                $result['bereiche'] = Bereiche::lesen($xmpRaw);
            }
        } catch (\Throwable) {
            // Imagick-Fehler – kein Problem
        }

        // Ergebnis für 1 Stunde cachen (invalidiert bei Dateiänderung via filemtime im Key)
        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, $result, 3600);
        }

        return $result;
    }

    /**
     * Schreibt EXIF/XMP-Metadaten in eine Bilddatei.
     * Der Bildinhalt (Pixel) wird nicht verändert.
     *
     * @param list<string> $personen
     * @param list<string> $keywords
     * @param bool   $datumUnsicher  Datum ist geschlossen, nicht abgelesen (Postkarten)
     * @param string $identifier     dc:identifier, z. B. der Kartenschluessel PK_0001
     * @param string $relation       dc:relation, z. B. der Dateiname der anderen Seite
     *
     * @throws \RuntimeException bei Schreibfehler
     */
    public function schreibeMeta(
        string $fullPath,
        string $beschreibung,
        string $datumIso,     // YYYY-MM-DD oder YYYY
        array  $personen,
        array  $keywords,
        Tree   $tree,
        bool   $datumUnsicher = false,
        string $identifier = '',
        string $relation = '',
    ): void {
        $this->aendereMeta($fullPath, [
            'beschreibung'  => $beschreibung,
            'datum'         => $datumIso,
            'datumUnsicher' => $datumUnsicher,
            'personen'      => $personen,
            'keywords'      => $keywords,
            'identifier'    => $identifier,
            'relation'      => $relation,
        ], $tree);
    }

    /**
     * Schreibt nur die mitgegebenen Felder; alles andere in der Datei bleibt,
     * wie es ist (Schluessel siehe XmpPaket::aendern). Der Weg der
     * App-Schnittstelle, die etwa nur die Gesichter schickt.
     *
     * @param array{beschreibung?:string, datum?:string, datumUnsicher?:bool, personen?:list<string>, keywords?:list<string>, identifier?:string, relation?:string, bereiche?:list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}>} $felder
     *
     * @throws \RuntimeException bei Schreibfehler
     */
    public function aendereMeta(string $fullPath, array $felder, Tree $tree): void
    {
        if (!is_file($fullPath) || !is_writable($fullPath)) {
            throw new \RuntimeException("Datei nicht schreibbar: {$fullPath}");
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::BILD_FORMATE, true)) {
            throw new \RuntimeException("Nicht unterstützt: {$ext}");
        }

        $istJpeg = in_array($ext, ['jpg', 'jpeg'], true);

        // Vorhandenes XMP lesen und nur die eigenen Felder darin ersetzen -
        // was andere Programme hineingeschrieben haben, bleibt stehen.
        // Vor dem Backup, damit ein unlesbares Paket gar nichts anfasst.
        $alt   = $istJpeg ? JpegXmp::lies($fullPath) : $this->xmpUeberImagick($fullPath);
        $masse = isset($felder['bereiche']) ? @getimagesize($fullPath) : false;
        $xmp   = XmpPaket::aendern(
            $alt,
            $felder,
            $masse !== false ? (int) $masse[0] : 0,
            $masse !== false ? (int) $masse[1] : 0,
        );

        // Backup vor destruktiver Operation (pro Datei max. 1× pro Tag)
        $this->erstelleBackup($fullPath, $tree);

        if ($istJpeg) {
            // Verlustfrei: nur das XMP-Segment wird getauscht. Kein Imagick noetig.
            if (!is_writable(dirname($fullPath))) {
                throw new \RuntimeException("Ordner nicht schreibbar: " . dirname($fullPath));
            }
            JpegXmp::schreibe($fullPath, $xmp);
            return;
        }

        if (!class_exists('Imagick')) {
            throw new \RuntimeException('Imagick nicht verfügbar.');
        }

        $imagick = new \Imagick($fullPath);

        // Bestehende Qualität beibehalten
        $quality = $imagick->getImageCompressionQuality();
        if ($quality === 0) {
            $quality = 92;
        }
        $imagick->setImageCompressionQuality($quality);

        $imagick->setImageProfile('xmp', $xmp);

        // Auch EXIF-Felder direkt setzen (für ältere Viewer)
        if (($felder['beschreibung'] ?? '') !== '') {
            $imagick->setImageProperty('exif:ImageDescription', $felder['beschreibung']);
        }
        if (($felder['datum'] ?? '') !== '') {
            $exifDatum = $this->formatiereDatumExif($felder['datum']);
            $imagick->setImageProperty('exif:DateTimeOriginal', $exifDatum);
            $imagick->setImageProperty('exif:DateTime', $exifDatum);
        }

        $imagick->writeImage($fullPath);
        $imagick->destroy();
    }

    /** Vollständiger Dateisystempfad aus Tree + relativer Pfad. */
    public function fullPath(Tree $tree, string $relativPfad): string
    {
        $base = MedienPfad::wurzel($tree);
        // Sicherheit: kein Path-Traversal
        $real = realpath($base . $relativPfad);
        $base = realpath($base);
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            throw new \RuntimeException('Ungültiger Pfad.');
        }
        return $real;
    }

    // ---------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------

    /**
     * Liest die Felder aus einem XMP-Paket in das Ergebnis.
     *
     * @param array<string,mixed> $result
     */
    private function parseXmp(string $xmpRaw, array &$result): void
    {
        $xml = @simplexml_load_string($xmpRaw);
        if ($xml === false) {
            return;
        }

        $xml->registerXPathNamespace('rdf',        self::NS_RDF);
        $xml->registerXPathNamespace('dc',         self::NS_DC);
        $xml->registerXPathNamespace('xmp',        self::NS_XMP);
        $xml->registerXPathNamespace('iptcExt',    self::NS_IPTCEX);
        $xml->registerXPathNamespace('sammlungen', self::NS_SAMMLUNGEN);

        // Beschreibung
        // Die Fassung x-default, sonst die erste
        $desc = $xml->xpath('//dc:description/rdf:Alt/rdf:li[@xml:lang="x-default"]')
            ?: $xml->xpath('//dc:description/rdf:Alt/rdf:li[1]');
        if (!empty($desc)) {
            $result['beschreibung'] = trim((string) $desc[0]);
        }

        // Datum - als Element oder in Kurzschreibweise als Attribut
        $date = $xml->xpath('//xmp:CreateDate') ?: $xml->xpath('//rdf:Description/@xmp:CreateDate');
        if (!empty($date)) {
            $raw = trim((string) $date[0]);
            $result['datum_iso']      = $raw;
            $result['datum']          = $this->formatiereDatumAnzeige($raw);
            $result['datum_aus_exif'] = false;
        }

        // Datum unsicher (eigener Namensraum)
        $unsicher = $xml->xpath('//sammlungen:DatumUnsicher') ?: $xml->xpath('//rdf:Description/@sammlungen:DatumUnsicher');
        if (!empty($unsicher)) {
            $result['datum_unsicher'] = in_array(strtolower(trim((string) $unsicher[0])), ['true', '1'], true);
        }

        // Personen (IPTC PersonInImage)
        $personen = $xml->xpath('//iptcExt:PersonInImage/rdf:Bag/rdf:li');
        foreach ($personen as $p) {
            $name = trim((string) $p);
            if ($name !== '') {
                $result['personen'][] = $name;
            }
        }

        // Keywords
        $keys = $xml->xpath('//dc:subject/rdf:Bag/rdf:li');
        foreach ($keys as $k) {
            $kw = trim((string) $k);
            if ($kw !== '') {
                $result['keywords'][] = $kw;
            }
        }

        // Kopplung: Kartenschluessel und Gegenseite
        $ident = $xml->xpath('//dc:identifier') ?: $xml->xpath('//rdf:Description/@dc:identifier');
        if (!empty($ident)) {
            $result['identifier'] = trim((string) $ident[0]);
        }
        $rel = $xml->xpath('//dc:relation/rdf:Bag/rdf:li[1]');
        if (!empty($rel)) {
            $result['relation'] = trim((string) $rel[0]);
        }
    }

    /**
     * Das zu schreibende XMP-Paket: das vorhandene, nur mit den Feldern des
     * Moduls ersetzt (siehe XmpPaket).
     *
     * @param list<string> $personen
     * @param list<string> $keywords
     */
    private function baueXmpPacket(
        string $beschreibung,
        string $datumIso,
        array  $personen,
        array  $keywords,
        bool   $datumUnsicher = false,
        string $identifier = '',
        string $relation = '',
        string $alt = '',
    ): string {
        return XmpPaket::mischen($alt, $beschreibung, $datumIso, $personen, $keywords, $datumUnsicher, $identifier, $relation);
    }

    /** Das XMP-Paket einer Nicht-JPEG-Datei, oder '' wenn keines drin ist. */
    private function xmpUeberImagick(string $fullPath): string
    {
        if (!class_exists('Imagick')) {
            return '';
        }

        try {
            $imagick = new \Imagick();
            $imagick->pingImage($fullPath);
            $profile = $imagick->getImageProfiles('xmp', true);
            $imagick->destroy();

            return (string) ($profile['xmp'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function formatiereDatumAnzeige(string $iso): string
    {
        // YYYY-MM-DD oder YYYY oder YYYY:MM:DD
        $iso = str_replace(':', '-', $iso);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
            // Monat/Tag „00" = unbekannt → nur das Jahr anzeigen.
            return (int) $m[3] === 0 || (int) $m[2] === 0 ? $m[1] : "{$m[3]}.{$m[2]}.{$m[1]}";
        }
        if (preg_match('/^(\d{4})/', $iso, $m)) {
            return $m[1];
        }
        return $iso;
    }

    private function formatiereDatumExif(string $iso): string
    {
        // EXIF erwartet: YYYY:MM:DD HH:MM:SS
        $iso = str_replace(':', '-', $iso);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
            return "{$m[1]}:{$m[2]}:{$m[3]} 00:00:00";
        }
        if (preg_match('/^(\d{4})/', $iso, $m)) {
            return "{$m[1]}:01:01 00:00:00";
        }
        return $iso;
    }

    /**
     * Erstellt Backup der Datei vor destruktiver Bearbeitung.
     * Pro Datei nur 1× pro Tag (überschreibt nicht bei mehrfacher Änderung).
     */
    private function erstelleBackup(string $fullPath, Tree $tree): void
    {
        // Frueher standen hier das Datenverzeichnis und der Ordnername 'media'
        // fest im Code. Wer seinen Medienordner anders nennt oder sein
        // Datenverzeichnis verschoben hat, bei dem schlug die Pruefung fehl -
        // und dann wurde EXIF geschrieben, ohne dass vorher gesichert wurde.
        // Still, ohne Meldung.
        $dataDir   = MedienPfad::datenverzeichnis();
        $mediaBase = realpath(MedienPfad::wurzel($tree)) ?: '';
        $realPath  = realpath($fullPath) ?: '';
        if ($mediaBase === '' || $realPath === '' || !str_starts_with($realPath, $mediaBase)) {
            return; // außerhalb media/ – kein Backup
        }

        // Relativer Pfad ab media/
        $relativ = ltrim(substr($realPath, strlen($mediaBase)), '/');

        // Backup-Ziel: data/sammlungen-backup/YYYY-MM-DD/relativ/datei.jpg
        $heute       = date('Y-m-d');
        $backupRoot  = realpath($dataDir) . '/sammlungen-backup/' . $heute;
        $backupDatei = $backupRoot . '/' . $relativ;

        // Bereits heute gesichert? → kein erneutes Backup
        if (file_exists($backupDatei)) {
            return;
        }

        // Zielverzeichnis anlegen
        $backupDir = dirname($backupDatei);
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        // Kopieren (keine harten Links – das Original soll editierbar bleiben)
        @copy($realPath, $backupDatei);
    }
}

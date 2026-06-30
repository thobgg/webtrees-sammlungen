<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Tree;

/**
 * Liest und schreibt EXIF/XMP-Metadaten in Bilddateien.
 * Nutzt Imagick für verlustfreies Metadaten-Update (kein Neukomprimieren des Bildsinhalts).
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
     * Liest XMP-Metadaten aus einer Bilddatei via Imagick.
     *
     * @return array{beschreibung:string, datum:string, datum_iso:string, personen:list<string>, keywords:list<string>}
     */
    public function leseMeta(string $fullPath): array
    {
        $result = [
            'beschreibung' => '',
            'datum'        => '',
            'datum_iso'    => '',
            'personen'     => [],
            'keywords'     => [],
            'breite'       => 0,
            'hoehe'        => 0,
            'groesse_kb'   => 0,
        ];

        if (!class_exists('Imagick') || !is_file($fullPath)) {
            return $result;
        }

        // Dateigröße (nur filesystem stat, sehr schnell)
        $result['groesse_kb'] = ($s = @filesize($fullPath)) ? (int) round($s / 1024) : 0;

        // Cache-Key: Pfad + Änderungsdatum → invalidiert automatisch nach EXIF-Schreiben
        $mtime    = @filemtime($fullPath) ?: 0;
        $cacheKey = 'exif:' . md5($fullPath) . ':' . $mtime;

        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey, $ok);
            if ($ok && is_array($cached)) {
                // Dateigröße ist immer aktuell, Rest aus Cache
                $cached['groesse_kb'] = $result['groesse_kb'];
                return $cached;
            }
        }

        try {
            $imagick = new \Imagick($fullPath);
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
                    $result['datum_iso'] = "$m[1]-$m[2]-$m[3]";
                    $result['datum']     = $this->formatiereDatumAnzeige($result['datum_iso']);
                }
            }

            $xmpRaw  = $imagick->getImageProfile('xmp');
            $imagick->destroy();

            if ($xmpRaw === '') {
                return $result;
            }

            $xml = @simplexml_load_string($xmpRaw);
            if ($xml === false) {
                return $result;
            }

            $xml->registerXPathNamespace('rdf',     self::NS_RDF);
            $xml->registerXPathNamespace('dc',      self::NS_DC);
            $xml->registerXPathNamespace('xmp',     self::NS_XMP);
            $xml->registerXPathNamespace('iptcExt', self::NS_IPTCEX);

            // Beschreibung
            $desc = $xml->xpath('//dc:description/rdf:Alt/rdf:li[1]');
            if (!empty($desc)) {
                $result['beschreibung'] = trim((string) $desc[0]);
            }

            // Datum
            $date = $xml->xpath('//xmp:CreateDate');
            if (!empty($date)) {
                $raw = trim((string) $date[0]);
                $result['datum_iso'] = $raw;
                $result['datum']     = $this->formatiereDatumAnzeige($raw);
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
     * @throws \RuntimeException bei Schreibfehler
     */
    public function schreibeMeta(
        string $fullPath,
        string $beschreibung,
        string $datumIso,     // YYYY-MM-DD oder YYYY
        array  $personen,
        array  $keywords
    ): void {
        if (!class_exists('Imagick')) {
            throw new \RuntimeException('Imagick nicht verfügbar.');
        }

        if (!is_file($fullPath) || !is_writable($fullPath)) {
            throw new \RuntimeException("Datei nicht schreibbar: {$fullPath}");
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::BILD_FORMATE, true)) {
            throw new \RuntimeException("Nicht unterstütztes Format: {$ext}");
        }

        // Backup vor destruktiver Operation (pro Datei max. 1× pro Tag)
        $this->erstelleBackup($fullPath);

        $imagick = new \Imagick($fullPath);

        // Bestehende Qualität beibehalten (JPEG)
        $quality = $imagick->getImageCompressionQuality();
        if ($quality === 0) {
            $quality = 92;
        }
        $imagick->setImageCompressionQuality($quality);

        // XMP-Paket bauen und setzen
        $xmp = $this->baueXmpPacket($beschreibung, $datumIso, $personen, $keywords);
        $imagick->setImageProfile('xmp', $xmp);

        // Auch EXIF-Felder direkt setzen (für ältere Viewer)
        if ($beschreibung !== '') {
            $imagick->setImageProperty('exif:ImageDescription', $beschreibung);
        }
        if ($datumIso !== '') {
            $exifDatum = $this->formatiereDatumExif($datumIso);
            $imagick->setImageProperty('exif:DateTimeOriginal', $exifDatum);
            $imagick->setImageProperty('exif:DateTime', $exifDatum);
        }

        $imagick->writeImage($fullPath);
        $imagick->destroy();
    }

    /** Vollständiger Dateisystempfad aus Tree + relativer Pfad. */
    public function fullPath(Tree $tree, string $relativPfad): string
    {
        $base = Webtrees::DATA_DIR . $tree->getPreference('MEDIA_DIRECTORY', 'media/');
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

    private function baueXmpPacket(
        string $beschreibung,
        string $datumIso,
        array  $personen,
        array  $keywords
    ): string {
        $e = fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $descXml = $beschreibung !== ''
            ? "<dc:description><rdf:Alt><rdf:li xml:lang=\"x-default\">{$e($beschreibung)}</rdf:li></rdf:Alt></dc:description>"
            : '';

        $dateXml = $datumIso !== ''
            ? "<xmp:CreateDate>{$e($datumIso)}</xmp:CreateDate>"
            : '';

        $personenXml = '';
        if ($personen !== []) {
            $items = implode('', array_map(fn ($p) => "<rdf:li>{$e($p)}</rdf:li>", $personen));
            $personenXml = "<iptcExt:PersonInImage><rdf:Bag>{$items}</rdf:Bag></iptcExt:PersonInImage>";
        }

        $keywordsXml = '';
        if ($keywords !== []) {
            $items = implode('', array_map(fn ($k) => "<rdf:li>{$e($k)}</rdf:li>", $keywords));
            $keywordsXml = "<dc:subject><rdf:Bag>{$items}</rdf:Bag></dc:subject>";
        }

        return <<<XML
<?xpacket begin="\xEF\xBB\xBF" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/">
  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description rdf:about=""
      xmlns:dc="http://purl.org/dc/elements/1.1/"
      xmlns:xmp="http://ns.adobe.com/xap/1.0/"
      xmlns:iptcExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/">
      {$descXml}
      {$dateXml}
      {$personenXml}
      {$keywordsXml}
    </rdf:Description>
  </rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>
XML;
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
    private function erstelleBackup(string $fullPath): void
    {
        $dataDir = \Fisharebest\Webtrees\Webtrees::DATA_DIR;
        $mediaBase = realpath($dataDir . 'media') ?: '';
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

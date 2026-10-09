<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use function array_splice;
use function chmod;
use function chr;
use function count;
use function dirname;
use function fclose;
use function feof;
use function file_exists;
use function fileperms;
use function fopen;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use function is_string;
use function ord;
use function pack;
use function rename;
use function str_starts_with;
use function stream_copy_to_stream;
use function strlen;
use function substr;
use function tempnam;
use function unlink;
use function unpack;

use const SEEK_CUR;
use const SEEK_SET;

/**
 * Liest und schreibt das XMP-Paket einer JPEG-Datei, ohne die Bilddaten
 * anzufassen.
 *
 * Warum nicht Imagick: `new Imagick($datei)` dekodiert das Bild, und
 * `writeImage()` kodiert es neu - bei jedem Speichern der Beschreibung ein
 * weiterer Generationsverlust, und die Datei ist danach nicht mehr der Scan.
 * Bei einer Postkarte mit 600 dpi, die als JPG das Original IST, geht das
 * nicht (Schritt 0 der Postkarten-Aufgabe).
 *
 * Eine JPEG-Datei ist eine Folge von Segmenten: SOI, dann APPn-, DQT-, SOF-,
 * DHT-Segmente mit Laengenangabe, dann SOS, ab dem die komprimierten Bilddaten
 * bis EOI folgen. Das XMP-Paket steckt in einem APP1-Segment mit dem Kopf
 * "http://ns.adobe.com/xap/1.0/\0". Hier wird genau dieses eine Segment
 * ersetzt oder eingefuegt; alle anderen Segmente und alles ab SOS werden
 * Byte fuer Byte uebernommen. EXIF (APP1 "Exif\0\0") und IPTC (APP13) bleiben,
 * wie sie sind.
 *
 * Geschrieben wird in eine Nachbardatei, die dann an die Stelle des Originals
 * tritt - ein Abbruch mitten im Schreiben laesst das Original unversehrt.
 */
final class JpegXmp
{
    public const XMP_KOPF = "http://ns.adobe.com/xap/1.0/\0";

    /**
     * Fortsetzungs-Segmente eines zu langen XMP-Pakets (Lightroom, Kameras mit
     * Tiefenkarte). Das Hauptpaket verweist per xmpNote:HasExtendedXMP auf
     * sie; da das Modul nur seine eigenen Felder im Hauptpaket ersetzt und
     * diesen Verweis stehen laesst, bleiben auch sie unangetastet.
     */
    public const XMP_ERWEITERUNG_KOPF = "http://ns.adobe.com/xmp/extension/\0";

    private const EXIF_KOPF = "Exif\0\0";

    private const SOI  = 0xD8;
    private const SOS  = 0xDA;
    private const EOI  = 0xD9;
    private const APP0 = 0xE0;
    private const APP1 = 0xE1;

    /** Segmentlaenge ist 16 Bit inklusive der beiden Laengenbytes. */
    private const MAX_NUTZLAST = 65533;

    /**
     * Das XMP-Paket der Datei, oder '' wenn keines drin ist.
     */
    public static function lies(string $pfad): string
    {
        $h = @fopen($pfad, 'rb');

        if ($h === false) {
            return '';
        }

        try {
            foreach (self::kopfSegmente($h) as $segment) {
                if ($segment['marker'] === self::APP1 && str_starts_with($segment['daten'], self::XMP_KOPF)) {
                    return substr($segment['daten'], strlen(self::XMP_KOPF));
                }
            }
        } catch (\RuntimeException) {
            // kein JPEG oder abgeschnitten - dann eben kein XMP
        } finally {
            fclose($h);
        }

        return '';
    }

    /**
     * Ersetzt das XMP-Paket (oder fuegt eines ein). Bilddaten und alle anderen
     * Segmente bleiben Byte fuer Byte erhalten.
     *
     * @throws \RuntimeException wenn die Datei kein JPEG ist oder nicht geschrieben werden kann
     */
    public static function schreibe(string $pfad, string $xmpPaket): void
    {
        $nutzlast = self::XMP_KOPF . $xmpPaket;

        if (strlen($nutzlast) > self::MAX_NUTZLAST) {
            throw new \RuntimeException('XMP-Paket zu gross fuer ein Segment.');
        }

        $quelle = @fopen($pfad, 'rb');

        if ($quelle === false) {
            throw new \RuntimeException("Datei nicht lesbar: {$pfad}");
        }

        try {
            $segmente = self::kopfSegmente($quelle);
            $sosStart = ftell($quelle);          // hier beginnt das SOS-Segment
            if ($sosStart === false) {
                throw new \RuntimeException('Position nicht lesbar.');
            }

            // Altes Hauptpaket raus, Position fuers neue merken.
            $behalten = [];
            $einfuegen = null;

            foreach ($segmente as $i => $segment) {
                // Nur das Hauptpaket - Fortsetzungen (XMP_ERWEITERUNG_KOPF) bleiben.
                $istXmp = $segment['marker'] === self::APP1
                    && str_starts_with($segment['daten'], self::XMP_KOPF);

                if ($istXmp) {
                    $einfuegen ??= count($behalten);
                    continue;
                }

                $behalten[] = $segment;
            }

            // Kein altes XMP: hinter JFIF (APP0) und Exif (APP1) einreihen, so
            // wie es die XMP-Spezifikation vorsieht und die meisten Programme
            // es schreiben. Alles andere (DQT, SOF, ...) kommt danach.
            if ($einfuegen === null) {
                $einfuegen = 0;
                foreach ($behalten as $i => $segment) {
                    $istKopf = $segment['marker'] === self::APP0
                        || ($segment['marker'] === self::APP1 && str_starts_with($segment['daten'], self::EXIF_KOPF));
                    if ($istKopf) {
                        $einfuegen = $i + 1;
                    } else {
                        break;
                    }
                }
            }

            $neu = ['marker' => self::APP1, 'daten' => $nutzlast];
            array_splice($behalten, $einfuegen, 0, [$neu]);

            $ziel = self::nachbarDatei($pfad);
            $ausgabe = @fopen($ziel, 'wb');

            if ($ausgabe === false) {
                throw new \RuntimeException("Kann nicht schreiben: {$ziel}");
            }

            try {
                fwrite($ausgabe, "\xFF" . chr(self::SOI));
                foreach ($behalten as $segment) {
                    fwrite($ausgabe, "\xFF" . chr($segment['marker']) . pack('n', strlen($segment['daten']) + 2) . $segment['daten']);
                }

                // Ab SOS alles unveraendert durchreichen - das sind die Bilddaten.
                fseek($quelle, $sosStart, SEEK_SET);
                stream_copy_to_stream($quelle, $ausgabe);
            } finally {
                fclose($ausgabe);
            }

            $rechte = @fileperms($pfad);
            if ($rechte !== false) {
                @chmod($ziel, $rechte & 0o777);
            }

            if (!@rename($ziel, $pfad)) {
                @unlink($ziel);
                throw new \RuntimeException("Kann Datei nicht ersetzen: {$pfad}");
            }
        } finally {
            fclose($quelle);
        }
    }

    /**
     * Die komprimierten Bilddaten (ab SOS bis Dateiende) - das, was sich beim
     * Schreiben von Metadaten nie aendern darf. Fuer Pruefungen und Tests.
     */
    public static function bilddaten(string $pfad): string
    {
        $h = @fopen($pfad, 'rb');

        if ($h === false) {
            return '';
        }

        try {
            self::kopfSegmente($h);
            $rest = '';
            while (!feof($h)) {
                $stueck = fread($h, 1 << 20);
                if (!is_string($stueck)) {
                    break;
                }
                $rest .= $stueck;
            }

            return $rest;
        } finally {
            fclose($h);
        }
    }

    /**
     * Liest alle Segmente vor SOS. Danach steht der Dateizeiger auf dem
     * 0xFF des SOS-Markers.
     *
     * @param resource $h
     * @return list<array{marker:int, daten:string}>
     * @throws \RuntimeException
     */
    private static function kopfSegmente($h): array
    {
        $soi = fread($h, 2);

        if ($soi !== "\xFF" . chr(self::SOI)) {
            throw new \RuntimeException('Kein JPEG (SOI fehlt).');
        }

        $segmente = [];

        while (true) {
            $ff = fread($h, 1);
            if ($ff !== "\xFF") {
                throw new \RuntimeException('JPEG beschaedigt: Marker erwartet.');
            }

            // Fuellbytes 0xFF sind erlaubt.
            do {
                $m = fread($h, 1);
                if ($m === false || $m === '') {
                    throw new \RuntimeException('JPEG abgeschnitten.');
                }
                $marker = ord($m);
            } while ($marker === 0xFF);

            if ($marker === self::SOS) {
                fseek($h, -2, SEEK_CUR);
                return $segmente;
            }

            if ($marker === self::EOI) {
                throw new \RuntimeException('JPEG ohne Bilddaten.');
            }

            // Marker ohne Nutzlast (RSTn, TEM) kommen vor SOS nicht vor, zur Sicherheit:
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                continue;
            }

            $laengeRoh = fread($h, 2);
            if ($laengeRoh === false || strlen($laengeRoh) < 2) {
                throw new \RuntimeException('JPEG abgeschnitten.');
            }
            $laenge = unpack('n', $laengeRoh)[1] - 2;
            if ($laenge < 0) {
                throw new \RuntimeException('JPEG beschaedigt: Segmentlaenge.');
            }

            $daten = $laenge > 0 ? fread($h, $laenge) : '';
            if ($daten === false || strlen($daten) !== $laenge) {
                throw new \RuntimeException('JPEG abgeschnitten.');
            }

            $segmente[] = ['marker' => $marker, 'daten' => $daten];
        }
    }

    /** Eine Nachbardatei im selben Verzeichnis, damit rename() kein Kopieren ueber Dateisystemgrenzen wird. */
    private static function nachbarDatei(string $pfad): string
    {
        $dir  = dirname($pfad);
        $ziel = @tempnam($dir, '.xmp-');

        if ($ziel === false || !file_exists($ziel)) {
            throw new \RuntimeException("Kann keine Hilfsdatei anlegen in: {$dir}");
        }

        return $ziel;
    }
}

<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sammlungen\Service\JpegXmp;
use Sammlungen\Service\XmpPaket;

/**
 * Beim Speichern darf das Modul nur seine eigenen Felder anfassen.
 *
 * Frueher wurde das XMP-Paket jedes Mal neu gebaut: Gesichtsregionen aus
 * digiKam, Bewertungen, Lightroom-Hierarchien und Beschreibungen in weiteren
 * Sprachen waren nach einem Klick auf "In Datei speichern" verloren.
 */
#[CoversClass(XmpPaket::class)]
final class XmpPaketTest extends TestCase
{
    /** Ein Paket, wie es ein anderes Programm geschrieben haben koennte. */
    private const FREMD = <<<'XML'
<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/">
 <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about=""
    xmlns:xmp="http://ns.adobe.com/xap/1.0/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:iptcExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"
    xmlns:lr="http://ns.adobe.com/lightroom/1.0/"
    xmlns:mwg-rs="http://www.metadataworkinggroup.com/schemas/regions/"
    xmlns:stArea="http://ns.adobe.com/xmp/sType/Area#"
    xmlns:xmpNote="http://ns.adobe.com/xmp/note/"
    xmp:Rating="4"
    xmp:CreateDate="1950-01-01"
    xmpNote:HasExtendedXMP="ABCDEF0123456789ABCDEF0123456789">
   <dc:description>
    <rdf:Alt>
     <rdf:li xml:lang="x-default">alte Beschreibung</rdf:li>
     <rdf:li xml:lang="en">old description</rdf:li>
    </rdf:Alt>
   </dc:description>
   <dc:subject><rdf:Bag><rdf:li>alt</rdf:li></rdf:Bag></dc:subject>
   <iptcExt:PersonInImage><rdf:Bag><rdf:li>Alter Name</rdf:li></rdf:Bag></iptcExt:PersonInImage>
   <lr:hierarchicalSubject><rdf:Bag><rdf:li>Orte|Kiel</rdf:li></rdf:Bag></lr:hierarchicalSubject>
   <mwg-rs:Regions rdf:parseType="Resource">
    <mwg-rs:RegionList>
     <rdf:Bag>
      <rdf:li rdf:parseType="Resource">
       <mwg-rs:Name>Anna Dilger</mwg-rs:Name>
       <mwg-rs:Type>Face</mwg-rs:Type>
       <mwg-rs:Area stArea:x="0.4" stArea:y="0.3" stArea:w="0.1" stArea:h="0.15" stArea:unit="normalized"/>
      </rdf:li>
     </rdf:Bag>
    </mwg-rs:RegionList>
   </mwg-rs:Regions>
  </rdf:Description>
 </rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>
XML;

    private static function felder(string $xmp): \SimpleXMLElement
    {
        $xml = simplexml_load_string($xmp);
        self::assertNotFalse($xml, 'Ergebnis ist kein gueltiges XML.');
        foreach ([
            'rdf' => XmpPaket::NS_RDF, 'dc' => XmpPaket::NS_DC, 'xmp' => XmpPaket::NS_XMP,
            'ie' => XmpPaket::NS_IPTCEX, 'sa' => XmpPaket::NS_SAMMLUNGEN,
            'lr' => 'http://ns.adobe.com/lightroom/1.0/',
            'mwg' => 'http://www.metadataworkinggroup.com/schemas/regions/',
            'note' => 'http://ns.adobe.com/xmp/note/',
        ] as $p => $ns) {
            $xml->registerXPathNamespace($p, $ns);
        }

        return $xml;
    }

    private static function texte(\SimpleXMLElement $xml, string $pfad): array
    {
        return array_map(static fn ($n): string => trim((string) $n), $xml->xpath($pfad) ?: []);
    }

    public function testFremdeFelderBleibenStehen(): void
    {
        $neu = XmpPaket::mischen(self::FREMD, 'neue Beschreibung', '1916', ['Anna Dilger'], ['Kiel'], true, 'PK_0003', 'PK_0003_V.jpg');
        $xml = self::felder($neu);

        // Gesichtsregion, Bewertung, Lightroom-Hierarchie, Verweis auf Fortsetzung
        self::assertSame(['Anna Dilger'], self::texte($xml, '//mwg:RegionList/rdf:Bag/rdf:li/mwg:Name'));
        self::assertSame(['4'], self::texte($xml, '//rdf:Description/@xmp:Rating'));
        self::assertSame(['Orte|Kiel'], self::texte($xml, '//lr:hierarchicalSubject/rdf:Bag/rdf:li'));
        self::assertSame(['ABCDEF0123456789ABCDEF0123456789'], self::texte($xml, '//rdf:Description/@note:HasExtendedXMP'));
        // Beschreibung in anderer Sprache bleibt
        self::assertSame(['old description'], self::texte($xml, '//dc:description/rdf:Alt/rdf:li[@xml:lang="en"]'));
    }

    public function testEigeneFelderWerdenErsetztNichtVerdoppelt(): void
    {
        $neu = XmpPaket::mischen(self::FREMD, 'neue Beschreibung', '1916', ['Anna Dilger'], ['Kiel'], true, 'PK_0003', 'PK_0003_V.jpg');
        $xml = self::felder($neu);

        self::assertSame(['neue Beschreibung'], self::texte($xml, '//dc:description/rdf:Alt/rdf:li[@xml:lang="x-default"]'));
        // x-default steht vorne
        self::assertSame('neue Beschreibung', self::texte($xml, '//dc:description/rdf:Alt/rdf:li[1]')[0]);
        // Datum: das alte Attribut ist weg, das neue Element da - genau einmal
        self::assertSame([], self::texte($xml, '//rdf:Description/@xmp:CreateDate'));
        self::assertSame(['1916'], self::texte($xml, '//xmp:CreateDate'));
        self::assertSame(['True'], self::texte($xml, '//sa:DatumUnsicher'));
        self::assertSame(['Anna Dilger'], self::texte($xml, '//ie:PersonInImage/rdf:Bag/rdf:li'));
        self::assertSame(['Kiel'], self::texte($xml, '//dc:subject/rdf:Bag/rdf:li'));
        self::assertSame(['PK_0003'], self::texte($xml, '//dc:identifier'));
        self::assertSame(['PK_0003_V.jpg'], self::texte($xml, '//dc:relation/rdf:Bag/rdf:li'));
        self::assertCount(1, $xml->xpath('//dc:description'));
    }

    public function testNeueNamensraeumeStehenNurEinmalImPaket(): void
    {
        $nurRegion = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmp:Rating="5"/></rdf:RDF></x:xmpmeta>';

        $neu = XmpPaket::mischen($nurRegion, 'Text', '1916', ['Anna'], ['Kiel'], true, 'PK_1', 'PK_1_R.jpg');

        self::assertSame(1, substr_count($neu, 'xmlns:dc='));
        self::assertSame(1, substr_count($neu, 'xmlns:rdf='));
        self::assertStringContainsString('<dc:relation><rdf:Bag><rdf:li>PK_1_R.jpg</rdf:li></rdf:Bag></dc:relation>', $neu);
        self::assertStringContainsString('xmp:Rating="5"', $neu);
    }

    public function testVorhandenerPraefixWirdWeiterbenutzt(): void
    {
        $neu = XmpPaket::mischen(self::FREMD, '', '', ['Anna'], [], false, '', '');

        self::assertStringContainsString('<iptcExt:PersonInImage>', $neu);
        self::assertStringNotContainsString('Iptc4xmpExt:', $neu);
    }

    public function testLeereFelderEntfernenDieAltenWerte(): void
    {
        $neu = XmpPaket::mischen(self::FREMD, '', '', [], [], false, '', '');
        $xml = self::felder($neu);

        self::assertSame([], self::texte($xml, '//xmp:CreateDate'));
        self::assertSame([], self::texte($xml, '//rdf:Description/@xmp:CreateDate'));
        self::assertSame([], self::texte($xml, '//dc:subject/rdf:Bag/rdf:li'));
        self::assertSame([], self::texte($xml, '//ie:PersonInImage/rdf:Bag/rdf:li'));
        self::assertSame([], self::texte($xml, '//dc:description/rdf:Alt/rdf:li[@xml:lang="x-default"]'));
        // ... die englische Fassung bleibt, und mit ihr das Feld
        self::assertSame(['old description'], self::texte($xml, '//dc:description/rdf:Alt/rdf:li'));
        // ... und die Gesichter sowieso
        self::assertSame(['Anna Dilger'], self::texte($xml, '//mwg:RegionList/rdf:Bag/rdf:li/mwg:Name'));
    }

    public function testUnsicherOhneDatumWirdNichtGeschrieben(): void
    {
        $neu = XmpPaket::mischen('', '', '', [], [], true, '', '');

        self::assertStringNotContainsString('DatumUnsicher', $neu);
    }

    public function testUnlesbaresPaketWirdNichtUeberschrieben(): void
    {
        $this->expectException(\RuntimeException::class);
        XmpPaket::mischen('<x:xmpmeta><kaputt', 'neu', '', [], [], false, '', '');
    }

    public function testNullbytesAmEndeStoerenNicht(): void
    {
        $neu = XmpPaket::mischen(self::FREMD . str_repeat("\0", 50), 'neu', '', [], [], false, '', '');

        self::assertSame(['neu'], self::texte(self::felder($neu), '//dc:description/rdf:Alt/rdf:li[@xml:lang="x-default"]'));
    }

    public function testPaketMitDtdWirdNichtUeberschrieben(): void
    {
        $boese = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]>'
            . '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>&e;</dc:title></rdf:Description>'
            . '</rdf:RDF></x:xmpmeta>';

        $this->expectException(\RuntimeException::class);
        XmpPaket::mischen($boese, 'neu', '', [], [], false, '', '');
    }

    /**
     * Rundlauf auf der Datei: fremdes XMP samt Fortsetzungs-Segment hinein,
     * mischen und schreiben - die Fortsetzung bleibt, die Bilddaten auch.
     */
    public function testFortsetzungsSegmentBleibtInDerDatei(): void
    {
        if (!function_exists('imagejpeg')) {
            self::markTestSkipped('GD fehlt - kein Testbild.');
        }

        $datei = tempnam(sys_get_temp_dir(), 'xmp') . '.jpg';
        imagejpeg(imagecreatetruecolor(32, 24), $datei, 90);

        try {
            // Fortsetzungs-Segment von Hand hinter das SOI setzen
            $roh   = (string) file_get_contents($datei);
            $fort  = JpegXmp::XMP_ERWEITERUNG_KOPF . 'ABCDEF0123456789ABCDEF0123456789' . pack('NN', 5, 0) . 'extra';
            $roh   = substr($roh, 0, 2) . "\xFF\xE1" . pack('n', strlen($fort) + 2) . $fort . substr($roh, 2);
            file_put_contents($datei, $roh);

            JpegXmp::schreibe($datei, XmpPaket::mischen('', 'eins', '', [], [], false, '', ''));
            $bilddaten = JpegXmp::bilddaten($datei);
            JpegXmp::schreibe($datei, XmpPaket::mischen(JpegXmp::lies($datei), 'zwei', '', [], [], false, '', ''));

            $nachher = (string) file_get_contents($datei);
            self::assertStringContainsString($fort, $nachher, 'Fortsetzungs-Segment fehlt nach dem Schreiben.');
            self::assertSame(1, substr_count($nachher, JpegXmp::XMP_KOPF));
            self::assertStringContainsString('zwei', JpegXmp::lies($datei));
            self::assertSame($bilddaten, JpegXmp::bilddaten($datei));
        } finally {
            @unlink($datei);
            @unlink(substr($datei, 0, -4));
        }
    }
}

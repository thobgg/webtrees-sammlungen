<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sammlungen\Service\Bereiche;
use Sammlungen\Service\XmpPaket;

/**
 * Gesichter auf Gruppenbildern: MWG Regions im XMP, nach aussen links oben.
 *
 * Die Falle bei MWG: stArea:x/y ist die MITTE des Rahmens. Wer das vergisst,
 * zeichnet jeden Rahmen um eine halbe Breite verschoben.
 */
#[CoversClass(Bereiche::class)]
#[CoversClass(XmpPaket::class)]
final class BereicheTest extends TestCase
{
    /** Gesicht mit Kennung, unbekanntes Gesicht, ein Haustier eines anderen Programms. */
    private const PAKET = <<<'XML'
<x:xmpmeta xmlns:x="adobe:ns:meta/">
 <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about=""
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"
    xmlns:mwg-rs="http://www.metadataworkinggroup.com/schemas/regions/"
    xmlns:stArea="http://ns.adobe.com/xmp/sType/Area#"
    xmlns:stDim="http://ns.adobe.com/xap/1.0/sType/Dimensions#"
    xmlns:sammlungen="https://github.com/thobgg/webtrees-sammlungen/ns/1.0/"
    xmlns:fremd="http://example.org/fremd/">
   <dc:description><rdf:Alt><rdf:li xml:lang="x-default">Hochzeit 1924</rdf:li></rdf:Alt></dc:description>
   <Iptc4xmpExt:PersonInImage><rdf:Bag><rdf:li>Heinrich Falkenrath</rdf:li></rdf:Bag></Iptc4xmpExt:PersonInImage>
   <mwg-rs:Regions rdf:parseType="Resource">
    <mwg-rs:AppliedToDimensions stDim:w="1310" stDim:h="715" stDim:unit="pixel"/>
    <mwg-rs:RegionList>
     <rdf:Bag>
      <rdf:li rdf:parseType="Resource">
       <mwg-rs:Type>Face</mwg-rs:Type>
       <mwg-rs:Name>Heinrich Falkenrath</mwg-rs:Name>
       <mwg-rs:Area stArea:x="0.4351" stArea:y="0.3007" stArea:w="0.0489" stArea:h="0.1371" stArea:unit="normalized"/>
       <sammlungen:Xref>I21</sammlungen:Xref>
      </rdf:li>
      <rdf:li>
       <rdf:Description>
        <mwg-rs:Type>Face</mwg-rs:Type>
        <mwg-rs:Area>
         <rdf:Description stArea:x="0.1" stArea:y="0.2" stArea:w="0.04" stArea:h="0.06" stArea:unit="normalized"/>
        </mwg-rs:Area>
       </rdf:Description>
      </rdf:li>
      <rdf:li rdf:parseType="Resource">
       <mwg-rs:Type>Pet</mwg-rs:Type>
       <mwg-rs:Name>Hasso</mwg-rs:Name>
       <mwg-rs:Area stArea:x="0.9" stArea:y="0.9" stArea:w="0.1" stArea:h="0.1" stArea:unit="normalized"/>
       <fremd:Notiz>bleibt</fremd:Notiz>
      </rdf:li>
     </rdf:Bag>
    </mwg-rs:RegionList>
   </mwg-rs:Regions>
  </rdf:Description>
 </rdf:RDF>
</x:xmpmeta>
XML;

    public function testMwgWirdLinksObenGelesen(): void
    {
        $b = Bereiche::lesen(self::PAKET);

        self::assertCount(3, $b);
        self::assertEqualsWithDelta(0.4351 - 0.0489 / 2, $b[0]['x'], 1e-6);
        self::assertEqualsWithDelta(0.3007 - 0.1371 / 2, $b[0]['y'], 1e-6);
        self::assertEqualsWithDelta(0.0489, $b[0]['w'], 1e-6);
        self::assertSame('Heinrich Falkenrath', $b[0]['name']);
        self::assertSame('I21', $b[0]['xref']);
        self::assertSame('Face', $b[0]['typ']);

        // Eigenschaften in rdf:Description statt parseType="Resource" - beides ist RDF.
        self::assertEqualsWithDelta(0.08, $b[1]['x'], 1e-6);
        self::assertSame('', $b[1]['name']);
        self::assertNull($b[1]['xref']);

        self::assertSame('Pet', $b[2]['typ']);
    }

    public function testMicrosoftNurOhneMwg(): void
    {
        $ms = <<<'XML'
<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
 <rdf:Description rdf:about="" xmlns:MP="http://ns.microsoft.com/photo/1.2/"
   xmlns:MPRI="http://ns.microsoft.com/photo/1.2/t/RegionInfo#" xmlns:MPReg="http://ns.microsoft.com/photo/1.2/t/Region#">
  <MP:RegionInfo rdf:parseType="Resource"><MPRI:Regions><rdf:Bag>
   <rdf:li MPReg:Rectangle="0.25, 0.1, 0.05, 0.08" MPReg:PersonDisplayName="Anna"/>
  </rdf:Bag></MPRI:Regions></MP:RegionInfo>
 </rdf:Description></rdf:RDF></x:xmpmeta>
XML;
        $b = Bereiche::lesen($ms);

        self::assertCount(1, $b);
        self::assertEqualsWithDelta(0.25, $b[0]['x'], 1e-6);
        self::assertSame('Anna', $b[0]['name']);
        self::assertSame('Face', $b[0]['typ']);
    }

    public function testKaputtesXmpErgibtKeineBereiche(): void
    {
        self::assertSame([], Bereiche::lesen('<x:xmpmeta'));
        self::assertSame([], Bereiche::lesen(''));
    }

    public function testNurBereicheSchreibenLaesstAllesAndereStehen(): void
    {
        $neu = XmpPaket::aendern(self::PAKET, ['bereiche' => [
            ['x' => 0.5, 'y' => 0.5, 'w' => 0.1, 'h' => 0.2, 'name' => 'Wilhelm Niebuhr', 'xref' => null, 'typ' => 'Face'],
        ]], 1310, 715);

        $b = Bereiche::lesen($neu);

        // Die beiden alten Gesichter sind ersetzt, das Haustier ist noch da - samt fremdem Feld.
        self::assertCount(2, $b);
        self::assertSame(['Pet', 'Face'], [$b[0]['typ'], $b[1]['typ']]);
        self::assertStringContainsString('<fremd:Notiz>bleibt</fremd:Notiz>', $neu);

        // Gespeichert als Mitte, gelesen als linke obere Ecke.
        self::assertStringContainsString('stArea:x="0.55"', $neu);
        self::assertStringContainsString('stArea:y="0.6"', $neu);
        self::assertEqualsWithDelta(0.5, $b[1]['x'], 1e-6);

        // Beschreibung bleibt, PersonInImage bekommt den neuen Namen dazu und behaelt den alten.
        self::assertStringContainsString('Hochzeit 1924', $neu);
        self::assertStringContainsString('<rdf:li>Heinrich Falkenrath</rdf:li>', $neu);
        self::assertStringContainsString('<rdf:li>Wilhelm Niebuhr</rdf:li>', $neu);

        // Vorhandene Masse bleiben, es kommen keine zweiten dazu.
        self::assertSame(1, substr_count($neu, 'AppliedToDimensions'));
    }

    public function testLeereListeEntferntNurGesichter(): void
    {
        $neu = XmpPaket::aendern(self::PAKET, ['bereiche' => []]);
        $b   = Bereiche::lesen($neu);

        self::assertCount(1, $b);
        self::assertSame('Pet', $b[0]['typ']);
    }

    public function testOhneUebrigeBereicheVerschwindetRegions(): void
    {
        $nurGesichter = XmpPaket::aendern(self::PAKET, ['bereiche' => [
            ['x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.1, 'name' => '', 'xref' => null, 'typ' => 'Face'],
        ]]);
        $ohnePet = str_replace('<mwg-rs:Type>Pet</mwg-rs:Type>', '<mwg-rs:Type>Face</mwg-rs:Type>', self::PAKET);

        $leer = XmpPaket::aendern($ohnePet, ['bereiche' => []]);

        self::assertStringNotContainsString('Regions', $leer);
        self::assertStringContainsString('Regions', $nurGesichter);
    }

    public function testNeuesPaketBekommtMasse(): void
    {
        $neu = XmpPaket::aendern('', ['bereiche' => [
            ['x' => 0.2, 'y' => 0.2, 'w' => 0.1, 'h' => 0.1, 'name' => 'Anna', 'xref' => 'I3', 'typ' => 'Face'],
        ]], 800, 600);

        self::assertStringContainsString('stDim:w="800"', $neu);
        self::assertStringContainsString('<sammlungen:Xref>I3</sammlungen:Xref>', $neu);
        self::assertSame('I3', Bereiche::lesen($neu)[0]['xref']);
    }

    public function testTeilweiseSchreibenLaesstUngesendetesStehen(): void
    {
        $alt = XmpPaket::mischen('', 'Karte', '1912-05-12', ['Anna'], ['Kiel'], true, 'PK_0001', 'PK_0001_R.jpg');

        $neu = XmpPaket::aendern($alt, ['keywords' => ['Hamburg']]);

        self::assertStringContainsString('Karte', $neu);
        self::assertStringContainsString('1912-05-12', $neu);
        self::assertStringContainsString('DatumUnsicher', $neu);
        self::assertStringContainsString('PK_0001_R.jpg', $neu);
        self::assertStringContainsString('<rdf:li>Anna</rdf:li>', $neu);
        self::assertStringContainsString('Hamburg', $neu);
        self::assertStringNotContainsString('Kiel', $neu);

        // Ein leeres Datum nimmt "unsicher" mit.
        $ohneDatum = XmpPaket::aendern($alt, ['datum' => '']);
        self::assertStringNotContainsString('CreateDate', $ohneDatum);
        self::assertStringNotContainsString('DatumUnsicher', $ohneDatum);
    }

    public function testAusJson(): void
    {
        $b = Bereiche::ausJson('[{"x":0.4106,"y":0.2322,"w":0.0489,"h":0.1371,"name":" Heinrich ","xref":"I21","typ":"Face"},'
            . '{"x":0,"y":0,"w":0.1,"h":0.1,"name":"","xref":null},'
            . '{"x":0.5,"y":0.5,"w":0.1,"h":0.1,"typ":"Pet"}]');

        self::assertNotNull($b);
        self::assertCount(2, $b, 'andere Typen werden nicht angenommen');
        self::assertSame('Heinrich', $b[0]['name']);
        self::assertSame('I21', $b[0]['xref']);
        self::assertNull($b[1]['xref']);
        self::assertSame([], Bereiche::ausJson('[]'));
    }

    /** @return iterable<string, array{string}> */
    public static function ungueltig(): iterable
    {
        yield 'kein JSON'          => ['{'];
        yield 'kein Feld'          => ['{"x":1}'];
        yield 'ausserhalb'         => ['[{"x":1.2,"y":0,"w":0.1,"h":0.1}]'];
        yield 'negativ'            => ['[{"x":-0.1,"y":0,"w":0.1,"h":0.1}]'];
        yield 'ueber den Rand'     => ['[{"x":0.95,"y":0,"w":0.1,"h":0.1}]'];
        yield 'ohne Flaeche'       => ['[{"x":0.1,"y":0,"w":0,"h":0.1}]'];
        yield 'Zahl als Text'      => ['[{"x":"a","y":0,"w":0.1,"h":0.1}]'];
        yield 'fehlende Hoehe'     => ['[{"x":0.1,"y":0,"w":0.1}]'];
        yield 'Kennung mit Unsinn' => ['[{"x":0.1,"y":0,"w":0.1,"h":0.1,"xref":"I1<script>"}]'];
    }

    #[DataProvider('ungueltig')]
    public function testUngueltigeBereiche(string $json): void
    {
        self::assertNull(Bereiche::ausJson($json));
    }

    public function testXrefsNurAusGesichtern(): void
    {
        self::assertSame(['I21'], Bereiche::xrefs(Bereiche::lesen(self::PAKET)));
    }
}

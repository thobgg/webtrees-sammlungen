<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sammlungen\Service\Postkarten;

#[CoversClass(Postkarten::class)]
final class PostkartenTest extends TestCase
{
    /** @return array<string, array{string, array{schluessel:string, seite:string}|null}> */
    public static function namen(): array
    {
        return [
            'Vorderseite'         => ['PK_0001_V.jpg', ['schluessel' => 'PK_0001', 'seite' => 'V']],
            'Rueckseite'          => ['PK_0001_R.jpg', ['schluessel' => 'PK_0001', 'seite' => 'R']],
            'Endung gross'        => ['PK_0001_R.JPG', ['schluessel' => 'PK_0001', 'seite' => 'R']],
            'jpeg'                => ['Oma_1912_v.jpeg', ['schluessel' => 'Oma_1912', 'seite' => 'V']],
            'mit Pfad'            => ['postkarten/1912/PK_0001_V.jpg', ['schluessel' => 'PK_0001', 'seite' => 'V']],
            'Unterstrich im Kern' => ['Karte_von_Oma_V.jpg', ['schluessel' => 'Karte_von_Oma', 'seite' => 'V']],
            'kein Muster'         => ['PK_0001.jpg', null],
            'falsche Seite'       => ['PK_0001_X.jpg', null],
            'png'                 => ['PK_0001_V.png', null],
        ];
    }

    #[DataProvider('namen')]
    public function testSeiteAusDemDateinamen(string $datei, ?array $erwartet): void
    {
        self::assertSame($erwartet, Postkarten::seite($datei));
    }

    public function testPartnerName(): void
    {
        self::assertSame('PK_0001_R.jpg', Postkarten::partnerName('PK_0001_V.jpg'));
        self::assertSame('PK_0001_V.jpg', Postkarten::partnerName('pfad/PK_0001_R.jpg'));
        self::assertSame('PK_0001_V.JPG', Postkarten::partnerName('PK_0001_R.JPG'));
        self::assertNull(Postkarten::partnerName('PK_0001.jpg'));
    }

    public function testSindPartner(): void
    {
        self::assertTrue(Postkarten::sindPartner('p/PK_0001_V.jpg', 'p/PK_0001_R.jpg'));
        self::assertFalse(Postkarten::sindPartner('p/PK_0001_V.jpg', 'p/PK_0001_V.jpg'), 'zweimal dieselbe Seite');
        self::assertFalse(Postkarten::sindPartner('p/PK_0001_V.jpg', 'p/PK_0002_R.jpg'), 'anderer Schluessel');
        self::assertFalse(Postkarten::sindPartner('p/PK_0001_V.jpg', 'q/PK_0001_R.jpg'), 'anderer Ordner');
    }

    private static function datei(string $pfad, ?string $mId = null): array
    {
        return [
            'pfad'   => $pfad,
            'datei'  => basename($pfad),
            'format' => strtolower(pathinfo($pfad, PATHINFO_EXTENSION)),
            'm_id'   => $mId,
            'titel'  => '',
        ];
    }

    public function testGruppierenNachNamensmuster(): void
    {
        $karten = Postkarten::gruppieren([
            self::datei('pk/PK_0002_R.jpg'),
            self::datei('pk/PK_0001_V.jpg'),
            self::datei('pk/PK_0001_R.jpg'),
            self::datei('pk/PK_0002_V.jpg'),
            self::datei('pk/liste.pdf'),
        ]);

        self::assertCount(2, $karten);
        self::assertSame('PK_0001', $karten[0]['schluessel']);
        self::assertSame('pk/PK_0001_V.jpg', $karten[0]['vorderseite']['pfad']);
        self::assertSame('pk/PK_0001_R.jpg', $karten[0]['rueckseite']['pfad']);
        self::assertSame('pk/PK_0002_V.jpg', $karten[1]['vorderseite']['pfad']);
        self::assertSame('pk/PK_0002_R.jpg', $karten[1]['rueckseite']['pfad']);
    }

    public function testDateiOhnePartnerIstEinzelkarte(): void
    {
        $karten = Postkarten::gruppieren([
            self::datei('pk/PK_0001_V.jpg'),
            self::datei('pk/sonstiges.jpg'),
        ]);

        self::assertCount(2, $karten);
        self::assertNull($karten[0]['rueckseite']);
        self::assertSame('sonstiges', $karten[1]['schluessel']);
        self::assertNull($karten[1]['rueckseite']);
    }

    public function testRueckseiteOhneVorderseiteWirdGezeigt(): void
    {
        $karten = Postkarten::gruppieren([self::datei('pk/PK_0007_R.jpg')]);

        self::assertCount(1, $karten);
        self::assertSame('pk/PK_0007_R.jpg', $karten[0]['vorderseite']['pfad']);
        self::assertNull($karten[0]['rueckseite']);
    }

    public function testGleicherSchluesselInVerschiedenenOrdnernSindZweiKarten(): void
    {
        $karten = Postkarten::gruppieren([
            self::datei('a/PK_0001_V.jpg'),
            self::datei('b/PK_0001_R.jpg'),
        ]);

        self::assertCount(2, $karten);
    }

    public function testMedienobjektMitZweiDateienIstEinPaar(): void
    {
        $karten = Postkarten::gruppieren([
            self::datei('pk/karte-hinten.jpg', 'M7'),
            self::datei('pk/karte-vorne.jpg', 'M7'),
            self::datei('pk/andere.jpg', 'M8'),
        ]);

        self::assertCount(2, $karten);
        // Im Pfad die erste ist die Vorderseite.
        self::assertSame('pk/karte-hinten.jpg', $karten[1]['vorderseite']['pfad']);
        self::assertSame('pk/karte-vorne.jpg', $karten[1]['rueckseite']['pfad']);
        self::assertNull($karten[0]['rueckseite']);
    }

    public function testNamensmusterGehtVorMedienobjekt(): void
    {
        // Beide Dateien haengen an M1, heissen aber nach dem Muster - das Muster entscheidet.
        $karten = Postkarten::gruppieren([
            self::datei('pk/PK_0001_V.jpg', 'M1'),
            self::datei('pk/PK_0001_R.jpg', 'M1'),
            self::datei('pk/PK_0002_V.jpg', 'M1'),
        ]);

        self::assertCount(2, $karten);
        self::assertSame('pk/PK_0001_R.jpg', $karten[0]['rueckseite']['pfad']);
        self::assertNull($karten[1]['rueckseite']);
    }

    // ---------------------------------------------------------------
    // Beschreibung
    // ---------------------------------------------------------------

    public function testBeschreibungMitAllenAbschnitten(): void
    {
        $text = Postkarten::beschreibung(
            'Marktplatz Lüneburg',
            "Liebe Mutter,\nwir sind gut angekommen.",
            'Stempel unleserlich'
        );

        self::assertSame(
            "Motiv: Marktplatz Lüneburg\nTranskription:\nLiebe Mutter,\nwir sind gut angekommen.\nNotiz: Stempel unleserlich",
            $text
        );
    }

    public function testNurMotivStehtOhneUeberschrift(): void
    {
        self::assertSame('Marktplatz Lüneburg', Postkarten::beschreibung('Marktplatz Lüneburg', '', ''));
        self::assertSame('', Postkarten::beschreibung('', '', ''));
    }

    public function testAbschnitteSindUmkehrbar(): void
    {
        $faelle = [
            ['Marktplatz', "Zeile 1\nZeile 2", 'Notiz'],
            ['Marktplatz', '', 'nur Notiz'],
            ['', 'nur Transkription', ''],
            ['nur Motiv', '', ''],
            ['', '', ''],
        ];

        foreach ($faelle as [$motiv, $transkription, $notiz]) {
            $text = Postkarten::beschreibung($motiv, $transkription, $notiz);

            self::assertSame(
                ['motiv' => $motiv, 'transkription' => $transkription, 'notiz' => $notiz],
                Postkarten::abschnitte($text),
                'Fall: ' . json_encode([$motiv, $transkription, $notiz])
            );
        }
    }

    public function testFreitextOhneUeberschriftenIstDasMotiv(): void
    {
        $abschnitte = Postkarten::abschnitte("Hochzeit in Stuttgart\n1923");

        self::assertSame("Hochzeit in Stuttgart\n1923", $abschnitte['motiv']);
        self::assertSame('', $abschnitte['transkription']);
        self::assertSame('', $abschnitte['notiz']);
    }

    public function testUeberschriftenWerdenOhneRuecksichtAufSchreibweiseErkannt(): void
    {
        $abschnitte = Postkarten::abschnitte("motiv:  Kirche\nTRANSKRIPTION: Gruss aus Kiel\nnotiz : ok");

        self::assertSame('Kirche', $abschnitte['motiv']);
        self::assertSame('Gruss aus Kiel', $abschnitte['transkription']);
        self::assertSame('ok', $abschnitte['notiz']);
    }

    public function testMotivAllein(): void
    {
        self::assertSame('Kirche', Postkarten::motiv("Motiv: Kirche\nTranskription:\nHallo"));
    }
}

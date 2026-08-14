<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Hält die Sprachkataloge und den Quelltext in Deckung.
 *
 * Anlass: die aria-Labels der neuen Blätternavigation (v1.2.4) landeten in
 * keinem Katalog und fielen in allen Sprachen auf den deutschen Quelltext
 * zurück. Derselbe Abgleich fand drei weitere Texte, die seit Längerem nie
 * übersetzt werden konnten, sowie zwei Einträge des in v1.2.1 entfernten
 * Footer-Schalters, die niemand aus den Katalogen genommen hatte.
 */
final class UebersetzungenTest extends TestCase
{
    /** Ein einfach gequoteter PHP-String, optional über `.` verkettet. */
    private const ARGUMENT = "'(?:\\\\.|[^'\\\\])*'(?:\\s*\\.\\s*'(?:\\\\.|[^'\\\\])*')*";

    private static function wurzel(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Alle Zeichenketten, die im Quelltext übersetzt werden sollen.
     *
     * @return array<string, string> Text => Datei, in der er steht
     */
    private static function imQuelltext(): array
    {
        $gefunden = [];

        foreach (self::quelldateien() as $datei) {
            $inhalt = (string) file_get_contents($datei);
            $name   = basename($datei);

            preg_match_all('/I18N::translate\s*\(\s*(' . self::ARGUMENT . ')/s', $inhalt, $einzahl);
            foreach ($einzahl[1] as $roh) {
                $gefunden[self::literal($roh)] = $name;
            }

            // Mehrzahl: Einzahl- und Mehrzahlform stehen beide im Katalog.
            preg_match_all(
                '/I18N::plural\s*\(\s*(' . self::ARGUMENT . ')\s*,\s*(' . self::ARGUMENT . ')/s',
                $inhalt,
                $mehrzahl
            );
            foreach ([$mehrzahl[1], $mehrzahl[2]] as $gruppe) {
                foreach ($gruppe as $roh) {
                    $gefunden[self::literal($roh)] = $name;
                }
            }
        }

        return $gefunden;
    }

    /** @return list<string> */
    private static function quelldateien(): array
    {
        $dateien = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::wurzel() . '/src')
        );

        /** @var SplFileInfo $eintrag */
        foreach ($iterator as $eintrag) {
            if ($eintrag->isFile() && $eintrag->getExtension() === 'php') {
                $dateien[] = $eintrag->getPathname();
            }
        }

        foreach (['/resources/views/*.phtml', '/resources/views/partials/*.phtml'] as $muster) {
            foreach (glob(self::wurzel() . $muster) ?: [] as $datei) {
                $dateien[] = $datei;
            }
        }

        return $dateien;
    }

    /** Setzt einen verketteten PHP-Literal-Ausdruck zum tatsächlichen Text zusammen. */
    private static function literal(string $roh): string
    {
        preg_match_all("/'((?:\\\\.|[^'\\\\])*)'/", $roh, $teile);

        return str_replace(["\\'", '\\\\'], ["'", '\\'], implode('', $teile[1]));
    }

    /**
     * Alle msgid eines Katalogs (inklusive msgid_plural, ohne den Kopfeintrag).
     *
     * @return list<string>
     */
    private static function katalog(string $sprache): array
    {
        $inhalt = (string) file_get_contents(self::wurzel() . '/resources/lang/' . $sprache . '.po');

        $ids     = [];
        $sammelt = false;
        $puffer  = '';

        // Zeilenweise, weil gettext lange Texte auf mehrere Zeilen umbricht:
        //   msgid ""
        //   "erster Teil "
        //   "zweiter Teil"
        // Ein Muster, das nur die erste Zeile liest, sieht dort einen leeren
        // Schluessel. Poedit bricht so um, unsere eigenen Dateien bisher nicht –
        // deshalb fiel es erst an einer beigesteuerten Uebersetzung auf.
        foreach (preg_split('/\R/', str_replace("\r\n", "\n", $inhalt)) ?: [] as $zeile) {
            $zeile = trim($zeile);

            if (preg_match('/^(?:msgid|msgid_plural)\s+"(.*)"$/', $zeile, $treffer) === 1) {
                if ($sammelt && $puffer !== '') {
                    $ids[] = stripcslashes($puffer);
                }
                $sammelt = true;
                $puffer  = $treffer[1];
                continue;
            }

            if ($sammelt && preg_match('/^"(.*)"$/', $zeile, $treffer) === 1) {
                $puffer .= $treffer[1];
                continue;
            }

            if ($sammelt) {
                if ($puffer !== '') {
                    $ids[] = stripcslashes($puffer);
                }
                $sammelt = false;
                $puffer  = '';
            }
        }

        if ($sammelt && $puffer !== '') {
            $ids[] = stripcslashes($puffer);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Aus dem Verzeichnis gelesen, nicht fest verdrahtet: eine neu
     * beigesteuerte Sprache wird damit ohne Teständerung mitgeprüft.
     *
     * @return list<array{string}>
     */
    public static function sprachen(): array
    {
        $sprachen = [];

        foreach (glob(self::wurzel() . '/resources/lang/*.po') ?: [] as $datei) {
            $sprachen[] = [basename($datei, '.po')];
        }

        return $sprachen;
    }

    /**
     * Jeder übersetzbare Text im Quelltext muss im Katalog stehen – sonst kann
     * er in keiner Sprache übersetzt werden und bleibt beim deutschen Original.
     */
    public function testJederTextImQuelltextStehtImKatalog(): void
    {
        $imCode  = self::imQuelltext();
        $fehlend = array_diff(array_keys($imCode), self::katalog('en'));

        $meldung = implode("\n", array_map(
            static fn (string $s): string => sprintf('  [%s] %s', $imCode[$s], $s),
            $fehlend
        ));

        self::assertSame([], array_values($fehlend), "Nicht übersetzbare Texte:\n" . $meldung);
    }

    /**
     * Umgekehrt: Einträge, die kein Aufruf mehr benutzt, sind Karteileichen.
     * Sie sind harmlos, zeigen aber an, dass Katalog und Code auseinanderlaufen.
     */
    public function testKatalogEnthaeltKeineVerwaistenEintraege(): void
    {
        $verwaist = array_diff(self::katalog('en'), array_keys(self::imQuelltext()));

        self::assertSame(
            [],
            array_values($verwaist),
            'Im Katalog, aber nirgends im Quelltext verwendet: ' . implode(' | ', $verwaist)
        );
    }

    /**
     * Alle Sprachen müssen denselben Satz an msgid führen, sonst fehlt einer
     * Sprache still ein Text – genau so ist es der Blätternavigation ergangen.
     *
     * @param string $sprache
     */
    #[DataProvider('sprachen')]
    public function testAlleKatalogeFuehrenDieselbenTexte(string $sprache): void
    {
        $referenz = self::katalog('en');
        $dieser   = self::katalog($sprache);

        sort($referenz);
        sort($dieser);

        self::assertSame($referenz, $dieser, $sprache . '.po weicht von en.po ab.');
    }

    /**
     * `I18N::translate()` reicht seine Nachricht immer durch `sprintf()`
     * (app/I18N.php). Enthält sie einen Platzhalter, aber der Aufruf übergibt
     * kein Argument, wirft PHP „2 arguments are required, 1 given" – ein
     * fataler Fehler, der die ganze Seite abstürzen lässt.
     *
     * Genau so ist v1.2.6 an der Detailansicht gescheitert: zwei Formatvorlagen
     * für JavaScript wurden übersetzt, ihr Platzhalter sollte aber erst im
     * Browser gefüllt werden. Richtig ist, '%s' als Argument mitzugeben, dann
     * setzt sprintf den Platzhalter unverändert wieder ein.
     */
    public function testFormatvorlagenBekommenEinArgument(): void
    {
        $verstoesse = [];

        foreach (self::quelldateien() as $datei) {
            $inhalt = (string) file_get_contents($datei);

            // Nur Aufrufe, bei denen direkt nach dem Text die Klammer schliesst.
            preg_match_all(
                '/I18N::translate\s*\(\s*(' . self::ARGUMENT . ')\s*\)/s',
                $inhalt,
                $treffer
            );

            foreach ($treffer[1] as $roh) {
                $text = self::literal($roh);
                if (preg_match('/%[-+ 0#\']*[\d.]*[bcdeEfFgGosuxX]/', $text) === 1) {
                    $verstoesse[] = basename($datei) . ': ' . $text;
                }
            }
        }

        self::assertSame(
            [],
            $verstoesse,
            "Platzhalter ohne Argument – sprintf() wirft zur Laufzeit:\n  "
                . implode("\n  ", $verstoesse)
        );
    }

    /**
     * Jede Übersetzung muss dieselben Platzhalter führen wie ihr Original.
     *
     * Ein `%` ohne gültige Angabe dahinter – etwa „% Objekte" statt
     * „%s Objekte" – lässt `sprintf()` mit `ValueError: Unknown format
     * specifier` werfen und reißt die ganze Seite mit. Genau das steckte in
     * einer beigesteuerten Übersetzung (sk, 08/2026) an vier Stellen.
     *
     * `msgfmt --check` fängt das NICHT: gettext prüft Formatangaben nur bei
     * Einträgen, die als c-format markiert sind, und diese Markierung fehlt
     * je nach Werkzeug.
     *
     * @param string $sprache
     */
    #[DataProvider('sprachen')]
    public function testPlatzhalterStimmenMitDemOriginalUeberein(string $sprache): void
    {
        $inhalt = self::normalisiert(self::wurzel() . '/resources/lang/' . $sprache . '.po');
        $muster = '/%[-+ 0#\']*[\d.]*[bcdeEfFgGosuxX]/';
        $fehler = [];

        foreach (explode("\n\n", $inhalt) as $block) {
            $felder = [];
            foreach (explode("\n", $block) as $zeile) {
                if (preg_match('/^(msgid_plural|msgid|msgstr(?:\[\d+\])?) "(.*)"$/', trim($zeile), $t) === 1) {
                    $felder[$t[1]] = $t[2];
                }
            }

            if (($felder['msgid'] ?? '') === '') {
                continue;
            }

            $soll = preg_match_all($muster, $felder['msgid']);

            foreach ($felder as $name => $wert) {
                if (!str_starts_with($name, 'msgstr') || $wert === '') {
                    continue;
                }

                $gueltige = preg_match_all($muster, $wert);
                // Jedes %-Zeichen muss zu einer gültigen Angabe gehören
                // (oder als %% verdoppelt sein).
                $alle = preg_match_all('/%/', str_replace('%%', '', $wert));

                if ($gueltige !== $soll || $alle !== $gueltige) {
                    $fehler[] = sprintf(
                        '%s: „%s" → „%s" (erwartet %d, gültig %d, %%-Zeichen %d)',
                        $name,
                        mb_strimwidth($felder['msgid'], 0, 45, '…'),
                        mb_strimwidth($wert, 0, 45, '…'),
                        $soll,
                        $gueltige,
                        $alle
                    );
                }
            }
        }

        self::assertSame(
            [],
            $fehler,
            $sprache . ".po – sprintf() würde zur Laufzeit werfen:\n  " . implode("\n  ", $fehler)
        );
    }

    /** Liest eine .po in kanonischer Form (ein Feld je Zeile, Blöcke getrennt). */
    private static function normalisiert(string $pfad): string
    {
        $befehl = sprintf('msgcat --no-wrap -o - %s 2>/dev/null', escapeshellarg($pfad));

        return (string) shell_exec($befehl);
    }

    /** Zu jeder .po muss eine kompilierte .mo gehören – webtrees liest nur die .mo. */
    #[DataProvider('sprachen')]
    public function testZuJedemKatalogGibtEsEineKompilierteFassung(string $sprache): void
    {
        $po = self::wurzel() . '/resources/lang/' . $sprache . '.po';
        $mo = self::wurzel() . '/resources/lang/' . $sprache . '.mo';

        self::assertFileExists($mo);
        self::assertGreaterThanOrEqual(
            filemtime($po),
            filemtime($mo),
            $sprache . '.mo ist älter als ' . $sprache . '.po – nach dem Bearbeiten neu kompilieren '
                . '(msgfmt -o ' . $sprache . '.mo ' . $sprache . '.po).'
        );
    }
}

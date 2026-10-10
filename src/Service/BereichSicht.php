<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

/**
 * Was ein Betrachter von den Gesichtern eines Bildes sehen darf - nach den
 * Regeln von webtrees, nicht nach dem, was in der Datei steht.
 *
 *  - Person im Baum und sichtbar: Name wie in der Datei, dazu die Kennung
 *    (und fuer die Lightbox die Adresse der Personenseite).
 *  - Person im Baum, aber ihr Name ist fuer diesen Betrachter verborgen
 *    (lebend, vertraulich): weder Name noch Kennung. Sonst verriete der
 *    Rahmen, was webtrees an jeder anderen Stelle verbirgt.
 *  - Name sichtbar, Personenseite nicht: Name ja, Kennung nein.
 *  - Kennung, die es im Baum nicht (mehr) gibt: nur der Name.
 *
 * Verwalter sehen alles - sie sind auch die einzigen, die Bereiche
 * zurueckschreiben, es geht also nichts verloren.
 */
final class BereichSicht
{
    /**
     * @param list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}> $bereiche
     * @return list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string, url?:string}>
     */
    public static function fuer(Tree $tree, array $bereiche, bool $mitUrl = false): array
    {
        $sicht = [];

        foreach ($bereiche as $b) {
            if ($b['xref'] !== null) {
                $person = Registry::individualFactory()->make($b['xref'], $tree);

                if (!$person instanceof Individual) {
                    $b['xref'] = null;
                } elseif (!$person->canShowName()) {
                    $b['name'] = '';
                    $b['xref'] = null;
                } elseif (!$person->canShow()) {
                    $b['xref'] = null;
                } elseif ($mitUrl) {
                    $b['url'] = $person->url();
                }
            }

            $sicht[] = $b;
        }

        return $sicht;
    }
}

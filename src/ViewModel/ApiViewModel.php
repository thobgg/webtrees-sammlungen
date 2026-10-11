<?php

declare(strict_types=1);

namespace Sammlungen\ViewModel;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaPage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Sammlungen\Dto\SammlungDto;
use Sammlungen\Http\RequestHandlers\AdminSammlungEdit;
use Sammlungen\Dto\Symbole;
use Sammlungen\Repository\SammlungenRepository;
use Sammlungen\SammlungenModule;
use Sammlungen\Service\BereichIndex;
use Sammlungen\Service\BereichSicht;
use Sammlungen\Service\CollectionService;
use Sammlungen\Service\ExifService;
use Sammlungen\Service\MedienPfad;
use Sammlungen\Service\Postkarten;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function basename;
use function ceil;
use function count;
use function in_array;
use function max;
use function min;
use function pathinfo;
use function route;
use function str_contains;
use function strip_tags;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * Baut die Antworten der App-Schnittstelle. Die Daten kommen aus derselben
 * Aufbereitung wie die Galerie (SammlungenViewModel::fuerSchnittstelle), hier
 * werden sie nur in eine Form gebracht, die ein Programm liest: feste
 * Schluessel, fertige Adressen fuer Kachel, Vollbild und Original, Listen
 * statt Zuordnungen mit wechselnden Schluesseln.
 *
 * Eintraege sehen in jeder Sammlung gleich aus, ob sie aus einem Ordner,
 * einer Auswahl oder einem Medientyp kommen. Was eine Quelle nicht kennt,
 * bleibt leer, faellt aber nicht weg.
 */
final class ApiViewModel
{
    private const BILD_FORMATE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Breiten wie in der Galerie: Vorschau auf der Uebersicht, Kachel im Raster, Vollbild in der Lightbox. */
    private const VORSCHAU_BREITE = 240;
    private const KACHEL_BREITE   = 400;
    private const VOLLBILD_BREITE = 1600;

    private const SLUG_UNLINKED = '__unlinked__';
    private const SLUG_PERSON   = '__person__';

    public function __construct(
        private readonly SammlungenViewModel  $viewModel,
        private readonly SammlungenRepository $repository,
        private readonly CollectionService    $collectionService,
        private readonly ExifService          $exifService,
        private readonly SammlungenModule     $module,
        private readonly BereichIndex         $bereichIndex,
    ) {}

    // ---------------------------------------------------------------
    // Uebersicht
    // ---------------------------------------------------------------

    /** @return array<string,mixed> */
    public function uebersicht(Tree $tree, bool $alle = false): array
    {
        $daten = $this->viewModel->fuerSchnittstelle($tree, '', '', 1, $this->module->perPage());

        $sammlungen = [];

        foreach ($daten['sammlungen']['manuell'] as $s) {
            $istOrdner = $s->ordner !== null && $s->ordner !== '';

            $sammlungen[] = [
                'slug'         => $s->slug,
                'art'          => $istOrdner ? 'ordner' : 'thematisch',
                'name'         => $s->name,
                'beschreibung' => (string) ($s->beschreibung ?? ''),
                'farbe'        => $s->farbe,
                'icon'         => Symbole::fa($s->icon),
                'ansicht'      => $s->ansicht,
                'ordner'       => $istOrdner ? $s->ordner : null,
                // Stufe 6: Album = nimmt Bilder per "Zu Sammlung" auf; Bildquelle = Ordner, aus dem die
                // Auswahl im Browser Bilder fuer ein Album anbietet.
                'album'        => self::istAlbum($s),
                'bildquelle'   => self::istBildquelle($s),
                'anzahl'       => $istOrdner ? (int) $s->anzahl : $this->collectionService->anzahlPfadeSammlung($tree, $s->id),
                'vorschau'     => $istOrdner
                    ? $this->vorschauAusXrefs($tree, $s->vorschau)
                    : $this->vorschauAusPfaden($tree, $s->id),
            ];
        }

        foreach ($daten['sammlungen']['automatisch'] as $a) {
            $sammlungen[] = [
                'slug'         => $a->slug(),
                'art'          => 'medientyp',
                'name'         => $a->name,
                'beschreibung' => '',
                'farbe'        => null,
                'icon'         => $a->icon(),
                'ansicht'      => 'foto',
                'ordner'       => null,
                'album'        => false,
                'bildquelle'   => false,
                'anzahl'       => $a->anzahl,
                'vorschau'     => $this->vorschauAusXrefs($tree, $a->vorschauXrefs),
            ];
        }

        $unverknuepft = [];

        foreach ($daten['unverknuepft_typen'] as $typ => $anzahl) {
            $typ = (string) $typ;

            $unverknuepft[] = [
                'typ'    => $typ === '' ? '__ohne_typ__' : $typ,
                'name'   => SammlungDto::typBezeichnung($typ),
                'anzahl' => (int) $anzahl,
            ];
        }

        $frei     = $daten['freie_dateien'];
        $jeOrdner = [];

        foreach ($frei['jeOrdner'] as $ordner => $anzahl) {
            $jeOrdner[] = ['ordner' => (string) $ordner, 'anzahl' => (int) $anzahl];
        }

        // Was dieser Nutzer schreiben darf - die App zeigt Knoepfe nur, wo der Server sie annaehme.
        $user          = Auth::user();
        $darfHochladen = Auth::canUploadMedia($tree, $user);

        $istVerwalter = Auth::isManager($tree, $user);

        $antwort = $this->kopf($tree) + [
            'proSeite'      => $this->module->perPage(),
            'galerie'       => route('sammlungen.sammlungen', ['tree' => $tree->name()]),
            'darfHochladen' => $darfHochladen,
            'darfExif'      => Auth::isManager($tree, $user),
            // Stufe 5: Gesichter markieren (schreiben ueber api/exif, Feld bereiche) und
            // Bilder einer Person finden (api/sammlung?person=…).
            'darfBereiche'   => Auth::isManager($tree, $user),
            'personenFilter' => true,
            // Stufe 6: Dateien umbenennen, Bilder einem Album zuordnen, Sammlungen ein-/ausblenden
            // (Verwalter des Baums, wie die Knoepfe in der Galerie) - und Sammlungen anlegen, bearbeiten,
            // loeschen sowie die Einstellungen (Administrator, wie die Verwaltung im Browser).
            'darfOrdnen'       => $istVerwalter,
            'darfVerwalten'    => Auth::isAdmin($user),
            'darfEinstellungen' => Auth::isAdmin($user),
            // Ziele fuer das Hochladen: die Unterordner des Medienordners; der Hauptordner ist immer moeglich.
            'ordnerListe'   => $darfHochladen ? $this->collectionService->verfuegbareOrdner($tree) : [],
            'sammlungen'    => $sammlungen,
            'unverknuepft' => $unverknuepft,
            'frei'         => [
                'gesamt'   => (int) $frei['gesamt'],
                'dateien'  => (int) $frei['dateien'],
                'jeOrdner' => $jeOrdner,
            ],
        ];

        // ?alle=1: auch die ausgeblendeten Sammlungen, mit allen Feldern der Maske und den Auswahllisten.
        if ($alle && $istVerwalter) {
            $antwort['verwaltung'] = $this->verwaltung($tree);
        }

        return $antwort;
    }

    // ---------------------------------------------------------------
    // Verwaltung (Stufe 6)
    // ---------------------------------------------------------------

    /**
     * Alle Sammlungen des Baums, sichtbar oder nicht, mit den Feldern der Maske
     * im Browser - und was man dort auswaehlen kann.
     *
     * @return array<string,mixed>
     */
    public function verwaltung(Tree $tree): array
    {
        $symbole = [];
        foreach (AdminSammlungEdit::ICON_AUSWAHL as $wert => $name) {
            $symbole[] = ['wert' => $wert, 'name' => $name, 'icon' => Symbole::fa($wert)];
        }

        $ansichten = [];
        foreach (self::ansichtNamen() as $wert => $name) {
            $ansichten[] = ['wert' => $wert, 'name' => $name];
        }

        return [
            'sammlungen' => array_map(
                fn (object $s): array => $this->verwaltungsEintrag($tree, $s),
                $this->collectionService->alle($tree)
            ),
            'auswahl' => [
                'symbole'   => $symbole,
                'ansichten' => $ansichten,
                'ordner'    => $this->collectionService->verfuegbareOrdner($tree),
            ],
        ];
    }

    /**
     * Eine Sammlung mit allen Feldern der Maske - die Antwort nach dem Speichern
     * und Ein-/Ausblenden. `symbol` ist der Wert fuers Formular, `icon` die
     * FontAwesome-Klasse wie in der Uebersicht.
     *
     * @return array<string,mixed>
     */
    public function verwaltungsEintrag(Tree $tree, object $s): array
    {
        $istOrdner = $s->ordner !== null && $s->ordner !== '';

        return [
            'slug'         => (string) $s->slug,
            'art'          => $istOrdner ? 'ordner' : 'thematisch',
            'name'         => (string) $s->name,
            'beschreibung' => (string) ($s->beschreibung ?? ''),
            'symbol'       => (string) $s->icon,
            'icon'         => Symbole::fa((string) $s->icon),
            'farbe'        => (string) $s->farbe,
            'ansicht'      => (string) $s->ansicht,
            'ordner'       => $istOrdner ? (string) $s->ordner : '',
            'aktiv'        => (bool) $s->aktiv,
            'reihenfolge'  => (int) $s->reihenfolge,
            'album'        => self::istAlbum($s),
            'bildquelle'   => self::istBildquelle($s),
            'anzahl'       => $istOrdner
                ? $this->collectionService->anzahlDateienImOrdner($tree, (string) $s->ordner)
                : $this->collectionService->anzahlPfadeSammlung($tree, (int) $s->id),
        ];
    }

    /** Nimmt Bilder per "Zu Sammlung" auf: jede Sammlung ohne Ordner (wie die Auswahl im Browser). */
    public static function istAlbum(object $s): bool
    {
        return $s->ordner === null || $s->ordner === '';
    }

    /**
     * Aus dieser Sammlung bietet der Browser Bilder fuer ein Album an: sichtbare
     * Ordner-Sammlung direkt unter dem Medienordner, Fotogalerie oder Raster.
     * Zuordnen laesst sich trotzdem jedes Bild des Medienordners.
     */
    public static function istBildquelle(object $s): bool
    {
        return (bool) $s->aktiv
            && $s->ordner !== null && $s->ordner !== ''
            && !str_contains((string) $s->ordner, '/')
            && in_array($s->ansicht ?? 'foto', ['foto', 'raster'], true);
    }

    /** @return array<string,string> Anzeigetyp => Bezeichnung, wie in der Maske */
    public static function ansichtNamen(): array
    {
        return [
            'foto'     => I18N::translate('Photo gallery'),
            'raster'   => I18N::translate('Photo grid'),
            'gemischt' => I18N::translate('Mixed'),
            'dokument' => I18N::translate('Document list'),
            'postkarte' => I18N::translate('Postcards'),
        ];
    }

    /**
     * Die Felder einer vorhandenen Sammlung in der Form, die SammlungFormular liest.
     *
     * @return array<string,mixed>
     */
    public static function formularFelder(object $s): array
    {
        return [
            'name'         => (string) $s->name,
            'beschreibung' => (string) ($s->beschreibung ?? ''),
            'icon'         => (string) $s->icon,
            'farbe'        => (string) $s->farbe,
            'ansicht'      => (string) $s->ansicht,
            'ordner'       => (string) ($s->ordner ?? ''),
            'aktiv'        => (bool) $s->aktiv,
            'reihenfolge'  => (int) $s->reihenfolge,
        ];
    }

    /** Was auf jeder Antwort steht - fuer Antworten ohne eigene Daten (Loeschen, Einstellungen). */
    public function kopfFuer(Tree $tree): array
    {
        return $this->kopf($tree);
    }

    // ---------------------------------------------------------------
    // Eine Sammlung
    // ---------------------------------------------------------------

    /**
     * @return array<string,mixed>|null  null: keine Sammlung mit diesem Schluessel
     */
    public function sammlung(Tree $tree, string $kategorie, string $typ, int $seite, int $proSeite): ?array
    {
        $proSeite = $proSeite > 0 ? SammlungenModule::normalisierePerPage($proSeite) : $this->module->perPage();
        $seite    = max(1, $seite);

        $daten  = $this->viewModel->fuerSchnittstelle($tree, $kategorie, $typ, $seite, $proSeite);
        $aktive = $daten['aktive'];

        if ($aktive === null) {
            return null;
        }

        return match ($aktive['typ']) {
            'ordner'               => $this->ordner($tree, $aktive),
            'manuell'              => $this->thematisch($tree, $aktive),
            'automatisch'          => $this->medientyp($tree, $aktive['sammlung'], $seite, $proSeite),
            'unverknuepft'         => $this->unverknuepftTypen($tree, $aktive),
            'unverknuepft_galerie' => $this->unverknuepftGalerie($tree, $aktive, $seite, $proSeite),
            default                => null,
        };
    }

    /** @param array<string,mixed> $aktive */
    private function ordner(Tree $tree, array $aktive): array
    {
        $s     = $aktive['sammlung'];
        $slugs = $this->slugsJeId($tree);

        if ($aktive['istPostkarte'] ?? false) {
            // Eine Karte ist ein Eintrag: die Vorderseite mit ihren Feldern,
            // die Rueckseite als eigener Eintrag darunter, dazu die
            // Abschnitte der Beschreibung.
            $eintraege = [];
            foreach ($aktive['karten'] as $karte) {
                $eintrag = $this->pfadEintraege($tree, [$karte['vorderseite']], $slugs)[0];
                $eintrag['rueckseite'] = $karte['rueckseite'] !== null
                    ? $this->pfadEintraege($tree, [$karte['rueckseite']], $slugs)[0]
                    : null;
                $eintrag['motiv']         = $karte['abschnitte']['motiv'];
                $eintrag['transkription'] = $karte['abschnitte']['transkription'];
                $eintrag['notiz']         = $karte['abschnitte']['notiz'];
                $eintraege[] = $eintrag;
            }
        } elseif ($aktive['istBild']) {
            $eintraege = $this->pfadEintraege($tree, $aktive['bilder'], $slugs);

            if ($aktive['istGemischt']) {
                $eintraege = [...$eintraege, ...$this->pfadEintraege($tree, $aktive['dokumente'] ?? [], $slugs)];
            }
        } else {
            $eintraege = $this->pfadEintraege($tree, $aktive['alle'] ?? [], $slugs);
        }

        return $this->kopf($tree) + [
            'slug'         => $s->slug,
            'art'          => 'ordner',
            'name'         => $s->name,
            'beschreibung' => (string) ($s->beschreibung ?? ''),
            'farbe'        => $s->farbe,
            'icon'         => Symbole::fa($s->icon),
            'ansicht'      => $s->ansicht,
            'ordner'       => $s->ordner,
            'anzahl'       => (int) $aktive['anzahl'],
            'dateien'      => (int) ($aktive['datei_anzahl'] ?? $aktive['anzahl']),
            'seite'        => (int) $aktive['seite'],
            'seiten'       => (int) $aktive['seiten_gesamt'],
            'proSeite'     => (int) $aktive['per_seite'],
            'eintraege'    => $eintraege,
            // Video, Audio, Dokumente eines Foto-Ordners: wie in der Galerie
            // als Liste unter den Bildern, nicht seitenweise.
            'weitere'      => $this->pfadEintraege($tree, $aktive['weitere'] ?? [], $slugs),
        ];
    }

    /** @param array<string,mixed> $aktive */
    private function thematisch(Tree $tree, array $aktive): array
    {
        $s      = $aktive['sammlung'];
        $bilder = $aktive['bilder'];

        // Die Galerie zeigt bei einer Auswahl nur die Datei und ihr EXIF. Wo
        // eine Datei auch Medienobjekt ist, kennt webtrees Titel und Personen -
        // eine Abfrage fuer die Seite, und die App kann Namen unter das Bild
        // schreiben.
        $mIds    = array_values(array_filter(array_map(static fn (array $b) => $b['m_id'], $bilder)));
        $wtDaten = $this->collectionService->webtreesDatenFuerMediaIds($tree, $mIds);

        foreach ($bilder as &$bild) {
            $wt = $bild['m_id'] !== null ? ($wtDaten[$bild['m_id']] ?? null) : null;

            if ($wt !== null) {
                $bild['wt']              = $wt;
                $bild['personen_gesamt'] = count($wt['personen']);
                $bild['wt']['personen']  = array_slice($wt['personen'], 0, SammlungenViewModel::MAX_PERSONEN_JE_BILD);
            }
        }
        unset($bild);

        return $this->kopf($tree) + [
            'slug'         => $s->slug,
            'art'          => 'thematisch',
            'name'         => $s->name,
            'beschreibung' => (string) ($s->beschreibung ?? ''),
            'farbe'        => $s->farbe,
            'icon'         => Symbole::fa($s->icon),
            'ansicht'      => $s->ansicht,
            'ordner'       => null,
            'anzahl'       => (int) $aktive['anzahl'],
            'dateien'      => (int) $aktive['anzahl'],
            'seite'        => (int) $aktive['seite'],
            'seiten'       => (int) $aktive['seiten_gesamt'],
            'proSeite'     => (int) $aktive['per_seite'],
            'eintraege'    => $this->pfadEintraege($tree, $bilder, $this->slugsJeId($tree)),
            'weitere'      => [],
        ];
    }

    private function medientyp(Tree $tree, SammlungDto $dto, int $seite, int $proSeite): array
    {
        $seiten = max(1, (int) ceil($dto->anzahl / $proSeite));
        $seite  = min($seite, $seiten);
        $xrefs  = $this->repository->medienInSammlung($tree, $dto->typ, ($seite - 1) * $proSeite, $proSeite);

        return $this->kopf($tree) + [
            'slug'         => $dto->slug(),
            'art'          => 'medientyp',
            'name'         => $dto->name,
            'beschreibung' => '',
            'farbe'        => null,
            'icon'         => $dto->icon(),
            'ansicht'      => 'foto',
            'ordner'       => null,
            'anzahl'       => $dto->anzahl,
            'dateien'      => $dto->anzahl,
            'seite'        => $seite,
            'seiten'       => $seiten,
            'proSeite'     => $proSeite,
            'eintraege'    => $this->xrefEintraege($tree, $xrefs),
            'weitere'      => [],
        ];
    }

    /** @param array<string,mixed> $aktive */
    private function unverknuepftTypen(Tree $tree, array $aktive): array
    {
        $typen = [];

        foreach ($aktive['typen'] as $typ => $anzahl) {
            $typ = (string) $typ;

            $typen[] = [
                'typ'    => $typ === '' ? '__ohne_typ__' : $typ,
                'name'   => SammlungDto::typBezeichnung($typ),
                'anzahl' => (int) $anzahl,
            ];
        }

        return $this->kopf($tree) + [
            'slug'         => self::SLUG_UNLINKED,
            'art'          => 'unverknuepft',
            'name'         => '',
            'beschreibung' => '',
            'farbe'        => null,
            'icon'         => 'fa-unlink',
            'ansicht'      => 'foto',
            'ordner'       => null,
            'anzahl'       => (int) $aktive['gesamt'],
            'dateien'      => (int) $aktive['gesamt'],
            'seite'        => 1,
            'seiten'       => 1,
            'proSeite'     => 0,
            'typen'        => $typen,
            'eintraege'    => [],
            'weitere'      => [],
        ];
    }

    /** @param array<string,mixed> $aktive */
    private function unverknuepftGalerie(Tree $tree, array $aktive, int $seite, int $proSeite): array
    {
        $anzahl = (int) $aktive['anzahl'];
        $seiten = max(1, (int) ceil($anzahl / $proSeite));
        $seite  = min($seite, $seiten);
        $xrefs  = $this->repository->medienOhneVerknuepfung($tree, (string) $aktive['typ_key'], ($seite - 1) * $proSeite, $proSeite);

        return $this->kopf($tree) + [
            'slug'         => self::SLUG_UNLINKED,
            'art'          => 'unverknuepft',
            'typ'          => $aktive['typ_key'] === '' ? '__ohne_typ__' : $aktive['typ_key'],
            'name'         => (string) $aktive['typ_name'],
            'beschreibung' => '',
            'farbe'        => null,
            'icon'         => 'fa-unlink',
            'ansicht'      => 'foto',
            'ordner'       => null,
            'anzahl'       => $anzahl,
            'dateien'      => $anzahl,
            'seite'        => $seite,
            'seiten'       => $seiten,
            'proSeite'     => $proSeite,
            'eintraege'    => $this->xrefEintraege($tree, $xrefs),
            'weitere'      => [],
        ];
    }

    // ---------------------------------------------------------------
    // Bilder einer Person
    // ---------------------------------------------------------------

    /**
     * Alle Bilder des Archivs, auf denen diese Person als Gesicht markiert
     * ist (sammlungen:Xref in den Bereichen) - mit oder ohne Medienobjekt,
     * seitenweise wie eine Sammlung. Fuer "Auf Gruppenbildern" im
     * Personenblatt der Apps.
     *
     * @return array<string,mixed>|null  null: keine Person, die dieser Nutzer sehen darf
     */
    public function person(Tree $tree, string $xref, int $seite, int $proSeite): ?array
    {
        $person = Registry::individualFactory()->make($xref, $tree);

        if (!$person instanceof Individual || !$person->canShow()) {
            return null;
        }

        $vollstaendig = $this->bereichIndex->auffrischen($tree);

        $proSeite = $proSeite > 0 ? SammlungenModule::normalisierePerPage($proSeite) : $this->module->perPage();
        $pfade    = $this->bereichIndex->pfadeMitPerson($tree, $person->xref());
        $anzahl   = count($pfade);
        $seiten   = max(1, (int) ceil($anzahl / $proSeite));
        $seite    = min(max(1, $seite), $seiten);

        $eintraege = array_map(
            fn (string $pfad): array => $this->eintrag($tree, $pfad),
            array_slice($pfade, ($seite - 1) * $proSeite, $proSeite)
        );

        return $this->kopf($tree) + [
            'slug'         => self::SLUG_PERSON,
            'art'          => 'person',
            'person'       => $person->xref(),
            'name'         => strip_tags($person->fullName()),
            'beschreibung' => '',
            'farbe'        => null,
            'icon'         => 'fa-user',
            'ansicht'      => 'foto',
            'ordner'       => null,
            'anzahl'       => $anzahl,
            'dateien'      => $anzahl,
            'seite'        => $seite,
            'seiten'       => $seiten,
            'proSeite'     => $proSeite,
            'eintraege'    => $eintraege,
            'weitere'      => [],
            // false: der Index war beim ersten Durchlauf eines grossen Archivs
            // noch nicht fertig - spaeter noch einmal fragen.
            'vollstaendig' => $vollstaendig,
        ];
    }

    // ---------------------------------------------------------------
    // Eintraege
    // ---------------------------------------------------------------

    /**
     * Ein einzelner Eintrag fuer eine Datei des Medienordners - die Antwort nach dem Hochladen oder Schreiben,
     * in derselben Form wie in einer Sammlung, damit die App ihn direkt einreihen kann.
     *
     * @return array<string,mixed>
     */
    public function eintrag(Tree $tree, string $pfad): array
    {
        $mId = DB::table('media_file')
            ->where('m_file', '=', $tree->id())
            ->where('multimedia_file_refn', '=', $pfad)
            ->value('m_id');
        $mId = $mId !== null ? (string) $mId : null;

        $datei = [
            'pfad'   => $pfad,
            'datei'  => basename($pfad),
            'format' => strtolower(pathinfo($pfad, PATHINFO_EXTENSION)),
            'm_id'   => $mId,
            'titel'  => '',
            'exif'   => $this->exifService->leseMeta(MedienPfad::wurzel($tree) . $pfad),
            'in_sammlungen' => $this->collectionService->sammlungenFuerPfade($tree, [$pfad])[$pfad] ?? [],
        ];

        if ($mId !== null) {
            $wt = $this->collectionService->webtreesDatenFuerMediaIds($tree, [$mId])[$mId] ?? null;

            if ($wt !== null) {
                $datei['personen_gesamt'] = count($wt['personen']);
                $wt['personen']           = array_slice($wt['personen'], 0, SammlungenViewModel::MAX_PERSONEN_JE_BILD);
                $datei['wt']              = $wt;
            }
        }

        return $this->pfadEintraege($tree, [$datei], $this->slugsJeId($tree))[0];
    }

    /**
     * Ein Eintrag einer Postkarte nach dem Schreiben: wie eintrag(), dazu die
     * Abschnitte der Beschreibung und die andere Seite (`gegenseite`, Eintrag
     * oder null). `gegenseite` statt `rueckseite`, weil die App auch die
     * Rueckseite geschickt haben kann.
     *
     * @return array<string,mixed>
     */
    public function postkartenEintrag(Tree $tree, string $pfad, ?string $partner): array
    {
        $eintrag = $this->eintrag($tree, $pfad);
        $teile   = Postkarten::abschnitte((string) $eintrag['beschreibung']);

        $eintrag['motiv']         = $teile['motiv'];
        $eintrag['transkription'] = $teile['transkription'];
        $eintrag['notiz']         = $teile['notiz'];
        $eintrag['gegenseite']    = $partner !== null ? $this->eintrag($tree, $partner) : null;

        return $eintrag;
    }

    /**
     * Eintraege aus Dateien des Medienordners - mit oder ohne Medienobjekt.
     * Nimmt die angereicherten Bilder der Galerie ebenso wie die schlichten
     * Dateilisten (Dokumente, "weitere").
     *
     * @param list<array<string,mixed>> $dateien
     * @param array<int,string>         $slugsJeId
     * @return list<array<string,mixed>>
     */
    private function pfadEintraege(Tree $tree, array $dateien, array $slugsJeId): array
    {
        $eintraege = [];

        foreach ($dateien as $d) {
            $exif     = $d['exif'] ?? null;
            $wt       = $d['wt'] ?? null;
            $format   = strtolower((string) $d['format']);
            $istBild  = in_array($format, self::BILD_FORMATE, true);
            $personen = $wt['personen'] ?? [];
            $titel    = (string) ($wt['titel'] ?? $d['titel'] ?? '');

            $eintraege[] = [
                'pfad'            => $d['pfad'],
                'datei'           => $d['datei'] ?? basename($d['pfad']),
                'format'          => $format,
                'istBild'         => $istBild,
                'xref'            => $d['m_id'],
                'titel'           => $titel,
                'notiz'           => (string) ($wt['notiz'] ?? ''),
                'beschreibung'    => (string) ($exif['beschreibung'] ?? ''),
                'bildunterschrift' => $this->bildunterschrift((string) ($exif['beschreibung'] ?? ''), $titel, $d['datei'] ?? basename($d['pfad'])),
                'datum'           => (string) ($exif['datum'] ?? ''),
                'datumIso'        => (string) ($exif['datum_iso'] ?? ''),
                'datumUnsicher'   => (bool) ($exif['datum_unsicher'] ?? false),
                'exifPersonen'    => $exif['personen'] ?? [],
                'keywords'        => $exif['keywords'] ?? [],
                'bereiche'        => BereichSicht::fuer($tree, $exif['bereiche'] ?? []),
                'breite'          => (int) ($exif['breite'] ?? 0),
                'hoehe'           => (int) ($exif['hoehe'] ?? 0),
                'groesseKb'       => (int) ($exif['groesse_kb'] ?? 0),
                'personen'        => $personen,
                'personenGesamt'  => (int) ($d['personen_gesamt'] ?? count($personen)),
                'inSammlungen'    => array_values(array_filter(array_map(
                    static fn (int $id) => $slugsJeId[$id] ?? null,
                    $d['in_sammlungen'] ?? []
                ))),
                'kachel'          => $istBild ? $this->dateiUrl($tree, $d['pfad'], self::KACHEL_BREITE) : null,
                'vollbild'        => $istBild ? $this->dateiUrl($tree, $d['pfad'], self::VOLLBILD_BREITE) : null,
                'original'        => $this->dateiUrl($tree, $d['pfad'], null),
                'seite'           => $d['m_id'] !== null ? $this->medienSeite($tree, (string) $d['m_id']) : null,
            ];
        }

        return $eintraege;
    }

    /**
     * Eintraege aus Medienobjekten (Sammlungen nach Medientyp, nicht
     * eingebundene Medien). Bilder liefert webtrees selbst aus - mit seinen
     * Datenschutzregeln und Wasserzeichen.
     *
     * @param list<string> $xrefs
     * @return list<array<string,mixed>>
     */
    private function xrefEintraege(Tree $tree, array $xrefs): array
    {
        $wtDaten   = $this->collectionService->webtreesDatenFuerMediaIds($tree, $xrefs);
        $wurzel    = MedienPfad::wurzel($tree);
        $eintraege = [];

        foreach ($xrefs as $xref) {
            $media = Registry::mediaFactory()->make($xref, $tree);

            if (!$media instanceof Media || !$media->canShow()) {
                continue;
            }

            $datei = $media->firstImageFile() ?? $media->mediaFiles()->first();

            if (!$datei instanceof MediaFile) {
                continue;
            }

            $extern   = $datei->isExternal();
            $pfad     = $extern ? null : $datei->filename();
            $format   = $pfad !== null ? strtolower(pathinfo($pfad, PATHINFO_EXTENSION)) : '';
            $istBild  = $datei->isImage();
            $exif     = $pfad !== null && $istBild ? $this->exifService->leseMeta($wurzel . $pfad) : null;
            $wt       = $wtDaten[$xref] ?? null;
            $personen = array_slice($wt['personen'] ?? [], 0, SammlungenViewModel::MAX_PERSONEN_JE_BILD);
            $titel    = $datei->title() !== '' ? $datei->title() : (string) ($wt['titel'] ?? '');
            $name     = $pfad !== null ? basename($pfad) : $datei->filename();

            $eintraege[] = [
                'pfad'            => $pfad,
                'datei'           => $name,
                'format'          => $format,
                'istBild'         => $istBild,
                'xref'            => $xref,
                'titel'           => $titel,
                'notiz'           => (string) ($wt['notiz'] ?? ''),
                'beschreibung'    => (string) ($exif['beschreibung'] ?? ''),
                'bildunterschrift' => $this->bildunterschrift((string) ($exif['beschreibung'] ?? ''), $titel, $name),
                'datum'           => (string) ($exif['datum'] ?? ''),
                'datumIso'        => (string) ($exif['datum_iso'] ?? ''),
                'datumUnsicher'   => (bool) ($exif['datum_unsicher'] ?? false),
                'exifPersonen'    => $exif['personen'] ?? [],
                'keywords'        => $exif['keywords'] ?? [],
                'bereiche'        => BereichSicht::fuer($tree, $exif['bereiche'] ?? []),
                'breite'          => (int) ($exif['breite'] ?? 0),
                'hoehe'           => (int) ($exif['hoehe'] ?? 0),
                'groesseKb'       => (int) ($exif['groesse_kb'] ?? 0),
                'personen'        => $personen,
                'personenGesamt'  => count($wt['personen'] ?? []),
                'inSammlungen'    => [],
                'kachel'          => $istBild ? $datei->imageUrl(self::KACHEL_BREITE, self::KACHEL_BREITE, 'contain') : null,
                'vollbild'        => $istBild ? $datei->imageUrl(self::VOLLBILD_BREITE, self::VOLLBILD_BREITE, 'contain') : null,
                'original'        => $extern ? $datei->filename() : $datei->downloadUrl('inline'),
                'seite'           => $media->url(),
            ];
        }

        return $eintraege;
    }

    // ---------------------------------------------------------------
    // Hilfen
    // ---------------------------------------------------------------

    /** Was auf jeder Antwort steht: Stufe der Schnittstelle, Modulversion, Baum. */
    private function kopf(Tree $tree): array
    {
        return [
            'api'   => SammlungenModule::API_VERSION,
            'modul' => $this->module->customModuleVersion(),
            'baum'  => $tree->name(),
        ];
    }

    /** Wie die Galerie: die EXIF-Beschreibung, sonst der webtrees-Titel, sonst der Dateiname. */
    private function bildunterschrift(string $beschreibung, string $titel, string $datei): string
    {
        if ($beschreibung !== '') {
            return $beschreibung;
        }

        return $titel !== '' ? $titel : $datei;
    }

    /** Adresse einer Datei des Medienordners, auf Wunsch verkleinert. */
    private function dateiUrl(Tree $tree, string $pfad, ?int $breite): string
    {
        $params = ['tree' => $tree->name(), 'pfad' => $pfad];

        if ($breite !== null) {
            $params['w'] = $breite;
        }

        return route('sammlungen.media-datei', $params);
    }

    private function medienSeite(Tree $tree, string $xref): string
    {
        return route(MediaPage::class, ['tree' => $tree->name(), 'xref' => $xref]);
    }

    /**
     * Vorschaubilder aus Medienobjekten - nur, was der Nutzer sehen darf.
     *
     * @param list<string> $xrefs
     * @return list<string>
     */
    private function vorschauAusXrefs(Tree $tree, array $xrefs): array
    {
        $urls = [];

        foreach (array_slice($xrefs, 0, 3) as $xref) {
            $media = Registry::mediaFactory()->make($xref, $tree);
            $datei = $media instanceof Media && $media->canShow() ? $media->firstImageFile() : null;

            if ($datei instanceof MediaFile) {
                $urls[] = $datei->imageUrl(self::VORSCHAU_BREITE, self::VORSCHAU_BREITE, 'crop');
            }
        }

        return $urls;
    }

    /**
     * Vorschaubilder einer Auswahl: die ersten Bilddateien ihrer Pfade. Die
     * Galerie zeigt dort keine; die App hat auf der Uebersicht sonst eine
     * leere Karte.
     *
     * @return list<string>
     */
    private function vorschauAusPfaden(Tree $tree, int $collectionId): array
    {
        $urls = [];

        foreach ($this->collectionService->pfadeDerSammlung($tree, $collectionId, 0, 12) as $eintrag) {
            $format = strtolower(pathinfo($eintrag['pfad'], PATHINFO_EXTENSION));

            if (in_array($format, self::BILD_FORMATE, true)) {
                $urls[] = $this->dateiUrl($tree, $eintrag['pfad'], self::VORSCHAU_BREITE);
            }

            if (count($urls) === 3) {
                break;
            }
        }

        return $urls;
    }

    /**
     * Sammlungs-Ids -> Slugs, damit ein Eintrag sagen kann, in welchen
     * Sammlungen er sonst noch liegt, ohne Datenbank-Ids preiszugeben.
     *
     * @return array<int,string>
     */
    private function slugsJeId(Tree $tree): array
    {
        $map = [];

        foreach ($this->collectionService->aktive($tree) as $s) {
            $map[$s->id] = $s->slug;
        }

        return $map;
    }
}

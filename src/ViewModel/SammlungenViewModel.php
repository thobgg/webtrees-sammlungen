<?php

declare(strict_types=1);

namespace Sammlungen\ViewModel;

use Sammlungen\Dto\SammlungDto;
use Sammlungen\Repository\SammlungenRepository;
use Sammlungen\Service\CollectionService;
use Sammlungen\Service\ExifService;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Webtrees;

/**
 * Baut das Daten-Array fuer den sammlungen-View aus mehreren Quellen
 * (Repository, CollectionService, ExifService) zusammen.
 *
 * Trennt Daten-Aufbereitung vom Request-Handler.
 */
final class SammlungenViewModel
{
    private const SLUG_UNLINKED = '__unlinked__';
    private const PER_SEITE_FOTO     = 48;
    private const PER_SEITE_DOKUMENT = 50;

    public function __construct(
        private readonly SammlungenRepository $sammlungenRepository,
        private readonly CollectionService    $collectionService,
        private readonly ExifService          $exifService,
    ) {}

    /**
     * Hauptmethode: liefert das komplette $data-Array fuer viewResponse().
     *
     * @param array<string,mixed> $queryParams
     * @return array<string,mixed>
     */
    public function aufbauen(?Tree $tree, array $queryParams): array
    {
        $kategorie = trim((string) ($queryParams['kategorie'] ?? ''));
        $typParam  = trim((string) ($queryParams['typ'] ?? ''));

        if ($tree === null) {
            return $this->leereDaten($kategorie);
        }

        // (a) Automatische Sammlungen aus media_file
        $mediaSammlungen = $this->sammlungenRepository->alleSammlungen($tree);

        // (b) Manuell verwaltete Sammlungen + Ordner-Statistiken
        $manuell = $this->manuelleAnreichern($tree, $this->collectionService->aktive($tree));

        // (c) Automatische filtern: keine Duplikate zu manuellen Slugs
        $manuelleSlugs = array_map(static fn (object $c) => $c->slug, $manuell);
        $automatisch   = array_values(array_filter(
            $mediaSammlungen,
            static fn (SammlungDto $s) => !in_array($s->typ, $manuelleSlugs, true)
        ));

        // (d) Nicht-eingebundene Medien (fuer Banner auf Uebersicht)
        $unverknuepftTypen = $this->sammlungenRepository->anzahlNachTypOhneVerknuepfung($tree);

        // Aktive Sammlung bestimmen
        $aktive = $this->aktiveBestimmen($tree, $kategorie, $typParam, $manuell, $automatisch, $unverknuepftTypen);

        // Galerie-Daten fuer manuelle Sammlung (kein Ordner) laden
        if ($aktive !== null && $aktive['typ'] === 'manuell') {
            $aktive = $this->manuelleGalerieAnreichern($tree, $aktive, $queryParams);
        }

        // Galerie-Daten fuer Ordner-basierte Sammlung laden
        if ($aktive !== null && $aktive['typ'] === 'ordner') {
            $aktive = $this->ordnerGalerieAnreichern($tree, $aktive, $queryParams);
        }

        // Manuelle Fotogalerien (kein Ordner, Ansicht=foto) fuer "+ Zu Sammlung"-Button
        $manuelleGalerien = array_values(array_filter(
            $this->collectionService->aktive($tree),
            static fn (object $s) => ($s->ordner === null || $s->ordner === '') && $s->ansicht === 'foto'
        ));

        return [
            'title'              => I18N::translate('Sammlungen'),
            'tree'               => $tree,
            'kategorie'          => $kategorie,
            'sammlungen'         => [
                'manuell'     => $manuell,
                'automatisch' => $automatisch,
            ],
            'aktive'             => $aktive,
            'unverknuepft_typen' => $unverknuepftTypen,
            'istAdmin'           => Auth::isAdmin(),
            'mediaDateiRoute'    => 'sammlungen.media-datei',
            'manuelleGalerien'   => $manuelleGalerien,
            'toggleRoute'        => route('sammlungen.sammlung-medium', ['tree' => $tree->name()]),
        ];
    }

    /** @return array<string,mixed> */
    private function leereDaten(string $kategorie): array
    {
        return [
            'title'              => I18N::translate('Sammlungen'),
            'tree'               => null,
            'kategorie'          => $kategorie,
            'sammlungen'         => [],
            'aktive'             => null,
            'unverknuepft_typen' => [],
            'istAdmin'           => false,
            'mediaDateiRoute'    => 'sammlungen.media-datei',
            'manuelleGalerien'   => [],
            'toggleRoute'        => '',
        ];
    }

    /**
     * Ordner-Sammlungen: anzahl + vorschau aus dem Dateisystem ergaenzen.
     *
     * @param list<object> $manuell
     * @return list<object>
     */
    private function manuelleAnreichern(Tree $tree, array $manuell): array
    {
        return array_map(function (object $s) use ($tree): object {
            if ($s->ordner !== null && $s->ordner !== '') {
                $s->anzahl   = $this->collectionService->anzahlDateienImOrdner($tree, $s->ordner);
                $s->vorschau = $this->collectionService->vorschauInOrdner($tree, $s->ordner, 3);
            } else {
                $s->anzahl   = null;
                $s->vorschau = [];
            }
            return $s;
        }, $manuell);
    }

    /**
     * Entscheidet anhand von $kategorie/$typParam, welche Detailansicht aktiv ist.
     *
     * @param list<object>       $manuell
     * @param list<SammlungDto>  $automatisch
     * @param array<string,int>  $unverknuepftTypen
     * @return array<string,mixed>|null
     */
    private function aktiveBestimmen(
        Tree   $tree,
        string $kategorie,
        string $typParam,
        array  $manuell,
        array  $automatisch,
        array  $unverknuepftTypen
    ): ?array {
        if ($kategorie === self::SLUG_UNLINKED) {
            return $this->aktiveUnverknuepft($tree, $typParam, $unverknuepftTypen);
        }

        if ($kategorie === '') {
            return null;
        }

        // Erst in manuellen Sammlungen suchen
        foreach ($manuell as $m) {
            if ($m->slug === $kategorie) {
                $typStr = ($m->ordner !== null && $m->ordner !== '') ? 'ordner' : 'manuell';
                return ['typ' => $typStr, 'sammlung' => $m];
            }
        }

        // Dann in automatischen
        foreach ($automatisch as $a) {
            if ($a->slug() === $kategorie) {
                return ['typ' => 'automatisch', 'sammlung' => $a];
            }
        }

        return null;
    }

    /**
     * Spezialfall: nicht-eingebundene Medien (Sonder-Slug __unlinked__).
     *
     * @param array<string,int> $unverknuepftTypen
     * @return array<string,mixed>
     */
    private function aktiveUnverknuepft(Tree $tree, string $typParam, array $unverknuepftTypen): array
    {
        // '__ohne_typ__' ist der Sentinel fuer Medien ohne Typangabe (leerer String)
        $typFuerQuery = $typParam === '__ohne_typ__' ? '' : $typParam;

        if ($typParam !== '') {
            // Galerie-Ansicht eines bestimmten Medientyps
            return [
                'typ'      => 'unverknuepft_galerie',
                'typ_key'  => $typFuerQuery,
                'typ_name' => $typFuerQuery !== ''
                    ? (SammlungDto::TYPEN[strtolower($typFuerQuery)] ?? ucfirst($typFuerQuery))
                    : I18N::translate('Ohne Typ'),
                'anzahl'   => $unverknuepftTypen[$typFuerQuery] ?? 0,
                'medien'   => $this->sammlungenRepository->medienOhneVerknuepfung($tree, $typFuerQuery, 0, 48),
                'vorschau' => $this->sammlungenRepository->queryVorschauOhneVerknuepfung($tree, $typFuerQuery, 3),
            ];
        }

        // Typ-Uebersicht
        return [
            'typ'      => 'unverknuepft',
            'typen'    => $unverknuepftTypen,
            'gesamt'   => array_sum($unverknuepftTypen),
            'vorschau' => $this->sammlungenRepository->queryVorschauOhneVerknuepfung($tree, '', 3),
        ];
    }

    /**
     * Laedt Fotos einer manuellen Sammlung (pfad-basiert, ohne Ordner).
     *
     * @param array<string,mixed> $aktive
     * @param array<string,mixed> $queryParams
     * @return array<string,mixed>
     */
    private function manuelleGalerieAnreichern(Tree $tree, array $aktive, array $queryParams): array
    {
        $s        = $aktive['sammlung'];
        $perSeite = self::PER_SEITE_FOTO;
        $seite    = max(1, (int) ($queryParams['seite'] ?? 1));
        $gesamt   = $this->collectionService->anzahlPfadeSammlung($tree, $s->id);
        $seiten   = max(1, (int) ceil($gesamt / $perSeite));
        $seite    = min($seite, $seiten);
        $pfade    = $this->collectionService->pfadeDerSammlung($tree, $s->id, ($seite - 1) * $perSeite, $perSeite);

        $mediaBase = Webtrees::DATA_DIR . $tree->getPreference('MEDIA_DIRECTORY', 'media/');

        $bilder = [];
        foreach ($pfade as $eintrag) {
            $bilder[] = [
                'pfad'          => $eintrag['pfad'],
                'datei'         => basename($eintrag['pfad']),
                'format'        => strtolower(pathinfo($eintrag['pfad'], PATHINFO_EXTENSION)),
                'm_id'          => $eintrag['m_id'],
                'titel'         => '',
                'exif'          => $this->exifService->leseMeta($mediaBase . $eintrag['pfad']),
                'personen'      => [],
                'wt'            => null,
                'in_sammlungen' => [],
            ];
        }

        return [
            ...$aktive,
            'bilder'        => $bilder,
            'istBild'       => true,
            'istRaster'     => false,
            'istGemischt'   => false,
            'anzahl'        => $gesamt,
            'seite'         => $seite,
            'seiten_gesamt' => $seiten,
            'per_seite'     => $perSeite,
        ];
    }

    /**
     * Laedt Galerie/Liste einer ordner-basierten Sammlung inkl. EXIF + webtrees-Daten.
     *
     * @param array<string,mixed> $aktive
     * @param array<string,mixed> $queryParams
     * @return array<string,mixed>
     */
    private function ordnerGalerieAnreichern(Tree $tree, array $aktive, array $queryParams): array
    {
        $s           = $aktive['sammlung'];
        $ansicht     = $s->ansicht ?? 'foto';
        $istBild     = in_array($ansicht, ['foto', 'raster', 'gemischt'], true);
        $istRaster   = $ansicht === 'raster';
        $istGemischt = $ansicht === 'gemischt';
        $perSeite    = $istBild ? self::PER_SEITE_FOTO : self::PER_SEITE_DOKUMENT;
        $seite       = max(1, (int) ($queryParams['seite'] ?? 1));

        $bildFormate = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        // Dateisystem-Scan fuer ALLE Dateien (schnell, kein Imagick)
        $alleDateien   = $this->collectionService->alleDateienInOrdner($tree, $s->ordner);
        $gesamtDateien = count($alleDateien);

        // Nur die in dieser Ansicht tatsaechlich darstellbaren Dateien zaehlen/paginieren,
        // damit der Zaehler ehrlich ist (sonst werden z. B. Video/Audio als "Fotos" mitgezaehlt).
        if ($ansicht === 'dokument') {
            $anzeige = array_values(array_filter($alleDateien, static fn ($d) => !in_array($d['format'], $bildFormate, true)));
        } elseif ($istGemischt) {
            $anzeige = $alleDateien;
        } else { // foto, raster
            $anzeige = array_values(array_filter($alleDateien, static fn ($d) => in_array($d['format'], $bildFormate, true)));
        }

        $gesamtAnzahl = count($anzeige);
        $seitenGesamt = max(1, (int) ceil($gesamtAnzahl / $perSeite));
        $seite        = min($seite, $seitenGesamt);
        $seiteDateien = array_slice($anzeige, ($seite - 1) * $perSeite, $perSeite);

        $aktive['anzahl']        = $gesamtAnzahl;      // in dieser Ansicht angezeigte Objekte
        $aktive['datei_anzahl']  = $gesamtDateien;     // alle Dateien im Ordner (inkl. Video/Audio/...)
        $aktive['istBild']       = $istBild;
        $aktive['istRaster']     = $istRaster;
        $aktive['istGemischt']   = $istGemischt;
        $aktive['seite']         = $seite;
        $aktive['seiten_gesamt'] = $seitenGesamt;
        $aktive['per_seite']     = $perSeite;

        if (!$istBild) {
            $aktive['alle'] = $seiteDateien;
            return $aktive;
        }

        // Bilder dieser Seite filtern und mit EXIF + webtrees-Daten anreichern
        $bilder = array_values(array_filter(
            $seiteDateien,
            static fn ($d) => in_array($d['format'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
        ));

        $mediaBase = Webtrees::DATA_DIR . $tree->getPreference('MEDIA_DIRECTORY', 'media/');
        foreach ($bilder as &$bild) {
            $bild['exif'] = $this->exifService->leseMeta($mediaBase . $bild['pfad']);
        }
        unset($bild);

        // Batch-Query fuer webtrees-Daten
        $mIds    = array_values(array_filter(array_column($bilder, 'm_id')));
        $wtDaten = $this->collectionService->webtreesDatenFuerMediaIds($tree, $mIds);

        foreach ($bilder as &$bild) {
            $wt = $bild['m_id'] !== null ? ($wtDaten[$bild['m_id']] ?? null) : null;
            $bild['personen']      = $wt !== null ? array_column($wt['personen'], 'name') : [];
            $bild['wt']            = $wt;
            $bild['in_sammlungen'] = $this->collectionService->sammlungenDesPfades($tree, $bild['pfad']);
        }
        unset($bild);

        $aktive['bilder'] = $bilder;

        // Gemischt: zusaetzlich Nicht-Bilder als Dokumentenliste
        if ($istGemischt) {
            $aktive['dokumente'] = array_values(array_filter(
                $seiteDateien,
                static fn ($d) => !in_array($d['format'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            ));
        } else {
            // Foto/Raster: Nicht-Bilder (Video/Audio/Dokumente) als abspielbare Liste
            // unter der Galerie zeigen, statt sie nur zu zaehlen.
            $aktive['weitere'] = array_values(array_filter(
                $alleDateien,
                static fn ($d) => !in_array($d['format'], $bildFormate, true)
            ));
        }

        return $aktive;
    }
}

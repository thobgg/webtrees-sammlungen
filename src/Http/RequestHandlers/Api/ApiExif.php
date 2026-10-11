<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sammlungen\Service\ArchivAblage;
use Sammlungen\Service\BereichIndex;
use Sammlungen\Service\Bereiche;
use Sammlungen\Service\CollectionService;
use Sammlungen\Service\ExifService;
use Sammlungen\Service\MedienPfad;
use Sammlungen\Service\Postkarten;
use Sammlungen\ViewModel\ApiViewModel;

use function array_key_exists;
use function basename;
use function count;
use function dirname;
use function in_array;
use function is_file;
use function pathinfo;
use function strip_tags;
use function strtolower;
use function trim;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * POST /tree/{tree}/archiv/api/exif
 *
 *   pfad            Datei im Medienordner (relativ)
 *   beschreibung, datum (YYYY, YYYY-MM oder YYYY-MM-DD), personen, keywords (je durch Komma getrennt)
 *   datum_unsicher  1/0 (Postkarten: Datum geschlossen, nicht abgelesen)
 *   bereiche        JSON-Liste der Gesichter, links oben normiert (Stufe 5, siehe Bereiche)
 *
 * Postkarten (Stufe 6; Datei im Ordner einer Postkarten-Sammlung):
 *   motiv, transkription, notiz   je ein Abschnitt der Beschreibung; was fehlt, bleibt
 *   beschreibung    ohne Ueberschriften: das Motiv (Transkription und Notiz bleiben);
 *                   mit Ueberschriften "Motiv:/Transkription:/Notiz:": alle drei
 *   partner         die andere Seite, wenn sie nicht PK_…_V/_R heisst; leer: nur diese Seite
 * Was die Karte beschreibt, kommt in beide Seiten, mit Kartenschluessel (dc:identifier) und Gegenseite
 * (dc:relation) - wie die Lightbox. Gesichter (`bereiche`) nur in die eine Datei. Die Antwort traegt dann
 * `beide`, im Eintrag `motiv`, `transkription`, `notiz` und `gegenseite`. Abschnitte oder `partner` an
 * einem Bild, das keine Postkarte ist: `not-postcard`; eine Gegenseite, die nicht passt: `bad-partner`.
 *
 * Schreibt die Felder als XMP in die Bilddatei - dieselbe Arbeit wie die Seitenleiste der Lightbox, mit
 * derselben Regel: nur Verwalter des Baums. Vor dem Schreiben sichert der EXIF-Dienst die Datei (einmal am Tag).
 *
 * Seit Stufe 5 gilt: Was nicht mitgeschickt wird, bleibt in der Datei, wie es ist. Ein leer mitgeschicktes Feld
 * leert. Eine App, die nur `pfad` und `bereiche` schickt, aendert also nur die Gesichter; `bereiche` = [] nimmt
 * alle Gesichter heraus. Andere Bereiche (Haustier, Fokus) bleiben immer stehen.
 */
final class ApiExif extends AbstractApiHandler
{
    public function __construct(
        private readonly ExifService  $exifService,
        private readonly ApiViewModel $viewModel,
        private readonly BereichIndex $bereichIndex,
        private readonly CollectionService $collectionService,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $this->fehler(StatusCodeInterface::STATUS_METHOD_NOT_ALLOWED, 'post-required');
        }

        if (!Auth::isManager($tree, Validator::attributes($request)->user())) {
            return $this->fehler(StatusCodeInterface::STATUS_FORBIDDEN, 'not-manager');
        }

        $body = (array) $request->getParsedBody();
        $pfad = trim((string) ($body['pfad'] ?? ''));
        $felder = Metadaten::mitgeschickt($body);

        if ($pfad === '') {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'missing-pfad');
        }

        if ($felder === null) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'bad-date');
        }

        if (array_key_exists('bereiche', $body)) {
            $bereiche = Bereiche::ausJson((string) $body['bereiche']);

            if ($bereiche === null) {
                return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'bad-regions');
            }

            $felder['bereiche'] = $this->namenErgaenzen($tree, $bereiche);
        }

        if (!ArchivAblage::istBild($pfad)) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'not-image');
        }

        try {
            $voll = $this->exifService->fullPath($tree, $pfad);
        } catch (RuntimeException) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'file-not-found');
        }

        if (!is_file($voll)) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'file-not-found');
        }

        // Postkarten (Stufe 6): Abschnitte erhalten, beide Seiten beschreiben.
        $abschnitte = [];
        foreach (['motiv', 'transkription', 'notiz'] as $feld) {
            if (array_key_exists($feld, $body)) {
                $abschnitte[$feld] = (string) $body[$feld];
            }
        }

        $istKarte = $this->collectionService->postkartenSammlung($tree, $pfad) !== null;

        if (!$istKarte) {
            if ($abschnitte !== [] || array_key_exists('partner', $body)) {
                return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'not-postcard');
            }

            return $this->schreiben($tree, [$pfad => $felder], $pfad, null);
        }

        $partner = $this->gegenseite($tree, $pfad, trim((string) ($body['partner'] ?? '')), array_key_exists('partner', $body));

        if ($partner === false) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'bad-partner');
        }

        if (array_key_exists('beschreibung', $felder) || $abschnitte !== []) {
            $alt = $this->exifService->leseMeta($voll)['beschreibung'];

            if ($alt === '' && $partner !== null) {
                $alt = $this->exifService->leseMeta(MedienPfad::wurzel($tree) . $partner)['beschreibung'];
            }

            $neu = $abschnitte;
            if (array_key_exists('beschreibung', $felder)) {
                $neu['beschreibung'] = $felder['beschreibung'];
            }

            $felder['beschreibung'] = Postkarten::zusammenfuehren($alt, $neu);
        }

        // Was die Karte beschreibt, steht in beiden Dateien; Gesichter gehoeren zu dem einen Bild.
        $gemeinsam = $felder;
        unset($gemeinsam['bereiche']);

        $jeDatei = [$pfad => $felder];

        if ($partner !== null && $gemeinsam !== []) {
            $seite      = Postkarten::seite($pfad);
            $identifier = $seite !== null ? $seite['schluessel'] : pathinfo($pfad, PATHINFO_FILENAME);

            $jeDatei[$pfad]    += ['identifier' => $identifier, 'relation' => basename($partner)];
            $jeDatei[$partner]  = $gemeinsam + ['identifier' => $identifier, 'relation' => basename($pfad)];
        }

        return $this->schreiben($tree, $jeDatei, $pfad, $partner ?? '');
    }

    /**
     * Schreibt je Datei ihre Felder und antwortet mit dem Eintrag. $partner: null
     * fuer ein Bild, das keine Postkarte ist; '' fuer eine Karte ohne Gegenseite.
     *
     * @param array<string,array<string,mixed>> $jeDatei  Pfad => Felder fuer ExifService::aendereMeta
     */
    private function schreiben(Tree $tree, array $jeDatei, string $pfad, ?string $partner): ResponseInterface
    {
        foreach ($jeDatei as $datei => $felder) {
            if ($felder === []) {
                continue;
            }

            try {
                $this->exifService->aendereMeta($this->exifService->fullPath($tree, $datei), $felder, $tree);
            } catch (RuntimeException) {
                // Kein Imagick, Datei schreibgeschuetzt, Format ohne XMP - diese Datei ist unveraendert.
                // Bei einer Karte kann die Vorderseite dann schon geschrieben sein; die App liest den Stand
                // aus dem naechsten Abruf.
                return $this->fehler(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR, 'exif-failed');
            }

            $this->bereichIndex->datei($tree, $datei);
        }

        if ($partner === null) {
            return $this->json(['ok' => true, 'eintrag' => $this->viewModel->eintrag($tree, $pfad)]);
        }

        return $this->json([
            'ok'      => true,
            'beide'   => count($jeDatei) === 2,
            'eintrag' => $this->viewModel->postkartenEintrag($tree, $pfad, $partner !== '' ? $partner : null),
        ]);
    }

    /**
     * Die andere Seite einer Karte: mitgeschickt (`partner`, etwa bei einem
     * Medienobjekt mit zwei Dateien) oder nach dem Namensmuster PK_0001_V/_R.
     * Wie im Browser muss sie im selben Ordner liegen und ein JPG sein.
     *
     * @return string|null|false  Pfad; null: Einzelkarte; false: die mitgeschickte passt nicht
     */
    private function gegenseite(Tree $tree, string $pfad, string $partner, bool $mitgeschickt): string|null|false
    {
        $ordner = dirname($pfad);

        if (!$mitgeschickt || $partner === '') {
            if ($mitgeschickt) {
                return null; // ausdruecklich leer: nur diese Seite
            }

            return $this->viewModel->gegenseiteNachName($tree, $pfad);
        }

        $istJpg = static fn (string $p): bool => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true);

        if (dirname($partner) !== $ordner || $partner === $pfad || !$istJpg($pfad) || !$istJpg($partner)) {
            return false;
        }

        try {
            return is_file($this->exifService->fullPath($tree, $partner)) ? $partner : false;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Ein Gesicht mit Kennung, aber ohne Namen bekommt den Namen aus dem Stammbaum - so lesen ihn auch
     * Programme, die sammlungen:Xref nicht kennen.
     *
     * @param list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}> $bereiche
     * @return list<array{x:float, y:float, w:float, h:float, name:string, xref:string|null, typ:string}>
     */
    private function namenErgaenzen(Tree $tree, array $bereiche): array
    {
        foreach ($bereiche as &$b) {
            if ($b['name'] === '' && $b['xref'] !== null) {
                $person = Registry::individualFactory()->make($b['xref'], $tree);

                if ($person instanceof Individual && $person->canShowName()) {
                    $b['name'] = trim(strip_tags($person->fullName()));
                }
            }
        }
        unset($b);

        return $bereiche;
    }
}

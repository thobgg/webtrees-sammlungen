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
use Sammlungen\Service\ExifService;
use Sammlungen\ViewModel\ApiViewModel;

use function array_key_exists;
use function is_file;
use function strip_tags;
use function trim;

/**
 * POST /tree/{tree}/archiv/api/exif
 *
 *   pfad            Datei im Medienordner (relativ)
 *   beschreibung, datum (YYYY, YYYY-MM oder YYYY-MM-DD), personen, keywords (je durch Komma getrennt)
 *   datum_unsicher  1/0 (Postkarten: Datum geschlossen, nicht abgelesen)
 *   bereiche        JSON-Liste der Gesichter, links oben normiert (Stufe 5, siehe Bereiche)
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

        if ($felder !== []) {
            try {
                $this->exifService->aendereMeta($voll, $felder, $tree);
            } catch (RuntimeException) {
                // Kein Imagick, Datei schreibgeschuetzt, Format ohne XMP - die Datei ist unveraendert.
                return $this->fehler(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR, 'exif-failed');
            }

            $this->bereichIndex->datei($tree, $pfad);
        }

        return $this->json(['ok' => true, 'eintrag' => $this->viewModel->eintrag($tree, $pfad)]);
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

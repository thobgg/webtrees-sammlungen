<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sammlungen\Service\ArchivAblage;
use Sammlungen\Service\ExifService;
use Sammlungen\ViewModel\ApiViewModel;

use function is_file;
use function trim;

/**
 * POST /tree/{tree}/archiv/api/exif
 *
 *   pfad          Datei im Medienordner (relativ)
 *   beschreibung, datum (YYYY, YYYY-MM oder YYYY-MM-DD), personen, keywords (je durch Komma getrennt)
 *
 * Schreibt die Felder als EXIF/XMP in die Bilddatei - dieselbe Arbeit wie die Seitenleiste der Lightbox, mit
 * derselben Regel: nur Verwalter des Baums. Vor dem Schreiben sichert der EXIF-Dienst die Datei (einmal am Tag).
 */
final class ApiExif extends AbstractApiHandler
{
    public function __construct(
        private readonly ExifService  $exifService,
        private readonly ApiViewModel $viewModel,
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
        $meta = Metadaten::ausFormular($body);

        if ($pfad === '') {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'missing-pfad');
        }

        if ($meta === null) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'bad-date');
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

        try {
            $this->exifService->schreibeMeta($voll, $meta->beschreibung, $meta->datum, $meta->personen, $meta->keywords, $tree);
        } catch (RuntimeException) {
            // Kein Imagick, Datei schreibgeschuetzt, Format ohne XMP - die Datei ist unveraendert.
            return $this->fehler(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR, 'exif-failed');
        }

        return $this->json(['ok' => true, 'eintrag' => $this->viewModel->eintrag($tree, $pfad)]);
    }
}

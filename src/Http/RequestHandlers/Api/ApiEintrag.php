<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sammlungen\Service\ExifService;
use Sammlungen\ViewModel\ApiViewModel;

use function is_file;
use function trim;

/**
 * GET /tree/{tree}/archiv/api/eintrag?pfad=<Datei im Medienordner>
 *
 * Ein einzelner Eintrag zu einer Datei - in derselben Form wie in einer Sammlung, mit EXIF, Personen und Adressen.
 * Fuer eine App, die ein Foto nicht aus dem Archiv, sondern aus dem Stammbaum oder einem Profil kennt und vor dem
 * Bearbeiten wissen muss, was schon in der Datei steht.
 */
final class ApiEintrag extends AbstractApiHandler
{
    public function __construct(
        private readonly ExifService  $exifService,
        private readonly ApiViewModel $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $pfad = trim(Validator::queryParams($request)->string('pfad', ''));

        if ($pfad === '') {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'missing-pfad');
        }

        try {
            $voll = $this->exifService->fullPath($tree, $pfad);
        } catch (RuntimeException) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'file-not-found');
        }

        if (!is_file($voll)) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'file-not-found');
        }

        return $this->json(['ok' => true, 'eintrag' => $this->viewModel->eintrag($tree, $pfad)]);
    }
}

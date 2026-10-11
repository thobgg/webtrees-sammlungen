<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sammlungen\Service\CollectionService;
use Sammlungen\ViewModel\ApiViewModel;

use function trim;

/**
 * POST /tree/{tree}/archiv/api/sammlung-loeschen   (Stufe 6)
 *
 *   sammlung  Slug
 *
 * Loescht die Sammlung und ihre Zuordnungen, wie der Knopf in der Verwaltung.
 * Die Dateien selbst bleiben, wo sie sind - auch die im Ordner einer
 * Ordner-Sammlung. Nur Administratoren. Antwort: {ok, sammlung}.
 */
final class ApiSammlungLoeschen extends AbstractApiHandler
{
    public function __construct(
        private readonly CollectionService $collectionService,
        private readonly ApiViewModel      $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $verboten = $this->schreibenErlaubt($request, $tree, 'admin');

        if ($verboten !== null) {
            return $verboten;
        }

        $slug     = trim((string) ($this->formular($request)['sammlung'] ?? ''));
        $sammlung = $slug !== '' ? $this->collectionService->findeNachSlug($tree, $slug) : null;

        if ($sammlung === null) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'unknown-collection');
        }

        $this->collectionService->loeschen((int) $sammlung->id);

        return $this->json($this->viewModel->kopfFuer($tree) + [
            'ok'       => true,
            'sammlung' => $slug,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sammlungen\Service\CollectionService;
use Sammlungen\ViewModel\ApiViewModel;

use function array_key_exists;
use function in_array;
use function trim;

/**
 * POST /tree/{tree}/archiv/api/sichtbar   (Stufe 6)
 *
 *   sammlung  Slug
 *   aktiv     1/0
 *
 * Antwort: {ok, sammlung} - die Sammlung mit allen Feldern (wie in
 * `verwaltung`). Wie der Schalter in der Verwaltung, nur Verwalter.
 */
final class ApiSichtbar extends AbstractApiHandler
{
    public function __construct(
        private readonly CollectionService $collectionService,
        private readonly ApiViewModel      $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $verboten = $this->schreibenErlaubt($request, $tree, 'verwalter');

        if ($verboten !== null) {
            return $verboten;
        }

        $body     = $this->formular($request);
        $sammlung = $this->collectionService->findeNachSlug($tree, trim((string) ($body['sammlung'] ?? '')));

        if ($sammlung === null) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'unknown-collection');
        }

        if (!array_key_exists('aktiv', $body)) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'missing-aktiv');
        }

        $this->collectionService->setAktiv(
            (int) $sammlung->id,
            in_array((string) $body['aktiv'], ['1', 'true', 'on'], true)
        );

        $sammlung = $this->collectionService->findeNachSlug($tree, (string) $sammlung->slug);

        return $this->json($this->viewModel->kopfFuer($tree) + [
            'ok'       => true,
            'sammlung' => $this->viewModel->verwaltungsEintrag($tree, $sammlung),
        ]);
    }
}

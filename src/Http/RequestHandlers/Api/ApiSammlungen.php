<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sammlungen\ViewModel\ApiViewModel;

/**
 * GET /tree/{tree}/archiv/api/sammlungen
 *
 * Die Uebersicht des Archivs: Ordner-Sammlungen, thematische Sammlungen,
 * Sammlungen nach Medientyp, nicht eingebundene Medien und der freie Bestand.
 *
 * ?alle=1 (Stufe 6, nur Verwalter): dazu `verwaltung` - alle Sammlungen, auch
 * ausgeblendete, mit den Feldern der Maske und den Auswahllisten.
 */
final class ApiSammlungen extends AbstractApiHandler
{
    public function __construct(
        private readonly ApiViewModel $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $alle = Validator::queryParams($request)->string('alle', '') === '1';

        return $this->json($this->viewModel->uebersicht($tree, $alle));
    }
}

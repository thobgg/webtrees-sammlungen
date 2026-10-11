<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sammlungen\Service\DateiUmbenennen;
use Sammlungen\Service\DateiUmbenennenException;
use Sammlungen\Service\Postkarten;
use Sammlungen\Service\MedienPfad;
use Sammlungen\Service\CollectionService;
use Sammlungen\ViewModel\ApiViewModel;

use function dirname;
use function is_file;
use function trim;

/**
 * POST /tree/{tree}/archiv/api/umbenennen   (Stufe 6)
 *
 *   pfad        Datei im Medienordner (relativ)
 *   neuer_name  neuer Dateiname, gleicher Ordner, gleiche Endung
 *
 * Antwort: {ok, alterPfad, eintrag} - der Eintrag unter dem neuen Pfad. Wie
 * datei-umbenennen im Browser, nur Verwalter. Eine Seite einer Postkarte mit
 * Gegenseite laesst sich nicht umbenennen (das Paar haengt am Namen;
 * `postcard-pair`) - der Browser bietet es dort auch nicht an.
 */
final class ApiUmbenennen extends AbstractApiHandler
{
    private const STATUS = [
        'missing-pfad'      => StatusCodeInterface::STATUS_BAD_REQUEST,
        'bad-name'          => StatusCodeInterface::STATUS_BAD_REQUEST,
        'extension-changed' => StatusCodeInterface::STATUS_BAD_REQUEST,
        'file-not-found'    => StatusCodeInterface::STATUS_NOT_FOUND,
        'name-exists'       => StatusCodeInterface::STATUS_CONFLICT,
        'rename-failed'     => StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
    ];

    public function __construct(
        private readonly DateiUmbenennen   $umbenennen,
        private readonly CollectionService $collectionService,
        private readonly ApiViewModel      $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $verboten = $this->schreibenErlaubt($request, $tree, 'verwalter');

        if ($verboten !== null) {
            return $verboten;
        }

        $body = $this->formular($request);
        $pfad = trim((string) ($body['pfad'] ?? ''));

        if ($pfad !== '' && $this->hatGegenseite($tree, $pfad)) {
            return $this->fehler(StatusCodeInterface::STATUS_CONFLICT, 'postcard-pair');
        }

        try {
            $neu = $this->umbenennen->umbenennen($tree, $pfad, (string) ($body['neuer_name'] ?? ''));
        } catch (DateiUmbenennenException $e) {
            return $this->fehler(self::STATUS[$e->fehlercode] ?? StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR, $e->fehlercode);
        }

        return $this->json($this->viewModel->kopfFuer($tree) + [
            'ok'        => true,
            'alterPfad' => $pfad,
            'eintrag'   => $this->viewModel->eintrag($tree, $neu),
        ]);
    }

    /** Liegt die Datei in einer Postkarten-Sammlung, und gibt es ihre andere Seite? */
    private function hatGegenseite(Tree $tree, string $pfad): bool
    {
        $partner = Postkarten::partnerName($pfad);

        if ($partner === null || $this->collectionService->postkartenSammlung($tree, $pfad) === null) {
            return false;
        }

        $ordner = dirname($pfad);

        return is_file(MedienPfad::wurzel($tree) . ($ordner === '.' ? '' : $ordner . '/') . $partner);
    }
}

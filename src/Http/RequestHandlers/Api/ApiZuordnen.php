<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sammlungen\Service\CollectionService;
use Sammlungen\Service\ExifService;
use Sammlungen\ViewModel\ApiViewModel;

use function is_file;
use function trim;

/**
 * POST /tree/{tree}/archiv/api/zuordnen   (Stufe 6)
 *
 *   pfad      Datei im Medienordner (relativ)
 *   sammlung  Slug eines Albums (Sammlung ohne Ordner, `album: true` in der Uebersicht)
 *   aktion    hinzufuegen (Vorgabe) oder entfernen
 *
 * Antwort: {ok, sammlung, istDrin, eintrag} - der Eintrag mit seinen neuen
 * `inSammlungen`. Wie "Zu Sammlung" in der Galerie, nur Verwalter. Jedes Bild
 * des Medienordners laesst sich zuordnen, ob Medienobjekt oder nicht; das
 * Medienobjekt (falls vorhanden) merkt sich das Modul mit.
 */
final class ApiZuordnen extends AbstractApiHandler
{
    public function __construct(
        private readonly CollectionService $collectionService,
        private readonly ExifService       $exifService,
        private readonly ApiViewModel      $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $verboten = $this->schreibenErlaubt($request, $tree, 'verwalter');

        if ($verboten !== null) {
            return $verboten;
        }

        $body   = $this->formular($request);
        $pfad   = trim((string) ($body['pfad'] ?? ''));
        $slug   = trim((string) ($body['sammlung'] ?? ''));
        $aktion = trim((string) ($body['aktion'] ?? 'hinzufuegen'));

        if ($pfad === '') {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'missing-pfad');
        }

        if ($aktion !== 'hinzufuegen' && $aktion !== 'entfernen') {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'bad-action');
        }

        $sammlung = $slug !== '' ? $this->collectionService->findeNachSlug($tree, $slug) : null;

        if ($sammlung === null) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'unknown-collection');
        }

        // Eine Ordner-Sammlung zeigt ihren Ordner - eine Zuordnung kaeme dort nie an.
        if (!ApiViewModel::istAlbum($sammlung)) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'not-album');
        }

        if ($aktion === 'entfernen') {
            $this->collectionService->pfadEntfernen($tree, (int) $sammlung->id, $pfad);
        } else {
            try {
                $voll = $this->exifService->fullPath($tree, $pfad);
            } catch (RuntimeException) {
                return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'file-not-found');
            }

            if (!is_file($voll)) {
                return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'file-not-found');
            }

            $mId = DB::table('media_file')
                ->where('m_file', '=', $tree->id())
                ->where('multimedia_file_refn', '=', $pfad)
                ->value('m_id');

            $this->collectionService->pfadZuordnen($tree, (int) $sammlung->id, $pfad, $mId !== null ? (string) $mId : null);
        }

        return $this->json($this->viewModel->kopfFuer($tree) + [
            'ok'       => true,
            'sammlung' => (string) $sammlung->slug,
            'istDrin'  => $aktion !== 'entfernen',
            'eintrag'  => $this->viewModel->eintrag($tree, $pfad),
        ]);
    }
}

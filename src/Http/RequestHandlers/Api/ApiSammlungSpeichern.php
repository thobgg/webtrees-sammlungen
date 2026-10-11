<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Tree;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sammlungen\Http\RequestHandlers\AdminSammlungEdit;
use Sammlungen\Service\CollectionService;
use Sammlungen\ViewModel\ApiViewModel;

use function array_keys;
use function strlen;
use function substr;
use function trim;

/**
 * POST /tree/{tree}/archiv/api/sammlung-speichern   (Stufe 6)
 *
 *   slug          leer oder fehlend: neu anlegen; sonst die Sammlung, die geaendert wird
 *   name, beschreibung, icon, farbe (#rrggbb), ansicht, ordner, aktiv (1/0), reihenfolge (0-9999)
 *
 * Die Felder der Maske im Browser; Auswahl fuer icon, ansicht und ordner unter
 * `verwaltung.auswahl` (api/sammlungen?alle=1). Beim Aendern bleibt, was nicht
 * mitkommt. Die Kennung einer neuen Sammlung entsteht aus dem Namen (bei
 * Gleichstand mit -2, -3 ...) und bleibt danach fest. Nur Administratoren,
 * wie die Verwaltung im Browser.
 *
 * Antwort: {ok, neu, sammlung}.
 */
final class ApiSammlungSpeichern extends AbstractApiHandler
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

        $body = $this->formular($request);
        $slug = trim((string) ($body['slug'] ?? ''));
        $alt  = null;

        if ($slug !== '') {
            $alt = $this->collectionService->findeNachSlug($tree, $slug);

            if ($alt === null) {
                return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'unknown-collection');
            }
        }

        $gelesen = SammlungFormular::lesen(
            $body,
            $alt !== null ? ApiViewModel::formularFelder($alt) : null,
            array_keys(AdminSammlungEdit::ICON_AUSWAHL),
            CollectionService::ANSICHTEN,
            $this->collectionService->verfuegbareOrdner($tree),
        );

        if ($gelesen['fehler'] !== null) {
            $status = $gelesen['fehler'] === 'folder-not-found'
                ? StatusCodeInterface::STATUS_NOT_FOUND
                : StatusCodeInterface::STATUS_BAD_REQUEST;

            return $this->fehler($status, $gelesen['fehler']);
        }

        $f = $gelesen['felder'];

        try {
            if ($alt === null) {
                $slug = $this->freieKennung($tree, SammlungFormular::slugAusName($f['name']));

                $this->collectionService->erstellen(
                    tree:         $tree,
                    slug:         $slug,
                    name:         $f['name'],
                    beschreibung: $f['beschreibung'],
                    farbe:        $f['farbe'],
                    icon:         $f['icon'],
                    reihenfolge:  $f['reihenfolge'],
                    aktiv:        $f['aktiv'],
                    ordner:       $f['ordner'],
                    ansicht:      $f['ansicht'],
                );
            } else {
                $this->collectionService->aktualisieren(
                    id:           (int) $alt->id,
                    name:         $f['name'],
                    beschreibung: $f['beschreibung'],
                    farbe:        $f['farbe'],
                    icon:         $f['icon'],
                    reihenfolge:  $f['reihenfolge'],
                    aktiv:        $f['aktiv'],
                    ordner:       $f['ordner'],
                    ansicht:      $f['ansicht'],
                );
            }
        } catch (InvalidArgumentException) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'bad-slug');
        }

        $sammlung = $this->collectionService->findeNachSlug($tree, $slug);

        if ($sammlung === null) {
            return $this->fehler(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR, 'save-failed');
        }

        return $this->json($this->viewModel->kopfFuer($tree) + [
            'ok'       => true,
            'neu'      => $alt === null,
            'sammlung' => $this->viewModel->verwaltungsEintrag($tree, $sammlung),
        ]);
    }

    /** "familie", sonst "familie-2", "familie-3" ... - hoechstens 80 Zeichen. */
    private function freieKennung(Tree $tree, string $wunsch): string
    {
        $kandidat = $wunsch;

        for ($n = 2; $this->collectionService->findeNachSlug($tree, $kandidat) !== null; $n++) {
            $zusatz   = '-' . $n;
            $kandidat = substr($wunsch, 0, 80 - strlen($zusatz)) . $zusatz;
        }

        return $kandidat;
    }
}

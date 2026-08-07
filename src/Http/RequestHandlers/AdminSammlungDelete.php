<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Sammlungen\Service\CollectionService;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /tree/{tree}/archiv/admin/sammlungen/delete
 *
 * Löscht eine Sammlung anhand ihrer ID.
 * Erwartet `id` im POST-Body.
 * Redirect-After-Post zurück zur Übersicht des gleichen Stammbaums.
 */
class AdminSammlungDelete implements RequestHandlerInterface
{
    public function __construct(
        private readonly CollectionService $collectionService,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException(
                I18N::translate('You do not have permission for this action.')
            );
        }

        // Tree aus Route-Attributen – Route enthält jetzt {tree}
        try {
            $tree = Validator::attributes($request)->tree();
        } catch (\Throwable) {
            $tree = null;
        }

        $body = (array) $request->getParsedBody();
        $id   = (int) ($body['id'] ?? 0);

        if ($id > 0) {
            $deleted = $this->collectionService->loeschen($id);

            FlashMessages::addMessage(
                $deleted
                    ? I18N::translate('Collection #%s was deleted.', (string) $id)
                    : I18N::translate('Collection #%s was not found.', (string) $id),
                $deleted ? 'success' : 'warning'
            );
        }

        // Zurück zur Sammlungs-Übersicht – mit tree, falls vorhanden
        $redirectParams = $tree !== null ? ['tree' => $tree->name()] : [];

        return redirect(route('sammlungen.admin.sammlungen', $redirectParams));
    }
}

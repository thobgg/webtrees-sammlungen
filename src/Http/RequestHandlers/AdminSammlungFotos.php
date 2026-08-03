<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Sammlungen\Service\CollectionService;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /tree/{tree}/archiv/admin/sammlung-fotos?id=N
 *
 * Foto-Picker für manuelle Sammlungen (kein Ordner gesetzt).
 * Zeigt alle Bilder aus ordner-basierten Fotogalerien mit Toggle.
 */
class AdminSammlungFotos implements RequestHandlerInterface
{
    use ViewResponseTrait;

    public function __construct(
        private readonly CollectionService $collectionService,
    ) {
        // Control Panel statt Besucheroberflaeche – siehe AdminConfig.
        $this->layout = 'layouts/administration';
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException(
                I18N::translate('Sie haben keine Berechtigung für diese Seite.')
            );
        }

        try {
            $tree = Validator::attributes($request)->tree();
        } catch (\Throwable) {
            $tree = null;
        }

        if ($tree === null) {
            return redirect(route('sammlungen.admin.sammlungen'));
        }

        $id       = (int) ($request->getQueryParams()['id'] ?? 0);
        $sammlung = $this->collectionService->findeNachId($id);

        if ($sammlung === null || (int) $sammlung->gedcom_id !== $tree->id()) {
            throw new HttpNotFoundException(I18N::translate('Sammlung nicht gefunden.'));
        }

        // Ordner-basierte Fotogalerien als Quellen (nur Top-Level-Ordner, kein Unterverzeichnis)
        $quellen = array_values(array_filter(
            $this->collectionService->aktive($tree),
            fn (object $s) => $s->ordner !== null && $s->ordner !== ''
                && strpos($s->ordner, '/') === false
                && in_array($s->ansicht ?? 'foto', ['foto', 'raster'], true)
        ));

        // Bereits in Sammlung enthaltene Pfade (neue pfad-Tabelle)
        $inSammlung = array_values(
            \Fisharebest\Webtrees\DB::table('sammlungen_collection_pfad')
                ->where('collection_id', '=', $id)
                ->where('gedcom_id', '=', $tree->id())
                ->pluck('pfad')
                ->map(fn ($v) => (string) $v)
                ->all()
        );

        // Gewählte Quell-Sammlung
        $quelleId   = (int) ($request->getQueryParams()['quelle'] ?? ($quellen[0]->id ?? 0));
        $quelleAktiv = null;
        foreach ($quellen as $q) {
            if ($q->id === $quelleId) {
                $quelleAktiv = $q;
                break;
            }
        }

        // Paginierung
        $seite    = max(1, (int) ($request->getQueryParams()['seite'] ?? 1));
        $perSeite = 24;

        $alleDateien = $quelleAktiv !== null
            ? $this->collectionService->alleDateienInOrdner($tree, $quelleAktiv->ordner)
            : [];

        // Alle Bilder zeigen – auch ohne m_id (nicht-importierte Fotos)
        $bilder = array_values(array_filter(
            $alleDateien,
            fn ($d) => in_array($d['format'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
        ));

        $gesamt       = count($bilder);
        $seitenGesamt = max(1, (int) ceil($gesamt / $perSeite));
        $seite        = min($seite, $seitenGesamt);
        $bilderSeite  = array_slice($bilder, ($seite - 1) * $perSeite, $perSeite);

        return $this->viewResponse('_sammlungen_::admin-sammlung-fotos', [
            'title'       => I18N::translate('Fotos für: %s', $sammlung->name),
            'tree'        => $tree,
            'sammlung'    => $sammlung,
            'quellen'     => $quellen,
            'quelleAktiv' => $quelleAktiv,
            'bilder'      => $bilderSeite,
            'inSammlung'  => $inSammlung,
            'seite'       => $seite,
            'seiten'      => $seitenGesamt,
            'gesamt'      => $gesamt,
            'toggleRoute' => route('sammlungen.sammlung-medium', ['tree' => $tree->name()]),
            'csrf'        => csrf_token(),
        ]);
    }
}

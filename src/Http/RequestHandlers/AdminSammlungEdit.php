<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Sammlungen\Dto\SammlungDto;
use Sammlungen\Service\CollectionService;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET  /archiv/admin/sammlungen/edit[?id=N]   – Formular (neu oder bearbeiten)
 * POST /archiv/admin/sammlungen/edit           – Speichern
 *
 * id=0 oder fehlt → neue Sammlung anlegen
 * id=N            → bestehende Sammlung bearbeiten
 */
class AdminSammlungEdit implements RequestHandlerInterface
{
    use ViewResponseTrait;

    /** Nur Icons aus dem webtrees FA-Kit (bestätigt funktionsfähig) */
    private const ICON_AUSWAHL = [
        'folder'      => 'Ordner',
        'users'       => 'Fotos / Personen',
        'user'        => 'Portrait / Konterfei',
        'file-image'  => 'Foto / Bild',
        'file-lines'  => 'Dokument / Anzeige',
        'file-alt'    => 'Dokument',
        'file'        => 'Datei',
        'envelope'    => 'Brief / Postkarte',
        'scroll'      => 'Akte / Schriftrolle',
        'note-sticky' => 'Notiz',
        'sitemap'         => 'Stammbaum',
        'tree'            => 'Natur / Baum',
        'building-columns' => 'Grabstein / Tempel / Denkmal',
        'university'      => 'Gebäude / Institution',
        'star'            => 'Auszeichnung / Militär',
        'map'         => 'Karte / Ort',
        'search'      => 'Suche',
        'tags'        => 'Kategorie',
        'list'        => 'Liste',
        'thumbtack'   => 'Markierung',
    ];

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
            FlashMessages::addMessage(I18N::translate('Bitte wählen Sie einen Stammbaum aus.'), 'warning');
            return redirect(route('sammlungen.admin.sammlungen'));
        }

        $id = (int) ($request->getQueryParams()['id'] ?? 0);

        if ($request->getMethod() === 'POST') {
            return $this->save($request, $tree, $id);
        }

        return $this->showForm($tree, $id);
    }

    // ---------------------------------------------------------------
    // GET – Formular
    // ---------------------------------------------------------------

    private function showForm(Tree $tree, int $id): ResponseInterface
    {
        $sammlung = null;

        if ($id > 0) {
            $alle = $this->collectionService->alle($tree);
            foreach ($alle as $s) {
                if ($s->id === $id) {
                    $sammlung = $s;
                    break;
                }
            }

            if ($sammlung === null) {
                throw new HttpNotFoundException(
                    I18N::translate('Sammlung #%s nicht gefunden.', (string) $id)
                );
            }
        }

        return $this->viewResponse('_sammlungen_::admin-sammlung-edit', [
            'title'              => $sammlung !== null
                ? I18N::translate('Sammlung bearbeiten: %s', $sammlung->name)
                : I18N::translate('Neue Sammlung anlegen'),
            'tree'               => $tree,
            'sammlung'           => $sammlung,
            'iconAuswahl'        => self::ICON_AUSWAHL,
            'medienTypen'        => SammlungDto::TYPEN,
            'verfuegbareOrdner'  => $this->collectionService->verfuegbareOrdner($tree),
        ]);
    }

    // ---------------------------------------------------------------
    // POST – Speichern
    // ---------------------------------------------------------------

    private function save(ServerRequestInterface $request, Tree $tree, int $id): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        $slug         = trim((string) ($body['slug']         ?? ''));
        $name         = trim((string) ($body['name']         ?? ''));
        $beschreibung = trim((string) ($body['beschreibung'] ?? ''));
        $farbe        = trim((string) ($body['farbe']        ?? '#6c757d'));
        $icon         = trim((string) ($body['icon']         ?? 'folder'));
        $reihenfolge  = (int) ($body['reihenfolge'] ?? 0);
        $aktiv        = isset($body['aktiv']);
        $ordner       = trim((string) ($body['ordner'] ?? ''));
        $ansicht      = in_array($body['ansicht'] ?? '', ['foto', 'raster', 'gemischt', 'dokument'], true)
                        ? (string) $body['ansicht'] : 'foto';

        if ($name === '') {
            FlashMessages::addMessage(I18N::translate('Bitte geben Sie einen Namen ein.'), 'danger');
            return redirect(route('sammlungen.admin.sammlungen.edit', ['tree' => $tree->name(), 'id' => $id]));
        }

        try {
            if ($id === 0) {
                // Neu anlegen
                if ($slug === '') {
                    // Slug aus Name ableiten
                    $slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $name));
                    $slug = trim($slug, '-');
                }

                $this->collectionService->erstellen(
                    tree:         $tree,
                    slug:         $slug,
                    name:         $name,
                    beschreibung: $beschreibung,
                    farbe:        $farbe,
                    icon:         $icon,
                    reihenfolge:  $reihenfolge,
                    aktiv:        $aktiv,
                    ordner:       $ordner,
                    ansicht:      $ansicht,
                );

                FlashMessages::addMessage(
                    I18N::translate('Sammlung „%s" wurde angelegt.', e($name)),
                    'success'
                );
            } else {
                // Aktualisieren
                $this->collectionService->aktualisieren(
                    id:           $id,
                    name:         $name,
                    beschreibung: $beschreibung,
                    farbe:        $farbe,
                    icon:         $icon,
                    reihenfolge:  $reihenfolge,
                    aktiv:        $aktiv,
                    ordner:       $ordner,
                    ansicht:      $ansicht,
                );

                FlashMessages::addMessage(
                    I18N::translate('Sammlung „%s" wurde aktualisiert.', e($name)),
                    'success'
                );
            }
        } catch (\InvalidArgumentException $e) {
            FlashMessages::addMessage($e->getMessage(), 'danger');
            return redirect(route('sammlungen.admin.sammlungen.edit', ['tree' => $tree->name(), 'id' => $id]));
        }

        return redirect(route('sammlungen.admin.sammlungen', ['tree' => $tree->name()]));
    }
}

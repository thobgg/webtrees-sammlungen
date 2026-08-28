<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Sammlungen\Cache\ApcuCacheService;
use Sammlungen\SammlungenModule;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminConfig implements RequestHandlerInterface
{
    use ViewResponseTrait;

    private ApcuCacheService $cache;

    public function __construct(
        private readonly SammlungenModule $module,
        private readonly TreeService          $treeService,
    ) {
        $this->cache = new ApcuCacheService($module->cacheTtl());

        // ViewResponseTrait rendert per Default mit 'layouts/default', also der
        // Besucheroberflaeche: die Seite bekaeme Kopfzeile und Navigation eines
        // konkreten Baums und der Nutzer landet sichtbar im ersten Stammbaum.
        // Admin-Seiten gehoeren ins Control Panel.
        // Zuweisung statt Property-Deklaration: eine Neudeklaration mit
        // abweichendem Default kollidiert mit der Property aus dem Trait
        // (fataler Fehler beim Komponieren der Klasse).
        $this->layout = 'layouts/administration';
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException(
                I18N::translate('You do not have permission for this page.')
            );
        }

        if ($request->getMethod() === 'POST') {
            return $this->save($request);
        }

        return $this->showForm($request);
    }

    /**
     * Der Baum, aus dem der Aufruf kam - oder null.
     *
     * Die Einstellungen gelten baumuebergreifend, die Route kennt deshalb
     * keinen Baum. Wer aus dem Menue eines Baums kommt, gibt ihn als
     * Abfrageparameter mit, damit die Seite einen Rueckweg genau in dessen
     * Galerie anbieten kann. Nachgeschlagen wird gegen die vorhandenen
     * Baeume - ein erfundener Name fuehrt zu keinem Verweis, nicht zu einem
     * falschen.
     */
    private function baumAusDerAnfrage(ServerRequestInterface $request): ?Tree
    {
        $name = Validator::queryParams($request)->string('tree', '');

        return $name === '' ? null : $this->treeService->all()->get($name);
    }

    private function showForm(ServerRequestInterface $request): ResponseInterface
    {
        // Alle vorhandenen Bäume – für den "Sammlungen verwalten"-Link in der View.
        $trees = $this->treeService->all();
        $baum  = $this->baumAusDerAnfrage($request);

        return $this->viewResponse(
            '_sammlungen_::admin-config',
            [
                'title'         => I18N::translate('Collections – settings'),
                'zurueck'       => $baum === null
                    ? ''
                    : route('sammlungen.sammlungen', ['tree' => $baum->name()]),
                'formularZiel'  => $baum === null
                    ? route('sammlungen.admin.config')
                    : route('sammlungen.admin.config', ['tree' => $baum->name()]),
                'module'        => $this->module,
                'cacheTtl'      => $this->module->cacheTtl(),
                'perPage'       => $this->module->perPage(),
                'trees'         => $trees,            // alle Bäume für Selektor
                'apcuAvailable' => $this->cache->isApcuAvailable(),
            ]
        );
    }

    private function save(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        $cacheTtl = SammlungenModule::normalisiereCacheTtl($params[SammlungenModule::SETTING_CACHE_TTL] ?? null);
        $perPage  = SammlungenModule::normalisierePerPage($params[SammlungenModule::SETTING_PER_PAGE]  ?? null);

        $this->module->setPreference(SammlungenModule::SETTING_CACHE_TTL, (string) $cacheTtl);
        $this->module->setPreference(SammlungenModule::SETTING_PER_PAGE,  (string) $perPage);

        $this->cache->flush();

        $baum = $this->baumAusDerAnfrage($request);

        return redirect($baum === null
            ? route('sammlungen.admin.config')
            : route('sammlungen.admin.config', ['tree' => $baum->name()]));
    }
}

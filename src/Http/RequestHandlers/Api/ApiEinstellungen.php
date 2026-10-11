<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sammlungen\Cache\ApcuCacheService;
use Sammlungen\SammlungenModule;
use Sammlungen\ViewModel\ApiViewModel;

use function array_key_exists;

/**
 * GET/POST /tree/{tree}/archiv/api/einstellungen   (Stufe 6)
 *
 *   cacheTtl      Sekunden (60-86400)
 *   seitenGroesse Eintraege je Seite (10-200)
 *
 * Die Einstellungen des Moduls - baumuebergreifend wie im Browser, der Baum in
 * der Adresse dient nur der Anmeldung. Nur Administratoren, auch zum Lesen.
 * Werte ausserhalb der Grenzen werden wie im Browser auf die Grenze gesetzt;
 * die Antwort nennt, was gilt. Was nicht mitkommt, bleibt.
 *
 * Antwort: {ok, cacheTtl, seitenGroesse, grenzen, apcu}.
 */
final class ApiEinstellungen extends AbstractApiHandler
{
    public function __construct(
        private readonly SammlungenModule $module,
        private readonly ApcuCacheService $cache,
        private readonly ApiViewModel     $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $verboten = $this->schreibenErlaubt($request, $tree, 'admin');

            if ($verboten !== null) {
                return $verboten;
            }

            $body = $this->formular($request);

            if (array_key_exists('cacheTtl', $body)) {
                $this->module->setPreference(
                    SammlungenModule::SETTING_CACHE_TTL,
                    (string) SammlungenModule::normalisiereCacheTtl($body['cacheTtl'])
                );
            }

            if (array_key_exists('seitenGroesse', $body)) {
                $this->module->setPreference(
                    SammlungenModule::SETTING_PER_PAGE,
                    (string) SammlungenModule::normalisierePerPage($body['seitenGroesse'])
                );
            }

            $this->cache->flush();
        } elseif (!Auth::isAdmin()) {
            return $this->fehler(StatusCodeInterface::STATUS_FORBIDDEN, 'not-admin');
        }

        return $this->json($this->viewModel->kopfFuer($tree) + [
            'ok'            => true,
            'cacheTtl'      => $this->module->cacheTtl(),
            'seitenGroesse' => $this->module->perPage(),
            'grenzen'       => [
                'cacheTtl'      => ['min' => SammlungenModule::MIN_CACHE_TTL, 'max' => SammlungenModule::MAX_CACHE_TTL, 'vorgabe' => SammlungenModule::DEFAULT_CACHE_TTL],
                'seitenGroesse' => ['min' => SammlungenModule::MIN_PER_PAGE, 'max' => SammlungenModule::MAX_PER_PAGE, 'vorgabe' => SammlungenModule::DEFAULT_PER_PAGE],
            ],
            // Ohne APCu merkt sich das Modul nichts ueber eine Anfrage hinaus; die Lebensdauer wirkt dann nicht.
            'apcu'          => $this->cache->isApcuAvailable(),
        ]);
    }
}

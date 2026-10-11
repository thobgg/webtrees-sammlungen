<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sammlungen\Cache\ApcuCacheService;
use Sammlungen\ViewModel\ApiViewModel;

use function function_exists;
use function opcache_reset;

/**
 * POST /tree/{tree}/archiv/api/cache-leeren   (Stufe 6)
 *
 * Leert den Zwischenspeicher des Moduls (und den OPcache), wie der Knopf in den
 * Einstellungen. Nur Administratoren. Antwort: {ok}.
 */
final class ApiCacheLeeren extends AbstractApiHandler
{
    public function __construct(
        private readonly ApcuCacheService $cache,
        private readonly ApiViewModel     $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $verboten = $this->schreibenErlaubt($request, $tree, 'admin');

        if ($verboten !== null) {
            return $verboten;
        }

        $this->cache->flush();

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return $this->json($this->viewModel->kopfFuer($tree) + ['ok' => true]);
    }
}

<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Sammlungen\Service\CollectionService;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminSammlungen implements RequestHandlerInterface
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
                I18N::translate('You do not have permission for this page.')
            );
        }

        try {
            $tree = Validator::attributes($request)->tree();
        } catch (\Throwable) {
            $tree = null;
        }

        $sammlungen = $tree !== null
            ? $this->collectionService->alle($tree)
            : [];

        return $this->viewResponse('_sammlungen_::admin-sammlungen', [
            'title'      => I18N::translate('Manage collections'),
            'tree'       => $tree,
            'sammlungen' => $sammlungen,
        ]);
    }
}

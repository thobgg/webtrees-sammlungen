<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sammlungen\ViewModel\ApiViewModel;

use function trim;

/**
 * GET /tree/{tree}/archiv/api/sammlung?kategorie=<slug>[&typ=…][&seite=1][&pro_seite=48]
 * GET /tree/{tree}/archiv/api/sammlung?person=<xref>[&seite=1][&pro_seite=48]
 *
 * Die Eintraege einer Sammlung, seitenweise - dieselben Schluessel, die auch
 * die Galerie in der Adresse fuehrt. `pro_seite` gilt fuer diese Antwort und
 * wird nicht beim Nutzer gemerkt.
 *
 * Mit `person` statt `kategorie` (Stufe 5): alle Bilder, auf denen diese
 * Person als Gesicht markiert ist.
 */
final class ApiSammlung extends AbstractApiHandler
{
    public function __construct(
        private readonly ApiViewModel $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $params    = Validator::queryParams($request);
        $kategorie = trim($params->string('kategorie', ''));
        $person    = trim($params->string('person', ''));

        if ($kategorie === '' && $person !== '') {
            $daten = $this->viewModel->person($tree, $person, $params->integer('seite', 1), $params->integer('pro_seite', 0));

            return $daten === null
                ? $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'unknown-person')
                : $this->json($daten);
        }

        if ($kategorie === '') {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'missing-kategorie');
        }

        $daten = $this->viewModel->sammlung(
            $tree,
            $kategorie,
            trim($params->string('typ', '')),
            $params->integer('seite', 1),
            $params->integer('pro_seite', 0)
        );

        if ($daten === null) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'unknown-collection');
        }

        return $this->json($daten);
    }
}

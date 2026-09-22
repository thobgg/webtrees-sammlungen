<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sammlungen\Service\Sprachwahl;
use Throwable;

use function array_map;
use function response;

/**
 * Gemeinsame Basis der App-Schnittstelle: dieselben Daten wie die Galerie,
 * als JSON statt als Seite. Gedacht fuer wtAnd, offen fuer jeden Client, der
 * mit dem Sitzungs-Cookie eines Baummitglieds kommt.
 *
 * Zugriff wie die Galerie: nur Mitglieder des Baums. Wer keins ist, bekommt
 * keine Anmeldeseite, sondern eine Antwort, die ein Programm lesen kann. Die
 * App unterscheidet daran "Modul fehlt" (webtrees antwortet mit seiner
 * 404-Seite) von "kein Zugriff" (JSON mit 403).
 */
abstract class AbstractApiHandler implements RequestHandlerInterface
{
    final public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $tree = Validator::attributes($request)->tree();
        } catch (Throwable) {
            return $this->fehler(StatusCodeInterface::STATUS_NOT_FOUND, 'tree-not-found');
        }

        if (!Auth::isMember($tree)) {
            return $this->fehler(
                StatusCodeInterface::STATUS_FORBIDDEN,
                Auth::check() ? 'not-member' : 'not-logged-in'
            );
        }

        $this->spracheWaehlen(Validator::queryParams($request)->string('lang', ''));

        return $this->antworten($request, $tree);
    }

    abstract protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface;

    /** @param array<string,mixed> $daten */
    protected function json(array $daten): ResponseInterface
    {
        return response($daten, StatusCodeInterface::STATUS_OK, ['Cache-Control' => 'private, no-store']);
    }

    protected function fehler(int $status, string $code): ResponseInterface
    {
        return response(
            ['ok' => false, 'error' => $code, 'status' => $status],
            $status,
            ['Cache-Control' => 'private, no-store']
        );
    }

    /**
     * ?lang=de: die Antwort in der Sprache der App, nicht der des Kontos.
     * Sonst stuenden die Bezeichnungen der Medientypen ("Grabstein") zwischen
     * den eigenen Texten der App in einer anderen Sprache. Gilt nur fuer diese
     * eine Antwort; Sitzung und Konto bleiben, wie sie sind.
     */
    private function spracheWaehlen(string $gewuenscht): void
    {
        $verfuegbar = array_map(
            static fn ($locale): string => $locale->languageTag(),
            I18N::activeLocales()
        );

        $tag = Sprachwahl::passend($gewuenscht, $verfuegbar);

        if ($tag !== null && $tag !== I18N::languageTag()) {
            I18N::init($tag);
        }
    }
}

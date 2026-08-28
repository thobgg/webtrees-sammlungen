<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Sammlungen\Service\ExifService;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function trim;

/**
 * POST /tree/{tree}/archiv/exif-schreiben
 *
 * Schreibt EXIF/XMP-Metadaten in eine Bilddatei.
 * Gibt JSON zurück (für AJAX-Aufruf aus der Lightbox).
 */
final class ExifSchreiben implements RequestHandlerInterface
{
    public function __construct(
        private readonly ExifService             $exifService,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface  $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();

        // Nur eingeloggte Mitglieder dürfen Metadaten schreiben
        if (!Auth::isManager($tree, $user)) {
            return $this->json(['ok' => false, 'fehler' => 'Keine Berechtigung.'], 403);
        }

        $body        = (array) $request->getParsedBody();
        $pfad        = trim((string) ($body['pfad']        ?? ''));
        $beschreibung = trim((string) ($body['beschreibung'] ?? ''));
        $datum       = trim((string) ($body['datum']       ?? ''));
        $personenRaw = trim((string) ($body['personen']    ?? ''));
        $keywordsRaw = trim((string) ($body['keywords']    ?? ''));

        // Komma-getrennte Listen → Arrays
        $personen = array_values(array_filter(
            array_map('trim', explode(',', $personenRaw))
        ));
        $keywords = array_values(array_filter(
            array_map('trim', explode(',', $keywordsRaw))
        ));

        try {
            $fullPath = $this->exifService->fullPath($tree, $pfad);
            $this->exifService->schreibeMeta($fullPath, $beschreibung, $datum, $personen, $keywords, $tree);

            return $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'fehler' => $e->getMessage()], 500);
        }
    }

    private function json(array $data, int $status = 200): ResponseInterface
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($body));
    }
}

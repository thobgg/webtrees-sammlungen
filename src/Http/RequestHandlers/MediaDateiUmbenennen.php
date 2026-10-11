<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sammlungen\Service\DateiUmbenennen;
use Sammlungen\Service\DateiUmbenennenException;

use function basename;
use function json_encode;

/**
 * POST /tree/{tree}/archiv/datei-umbenennen
 *
 * Benennt eine Mediendatei um (siehe DateiUmbenennen). Gibt JSON zurueck,
 * fuer die Lightbox. Die App nimmt api/umbenennen.
 */
final class MediaDateiUmbenennen implements RequestHandlerInterface
{
    private const MELDUNGEN = [
        'missing-pfad'      => ['Fehlende Parameter.', 400],
        'bad-name'          => ['Ungültiger Dateiname.', 400],
        'extension-changed' => ['Die Endung der Datei lässt sich nicht ändern.', 400],
        'file-not-found'    => ['Originaldatei nicht gefunden.', 404],
        'name-exists'       => ['Datei mit diesem Namen existiert bereits.', 409],
        'rename-failed'     => ['Umbenennen fehlgeschlagen.', 500],
    ];

    public function __construct(
        private readonly DateiUmbenennen          $umbenennen,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface   $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();

        if (!Auth::isManager($tree, $user)) {
            return $this->json(['ok' => false, 'fehler' => 'Keine Berechtigung.'], 403);
        }

        $body = (array) $request->getParsedBody();

        try {
            $neuPfad = $this->umbenennen->umbenennen(
                $tree,
                (string) ($body['pfad'] ?? ''),
                (string) ($body['neuer_name'] ?? '')
            );
        } catch (DateiUmbenennenException $e) {
            [$text, $status] = self::MELDUNGEN[$e->fehlercode] ?? ['Umbenennen fehlgeschlagen.', 500];

            return $this->json(['ok' => false, 'fehler' => $text], $status);
        }

        return $this->json([
            'ok'       => true,
            'neu_pfad' => $neuPfad,
            'neu_name' => basename($neuPfad),
        ]);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data, int $status = 200): ResponseInterface
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE);

        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($body));
    }
}

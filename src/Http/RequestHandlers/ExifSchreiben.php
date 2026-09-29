<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Sammlungen\Service\ExifService;
use Sammlungen\Service\Postkarten;
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
use function basename;
use function dirname;
use function explode;
use function in_array;
use function pathinfo;
use function strtolower;
use function trim;

use const PATHINFO_EXTENSION;

/**
 * POST /tree/{tree}/archiv/exif-schreiben
 *
 * Schreibt EXIF/XMP-Metadaten in eine Bilddatei.
 * Gibt JSON zurück (für AJAX-Aufruf aus der Lightbox).
 *
 * Postkarten: kommt `partner` mit (die andere Seite derselben Karte), werden
 * beide Dateien beschrieben - dieselben Felder, dazu in jeder der
 * Kartenschluessel (dc:identifier) und der Name der Gegenseite (dc:relation).
 * `datum_unsicher` kennzeichnet ein geschlossenes Poststempeldatum.
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

        $body          = (array) $request->getParsedBody();
        $pfad          = trim((string) ($body['pfad']          ?? ''));
        $partner       = trim((string) ($body['partner']       ?? ''));
        $beschreibung  = trim((string) ($body['beschreibung']  ?? ''));
        $datum         = trim((string) ($body['datum']         ?? ''));
        $datumUnsicher = in_array((string) ($body['datum_unsicher'] ?? ''), ['1', 'true', 'on'], true);
        $personenRaw   = trim((string) ($body['personen']      ?? ''));
        $keywordsRaw   = trim((string) ($body['keywords']      ?? ''));

        // Komma-getrennte Listen → Arrays
        $personen = array_values(array_filter(
            array_map('trim', explode(',', $personenRaw))
        ));
        $keywords = array_values(array_filter(
            array_map('trim', explode(',', $keywordsRaw))
        ));

        try {
            $fullPath = $this->exifService->fullPath($tree, $pfad);

            if ($partner === '') {
                $this->exifService->schreibeMeta($fullPath, $beschreibung, $datum, $personen, $keywords, $tree, $datumUnsicher);

                return $this->json(['ok' => true]);
            }

            // Die Gegenseite muss im selben Ordner liegen und ein JPG sein -
            // mehr Kopplung verlangt das Modul nicht, denn auch Paare aus
            // einem Medienobjekt mit zwei Dateien folgen keinem Namensmuster.
            $partnerPath = $this->exifService->fullPath($tree, $partner);
            $istJpg      = static fn (string $p): bool => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true);

            if (dirname($partnerPath) !== dirname($fullPath) || $partnerPath === $fullPath || !$istJpg($fullPath) || !$istJpg($partnerPath)) {
                return $this->json(['ok' => false, 'fehler' => 'Die Gegenseite passt nicht zur Karte.'], 400);
            }

            $seite      = Postkarten::seite($pfad);
            $identifier = $seite !== null ? $seite['schluessel'] : pathinfo($pfad, \PATHINFO_FILENAME);

            $this->exifService->schreibeMeta(
                $fullPath, $beschreibung, $datum, $personen, $keywords, $tree,
                $datumUnsicher, $identifier, basename($partner)
            );
            $this->exifService->schreibeMeta(
                $partnerPath, $beschreibung, $datum, $personen, $keywords, $tree,
                $datumUnsicher, $identifier, basename($pfad)
            );

            return $this->json(['ok' => true, 'beide' => true]);
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

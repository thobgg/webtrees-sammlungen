<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Sammlungen\Service\MedienPfad;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /tree/{tree}/archiv/datei-umbenennen
 *
 * Benennt eine Mediendatei um. Aktualisiert collection_pfad und media_file.
 * Gibt JSON zurück.
 */
final class MediaDateiUmbenennen implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface  $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();

        if (!Auth::isManager($tree, $user)) {
            return $this->json(['ok' => false, 'fehler' => 'Keine Berechtigung.'], 403);
        }

        $body       = (array) $request->getParsedBody();
        $altPfad    = trim((string) ($body['pfad']         ?? ''));
        $neuerName  = trim((string) ($body['neuer_name']   ?? ''));

        if ($altPfad === '' || $neuerName === '') {
            return $this->json(['ok' => false, 'fehler' => 'Fehlende Parameter.'], 400);
        }

        // Sicherheits-Check: neuer Name darf keine Pfad-Trenner enthalten
        if (preg_match('/[\/\\\\]/', $neuerName) || str_starts_with($neuerName, '.')) {
            return $this->json(['ok' => false, 'fehler' => 'Ungültiger Dateiname.'], 400);
        }

        $mediaBase = MedienPfad::wurzel($tree);
        $altVoll   = realpath($mediaBase . $altPfad);
        $realBase  = realpath($mediaBase);

        if ($altVoll === false || $realBase === false || !str_starts_with($altVoll, $realBase) || !is_file($altVoll)) {
            return $this->json(['ok' => false, 'fehler' => 'Originaldatei nicht gefunden.'], 404);
        }

        // Neuer Pfad: gleicher Ordner, neuer Name
        $ordner   = dirname($altPfad);
        $neuPfad  = ($ordner === '.' ? '' : $ordner . '/') . $neuerName;
        $neuVoll  = $mediaBase . $neuPfad;

        if (file_exists($neuVoll)) {
            return $this->json(['ok' => false, 'fehler' => 'Datei mit diesem Namen existiert bereits.'], 409);
        }

        // Rename auf Dateisystem
        if (!@rename($altVoll, $neuVoll)) {
            return $this->json(['ok' => false, 'fehler' => 'Umbenennen fehlgeschlagen.'], 500);
        }

        // collection_pfad aktualisieren
        DB::table('sammlungen_collection_pfad')
            ->where('pfad', '=', $altPfad)
            ->where('gedcom_id', '=', $tree->id())
            ->update(['pfad' => $neuPfad, 'updated_at' => date('Y-m-d H:i:s')]);

        // media_file (webtrees) – falls importiert
        DB::table('media_file')
            ->where('m_file', '=', $tree->id())
            ->where('multimedia_file_refn', '=', $altPfad)
            ->update(['multimedia_file_refn' => $neuPfad]);

        return $this->json([
            'ok'       => true,
            'neu_pfad' => $neuPfad,
            'neu_name' => $neuerName,
        ]);
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

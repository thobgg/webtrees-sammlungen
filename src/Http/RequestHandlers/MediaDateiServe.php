<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers;

use Fig\Http\Message\StatusCodeInterface;
use Sammlungen\Service\MedienPfad;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function basename;
use function filesize;
use function getimagesize;
use function in_array;
use function is_file;
use function pathinfo;
use function realpath;
use function str_starts_with;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * GET /tree/{tree}/archiv/media-datei?pfad=Kirchenb%C3%BCcher-.../datei.pdf
 *
 * Liefert eine Mediendatei aus dem Baum-Medienverzeichnis direkt aus,
 * auch wenn sie noch nicht als GEDCOM-Medienobjekt importiert wurde.
 */
final class MediaDateiServe implements RequestHandlerInterface
{
    private const MIME_TYPES = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'mp3'  => 'audio/mpeg',
        'mp4'  => 'video/mp4',
        'avi'  => 'video/x-msvideo',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt'  => 'text/plain',
    ];

    /** Kleiner als das ist keine Vorschau, groesser lohnt die Umrechnung nicht. */
    private const MIN_BREITE = 60;
    private const MAX_BREITE = 2000;

    /** Darunter kostet das Umrechnen mehr, als es spart (gemessen). */
    private const KLEIN_GENUG = 150 * 1024;

    /** @return bool Bildformate, die sich verkleinern lassen. */
    private static function istBild(string $endung): bool
    {
        return in_array($endung, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /**
     * Umrechnen kostet Rechenzeit und Speicher; bei einer Datei, die ohnehin
     * klein ist, kostet es mehr als es spart. Am Beispiel gemessen: ein
     * 136-KB-Bild auszuliefern dauerte 54 ms, dasselbe Bild zu verkleinern
     * 149 ms bei 65 KB Ergebnis. Der Blick in den Dateikopf ist billig,
     * `getimagesize()` liest nicht das ganze Bild.
     */
    private static function lohntVerkleinern(string $datei, int $breite): bool
    {
        if (filesize($datei) <= self::KLEIN_GENUG) {
            return false;
        }

        $masse = @getimagesize($datei);

        return $masse === false || $masse[0] > $breite;
    }

    public function __construct(
        private readonly ResponseFactoryInterface $response_factory,
        private readonly StreamFactoryInterface   $stream_factory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();

        // Nur Mitglieder des Baums dürfen Mediendateien abrufen
        if (!Auth::isMember($tree, $user)) {
            return $this->response_factory->createResponse(StatusCodeInterface::STATUS_FORBIDDEN);
        }

        $pfad = Validator::queryParams($request)->string('pfad', '');

        // Sicherheit: Pfad normalisieren, keine Traversal-Angriffe
        $pfad = str_replace(['\\', '//'], ['/', '/'], $pfad);
        $pfad = preg_replace('/\.\.+/', '', $pfad) ?? '';
        $pfad = ltrim($pfad, '/');

        $mediaBase = MedienPfad::wurzel($tree);
        $fullPath  = $mediaBase . $pfad;

        $realBase = realpath($mediaBase);
        $realFile = realpath($fullPath);

        if (
            $realBase === false
            || $realFile === false
            || !str_starts_with($realFile, $realBase)
            || !is_file($realFile)
        ) {
            return $this->response_factory->createResponse(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $extension = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
        $mime      = self::MIME_TYPES[$extension] ?? 'application/octet-stream';

        // Verkleinerte Fassung, wenn eine Breite verlangt wird.
        //
        // Ohne das schickt eine Rasterseite die Originale: gemessen 174 MB fuer
        // 50 Bilder, im Mittel mit der 21-fachen Breite dessen, was auf dem
        // Schirm ankommt - also rund der 450-fachen Pixelzahl. Das Telefon
        // laedt nicht nur lange, es dekodiert auch jedes Bild in voller
        // Aufloesung und ruckelt beim Drehen.
        //
        // Erzeugt wird mit dem Bildstapel von webtrees selbst: derselbe Treiber
        // (Imagick oder GD, je nachdem was der Server hat), dieselbe Qualitaet,
        // keine zusaetzliche Abhaengigkeit fuer andere Installationen.
        $breite = Validator::queryParams($request)->integer('w', 0);

        if ($breite > 0 && self::istBild($extension) && self::lohntVerkleinern($realFile, $breite)) {
            $breite = max(self::MIN_BREITE, min(self::MAX_BREITE, $breite));

            $antwort = Registry::imageFactory()->thumbnailResponse(
                $tree->mediaFilesystem(),
                $pfad,
                $breite,
                (int) round($breite * 4),   // Hoehe nicht begrenzen: 'contain' nimmt die kleinere Vorgabe
                'contain'
            );

            // Die Fabrik liefert bei Fehlern ein Platzhalterbild aus. Das waere
            // hier schlechter als das Original - lieber gross als kaputt.
            if ($antwort->getStatusCode() === StatusCodeInterface::STATUS_OK
                && !$antwort->hasHeader('x-thumbnail-exception')) {
                return $antwort->withHeader('Cache-Control', 'private, max-age=86400');
            }
        }

        $stream = $this->stream_factory->createStreamFromFile($realFile, 'rb');

        return $this->response_factory
            ->createResponse(StatusCodeInterface::STATUS_OK)
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Length', (string) filesize($realFile))
            ->withHeader('Content-Disposition', 'inline; filename="' . basename($realFile) . '"')
            ->withHeader('Cache-Control', 'private, max-age=3600')
            ->withBody($stream);
    }
}

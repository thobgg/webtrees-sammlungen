<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Sammlungen\Cache\ApcuCacheService;
use Sammlungen\Service\ArchivAblage;
use Sammlungen\Service\ArchivAblageException;
use Sammlungen\Service\CollectionService;
use Sammlungen\Service\ExifService;
use Sammlungen\ViewModel\ApiViewModel;
use Throwable;

use function basename;
use function trim;

use const UPLOAD_ERR_OK;

/**
 * POST /tree/{tree}/archiv/api/hochladen   (multipart/form-data)
 *
 *   file          die Datei
 *   ordner        Unterordner des Medienordners, leer fuer den Hauptordner; muss es geben
 *   beschreibung, datum (YYYY, YYYY-MM oder YYYY-MM-DD), personen, keywords (je durch Komma getrennt)
 *                 optional - werden bei Bildern als EXIF/XMP in die Datei geschrieben
 *   sammlung      Slug einer thematischen Sammlung, der die Datei gleich zugeordnet wird (optional)
 *
 * Fuer "unterwegs festhalten": ein Foto aus der Schublade abfotografieren, sagen wer drauf ist, fertig.
 * Es entsteht eine Datei im Archiv, kein Medienobjekt und keine Verknuepfung - das bleibt Schreibtischarbeit.
 *
 * Darf, wer in webtrees Medien hochladen darf (canUploadMedia). Die Metadaten gehoeren zum Upload und duerfen mit;
 * EXIF an vorhandenen Dateien aendern ist dagegen Verwalterarbeit (ApiExif).
 */
final class ApiHochladen extends AbstractApiHandler
{
    public function __construct(
        private readonly ArchivAblage      $ablage,
        private readonly ExifService       $exifService,
        private readonly CollectionService $collectionService,
        private readonly ApcuCacheService  $cache,
        private readonly ApiViewModel      $viewModel,
    ) {}

    protected function antworten(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $this->fehler(StatusCodeInterface::STATUS_METHOD_NOT_ALLOWED, 'post-required');
        }

        if (!Auth::canUploadMedia($tree, Validator::attributes($request)->user())) {
            return $this->fehler(StatusCodeInterface::STATUS_FORBIDDEN, 'upload-not-allowed');
        }

        $body  = (array) $request->getParsedBody();
        $datei = $request->getUploadedFiles()['file'] ?? null;

        if (!$datei instanceof UploadedFileInterface || $datei->getError() !== UPLOAD_ERR_OK) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'upload-failed');
        }

        $meta = Metadaten::ausFormular($body);

        if ($meta === null) {
            return $this->fehler(StatusCodeInterface::STATUS_BAD_REQUEST, 'bad-date');
        }

        try {
            $pfad = $this->ablage->ablegen($tree, (string) ($body['ordner'] ?? ''), $datei);
        } catch (ArchivAblageException $e) {
            return $this->fehler($e->status, $e->fehlercode);
        }

        // Metadaten: bei einem Bild in die Datei, sonst gehen sie verloren - das sagt die Antwort.
        $exif    = null;
        $hinweis = null;

        if (!$meta->leer()) {
            if (!ArchivAblage::istBild($pfad)) {
                $hinweis = 'not-image';
            } else {
                try {
                    $this->exifService->schreibeMeta(
                        $this->exifService->fullPath($tree, $pfad),
                        $meta->beschreibung, $meta->datum, $meta->personen, $meta->keywords, $tree
                    );
                    $exif = true;
                } catch (Throwable) {
                    // Kein Imagick auf dem Server oder Datei nicht schreibbar: die Datei liegt trotzdem im Archiv.
                    $exif    = false;
                    $hinweis = 'exif-failed';
                }
            }
        }

        $slug = trim((string) ($body['sammlung'] ?? ''));

        if ($slug !== '') {
            $sammlung = $this->collectionService->findeNachSlug($tree, $slug);

            if ($sammlung === null || ($sammlung->ordner ?? '') !== '') {
                $hinweis ??= 'unknown-collection';
            } else {
                $this->collectionService->pfadZuordnen($tree, $sammlung->id, $pfad, null);
            }
        }

        // Zaehler der Uebersicht (Dateien je Ordner, freier Bestand) sind bis zu fuenf Minuten gemerkt.
        $this->cache->flush();

        return $this->json([
            'ok'      => true,
            'pfad'    => $pfad,
            'datei'   => basename($pfad),
            'exif'    => $exif,
            'hinweis' => $hinweis,
            'eintrag' => $this->viewModel->eintrag($tree, $pfad),
        ], StatusCodeInterface::STATUS_CREATED);
    }
}

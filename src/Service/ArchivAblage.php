<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Tree;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;

use function basename;
use function in_array;
use function pathinfo;
use function preg_replace;
use function str_contains;
use function str_replace;
use function strtolower;
use function strtr;
use function trim;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/** Ein Upload, der nicht abgelegt werden kann - mit dem Code, den die App liest, und dem HTTP-Status dazu. */
final class ArchivAblageException extends \RuntimeException
{
    public function __construct(public readonly string $fehlercode, public readonly int $status)
    {
        parent::__construct($fehlercode);
    }
}

/**
 * Legt eine hochgeladene Datei im Medienordner des Baums ab - als Datei, nicht als Medienobjekt. Das ist der
 * Unterschied zum Hochladen in webtrees: die Datei gehoert zum Archiv, nicht zu einer Person; ob sie spaeter ein
 * Medienobjekt wird, entscheidet jemand am Schreibtisch.
 *
 * Geprueft wird wie bei webtrees selbst (gesperrte Zeichen und Endungen), geschrieben ueber dasselbe Dateisystem.
 * Anders als dort wird eine vorhandene Datei nicht ueberschrieben und der Upload nicht still in den Hauptordner
 * verschoben: der Name bekommt eine Nummer, der Ordner bleibt.
 */
final class ArchivAblage
{
    private const BILD_FORMATE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** @return string der relative Pfad ab Medienordner, unter dem die Datei jetzt liegt */
    public function ablegen(Tree $tree, string $ordner, UploadedFileInterface $datei): string
    {
        $ordner = trim(str_replace('\\', '/', $ordner), '/');

        if (str_contains($ordner, '..')) {
            throw new ArchivAblageException('folder-not-found', 404);
        }

        $fs = $tree->mediaFilesystem();

        if ($ordner !== '' && !$fs->directoryExists($ordner)) {
            throw new ArchivAblageException('folder-not-found', 404);
        }

        $name = self::dateinameBereinigen((string) $datei->getClientFilename());

        if ($name === '') {
            throw new ArchivAblageException('bad-filename', 400);
        }

        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($endung === '' || in_array($endung, MediaFileService::BLOCKED_EXTENSIONS, true)) {
            throw new ArchivAblageException('blocked-extension', 400);
        }

        $pfad = self::freierName(
            static fn (string $p): bool => $fs->fileExists($p),
            $ordner === '' ? '' : $ordner . '/',
            $name
        );

        try {
            $fs->writeStream($pfad, $datei->getStream()->detach());
        } catch (FilesystemException) {
            throw new ArchivAblageException('upload-failed', 500);
        }

        return $pfad;
    }

    public static function istBild(string $pfad): bool
    {
        return in_array(strtolower(pathinfo($pfad, PATHINFO_EXTENSION)), self::BILD_FORMATE, true);
    }

    /**
     * Ein Dateiname, der im Medienordner nichts anrichtet: kein Pfad, keine Steuerzeichen, kein Doppelpunkt
     * (den sperrt webtrees), nicht versteckt, kein Punkt am Ende. Was uebrig bleibt, darf so heissen.
     */
    public static function dateinameBereinigen(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = strtr($name, [':' => '-']);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        return trim($name, " .");
    }

    /**
     * "foto.jpg", sonst "foto-2.jpg", "foto-3.jpg" ... - der erste Name, den es noch nicht gibt.
     *
     * @param callable(string):bool $existiert  ob ein Pfad schon belegt ist
     */
    public static function freierName(callable $existiert, string $praefix, string $name): string
    {
        if (!$existiert($praefix . $name)) {
            return $praefix . $name;
        }

        $stamm   = pathinfo($name, PATHINFO_FILENAME);
        $endung  = pathinfo($name, PATHINFO_EXTENSION);
        $endung  = $endung === '' ? '' : '.' . $endung;

        for ($n = 2; ; $n++) {
            $kandidat = $praefix . $stamm . '-' . $n . $endung;

            if (!$existiert($kandidat)) {
                return $kandidat;
            }
        }
    }
}

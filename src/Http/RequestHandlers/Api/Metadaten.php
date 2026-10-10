<?php

declare(strict_types=1);

namespace Sammlungen\Http\RequestHandlers\Api;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function explode;
use function in_array;
use function preg_match;
use function trim;

/**
 * Die vier Felder, die eine App zu einem Bild mitgeben kann - aus dem Formular gelesen und geprueft. Personen und
 * Schlagwoerter kommen durch Komma getrennt, wie in der Lightbox.
 */
final class Metadaten
{
    /**
     * @param list<string> $personen
     * @param list<string> $keywords
     */
    private function __construct(
        public readonly string $beschreibung,
        public readonly string $datum,
        public readonly array  $personen,
        public readonly array  $keywords,
    ) {}

    /**
     * @param array<string,mixed> $body
     * @return self|null  null: das Datum ist keins (erlaubt: YYYY, YYYY-MM, YYYY-MM-DD)
     */
    public static function ausFormular(array $body): ?self
    {
        $datum = trim((string) ($body['datum'] ?? ''));

        if (!self::datumGueltig($datum)) {
            return null;
        }

        return new self(
            trim((string) ($body['beschreibung'] ?? '')),
            $datum,
            self::liste((string) ($body['personen'] ?? '')),
            self::liste((string) ($body['keywords'] ?? '')),
        );
    }

    /**
     * Nur die Felder, die mitgeschickt wurden - fuer ExifService::aendereMeta.
     * Was fehlt, bleibt in der Datei, wie es ist; ein leer mitgeschicktes Feld
     * leert. Die Gesichter (`bereiche`) prueft der Aufrufer.
     *
     * @param array<string,mixed> $body
     * @return array{beschreibung?:string, datum?:string, datumUnsicher?:bool, personen?:list<string>, keywords?:list<string>}|null
     *         null: das Datum ist keins
     */
    public static function mitgeschickt(array $body): ?array
    {
        $felder = [];

        if (array_key_exists('beschreibung', $body)) {
            $felder['beschreibung'] = trim((string) $body['beschreibung']);
        }
        if (array_key_exists('datum', $body)) {
            $felder['datum'] = trim((string) $body['datum']);
            if (!self::datumGueltig($felder['datum'])) {
                return null;
            }
        }
        if (array_key_exists('datum_unsicher', $body)) {
            $felder['datumUnsicher'] = in_array((string) $body['datum_unsicher'], ['1', 'true', 'on'], true);
        }
        if (array_key_exists('personen', $body)) {
            $felder['personen'] = self::liste((string) $body['personen']);
        }
        if (array_key_exists('keywords', $body)) {
            $felder['keywords'] = self::liste((string) $body['keywords']);
        }

        return $felder;
    }

    public function leer(): bool
    {
        return $this->beschreibung === '' && $this->datum === '' && $this->personen === [] && $this->keywords === [];
    }

    public static function datumGueltig(string $datum): bool
    {
        return $datum === '' || preg_match('/^\d{4}(-(0[1-9]|1[0-2])(-(0[1-9]|[12]\d|3[01]))?)?$/', $datum) === 1;
    }

    /** @return list<string> */
    public static function liste(string $roh): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $roh)), static fn (string $s): bool => $s !== ''));
    }
}

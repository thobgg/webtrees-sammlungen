<?php

declare(strict_types=1);

namespace Sammlungen\Service;

use function explode;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Welche der angebotenen Sprachen zu einem Wunsch passt: "de-DE" -> "de",
 * "en" -> "en-US", "nl-BE" -> "nl". Ohne Treffer null, dann bleibt es bei der
 * Sprache des Kontos. Reine Funktion ohne webtrees, damit sie sich ohne
 * Installation pruefen laesst.
 */
final class Sprachwahl
{
    /**
     * @param list<string> $verfuegbar  Sprachkennungen, die diese Installation anbietet
     */
    public static function passend(string $gewuenscht, array $verfuegbar): ?string
    {
        $gewuenscht = strtolower(trim($gewuenscht));

        if ($gewuenscht === '') {
            return null;
        }

        $primaer = explode('-', $gewuenscht)[0];

        $regeln = [
            static fn (string $tag): bool => strtolower($tag) === $gewuenscht,
            static fn (string $tag): bool => strtolower($tag) === $primaer,
            static fn (string $tag): bool => $tag === 'en-US' && $primaer === 'en',
            static fn (string $tag): bool => str_starts_with(strtolower($tag), $primaer . '-'),
        ];

        foreach ($regeln as $regel) {
            foreach ($verfuegbar as $tag) {
                if ($regel($tag)) {
                    return $tag;
                }
            }
        }

        return null;
    }
}

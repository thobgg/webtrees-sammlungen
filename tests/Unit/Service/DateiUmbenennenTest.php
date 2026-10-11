<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sammlungen\Service\DateiUmbenennen;

#[CoversClass(DateiUmbenennen::class)]
final class DateiUmbenennenTest extends TestCase
{
    /** @return array<string,array{string,string|null}> */
    public static function namen(): array
    {
        return [
            'gut'              => ['Hochzeit 1928.jpg', null],
            'Endung gross'     => ['hochzeit.JPG', null],
            'leer'             => ['', 'bad-name'],
            'Pfad'             => ['../x.jpg', 'bad-name'],
            'Backslash'        => ['a\\b.jpg', 'bad-name'],
            'versteckt'        => ['.htaccess.jpg', 'bad-name'],
            'Doppelpunkt'      => ['a:b.jpg', 'bad-name'],
            'Steuerzeichen'    => ["a\nb.jpg", 'bad-name'],
            'Punkt am Ende'    => ['hochzeit.jpg.', 'bad-name'],
            'nur Endung'       => ['.jpg', 'bad-name'],
            'andere Endung'    => ['hochzeit.php', 'extension-changed'],
            'Endung weg'       => ['hochzeit', 'extension-changed'],
            'doppelte Endung'  => ['hochzeit.php.png', 'extension-changed'],
        ];
    }

    #[DataProvider('namen')]
    public function testNameFehler(string $neu, ?string $code): void
    {
        self::assertSame($code, DateiUmbenennen::nameFehler('fotos/alt.jpg', $neu));
    }

    public function testNeuerPfad(): void
    {
        self::assertSame('fotos/2026/neu.jpg', DateiUmbenennen::neuerPfad('fotos/2026/alt.jpg', 'neu.jpg'));
        self::assertSame('neu.jpg', DateiUmbenennen::neuerPfad('alt.jpg', 'neu.jpg'));
    }
}

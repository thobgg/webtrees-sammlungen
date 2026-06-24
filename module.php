<?php

/**
 * Sammlungen – webtrees module entry point
 *
 * Diese Datei wird von webtrees automatisch eingebunden
 * wenn das Modul in modules_v4/sammlungen/ liegt.
 * Sie muss eine Instanz der Modulklasse zurückgeben.
 */

declare(strict_types=1);

use Sammlungen\SammlungenModule;

// Composer-Autoloader des Moduls laden (falls kein globaler Autoloader greift)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Sammlungen\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    });
}

require_once __DIR__ . '/src/SammlungenModule.php';

return new SammlungenModule();

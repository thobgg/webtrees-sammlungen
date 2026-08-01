<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Die Modulklasse erbt von webtrees' AbstractModule – die Klasse liegt nicht im
// vendor/ des Moduls, sondern in der webtrees-Installation darueber. Liegt das
// Modul wie im Betrieb unter <webtrees>/modules_v4/sammlungen/, laedt dieser
// Autoloader sie nach. Fehlt er (Checkout ausserhalb einer Installation), laufen
// die reinen Service-/DTO-Tests weiter; SammlungenModuleTest ueberspringt sich.
$webtreesAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';

if (is_file($webtreesAutoload)) {
    require $webtreesAutoload;
}

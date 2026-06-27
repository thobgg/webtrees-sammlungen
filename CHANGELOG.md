# Changelog

Alle nennenswerten Änderungen an diesem Modul werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
und das Projekt nutzt [Semantic Versioning](https://semver.org/lang/de/).

## [1.0.11] – 2026-06-27

### Geändert
- **„Nicht eingebundene Medien" → „Freier Bestand":** Die Übersicht der Medien
  ohne Personen-/Familien-Verknüpfung heißt jetzt **„Freier Bestand"** und wird
  nicht mehr als Warnung (rot) dargestellt. Hintergrund: In diesem Modul ist ein
  Medium ohne Stammbaum-Verknüpfung ein gewollter, oft dauerhafter Archiv-Zustand
  – kein Fehler. Beschreibungstexte entsprechend angepasst; Übersetzungen de/en/nl
  aktualisiert. (Konzept-Klärung mit hartenthaler, #4)

### Behoben
- **Fehlende FontAwesome-Icons:** In der Bestands-Ansicht kamen Icons zum Einsatz,
  die webtrees nicht bündelt (`fa-unlink`, `fa-check-circle`) und daher als
  Platzhalter erschienen – ersetzt durch gebündelte Icons.

### Dokumentation
- **„Warum dieses Modul?" geschärft:** Einleitung ergänzt, die den Grundgedanken
  benennt – das Familienarchiv (Fotos, Urkunden, Briefe, Tonaufnahmen, Filme …)
  lebt dort, wo die Familie ohnehin als webtrees-Nutzer ist, statt in einem
  weiteren Insel-Tool. (de/en/nl)

## [1.0.10] – 2026-06-27

### Neu
- **Video, Audio & Dokumente in Foto-Galerien:** In Foto- und Raster-Sammlungen
  werden Nicht-Bild-Dateien (Video, Audio, PDF, Office-Dokumente) jetzt unter der
  Galerie als eigene Liste angezeigt und lassen sich öffnen/abspielen – statt nur
  als „N weitere Dateien werden nicht angezeigt" vermerkt zu werden. Die Einträge
  haben farbige Typ-Badges (Video, Audio, PDF, …). (angeregt von hartenthaler)

## [1.0.9] – 2026-06-26

### Behoben
- **Gepickte Bilder erschienen nicht („Sammlung noch leer"):** Beim Hinzufügen
  von Bildern zu einer manuellen Sammlung wurde der Pfad-Cache mit einem nicht
  passenden Schlüssel invalidiert – die Galerie zeigte bis zum Cache-Ablauf
  veraltete (leere) Daten, obwohl die Bilder gespeichert waren. Invalidierung
  läuft jetzt über `flush()`. (gemeldet von hartenthaler)
- **Lightbox in der manuellen Galerie:** In manuellen (gepickten) Sammlungen
  ließen sich Fotos nicht in der Lightbox öffnen – Modal *und* JavaScript
  fehlten dort. Beides ist jetzt in ein gemeinsames Partial (`_lightbox.phtml`)
  ausgelagert und auch in der manuellen Galerie eingebunden.

## [1.0.8] – 2026-06-25

### Behoben
- **Foto-Zähler korrigiert:** In Ordner-Sammlungen wurde die Gesamtzahl *aller*
  Dateien als „X Fotos" angezeigt, obwohl nur Bildformate (jpg/jpeg/png/gif/webp)
  gerendert werden. Der Zähler zählt/paginiert jetzt nur darstellbare Dateien;
  übrige (Video/Audio/…) werden als „N weitere Dateien … nicht angezeigt"
  ausgewiesen. (gemeldet von hartenthaler)
- **Speichern-Button sichtbar:** Der EXIF-Speichern- und der Datei-Umbenennen-Button
  konnten in manchen webtrees-Themes weiß-auf-weiß (unsichtbar) erscheinen; sie haben
  jetzt theme-feste Farben.

## [1.0.7] – 2026-06-25

### Behoben
- **Regression aus 1.0.6:** Der GROUP-BY-Umbau nutzte in `orderByRaw('MAX(mf.…)')`
  den **unpräfixierten** Tabellen-Alias. Da webtrees Aliase präfixt (`mf` → `wt_mf`),
  warf die Übersichts-Abfrage `SQLSTATE[42S22] 1054 Unknown column
  'mf.multimedia_file_refn'` — auf **jeder** Installation mit Tabellen-Präfix.
  Jetzt `DB::prefix('mf')` in beiden `orderByRaw`-Stellen
  (`vorschauInOrdner()`, `medienInOrdner()`). (#7)

### Geändert
- Niederländische Übersetzung vervollständigt/aktualisiert (Beitrag von
  TheDutchJewel, #10), inkl. APCu-Fallback-String.

## [1.0.6] – 2026-06-25

### Behoben
- **SQL-Crash beim Öffnen von Ordnern** unter MySQL-Strict-Mode
  (`ONLY_FULL_GROUP_BY`): `DISTINCT` mit `ORDER BY` auf eine Spalte außerhalb
  der SELECT-Liste löste `SQLSTATE[HY000] 3065` aus. `vorschauInOrdner()` und
  `medienInOrdner()` nutzen jetzt `GROUP BY` + `MAX()`. (#6)
- **Unübersetzter Hinweis** „APCu ist nicht verfügbar …" auf der
  Einstellungsseite: fehlender Eintrag in allen Übersetzungskatalogen ergänzt
  (de/en/nl). (#9)

## [1.0.5] – 2026-06-24

### Hinzugefügt
- **Vollständige englische Übersetzung** (`en.po` / `en.mo`, alle ~120 Texte
  + Pluralformen). Englischsprachige Nutzer (`en-GB` / `en-US`) sehen die
  Oberfläche jetzt auf Englisch statt auf Deutsch.
- **Vollständiger Übersetzungs-Katalog**: alle im Code verwendeten Texte sind
  jetzt erfasst (vorher nur ~34 von ~120). Deutsche Katalogdatei (`de.po`)
  vervollständigt; niederländische `nl.po` als vollständige Vorlage (bestehende
  Übersetzungen erhalten, fehlende offen zur Ergänzung).

### Geändert
- `customTranslations()` nutzt einen 2-Buchstaben-Fallback (`en-GB` → `en`),
  damit eine Sprachdatei alle Regionalvarianten abdeckt.

## [1.0.4] – 2026-06-24

### Behoben
- **Übersetzungen wurden gar nicht geladen:** Das Modul implementierte
  `customTranslations()` nicht, daher griff keine `.mo`-Datei und alle Sprachen
  fielen auf den deutschen Quelltext zurück. Jetzt werden die Sprachdateien aus
  `resources/lang/<sprache>.mo` korrekt geladen – die niederländische
  Übersetzung (von TheDutchJewel) wird damit endlich angezeigt.

### Bekannt
- Der Übersetzungs-Katalog deckt noch nicht alle Texte ab (u. a. Teile der
  Einstellungs-Seite). Diese erscheinen weiterhin auf Deutsch, bis sie ergänzt
  und übersetzt sind.

## [1.0.3] – 2026-06-24

### Hinzugefügt
- README erklärt nun ausführlich, **wie man Sammlungen mit Bildern füllt**
  (Ordner-Sammlung automatisch vs. Album-Sammlung manuell, Begriffe „Quelle",
  „aktiv", Top-Level-Ordner) – de und en.
- README-Installationsabschnitt: Install-ZIP als empfohlene Variante (ohne
  Composer/git) ergänzt.

### Geändert
- Foto-Picker zeigt eine aussagekräftige Meldung, wenn (noch) keine Quelle
  existiert, statt pauschal „Keine Fotos in dieser Quelle" – mit Hinweis, dass
  eine aktive, ordner-basierte Foto-Sammlung als Quelle nötig ist oder die
  Sammlung über das Feld „Medienordner" befüllt werden kann (Ursache aus Issue #4).

## [1.0.2] – 2026-06-24

### Hinzugefügt
- Niederländische Übersetzung (`nl.po` / `nl.mo`) – Beitrag von TheDutchJewel.
- GitHub-Actions-Release-Workflow: bei jedem Tag `v*` wird automatisch ein
  install-fertiges ZIP (Ordner `sammlungen/`) ans Release gehängt. Damit ist
  die Installation ohne Composer/git möglich (entpacken nach `modules_v4/`).

### Behoben
- Direkt-/ZIP-Installation ohne Composer: Fallback-Autoloader in `module.php`
  (kein „Class not found" mehr, wenn `vendor/` fehlt).
- Sammlungs-Zählung respektiert das konfigurierte Tabellen-Präfix
  (`DB::prefix('mf')` statt hartcodiertem `wt_mf`).
- View-Namespace `_sammlungen_` wird korrekt registriert (kein
  „Namespace not found" mehr).

(Fehlerbehebungen beigetragen von Hermann Hartenthaler.)

## [1.0.1] – 2026-05-22

### Geändert
- DB-Tabellen umbenannt: `familienarchiv_collection*` → `sammlungen_collection*`
  (saubere Modul-Identität, kein historischer Altlast-Name mehr).
- Migration ist idempotent: vorhandene `familienarchiv_*`-Tabellen werden bei
  Update einmalig umbenannt, Neuinstaller bekommen direkt die neuen Namen.
- Datenmodell-Section in README aktualisiert.

## [1.0.0] – 2026-05-22

### Erstes eigenständiges Release

Das Modul wurde aus dem früheren kombinierten `Familienarchiv`-Modul herausgelöst und
fokussiert sich auf Foto-/Dokumenten-Sammlungen. Orte-Funktionalität wurde in ein
separates Modul (`ortsregister`) ausgelagert, Quellen-Funktionalität ersatzlos gestrichen
(webtrees-Core deckt dies ab).

### Hinzugefügt
- Galerie-Ansicht für ordner-basierte und manuelle Sammlungen
- Lightbox mit Sidebar-Editor, Thumbnail-Streifen, Tastatur-Navigation
- EXIF-/XMP-Lesen und -Schreiben (Imagick) mit automatischem Tages-Backup
- Abgleich-Sektion EXIF ↔ webtrees (Beschreibung, Personen)
- Datei-Umbenennen aus der Lightbox heraus
- Manuelle Sammlungen (CRUD): Name, Slug, Icon, Farbe, Ansicht (foto/raster/gemischt/dokument)
- Pfad-basierte Sammlungszugehörigkeit (`sammlungen_collection_pfad`)
- „Nicht eingebundene Medien"-Übersicht mit Typ-Aufschlüsselung
- Foto-Picker im Admin für manuelle Sammlungen
- Klickbarer Aktiv-Status-Toggle in der Sammlungs-Verwaltung
- APCu-Cache mit Array-Fallback und konfigurierbarem TTL
- Deutsche Übersetzung (`de.po` / `de.mo`)
- Architektur: ViewModel-Schicht für Daten-Aufbereitung, externes JS-Asset (`sammlung-galerie.js`)
- Test-Suite (PHPUnit 11) mit Unit- und Integration-Tests (SQLite In-Memory)

### Datenmodell
Drei DB-Tabellen werden automatisch angelegt:
- `sammlungen_collection` (Sammlungs-Definitionen)
- `sammlungen_collection_medium` (M:N mit webtrees-Medien)
- `sammlungen_collection_pfad` (M:N mit Dateipfaden)

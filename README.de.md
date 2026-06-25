# Sammlungen – webtrees Custom Module

[🇬🇧 English](README.md) · 🇩🇪 **Deutsch** · [🇳🇱 Nederlands](README.nl.md)

**Foto- und Dokumenten-Sammlungen für [webtrees](https://webtrees.net) mit EXIF-Anreicherung, Galerie, Lightbox und Abgleich gegen GEDCOM-Daten.**

| | |
|---|---|
| Modul-Name | `sammlungen` |
| Version | 1.0.7 |
| webtrees | 2.2.x |
| PHP | 8.2 – 8.4 |
| Lizenz | GPL-3.0-or-later |

---

## Was macht das Modul?

webtrees verwaltet zwar Medienobjekte als Teil des GEDCOM-Standards, bietet aber keine
**visuellen Foto-Sammlungen** mit der Tiefe, die Familien-Archive brauchen. `Sammlungen`
ergänzt genau das:

- **Foto- und Dokumenten-Galerien** gruppiert nach Themen (Familienfotos, Grabsteine,
  Kirchenbuchakten, Briefe, Militärische Dokumente, …)
- **EXIF/XMP-Daten direkt aus den Bilddateien** lesen und editieren – ohne Umweg über
  GEDCOM-Tags
- **Bidirektionaler Abgleich** zwischen Foto-Metadaten und webtrees-Personenverknüpfungen
- **Pfad-basierte Sammlungen** für Fotos, die noch nicht in webtrees importiert sind

### Sammlungs-Übersicht

Übersicht aller Sammlungen, gruppiert nach Archiv-Ordnern und thematischen Gruppen:

![Sammlungs-Übersicht](docs/images/01-uebersicht.png)

### Foto-Lightbox mit EXIF-Editor

Klick auf ein Foto öffnet die Lightbox mit Sidebar – EXIF-Daten anzeigen, editieren und
direkt in die Datei zurückschreiben (automatisches Tages-Backup vor jeder Änderung):

![Lightbox mit Sidebar](docs/images/02-lightbox.png)

Die Sidebar zeigt EXIF und XMP, ermöglicht das Editieren von Beschreibung, Datum,
Personen und Keywords, vergleicht die Werte mit den webtrees-Personen-Verknüpfungen
und bietet Ein-Klick-Übernahme bei Unterschieden.

### Dokumenten-Listen

Für Sammlungen mit PDFs/Dokumenten (Kirchenbuch-Akten, Personenstandsregister, …)
zeigt das Modul automatisch eine Listenansicht statt Foto-Raster:

![Dokumenten-Liste](docs/images/04-dokumente.png)

### Admin-Verwaltung

Eigene Sammlungen anlegen mit Name, Icon, Farbe und Ansichts-Typ (Foto-Galerie,
Dokumenten-Liste, gemischt). Aktiv-Status per Ein-Klick-Toggle:

![Sammlungen verwalten](docs/images/03-admin.png)

---

## Funktionsumfang im Detail

- **Galerien** für Foto-Sammlungen (`Familienfotos`, `Grabsteine`, `Konterfeis`, eigene Sammlungen)
- **Lightbox** mit Tastatur-Navigation, Thumbnail-Streifen und Sidebar
- **EXIF/XMP-Lesen** (Beschreibung, Datum, Personen, Keywords) mit Imagick-Cache
- **EXIF/XMP-Schreiben** mit automatischem Tages-Backup vor jeder Änderung
- **Abgleich EXIF ↔ webtrees** (Beschreibung, Personen) mit Ein-Klick-Übernahme
- **Datei umbenennen** direkt in der Lightbox (DB wird mit-aktualisiert)
- **Manuelle Sammlungen** (CRUD): Name, Slug, Icon, Farbe, Ansicht
- **Pfad-basierte Zuordnung**: auch nicht-importierte Bilder können Sammlungen zugewiesen werden
- **„Nicht eingebundene Medien"** als eigene Übersicht (Medien ohne Personen-/Familien-Link)
- **APCu-Cache** für teure Queries mit konfigurierbarem TTL

## Voraussetzungen

- webtrees ≥ 2.2.0
- PHP ≥ 8.2 mit Erweiterungen: `imagick`, `gd`, `apcu` (optional, fällt sonst auf Array-Cache zurück)
- MariaDB / MySQL ≥ 10.5

## Installation

### Variante A: Install-ZIP (empfohlen – ohne Composer/git)

1. Lade das aktuelle `sammlungen-vX.Y.Z.zip` von der
   [Releases-Seite](https://github.com/thobgg/webtrees-sammlungen/releases/latest).
2. Entpacke es – du erhältst einen Ordner `sammlungen/`.
3. Kopiere diesen Ordner in das Verzeichnis `modules_v4/` deiner
   webtrees-Installation (Ziel: `modules_v4/sammlungen/`).

### Variante B: Per git + Composer (für Entwickler)

```bash
cd modules_v4
git clone https://github.com/thobgg/webtrees-sammlungen.git sammlungen
cd sammlungen
composer install --no-dev
```

Anschließend in webtrees unter **Steuerleiste → Module → Custom Modules** das Modul
„Sammlungen" aktivieren. Die DB-Tabellen werden beim ersten Aufruf automatisch angelegt.

## Bedienung

1. **Menü „Sammlungen"** in der webtrees-Navigation öffnet die Übersicht
2. **Klick auf eine Sammlung** öffnet die Galerie (Foto-Raster oder Dokumenten-Liste)
3. **Klick auf ein Foto** öffnet die Lightbox mit Pfeil-Tasten-Navigation
4. **Bleistift-Icon in der Lightbox** öffnet die Sidebar mit EXIF-Editor
5. **Admin-Bereich** über `Steuerleiste → Module → Sammlungen → Konfiguration` erreichbar:
   - Eigene Sammlungen anlegen/bearbeiten/löschen
   - Cache-TTL und Seitengröße konfigurieren
   - Footer-Link ein-/ausschalten

## Sammlungen mit Bildern füllen

Es gibt **zwei Arten** von Sammlungen – der Unterschied liegt allein im Feld
**„Medienordner"** in der Sammlungs-Maske:

### 1. Ordner-Sammlung (empfohlen, automatisch)

Du trägst im Feld **„Medienordner"** einen Ordner unter `data/media/` ein
(z. B. `grabsteine`). Die Sammlung enthält dann **automatisch alle Bilder** aus
diesem Ordner (inklusive Unterordner) – neue Dateien erscheinen ohne weiteres
Zutun. Das ist der normale Weg und der einzige, der für große Bestände
praktikabel ist.

1. Ordner unter `data/media/` anlegen (z. B. `data/media/grabsteine/`) und die
   Bilder hineinlegen.
2. Sammlung anlegen, bei **„Medienordner"** den Ordnernamen eintragen
   (`grabsteine`), Anzeigetyp **„Fotogalerie"** wählen.
3. **„Sichtbar (aktiv)"** einschalten, speichern. Fertig – alle Bilder sind
   automatisch enthalten.

### 2. Album-Sammlung (manuell kuratiert)

Lässt du **„Medienordner" leer**, entsteht ein freies Album, das du von Hand
bestückst: über den **📷-Button** in der Sammlungs-Verwaltung wählst du einzelne
Bilder aus.

Wichtig: Der 📷-Picker bietet keine beliebigen Dateien an, sondern nur Bilder aus
**bestehenden Ordner-Sammlungen** (Weg 1) – eine solche Sammlung dient ihm dann als
**Bildquelle**. Damit eine Ordner-Sammlung als Bildquelle erscheint, muss sie alle
drei Bedingungen erfüllen:

- sie ist **sichtbar geschaltet** – der Schalter **„Sichtbar (aktiv)"** in der
  Bearbeitung steht an;
- sie hat einen **Medienordner direkt unter `data/media/`** – ein Ordner ohne `/`
  im Namen (z. B. `grabsteine`, nicht `grabsteine/2024`);
- ihr Anzeigetyp ist **„Fotogalerie"** oder **„Foto-Raster"**.

Für ein manuelles Album brauchst du also **zuerst mindestens eine solche
Ordner-Sammlung** (Weg 1). Ohne sie hat der Picker keine Bildquelle und meldet
„keine Bilder vorhanden".

> **Faustregel:** Alle Bilder eines Themas liegen in einem Ordner? → Ordner-Sammlung
> (Weg 1). Du willst gezielt einzelne Bilder über mehrere Ordner hinweg
> zusammenstellen? → Album-Sammlung (Weg 2).

## Architektur

```
sammlungen/
├── module.php                       ← webtrees-Einstiegspunkt
├── composer.json                    ← Composer-Manifest
├── src/
│   ├── SammlungenModule.php        ← Modul-Hauptklasse (Routes, Menü, Migrations)
│   ├── Cache/                       ← APCu-Cache mit Array-Fallback
│   ├── Dto/                         ← Data Transfer Objects (SammlungDto)
│   ├── Http/RequestHandlers/        ← PSR-15 Handler (Galerie, Admin, AJAX-Endpunkte)
│   ├── Repository/                  ← DB-Zugriff (SammlungenRepository)
│   ├── Service/                     ← Business Logic (CollectionService, ExifService)
│   └── ViewModel/                   ← Daten-Aufbereitung (SammlungenViewModel)
├── resources/
│   ├── js/sammlung-galerie.js      ← Lightbox + Abgleich + Rename + EXIF-Save
│   ├── views/ + partials/          ← PHP-Templates
│   └── lang/                        ← de.po, de.mo (Übersetzungen)
└── docs/images/                     ← Screenshots für diese README
```

## Routing

Alle URLs sind unter `/tree/{tree}/archiv/…` erreichbar:

| Route-Name | URL | Methode |
|---|---|---|
| `sammlungen.sammlungen` | `/sammlungen[?kategorie=slug]` | GET |
| `sammlungen.sammlung-medium` | `/sammlung-medium` | POST |
| `sammlungen.exif-schreiben` | `/exif-schreiben` | POST |
| `sammlungen.datei-umbenennen` | `/datei-umbenennen` | POST |
| `sammlungen.media-datei` | `/media-datei` | GET |
| `sammlungen.admin.sammlungen` | `/admin/sammlungen` | GET |
| `sammlungen.admin.sammlungen.edit` | `/admin/sammlungen/edit` | POST |
| `sammlungen.admin.sammlungen.toggle-aktiv` | `/admin/sammlungen/toggle-aktiv` | POST |
| `sammlungen.admin.config` | `/admin/config` | POST |

## Datenmodell

```sql
sammlungen_collection           -- Definitionen: name, slug, icon, farbe, ansicht, ordner
sammlungen_collection_medium    -- M:N webtrees-Medium ↔ Sammlung (m_id-basiert)
sammlungen_collection_pfad      -- M:N Pfad ↔ Sammlung (auch nicht-importierte Bilder)
```

## Konfiguration

In der Admin-UI einstellbar:

- **Cache-TTL** (Default: 900 s)
- **Seiten-Größe** (Default: 50)
- **Footer-Link anzeigen** (ja/nein)

## Verwandte Module

| Modul | Funktion |
|---|---|
| [Ortsregister](https://github.com/thobgg/webtrees-ortsregister) | Schwestermodul für visuelle Orts-Landing-Pages und Foto-Ort-Verknüpfung |

## Lizenz

GPL-3.0-or-later, identisch zu webtrees. Siehe [LICENSE](LICENSE).

## Autor & Support

Thomas Bugge · thomas@bgg-mail.de  
Fragen / Bugs: GitHub-Issues

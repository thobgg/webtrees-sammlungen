# Sammlungen – webtrees Custom Module

🇬🇧 **English** · [🇩🇪 Deutsch](README.de.md) · [🇳🇱 Nederlands](README.nl.md)

**Photo and document collections for [webtrees](https://webtrees.net) with EXIF enrichment, gallery, lightbox and bidirectional sync with GEDCOM data.**

| | |
|---|---|
| Module name | `sammlungen` |
| Version | 1.0.7 |
| webtrees | 2.2.x |
| PHP | 8.2 – 8.4 |
| License | GPL-3.0-or-later |

---

## What does this module do?

webtrees manages media objects as part of the GEDCOM standard, but does not offer
**visual photo collections** with the depth that family archives need. `Sammlungen`
fills exactly that gap:

- **Photo and document galleries** grouped by topic (family photos, gravestones,
  parish records, letters, military documents, …)
- **EXIF/XMP data read and edited directly in the image files** – no detour via
  GEDCOM tags
- **Bidirectional sync** between photo metadata and webtrees person links
- **Path-based collections** for photos that are not (yet) imported into webtrees

### Why this module – and not just the webtrees media list?

The media list is an administration tool. `Sammlungen` is a **viewing experience** – and,
crucially, **built into webtrees**, with everything that brings:

- **Protected by webtrees' user management:** logged-in family members browse the
  galleries; anonymous visitors see the (privacy-protected) tree but **not** the
  collections. You don't build your own access control – webtrees' proven, tiered
  permission model does it for you.
- **Your data stays yours – no vendor lock-in:** unlike MyHeritage or Google Photos
  "albums", your photos and their descriptions remain entirely yours. The **bidirectional
  EXIF/XMP sync** even writes description, date and people back **into the image files** –
  the metadata doesn't just live in the database, it travels with the photos.
- **Photos without a person requirement:** they don't have to hang on individual people
  (no 30 grandma photos buried on one profile), yet stay close to the genealogical content
  and the audience already in webtrees.

Double benefit: the same media are **genealogical evidence** and at the same time a
**presentable, browsable gallery** – for showing within the family as well as for your own
archiving and metadata work.

### Collection overview

Overview of all collections, grouped by archive folders and thematic groups:

![Collection overview](docs/images/01-uebersicht.png)

### Photo lightbox with EXIF editor

Clicking a photo opens the lightbox with sidebar – view EXIF, edit it and write
changes back into the file (automatic daily backup before every write):

![Lightbox with sidebar](docs/images/02-lightbox.png)

The sidebar displays EXIF and XMP, lets you edit description, date, persons and
keywords, compares the values against the webtrees person links and offers
one-click "take over" for any differences.

### Document lists

Collections that contain PDFs/documents (parish records, civil registers, …)
automatically render as list view instead of photo grid:

![Document list](docs/images/04-dokumente.png)

### Admin management

Create your own collections with name, icon, colour and view type (photo gallery,
document list, mixed). Active status with one-click toggle:

![Manage collections](docs/images/03-admin.png)

---

## Feature list

- **Galleries** for photo collections (`Family photos`, `Gravestones`, `Portraits`, custom collections)
- **Lightbox** with keyboard navigation, thumbnail strip and sidebar
- **EXIF/XMP read** (description, date, persons, keywords) with Imagick caching
- **EXIF/XMP write** with automatic daily backup before every change
- **EXIF ↔ webtrees sync** (description, persons) with one-click take-over
- **File rename** directly from the lightbox (DB is updated atomically)
- **Custom collections** (CRUD): name, slug, icon, colour, view
- **Path-based assignment**: even non-imported photos can be added to collections
- **"Unlinked media"** as a separate overview (media without person/family links)
- **APCu cache** for expensive queries with configurable TTL

## Requirements

- webtrees ≥ 2.2.0
- PHP ≥ 8.2 with extensions: `imagick`, `gd`, `apcu` (optional, falls back to array cache otherwise)
- MariaDB / MySQL ≥ 10.5

## Installation

### Option A: install ZIP (recommended – no Composer/git)

1. Download the latest `sammlungen-vX.Y.Z.zip` from the
   [releases page](https://github.com/thobgg/webtrees-sammlungen/releases/latest).
2. Unzip it – you get a folder named `sammlungen/`.
3. Copy that folder into the `modules_v4/` directory of your webtrees
   installation (target: `modules_v4/sammlungen/`).

### Option B: via git + Composer (for developers)

```bash
cd modules_v4
git clone https://github.com/thobgg/webtrees-sammlungen.git sammlungen
cd sammlungen
composer install --no-dev
```

Then activate the module in webtrees under **Control Panel → Modules → Custom Modules**.
The database tables are created automatically on first load.

## Usage

1. **The "Sammlungen" menu** in the webtrees navigation opens the overview.
2. **Click a collection** to open the gallery (photo grid or document list).
3. **Click a photo** to open the lightbox with arrow-key navigation.
4. **The pencil icon in the lightbox** opens the sidebar with the EXIF editor.
5. The **admin area** is reachable via `Control Panel → Modules → Sammlungen → Preferences`:
   - Create / edit / delete custom collections
   - Configure cache TTL and page size
   - Toggle the footer link

## Filling collections with images

There are **two kinds** of collections – the difference is solely the
**"Medienordner" (media folder)** field in the collection form:

### 1. Folder collection (recommended, automatic)

You enter a folder below `data/media/` in the **"Medienordner"** field
(e.g. `grabsteine`). The collection then **automatically contains all images**
from that folder (including sub-folders) – new files appear without any further
action. This is the normal path and the only one that scales to large holdings.

1. Create a folder below `data/media/` (e.g. `data/media/grabsteine/`) and put
   the images into it.
2. Create a collection, enter the folder name in **"Medienordner"**
   (`grabsteine`), choose the display type **"Fotogalerie"** (photo gallery).
3. Turn on **"Sichtbar (aktiv)"** (visible/active), save. Done – all images are
   included automatically.

### 2. Album collection (manually curated)

If you leave **"Medienordner" empty**, you get a free album that you fill by
hand: use the **📷 button** in the collection management to pick individual
images.

Important: the 📷 picker does not offer arbitrary files – it only shows images from
**existing folder collections** (path 1), which then act as its **image source**.
For a folder collection to appear as an image source, it must meet all three
conditions:

- it is **switched visible** – the **"Sichtbar (aktiv)"** (visible/active) toggle in
  its edit form is on;
- it has a **media folder directly below `data/media/`** – a folder with no `/` in
  the name (e.g. `grabsteine`, not `grabsteine/2024`);
- its display type is **"Fotogalerie"** or **"Foto-Raster"**.

So for a manual album you first need **at least one such folder collection** (path 1).
Without one, the picker has no image source and reports "keine Bilder vorhanden"
(no images).

> **Rule of thumb:** All images of a topic live in one folder? → folder collection
> (path 1). You want to assemble individual images from across several folders?
> → album collection (path 2).

## Architecture

```
sammlungen/
├── module.php                       ← webtrees entry point
├── composer.json                    ← Composer manifest
├── src/
│   ├── SammlungenModule.php        ← Main module class (routes, menu, migrations)
│   ├── Cache/                       ← APCu cache with array fallback
│   ├── Dto/                         ← Data Transfer Objects (SammlungDto)
│   ├── Http/RequestHandlers/        ← PSR-15 handlers (gallery, admin, AJAX endpoints)
│   ├── Repository/                  ← DB access (SammlungenRepository)
│   ├── Service/                     ← Business logic (CollectionService, ExifService)
│   └── ViewModel/                   ← Data preparation (SammlungenViewModel)
├── resources/
│   ├── js/sammlung-galerie.js      ← Lightbox + sync + rename + EXIF save
│   ├── views/ + partials/          ← PHP templates
│   └── lang/                        ← de.po, de.mo (German translation)
└── docs/images/                     ← Screenshots for this README
```

## Routing

All URLs live under `/tree/{tree}/archiv/…`:

| Route name | URL | Method |
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

## Data model

```sql
sammlungen_collection           -- Definitions: name, slug, icon, colour, view, folder
sammlungen_collection_medium    -- M:N webtrees medium ↔ collection (m_id-based)
sammlungen_collection_pfad      -- M:N path ↔ collection (also non-imported images)
```

## Configuration

Available in the admin UI:

- **Cache TTL** (default: 900 s)
- **Page size** (default: 50)
- **Show footer link** (yes/no)

## Localisation

The UI is available in **German**, **English** and (partially) **Dutch**.
Translation files live in `resources/lang/` (`de`, `en`, `nl`). The source strings
are wrapped in `I18N::translate()` and German is the source language.

Contributions welcome: copy `resources/lang/nl.po` (a complete, up-to-date template),
translate the empty `msgstr` entries, compile with `msgfmt nl.po -o nl.mo`, and open a
pull request. New languages: copy `en.po` as a starting point and name it `<code>.po`.

## Related modules

| Module | Function |
|---|---|
| [Ortsregister](https://github.com/thobgg/webtrees-ortsregister) | Sister module for visual place landing pages and photo-to-place linking |

## License

GPL-3.0-or-later, same as webtrees. See [LICENSE](LICENSE).

## Author & support

Thomas Bugge · thomas@bgg-mail.de  
Questions / bugs: GitHub Issues

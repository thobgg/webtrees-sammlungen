# Sammlungen – webtrees Custom Module

[🇬🇧 English](README.md) · [🇩🇪 Deutsch](README.de.md) · 🇳🇱 **Nederlands**

**Foto- en documentcollecties voor [webtrees](https://webtrees.net) met EXIF-verrijking, galerij, lightbox en synchronisatie met GEDCOM-gegevens.**

| | |
|---|---|
| Modulenaam | `sammlungen` |
| Versie | 1.0.6 |
| webtrees | 2.2.x |
| PHP | 8.2 – 8.4 |
| Licentie | GPL-3.0-or-later |

---

## Wat doet de module?

webtrees beheert mediaobjecten weliswaar als onderdeel van de GEDCOM-standaard, maar biedt
geen **visuele fotocollecties** met de diepgang die familiearchieven nodig hebben.
`Sammlungen` vult precies dat gat:

- **Foto- en documentgalerijen** gegroepeerd op thema (familiefoto's, grafstenen,
  kerkboekakten, brieven, militaire documenten, …)
- **EXIF/XMP-gegevens rechtstreeks uit de afbeeldingsbestanden** lezen en bewerken – zonder
  omweg via GEDCOM-tags
- **Bidirectionele synchronisatie** tussen fotometadata en webtrees-persoonskoppelingen
- **Pad-gebaseerde collecties** voor foto's die nog niet in webtrees zijn geïmporteerd

### Collectieoverzicht

Overzicht van alle collecties, gegroepeerd op archiefmappen en thematische groepen:

![Collectieoverzicht](docs/images/01-uebersicht.png)

### Foto-lightbox met EXIF-editor

Klikken op een foto opent de lightbox met zijbalk – EXIF-gegevens weergeven, bewerken en
direct terugschrijven naar het bestand (automatische dagelijkse back-up vóór elke wijziging):

![Lightbox met zijbalk](docs/images/02-lightbox.png)

De zijbalk toont EXIF en XMP, maakt het bewerken van beschrijving, datum, personen en
trefwoorden mogelijk, vergelijkt de waarden met de webtrees-persoonskoppelingen en biedt
overname met één klik bij verschillen.

### Documentlijsten

Voor collecties met PDF's/documenten (kerkboekakten, registers van de burgerlijke stand, …)
toont de module automatisch een lijstweergave in plaats van een fotoraster:

![Documentlijst](docs/images/04-dokumente.png)

### Beheer

Eigen collecties aanmaken met naam, pictogram, kleur en weergavetype (fotogalerij,
documentlijst, gemengd). Actief-status met één klik schakelen:

![Collecties beheren](docs/images/03-admin.png)

---

## Functies in detail

- **Galerijen** voor fotocollecties (`Familienfotos`, `Grabsteine`, `Konterfeis`, eigen collecties)
- **Lightbox** met toetsenbordnavigatie, thumbnailstrook en zijbalk
- **EXIF/XMP lezen** (beschrijving, datum, personen, trefwoorden) met Imagick-cache
- **EXIF/XMP schrijven** met automatische dagelijkse back-up vóór elke wijziging
- **Synchronisatie EXIF ↔ webtrees** (beschrijving, personen) met overname in één klik
- **Bestand hernoemen** direct in de lightbox (DB wordt mee bijgewerkt)
- **Handmatige collecties** (CRUD): naam, slug, pictogram, kleur, weergave
- **Pad-gebaseerde toewijzing**: ook niet-geïmporteerde afbeeldingen kunnen aan collecties worden toegewezen
- **"Niet-gekoppelde media"** als eigen overzicht (media zonder persoons-/familiekoppeling)
- **APCu-cache** voor dure query's met instelbare TTL

## Vereisten

- webtrees ≥ 2.2.0
- PHP ≥ 8.2 met extensies: `imagick`, `gd`, `apcu` (optioneel, valt anders terug op array-cache)
- MariaDB / MySQL ≥ 10.5

## Installatie

### Optie A: install-ZIP (aanbevolen – zonder Composer/git)

1. Download de meest recente `sammlungen-vX.Y.Z.zip` van de
   [releases-pagina](https://github.com/thobgg/webtrees-sammlungen/releases/latest).
2. Pak het uit – je krijgt een map `sammlungen/`.
3. Kopieer die map naar de map `modules_v4/` van je webtrees-installatie
   (doel: `modules_v4/sammlungen/`).

### Optie B: via git + Composer (voor ontwikkelaars)

```bash
cd modules_v4
git clone https://github.com/thobgg/webtrees-sammlungen.git sammlungen
cd sammlungen
composer install --no-dev
```

Activeer daarna de module in webtrees onder **Bedieningspaneel → Modules → Aangepaste
modules** ("Custom Modules"). De databasetabellen worden bij de eerste keer laden
automatisch aangemaakt.

## Bediening

1. Het menu **"Collecties"** in de webtrees-navigatie opent het overzicht.
2. **Klikken op een collectie** opent de galerij (fotoraster of documentlijst).
3. **Klikken op een foto** opent de lightbox met pijltjestoets-navigatie.
4. Het **potloodpictogram in de lightbox** opent de zijbalk met de EXIF-editor.
5. Het **beheergedeelte** is bereikbaar via `Bedieningspaneel → Modules → Sammlungen → Configuratie`:
   - Eigen collecties aanmaken/bewerken/verwijderen
   - Cache-TTL en paginagrootte configureren
   - Footer-link in-/uitschakelen

## Collecties met afbeeldingen vullen

Er zijn **twee soorten** collecties – het verschil zit uitsluitend in het veld
**„Medienordner"** (mediamap) in het collectieformulier:

### 1. Mapcollectie (aanbevolen, automatisch)

Je vult in het veld **„Medienordner"** een map onder `data/media/` in
(bijv. `grabsteine`). De collectie bevat dan **automatisch alle afbeeldingen** uit die
map (inclusief submappen) – nieuwe bestanden verschijnen zonder verdere actie. Dit is de
normale manier en de enige die voor grote bestanden praktisch is.

1. Maak een map onder `data/media/` aan (bijv. `data/media/grabsteine/`) en plaats de
   afbeeldingen erin.
2. Maak een collectie aan, vul bij **„Medienordner"** de mapnaam in (`grabsteine`),
   kies als weergavetype **„Fotogalerie"** (fotogalerij).
3. Schakel **„Sichtbar (aktiv)"** (zichtbaar/actief) in en sla op. Klaar – alle
   afbeeldingen zijn automatisch opgenomen.

### 2. Albumcollectie (handmatig samengesteld)

Laat je **„Medienordner" leeg**, dan ontstaat een vrij album dat je met de hand vult:
via de **📷-knop** in het collectiebeheer kies je afzonderlijke afbeeldingen.

Belangrijk: de 📷-kiezer biedt geen willekeurige bestanden aan – hij toont alleen
afbeeldingen uit **bestaande mapcollecties** (manier 1), die dan als **beeldbron**
dienen. Een mapcollectie verschijnt pas als beeldbron als ze aan alle drie de
voorwaarden voldoet:

- ze is **zichtbaar gezet** – de schakelaar **„Sichtbar (aktiv)"** (zichtbaar/actief)
  in het bewerkscherm staat aan;
- ze heeft een **mediamap direct onder `data/media/`** – een map zonder `/` in de
  naam (bijv. `grabsteine`, niet `grabsteine/2024`);
- het weergavetype is **„Fotogalerie"** of **„Foto-Raster"**.

Voor een handmatig album heb je dus **eerst minstens één zo'n mapcollectie**
(manier 1) nodig. Zonder die heeft de kiezer geen beeldbron en meldt
„geen afbeeldingen aanwezig".

> **Vuistregel:** Liggen alle afbeeldingen van een thema in één map? → mapcollectie
> (manier 1). Wil je gericht afzonderlijke afbeeldingen uit meerdere mappen samenstellen?
> → albumcollectie (manier 2).

## Architectuur

```
sammlungen/
├── module.php                       ← webtrees-toegangspunt
├── composer.json                    ← Composer-manifest
├── src/
│   ├── SammlungenModule.php        ← hoofdklasse van de module (routes, menu, migraties)
│   ├── Cache/                       ← APCu-cache met array-fallback
│   ├── Dto/                         ← Data Transfer Objects (SammlungDto)
│   ├── Http/RequestHandlers/        ← PSR-15-handlers (galerij, beheer, AJAX-endpoints)
│   ├── Repository/                  ← DB-toegang (SammlungenRepository)
│   ├── Service/                     ← businesslogica (CollectionService, ExifService)
│   └── ViewModel/                   ← gegevensvoorbereiding (SammlungenViewModel)
├── resources/
│   ├── js/sammlung-galerie.js      ← lightbox + synchronisatie + hernoemen + EXIF-opslaan
│   ├── views/ + partials/          ← PHP-templates
│   └── lang/                        ← de.po, de.mo, nl.po, nl.mo (vertalingen)
└── docs/images/                     ← screenshots voor deze README
```

## Routing

Alle URL's zijn bereikbaar onder `/tree/{tree}/archiv/…`:

| Routenaam | URL | Methode |
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

## Datamodel

```sql
sammlungen_collection           -- definities: naam, slug, pictogram, kleur, weergave, map
sammlungen_collection_medium    -- M:N webtrees-medium ↔ collectie (op m_id-basis)
sammlungen_collection_pfad      -- M:N pad ↔ collectie (ook niet-geïmporteerde afbeeldingen)
```

## Configuratie

In de beheer-UI instelbaar:

- **Cache-TTL** (standaard: 900 s)
- **Paginagrootte** (standaard: 50)
- **Footer-link tonen** (ja/nee)

## Verwante modules

| Module | Functie |
|---|---|
| [Ortsregister](https://github.com/thobgg/webtrees-ortsregister) | Zustermodule voor visuele plaats-landingspagina's en foto-plaatskoppeling |

## Licentie

GPL-3.0-or-later, identiek aan webtrees. Zie [LICENSE](LICENSE).

## Auteur & ondersteuning

Thomas Bugge · thomas@bgg-mail.de  
Vragen / bugs: GitHub-issues

Nederlandse interface-vertaling: TheDutchJewel 🇳🇱

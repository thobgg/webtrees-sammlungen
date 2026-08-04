# Changelog

Alle nennenswerten Änderungen an diesem Modul werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
und das Projekt nutzt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

_Sammelstelle fürs nächste Bündel-Release. Einzelne Patch-Hotfixes nur bei Blockern (500er/Datenfehler)._

## [1.2.5] – 2026-08-04

**Vollständige Übersetzungen.** Mehrere Texte waren in keiner Sprachdatei
hinterlegt und erschienen deshalb auch in niederländischen, spanischen und
katalanischen Installationen auf Deutsch.

### Behoben
- **Sechs Texte sind jetzt übersetzbar.** Drei davon waren seit Längerem in
  keinem Katalog: die Sicherheitsabfrage vor dem Löschen einer Sammlung, die
  Vorschauzeile der nicht eingebundenen Medien und die Überschrift
  „Videos, Audio & Dokumente". Drei weitere kamen mit der Blätternavigation aus
  1.2.4 hinzu und betrafen die Vorlesetexte der Pfeil-Schaltflächen.
  Ergänzt in allen fünf Sprachen.

### Entfernt
- Zwei Katalogeinträge des Footer-Schalters, der in 1.2.1 entfernt wurde.

### Intern
- Ein Test gleicht die Sprachdateien gegen den Quelltext ab: jeder Text aus
  `I18N::translate()` muss im Katalog stehen, es darf keine verwaisten Einträge
  geben, alle Sprachen führen denselben Textsatz, und zu jeder `.po` gehört eine
  nicht ältere `.mo`. Die Sprachliste liest der Test aus dem Verzeichnis –
  eine neu beigesteuerte Übersetzung wird ohne Änderung mitgeprüft.

## [1.2.4] – 2026-08-04

**Der Anzeigetyp „Foto-Raster" funktioniert.** Er war seit dem ersten Release
unfertig ausgeliefert: die Kacheln waren nie formatiert, und eine Blätternavigation
gab es nicht.

### Behoben
- **Blätternavigation in „Foto-Raster" und „Gemischt".** Beide Ansichten gaben
  „Seite 1 von N" aus, boten aber keinen Weg auf die nächste Seite – man saß auf
  Seite 1 fest. Die Navigation lag doppelt in zwei anderen Zweigen und fehlte in
  diesen beiden schlicht. Sie liegt jetzt als eigenes Partial an einer Stelle
  und wird von allen Ansichten eingebunden. (Issue #17, gemeldet von @ro-la)
- **Kacheln im Foto-Raster zeigten dieselbe Datei doppelt.** Die Kachel besteht
  aus einem weichgezeichneten Hintergrund und dem vollständigen Bild darüber –
  die beiden CSS-Klassen dafür standen aber seit 1.0.0 ohne jede Regel im
  Markup. Der Browser zeichnete den Hintergrund deshalb in Originalgröße und
  ungefiltert, sodass ein herangezoomter Ausschnitt derselben Datei hinter dem
  Bild hervorsah. Betraf auch manuelle Galerien. (Issue #17)

### Intern
- Neue Tests halten fest, dass jede Ansicht mit Seitenzähler auch eine
  Navigation hat, dass das Navigations-Markup nur noch an einer Stelle liegt und
  dass keine `archiv-*`-Klasse im Markup steht, für die es weder eine CSS-Regel
  noch eine Verwendung im JavaScript gibt.
- Die wirkungslose Klasse `archiv-thumb` entfernt.
- PHPStan ist jetzt eine Dev-Abhängigkeit statt eines extern besorgten `.phar`.
  Die Konfiguration lag seit 1.1.0 im Repo, das Werkzeug fehlte – die statische
  Analyse lief damit stillschweigend gar nicht. `composer check` führt Tests und
  Analyse zusammen aus; beide Skripte laufen über `@php vendor/bin/…`, weil
  `vendor/bin` je nach Mount kein Ausführungsrecht hat.

## [1.2.3] – 2026-08-03

**Datenschutz und Tempo.** Verknüpfte Personennamen unterliegen jetzt den
Datenschutzregeln des Stammbaums, und die Galerie kommt mit deutlich weniger
Datenbankabfragen aus.

### Behoben
- **Verknüpfte Personennamen richten sich nach den Datenschutzregeln des Baums.**
  Bisher las das Modul die Namen direkt aus `link`/`name` und zeigte sie ohne
  Sichtbarkeitsprüfung an. Maßgeblich ist `canShowName()` – wahr, sobald der
  Baum Namen Lebender zeigt; das striktere `canShow()` würde mehr verbergen als
  webtrees selbst. Die Prüfung greift vor dem Kappen der Liste, sonst zählte der
  „… und N weitere"-Zähler die verborgenen Personen mit und verriete ihre
  Anzahl. Die Datensätze werden in einem Rutsch geladen und über den
  Factory-Mapper erzeugt, also eine zusätzliche Abfrage je Seite statt einer je
  Person. Zeigt der Stammbaum Namen vertraulicher Personen ohnehin auf der
  Zugriffsstufe des Betrachters – die verbreitete Einstellung –, steht die
  Antwort für alle Datensätze fest und es wird gar nichts geladen. (Issue #16)

### Geändert
- **Galerie holt die Sammlungszugehörigkeit seitenweise statt je Bild.** Bisher
  lief pro angezeigtem Bild eine eigene Abfrage – bei 200 Einträgen pro Seite
  also 200 Abfragen für eine Information, die in eine passt.

### Hinweis für Administratoren
Ob sich für Ihre Installation etwas ändert, hängt an einer einzigen Einstellung:
**Stammbaum verwalten → Datenschutz → „Namen vertraulicher Personen zeigen".**
Steht sie auf der Stufe Ihrer Mitglieder oder darunter (der übliche Fall), sehen
diese die Namen unverändert. Ist sie strenger gesetzt, verschwinden geschützte
Namen jetzt auch aus Galerie und Lightbox – so, wie es im übrigen webtrees
bereits der Fall war.

## [1.2.2] – 2026-08-03

**Bugfix-Release.** Die Seitenleiste der Lightbox konnte beim Speichern die
Personenliste im Dateikopf des Originalbildes überschreiben.

### Behoben
- **„In Datei speichern" schrieb webtrees-Namen in die Bilddatei.** Das Feld
  „Personen" der Seitenleiste war in Ordner-Galerien mit den in webtrees
  verknüpften Personen vorbelegt statt mit den im Bild hinterlegten Namen –
  wer die Seitenleiste öffnete und speicherte, etwa nur um eine Beschreibung zu
  korrigieren, ersetzte damit die Personenliste der Originaldatei. Die im Bild
  gespeicherten Namen erreichten die Lightbox überhaupt nicht. In manuellen
  Galerien blieb das Feld umgekehrt immer leer, auch wenn die Datei Namen
  enthielt. Beide Quellen sind jetzt getrennt: das Eingabefeld zeigt die Daten
  aus der Datei, die webtrees-Verknüpfungen stehen weiterhin im eigenen
  Abschnitt. (Issue #15)
- **Abgleich „EXIF ↔ webtrees" funktioniert wieder für Personen.** Er verglich
  bisher webtrees mit webtrees und konnte deshalb nie einen Unterschied finden.
  Bei sehr vielen Verknüpfungen wird die Übernahme bewusst nicht angeboten,
  weil dort nur ein Ausschnitt der Liste vorliegt und die Übernahme die
  vollständige Liste in der Datei durch diesen Ausschnitt ersetzen würde.

### Bekannt
- Verknüpfte Personennamen werden ohne Prüfung der webtrees-Datenschutzregeln
  angezeigt. Betrifft Installationen, die Lebende auch vor Mitgliedern
  verbergen; das Modul selbst ist nur für angemeldete Mitglieder sichtbar.
  (Issue #16)

## [1.2.1] – 2026-08-03

**Bugfix-Release.** Die Modul-Einstellungen hatten keine Wirkung, und Medien mit
sehr vielen Personen-Verknüpfungen (z. B. ein Wappen an einer ganzen Familie)
zeigten in der Lightbox kein Bild mehr.

### Behoben
- **Bild in der Lightbox unsichtbar bei vielen verknüpften Personen.** Die
  Kopfzeile der Lightbox darf nicht schrumpfen; eine ungekürzte Namensliste –
  bei einem Wappen an 300 Personen sind das dutzende Zeilen Umbruch – füllte die
  gesamte Höhe und quetschte den Bildbereich darunter auf null. Die Meta-Zeile
  zeigt jetzt drei Namen und einen Zähler, die Seitenleiste höchstens 25 Namen
  plus „… und N weitere" mit Verweis auf die webtrees-Medienseite. Zugleich
  wandern nicht mehr alle Namen ins Markup jeder Galerie-Kachel – bei solchen
  Sammlungen fiel die Seitengröße von mehreren MB auf ein normales Maß.
  (Issue #13, gemeldet von @ro-la)
- **„Einträge pro Seite" blieb wirkungslos.** Der Wert wurde gespeichert, aber
  nie gelesen: die Galerie paginierte mit fest verdrahteten 48 bzw. 50 Bildern.
  Sie richtet sich jetzt nach der Einstellung. (Issue #14)
- **Admin-Seiten rendern im Control Panel.** Sie liefen bisher im Layout der
  Besucheroberfläche und bekamen dadurch Kopfzeile und Navigation eines
  konkreten Stammbaums – wer die Einstellungen öffnete, landete sichtbar im
  ersten Baum. (Issue #14)

### Entfernt
- **Schalter „Link im Footer anzeigen".** Er wurde weder gespeichert noch
  ausgewertet, und einen Footer gab es nie – das Modul ist über seinen
  Menüpunkt erreichbar. Ein Schalter ohne Funktion ist irreführender als
  keiner. (Issue #14)

### Intern
- Die Lightbox lag als Byte-genaue Kopie zusätzlich in `_detail-ordner.phtml`
  (doppelte Element-IDs, Fixes kamen nur an einer Stelle an). Beide Galerien
  nutzen jetzt dasselbe Partial – 186 Zeilen Duplikat weniger.
- Grenzwerte für Cache-TTL und Seitengröße lagen doppelt vor (Modul und
  Handler, mit abweichender Obergrenze) und liegen jetzt als
  `normalisiereCacheTtl()` / `normalisierePerPage()` an einer Stelle.
- Personennamen der Seitenleiste werden beim Einsetzen ins DOM maskiert.
- Neue Regressionstests zu #13 und #14 (67 Tests gesamt).

## [1.2.0] – 2026-07-29

**Zwei neue Sprachen.**

### Hinzugefügt
- **Katalanisch (`ca`) und Spanisch (`es`)** – vollständige Übersetzungen
  (je 126 Texte), beigesteuert von **Bernat Josep Banyuls i Sala** (Issue #11).
  Herzlichen Dank!

## [1.1.1] – 2026-07-29

**Hotfix-Release (Blocker).** Das Speichern der Modul-Einstellungen führte zu
einem Fehler und war damit unbenutzbar.

### Behoben
- **Einstellungen speichern schlug fehl** (Foreign-Key-Verletzung auf
  `wt_module`). Der DI-Container reicht dem `AdminConfig`-Handler eine frisch
  erzeugte, noch unbenannte Modulinstanz; `name()` war leer, und
  `setPreference()` schrieb einen leeren `module_name` nach `wt_module_setting`.
  Der Modulname wird jetzt bereits im Konstruktor gesetzt, sodass jede Instanz –
  ob per `include module.php` oder per Container erzeugt – gültig ist.
  (Issue #12, Dank an @ro-la für den vollständigen Stacktrace.)

## [1.1.0] – 2026-06-30

**Reife-Release – kein neues Feature, sondern ein Qualitäts-Meilenstein.**
Hinter dieser Version steht ein intensiver Praxis-Test: ein Alpha-Test-Zyklus auf
echten Installationen, der eine Reihe realer Fehler zutage gefördert hat – vom
`ONLY_FULL_GROUP_BY`-Absturz über eine Tabellen-Präfix-Regression bis zum
EXIF-Foto-Zähler – allesamt gegen echte Datenbanken verifiziert und behoben.
Diesen erreichten Stand sichert 1.1.0 jetzt dauerhaft ab: durch statische Analyse
und einen automatisierten Test-Grundstock, damit er nicht unbemerkt zurückfällt.
Der Versionssprung auf **1.1.0** macht genau das sichtbar – die Patch-Folge der
intensiven Bugfix-Phase ist abgeschlossen, das Modul auf geprüftem Fundament.

### Entwicklung / Qualitätssicherung
- **Statische Analyse (PHPStan, Level 5):** Konfiguration (`phpstan.neon`) und
  Baseline ergänzt; prüft `src/` mit dem webtrees-Autoloader und läuft mit
  **0 offenen Befunden**.
- **Automatisierte Tests (PHPUnit):** Grundstock aus **32 Unit-Tests** für die
  reine Service-Logik (Datums-Formatierung, XMP-Aufbau samt
  Sonderzeichen-Maskierung, Slug-/Hexfarben-Validierung) – ohne Datenbank.
  Lauf via `composer test`.

### Behoben
- **EXIF-Datumsanzeige bei „00":** Die Erkennung „Monat/Tag = 00 = unbekannt →
  nur Jahr anzeigen" wird jetzt über einen numerischen Vergleich gelöst
  (`(int) … === 0`). Funktional unverändert; beseitigt einen falsch-positiven
  Befund der statischen Analyse, der die Stelle als nie zutreffend meldete.

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

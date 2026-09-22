# Changelog

Alle nennenswerten Änderungen an diesem Modul werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
und das Projekt nutzt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

_Sammelstelle fürs nächste Bündel-Release. Einzelne Patch-Hotfixes nur bei Blockern (500er/Datenfehler)._

## [1.7.0] – 2026-09-22

### Neu
- **Schnittstelle für Apps, Stufe 2: schreiben.** Zwei POST-Routen für
  „unterwegs festhalten“ – ein Foto aus der Schublade abfotografieren, sagen
  wer drauf ist, fertig:
  `/tree/{tree}/archiv/api/hochladen` legt eine Datei in einem Ordner des
  Medienordners ab, als Datei im Archiv, nicht als Medienobjekt; Beschreibung,
  Datum und Personen gehen bei Bildern als EXIF/XMP mit, auf Wunsch wird die
  Datei gleich einer thematischen Sammlung zugeordnet. Darf, wer in webtrees
  Medien hochladen darf. `/tree/{tree}/archiv/api/exif` schreibt dieselben
  Felder an eine vorhandene Bilddatei, mit der Regel der Lightbox: nur
  Verwalter. Die Übersicht nennt jetzt, was der Nutzer darf (`darfHochladen`,
  `darfExif`) und die Ordner, in die er hochladen kann (`ordnerListe`).
  Anders als beim Hochladen in webtrees wird eine vorhandene Datei nicht
  überschrieben und der Upload nicht still in den Hauptordner verschoben: der
  Name bekommt eine Nummer, der Ordner bleibt. `api: 2`.

## [1.6.0] – 2026-09-22

**Das Archiv kommt in die App.** Die Android-App [wtAnd](https://github.com/thobgg/wtAnd)
soll zeigen, worum es dem Modul geht: die Fotos, die im Stammbaum an niemandem
hängen. Dafür bekommt das Modul eine lesende Schnittstelle – bewusst hier und
nicht in api4webtrees, damit kein Modul an den Tabellen eines anderen hängt.

### Neu
- **Schnittstelle für Apps.** Zwei lesende Routen liefern das Archiv als JSON,
  gedacht für [wtAnd](https://github.com/thobgg/wtAnd), offen für jeden Client
  mit dem Sitzungs-Cookie eines Baummitglieds:
  `/tree/{tree}/archiv/api/sammlungen` (die Übersicht: Ordner-Sammlungen,
  thematische Sammlungen, Sammlungen nach Medientyp, nicht eingebundene Medien,
  freier Bestand) und `/tree/{tree}/archiv/api/sammlung?kategorie=…` (die
  Einträge einer Sammlung, seitenweise, mit fertigen Adressen für Kachel,
  Vollbild und Original). Die Daten kommen aus derselben Aufbereitung wie die
  Galerie; Seitengröße und Sprache (`pro_seite`, `lang`) gelten nur für die
  Antwort und werden nicht beim Nutzer gemerkt. Wer kein Mitglied ist, bekommt
  statt der Anmeldeseite eine JSON-Fehlermeldung (403). Stufe der
  Schnittstelle: `api: 1`. Beschreibung in der README unter „Schnittstelle für
  Apps“.

### Behoben
- **Vorschaubilder fehlten auf SQLite und PostgreSQL**, wenn ein GEDCOM die
  Dateiendung groß schreibt (`FORM JPG`). Die Vorschau-Abfragen verglichen das
  Format nur mit Kleinbuchstaben; MySQL sieht darüber hinweg, die anderen
  nicht. Jetzt zählen beide Schreibweisen.

## [1.5.1] – 2026-09-04

**Zwei Rückmeldungen von Bernat Banyuls**, per Mail statt als Issue – und
als eigenes kleines Release, weil die erste davon in v1.5.0 ein Symbol
verschwinden lässt, das vorher wenigstens da war.

### Behoben
- **Im Rural-Theme (jon48) fehlte das Menüsymbol ganz.** Rural gibt jedem
  Menü ohne eigenes Symbol ein generisches – mit einer Regel ohne Gewicht, die
  jede andere überstimmt. Unsere Grundregel „kein Symbol“ für unbekannte Themes
  tat genau das: alle Menüs hatten ein Symbol, nur unseres nicht. Die Grundregel
  ist gestrichen – ohne Regel zeichnet der Browser ohnehin nichts, Themes ohne
  Symbole bleiben also sauber – und Rural steht mit seinen Maßen (35 Pixel im
  Menü, 24 im Aufklappmenü) in der Größentabelle, sodass dort unser Symbol
  erscheint statt des generischen.

### Geändert
- **Thematische Sammlungen ohne gestrichelten Rand.** Der gestrichelte Rahmen
  sollte sie von den Ordner-Sammlungen abheben, wurde aber als „noch nicht
  fertig eingerichtet“ gelesen – gestrichelt heißt im Web fast überall
  Platzhalter. Jetzt tragen sie denselben Farbbalken links wie die
  Ordner-Sammlungen; die Unterscheidung leistet die Überschrift mit der
  Reißzwecke.

## [1.5.0] – 2026-08-28

**Vier Anregungen aus Issue #23 von @ro-la** – die erste Rückmeldung, die nicht
von einem Fehler handelt, sondern davon, wie sich das Modul anfühlt, wenn man es
nicht selbst gebaut hat. Drei davon sind Bedienung: die Verwaltung liegt jetzt im
Menü, Seitengröße und Darstellung entscheidet der Betrachter statt der Verwalter.
Die vierte war ein Fehler, den niemand sah, dessen Installation aussieht wie
unsere: wer sein Datenverzeichnis verschoben hatte, bekam ein leeres Archiv.

Ein fünfter Punkt – ein Bilderwähler über *alle* Medienobjekte statt nur die des
Ordners – steht als Issue #24 offen; er ist eine eigene Baustelle, kein Nebensatz.

### Behoben
- **Aus der Verwaltung führte kein Weg zurück** (Issue #23). Die
  Verwaltungsseiten des Moduls laufen im Verwaltungs-Layout von webtrees, und
  das zeigt kein Genealogie-Menü – oben stehen nur Hilfe, Meine Seite, Sprache,
  Abmelden. Solange man nur über die Schaltfläche auf der Übersicht hineinkam,
  fiel das nicht auf. Seit das Aufklappmenü von jeder Seite aus dorthin einlädt,
  saß man drin fest.

  Jetzt hat jede der vier Verwaltungsseiten die Brotkrumenleiste von webtrees
  selbst – Verwaltung / Einstellungen / Sammlungen verwalten / Name, jedes Glied
  anklickbar – und wo der Baum bekannt ist, führt ein Verweis direkt zurück in
  die Galerie.
- **Der Medienordner musste innerhalb der Installation liegen** (Issue #23,
  gemeldet von **@ro-la**). webtrees erlaubt es, das Datenverzeichnis zu
  verschieben – der empfohlene Weg, die Originaldateien aus der Reichweite der
  Adresszeile zu nehmen. Das Modul setzte den Pfad aber an zwölf Stellen selbst
  aus der Konstante `Webtrees::DATA_DIR` zusammen, die nur die *Vorgabe* für die
  Einstellung `INDEX_DIRECTORY` ist. Wer sein Datenverzeichnis verschoben hatte,
  bekam leere Sammlungen, einen freien Bestand von null und Bilder als 404.

  Eine Stelle war schwerwiegender als der Rest: die Sicherung vor dem Schreiben
  von EXIF prüfte gegen `data/media` – mit fest verdrahtetem Ordnernamen. Wer
  seinen Medienordner anders nennt, bei dem schlug die Prüfung fehl, die
  Sicherung wurde übersprungen und die Datei ohne Netz überschrieben. Still.

  Alle zwölf Stellen holen den Pfad jetzt aus einer Funktion, die ihn so
  bestimmt wie webtrees selbst. Bei unveränderten Einstellungen kommt Zeichen
  für Zeichen derselbe Pfad heraus wie bisher; ein Test hält das fest, ein
  zweiter, dass die feste Annahme nicht zurückkehrt.

### Hinzugefügt
- **Einträge pro Seite in der Ansicht umschaltbar** (Issue #23, vorgeschlagen von
  **@ro-la**), 10 bis 200, gemerkt je Nutzer. Bisher galt eine modulweite Zahl
  für alle und für jedes Gerät gleich – auf einem großen Schirm sind 50 Bilder
  wenig, auf dem Telefon sind dieselben 50 mehrere Megabyte. Die Grenzen des
  Moduls gelten weiter: wer eine größere Zahl in die Adresse schreibt, bekommt
  die Obergrenze.
- **Foto-Raster und große Bilder umschaltbar**, aber nur in Bildersammlungen.
  In einer Dokumentensammlung wäre ein Foto-Raster ein Gitter aus PDF-Symbolen;
  dort bleibt die Wahl des Verwalters stehen. Der Vorschlag lautete „Anzeigetyp
  frei wählbar" – umgesetzt ist er dort, wo beide Darstellungen etwas zeigen.
- **Verwaltung direkt aus dem Menü erreichbar** (Issue #23, vorgeschlagen von
  **@ro-la**). Für Administratoren klappt der Menüpunkt jetzt auf: Übersicht,
  Sammlungen verwalten, Einstellungen. Bisher führte der Weg dorthin nur über
  die Schaltfläche auf der Übersichtsseite oder über die Steuerleiste.

  Für alle anderen bleibt es ein einzelner Verweis – ein Klick, Galerie. Ein
  Aufklappmenü kostet dort einen Handgriff und bringt nichts. Die Übersicht
  steht deshalb auch als erster Untereintrag drin: ein Menü mit Untereinträgen
  klappt beim Klick auf, statt zu springen, sonst wäre der kurze Weg zur
  Galerie verloren.

## [1.4.1] – 2026-08-27

Ein Anzeigefehler, gemeldet von **@ro-la** – und weil Meldungen selten kommen,
soll er nicht bis zum nächsten Bündel warten.

### Behoben
- **Das Menüsymbol überstimmte jedes Theme** (Issue #22, gemeldet von **@ro-la**).
  Es wurde über `content: url(...)` eingesetzt, fest auf 50 Pixel skaliert und in
  *jedem* Theme. Über `content:` eingefügte Bilder ignorieren `width` und
  `height` – das Theme konnte sich also nicht wehren. Am Gerät gemessen sind die
  Symbole der Themes 50 Pixel groß (webtrees), 40 (Colors), 28 (Xenea), 22
  (Clouds); minimal, F.A.B. und die meisten fremden Themes haben gar keine. In
  Colors war unseres dadurch mehr als doppelt so groß wie alle anderen, in
  minimal stand ein Bild mitten in einer reinen Textzeile.

  Jetzt ist es ein Hintergrundbild in einer Box fester Größe – damit bestimmen
  wir Größe und Ausrichtung – und es erscheint nur in Themes, die selbst
  Menüsymbole zeigen; erkannt an der Klasse, die webtrees am `<body>` setzt.
  Statt des Fotos ein flaches SVG, das neben gezeichneten Symbolen nicht
  auffällt. Damit entfällt auch Imagick im Seitenkopf.

## [1.4.0] – 2026-08-15

**Das Modul wird auf dem Telefon benutzbar – und auf jedem Gerät schnell.**

Zwei Zahlen fassen das Release zusammen, beide an einer Instanz mit 1826
Archivdateien gemessen: eine Galerieseite wog **174,2 MB und braucht jetzt
4,8 MB**, und sie brauchte **12 Sekunden bis zum ersten Byte und braucht jetzt
0,2**. Dazu eine Bedienung, mit der man Fotos herumzeigen kann, ohne sich zu
entschuldigen: kneifen, schieben, wischen, tippen für randlos.

Der dritte Strang ist die Ehrlichkeit der Zahlen. Der freie Bestand – der Teil
des Archivs, der im Stammbaum nicht auftaucht – wurde falsch gezählt, und zwar
seit 1.0.0 in zwei Richtungen gleichzeitig: Verweise aus Quellen galten als
Einbindung, und Dateien ohne Medienobjekt kamen gar nicht vor. Auf der
Testinstanz meldete die Ansicht deshalb **0 Objekte, wo 1126 Dateien liegen**.

### Hinzugefügt
- **Bedienung wie in einer Foto-App auf Telefon und Tablet.** Die Lightbox
  öffnet dort über die volle Höhe, die Seitenleiste legt sich über das Bild
  statt daneben zu drängen, und die Blätterpfeile weichen den Gesten:
  - zwei Finger vergrößern bis fünffach,
  - ein Finger schiebt das vergrößerte Bild, das dabei im Rahmen bleibt,
  - Doppeltippen fährt auf die getippte Stelle zu und wieder zurück,
  - Wischen blättert weiter, aber nur bei unvergrößertem Bild – sonst wäre
    jedes Verschieben ein Bildwechsel,
  - am Rechner Doppelklick und Strg + Mausrad.

  Hintergrund: quer aufgenommene Fotos füllen auf einem 384 Pixel breiten
  Schirm nur ein Band von rund 256 Pixeln Höhe, egal wie viel Platz darunter
  frei bleibt. Ohne Vergrößern ist darauf kein Gesicht zu erkennen.
- **Einmal aufs Bild tippen räumt Kopfzeile und Vorschauleiste weg** und
  fordert zugleich Vollbild an, nochmal tippen holt beides zurück. Ein
  Schließer bleibt oben rechts stehen, sonst käme man nur über die
  Zurück-Taste heraus, und die verlässt im Telefon-Browser die Seite.

  Am S25 Ultra gemessen, quer gehalten und mit einem 3:2-Foto: **284 × 189
  vorher, 576 × 384 nachher** – gut die vierfache Fläche. Hochkant wächst der
  Rahmen von 646 auf 832 Pixel; ein Querformat-Foto ist dort weiterhin durch
  die Breite begrenzt, gewonnen wird also Schwarz – dafür ist der Zoom da.

  Wo es die Fullscreen-API nicht gibt (iPhone) oder sie abgelehnt wird, bleibt
  es beim Wegräumen der modul-eigenen Leisten.

  Das Vollbild wird dabei nur betreten und erst beim Schließen wieder verlassen.
  Jedes Betreten quittiert der Browser mit seinem Sicherheitshinweis („… ist
  jetzt im Vollbildmodus"), der sich aus der Seite heraus nicht abschalten
  lässt; so erscheint er einmal je Galerie statt bei jedem zweiten Tippen.

### Geändert
- **Das Auslesen der Metadaten dekodierte jedes Foto vollständig.** Für Angaben,
  die im Dateikopf stehen, öffnete das Modul die ganze Datei: bei einem 7 MB
  großen Bild mit 4796 × 7731 Punkten rund hundert Megabyte Speicher und ein
  Zehntel Sekunde Rechenzeit – mal fünfzig Bilder je Galerieseite. Gemessen
  vergingen so **5,3 Sekunden**, bevor das erste Byte beim Browser ankam, beim
  ersten Aufruf einer Seite sogar zwölf.

  `Imagick::pingImage()` liest nur den Kopf samt EXIF- und XMP-Profil. Dieselbe
  Seite braucht jetzt **125 bis 320 Millisekunden**, und die ausgelesenen Werte
  sind unverändert – gegengeprüft an drei Seiten: gleiche Anzahl Beschreibungen,
  Daten und Bildmaße wie vorher.
- **Galerien liefern verkleinerte Bilder statt der Originale.** Eine Rasterseite
  schickte bisher 50 Originaldateien: gemessen **174,2 MB**, im Mittel mit der
  21-fachen Breite dessen, was auf dem Schirm ankommt – also rund der
  450-fachen Pixelzahl. Jede Kachel wurde zudem zweimal dekodiert, weil dasselbe
  Original als weichgezeichneter Hintergrund dahinterliegt. Auf dem Telefon
  ruckelte davon jedes Blättern und jedes Drehen.

  Jetzt gibt es drei Größen: 400 Pixel für die Kachel, 1600 für die Lightbox,
  das Original hinter „Vollbild in neuem Tab". Dieselbe Seite wiegt damit
  **4,8 MB** statt 174,2 – ein Sechsunddreißigstel.

  Erzeugt wird mit dem Bildstapel von webtrees selbst (`ImageFactory`, also
  Imagick oder GD, je nach Server): keine zusätzliche Voraussetzung für andere
  Installationen. Dateien unter 150 KB oder solche, die ohnehin schmaler sind
  als angefordert, gehen unverändert raus – bei denen kostet das Umrechnen mehr
  als es spart (gemessen: 54 ms unverändert gegen 149 ms verkleinert).
- **Ein quer gehaltenes Telefon zählt jetzt als Telefon.** Die Regeln für die
  randlose Lightbox hingen an `max-width: 767.98px`; quer gehalten ist dasselbe
  Gerät 832 Pixel breit und bekam die Darstellung für große Bildschirme, also
  einen geschrumpften Dialog mitten auf dem Schirm. Maßgeblich ist zusätzlich
  die geringe Höhe (`max-height: 500px`). Die Blätterpfeile verschwinden nun
  nach Eingabegerät (`hover: none` und `pointer: coarse`) statt nach
  Fensterbreite – ein schmales Fenster am Rechner hat weiterhin eine Maus.

### Behoben
- **Ein Teil der Piktogramme erschien als Fragezeichen.** webtrees liefert kein
  vollständiges FontAwesome aus, sondern die Auswahl, die es selbst benutzt –
  ein Name außerhalb dieser Auswahl wird nicht etwa weggelassen, sondern als
  gestrichelter Kreis mit Fragezeichen gezeichnet. Das Modul benutzte 16 solcher
  Namen, unter anderem beim freien Bestand und beim Medienobjekt.

  Alle Symbole laufen jetzt über eine Zuordnung auf das, was vorhanden ist; auch
  die in der Verwaltung frei eingetragenen Namen. Ein Test hält fest, dass im
  Markup kein unbekannter Name mehr steht.
- **Der freie Bestand sah nur Medienobjekte und übersah das Archiv.** Gezählt
  wurde in der Datenbank: Medienobjekte ohne Verknüpfung. Ein Archiv besteht
  aber in aller Regel aus Dateien, die gar kein Medienobjekt haben – zu einem
  Vorfahren gehören dutzende Aufnahmen, von denen nur ein paar am Datensatz
  hängen sollen. Der Rest ist kein Rückstand, sondern der Bestand.

  Gezählt wird jetzt vom Dateisystem her: frei ist, was entweder gar nicht als
  Medienobjekt eingetragen ist **oder** eingetragen ist und an keiner Person
  und keiner Familie hängt. Die Übersicht zeigt die Summe und eine Kachel je
  Ordner. Ein Durchlauf durch das Verzeichnis und zwei Abfragen, nicht eine je
  Datei.

  Am Beispiel eines Archivs mit 1826 Dateien: vorher meldete die Ansicht
  **0 Objekte**, jetzt **1126 Dateien**, aufgeschlüsselt nach Ordner.
- **Der freie Bestand zählte Verweise aus Quellen und Notizen als Einbindung**
  und meldete dadurch „Alle Medienobjekte sind mit Personen oder Familien
  verknüpft", obwohl an keiner Person etwas hing. In webtrees darf eine
  `OBJE`-Zeile in jedem Datensatz stehen – auch in einer Quelle, einer Notiz,
  einem Repositorium oder einem Einreicher. Die Abfrage prüfte nur, *dass* es
  eine gibt, nicht *wer* verweist. Wer seine Registerscans an Quellen hängt –
  der Normalfall bei Kirchenbüchern und Standesämtern – bekam einen leeren
  freien Bestand gemeldet, also genau das Gegenteil der Aussage der Ansicht.

  Maßgeblich sind jetzt ausschließlich Personen und Familien. Betroffen waren
  drei Abfragen (Zählung, Auflistung, Vorschaubilder); sie teilen sich die
  Regel nun an einer Stelle. Der Fehler steckt seit 1.0.0 im Modul.
- **Lightbox saß auf dem Telefon um 8 Pixel versetzt** und ragte rechts über den
  Bildschirmrand hinaus, wodurch der Schließknopf angeschnitten war. Das
  Bootstrap-Bündel von webtrees ist für Schreibrichtungen aufbereitet und setzt
  den Abstand des Dialogs über `[dir] .modal-dialog`; Attribut plus Klasse
  schlägt eine einzelne Klasse, unser `margin` kam nie an. Jetzt wird
  Bootstraps eigene Stellschraube `--bs-modal-margin` umgestellt.

## [1.3.3] – 2026-08-14

Zwei Anzeigefehler, beide gemeldet von **@ro-la**.

### Behoben
- **Kopfzeile der Lightbox lief über, statt zu kürzen** (Issue #21). Bei mehreren
  verknüpften Personen mit langen Namen wurden die Schaltflächen aus dem Bild
  geschoben oder von der Seitenleiste überdeckt. Ursache: das Markup benutzte
  `min-w-0`, um dem Textbereich das Schrumpfen zu erlauben – diese Klasse gehört
  jedoch zu Tailwind, das Bootstrap von webtrees kennt sie nicht. Ohne
  `min-width: 0` darf ein Flex-Element nicht unter seine Inhaltsbreite
  schrumpfen, und weil `text-truncate` Umbrüche verbietet, ist das die volle
  Textbreite. Die Klasse ist jetzt im Modul definiert; sie wurde an **sechs**
  Stellen benutzt, betroffen waren also auch Dokumentenlisten und Übersichtskarten.
- **Weiße Schrift auf weißem Grund im Theme „Potts Modern"** (Issue #20). Die
  Lightbox ist bewusst dunkel, verließ sich für den Hintergrund aber auf das
  Theme. Färbt dieses den Modal-Körper hell, blieb die weiße Schrift unlesbar –
  die Seitenleiste nicht, weil sie ihre Farbe als `style`-Attribut trägt.
  Hintergrund und Schriftfarben sind jetzt für alle Flächen der Lightbox
  ausdrücklich gesetzt und damit vom Theme unabhängig.

## [1.3.2] – 2026-08-14

**Slowakisch ist vollständig.** 147 von 147 Texten, überarbeitet von
**Ladislav Rosival** (Issue #19). Herzlichen Dank!

### Behoben
- **Vier Übersetzungen hätten die Seite zum Absturz gebracht.** In der
  beigesteuerten Fassung stand an vier Stellen `%` statt `%s` – bei der Zählung
  im freien Bestand (alle drei Mehrzahlformen) und in der Vorschauzeile.
  `sprintf()` wirft dabei `Unknown format specifier`, und die Übersichtsseite
  sowie die Galerie nicht eingebundener Medien hätten auf Slowakisch mit einem
  Serverfehler geantwortet. Der Wortlaut blieb unverändert, nur der Platzhalter
  ist repariert.

### Intern
- **Neuer Wächter für Platzhalter.** Ein Test vergleicht in jeder Sprachdatei
  die Formatangaben jeder Übersetzung mit denen des Originals und meldet auch
  jedes `%`, das zu keiner gültigen Angabe gehört. `msgfmt --check` fängt das
  nicht: gettext prüft nur Einträge, die als c-format markiert sind, und diese
  Markierung fehlt je nach Werkzeug.
- Der Renderprüfer deckt jetzt auch die Übersichtsseite, automatische
  Sammlungen und die Galerie nicht eingebundener Medien ab. Zwei der vier
  Fehler wären ihm vorher entgangen, weil diese Ansichten nicht geprüft wurden.

## [1.3.1] – 2026-08-07

### Behoben
- **„Name" war mehrdeutig und übernahm die falsche Übersetzung.** Der Text
  bezeichnet den Namen einer Sammlung, stimmte als Schlüssel aber mit dem
  webtrees-Kern überein, wo „Name" den Namen einer **Person** meint. Seit der
  Umstellung auf englische Schlüssel griff dadurch die Kern-Übersetzung. In
  Sprachen, die zwischen Personen- und Sachbezeichnung unterscheiden, war das
  schlicht falsch – im Slowakischen etwa „meno" (Person) statt „názov" (Sache).
  Der Schlüssel heißt jetzt `Collection name`; jede Sprache wählt ihr Wort
  selbst, Deutsch zeigt weiterhin „Name". Danke an **@ro-la** für den Hinweis
  (Issue #19).

### Hinweis für Übersetzer
Ein Schlüssel hat sich geändert: `Name` → `Collection name`. Vorhandene
Übersetzungen wurden übernommen, es ist nichts neu zu übersetzen.

## [1.3.0] – 2026-08-07

**Englisch als Quellsprache.** Das Modul war das einzige im webtrees-Umfeld, das
Deutsch als Quellsprache benutzte. Angeregt von **@ro-la** (Issue #19).

### Geändert
- **Die Quellsprache ist jetzt Englisch.** Bisher waren die Schlüssel der
  Übersetzungen deutsch. webtrees legt die Sprachkataloge flach übereinander,
  und die Kataloge des Kerns sind nach englischen Schlüsseln aufgebaut. Daraus
  folgten zwei Nachteile: ohne passende Übersetzung erschien **Deutsch** statt
  Englisch, und vorhandene webtrees-Übersetzungen konnten nie greifen, selbst
  bei wortgleichen Texten.

  Für bestehende Sprachen ändert sich **nichts** – Deutsch, Englisch,
  Niederländisch, Spanisch, Katalanisch und Slowakisch wurden vollständig
  übernommen und nur neu verschlüsselt. Eine Sprache **ohne** eigene
  Übersetzung, etwa Ungarisch oder Polnisch, bekommt jetzt zehn Texte direkt vom
  webtrees-Kern und alle übrigen auf Englisch statt auf Deutsch.

### Hinweis für Übersetzer
Wer eine eigene `.po` gepflegt hat, muss sie **nicht** neu übersetzen, aber neu
verschlüsseln: die `msgid` sind jetzt die englischen Texte. `resources/lang/en.po`
aus dieser Version ist die Vorlage. Für vorhandene Dateien geht das mit
`msgmerge`; im Zweifel schicken Sie die alte Datei, wir übernehmen das.

### Intern
- Alle Kataloge sind mit `msgcat --no-wrap --sort-output` normalisiert. Ohne das
  trennen sich Einträge uneinheitlich, was beim Umschlüsseln um ein Haar einen
  spanischen Eintrag gekostet hätte.
- Belegt statt behauptet: für jeden der 205 übersetzbaren Texte wurde der alte
  deutsche Quelltext gegen den neuen englischen plus `de.po` gehalten – kein
  einziger Unterschied. Die Menge der Übersetzungswerte ist in jeder Sprache
  zeichengleich geblieben.
- **Neue Laufzeitprüfung:** ein Test rendert jede Ansicht in jeder Sprache und
  schlägt fehl, sobald dabei etwas wirft. Genau diese Ebene fehlte, als v1.2.6
  auf jeder Galerie abstürzte – Unit-Tests und statische Analyse prüfen
  Struktur, nicht Laufzeit. Gegen den bekannten Absturz gegengeprüft: er wird
  in allen sechs Sprachen erkannt.

## [1.2.7] – 2026-08-07

**Hotfix (Blocker).** Das Öffnen einer Sammlung führte in v1.2.6 zu einem
Serverfehler. Bitte sofort aktualisieren.

### Behoben
- **Serverfehler beim Öffnen einer Sammlung.** `I18N::translate()` reicht seine
  Nachricht immer durch `sprintf()`. Zwei mit v1.2.6 eingeführte Texte enthalten
  einen Platzhalter, der erst im Browser gefüllt wird – ohne Argument warf PHP
  „2 arguments are required, 1 given" und die Seite brach ab. Betroffen war jede
  Ansicht mit Lightbox, also alle Galerien. Ein Test prüft jetzt, dass kein
  übersetzter Text mit Platzhalter ohne Argument aufgerufen wird.

## [1.2.6] – 2026-08-07

**Slowakisch.** Dazu zwei Fehler in der Lightbox-Seitenleiste, die beim Einbau
der Übersetzung sichtbar wurden.

### Hinzugefügt
- **Slowakische Oberfläche** (`sk`), beigesteuert von **Ladislav Rosival**
  (Issue #19). Herzlichen Dank!

### Behoben
- **Texte der Seitenleiste blieben deutsch, egal welche Sprache eingestellt war.**
  Sie werden vom JavaScript zur Laufzeit gesetzt, und dort greift
  `I18N::translate()` nicht. Die betroffenen Texte – der Abgleich EXIF ↔
  webtrees, seine Schaltfläche, die Statusmeldungen beim Speichern und
  Umbenennen sowie „… und N weitere" – werden jetzt übersetzt an das Skript
  übergeben. Zusätzlich waren vier Platzhalter und zwei Schaltflächen im Markup
  gar nicht als übersetzbar ausgezeichnet.
- **Platzhalter in der Seitenleiste waren nicht lesbar.** Sie stehen auf fast
  schwarzem Grund, wo Bootstraps Vorgabefarbe praktisch unsichtbar ist – der
  Text war nur beim Markieren zu erkennen.

### Intern
- Der i18n-Test las `msgid` zeilenweise und übersah dadurch Einträge, die über
  mehrere Zeilen umbrochen sind. Unsere eigenen Dateien schreiben lange Texte
  auf eine Zeile, Poedit bricht sie um – der Fehler fiel deshalb erst an einer
  beigesteuerten Übersetzung auf, die er fälschlich als unvollständig meldete.

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

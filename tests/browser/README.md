# Prüfstand für die Bedienung auf Berührungsgeräten

Gesten lassen sich in PHPUnit nicht ausführen. Diese beiden Dateien bauen
stattdessen eine Testseite, die **die echten Moduldateien lädt** – Markup aus
`_lightbox.phtml`, Stylesheet aus `sammlungen.phtml`, Skript aus
`sammlung-galerie.js`, dazu das Bootstrap-Bündel von webtrees – und treibt die
Handler mit synthetischen Berührungen an. Nur die Fotos sind erzeugte Raster;
es wandern also keine echten Bilder durch die Messung.

Warum keine Kopie des Markups: eine Attrappe, die sich anders verhält als das
Original, ist schlimmer als keine. Zwei Fehler dieser Art sind schon
durchgerutscht – ein `%s` ohne Argument, das erst live einen 500er warf, und
ein fehlendes `dir="ltr"`, ohne das `[dir=ltr] .modal{left:0}` nicht greift und
der Dialog scheinbar falsch sitzt.

## Bauen und ausliefern

```bash
php tests/browser/pruefstand.php                 # baut nach /tmp/sammlungen-pruefstand
php -S 127.0.0.1:8099 -t /tmp/sammlungen-pruefstand
```

Zweites Argument ist die webtrees-Wurzel, falls das Modul nicht unter
`modules_v4/` einer Installation liegt.

## Am Telefon

```bash
adb reverse tcp:8099 tcp:8099
```

Dann im Browser des Geräts `http://127.0.0.1:8099/index.html#auto` öffnen – der
Selbsttest läuft von allein und schreibt Bestanden/Gescheitert oben auf den
Schirm. Ohne `#auto` startet ihn die Schaltfläche.

Mit einem Chromium-Browser (Chrome, Brave) geht es auch ohne Screenshots:

```bash
adb forward tcp:9222 localabstract:chrome_devtools_remote
curl -s http://127.0.0.1:9222/json/list        # Ziel-Tab suchen
```

Danach lässt sich über das DevTools-Protokoll `document.getElementById('bericht').innerText`
auslesen und jeder Messwert direkt am Gerät abfragen.

## Was geprüft wird

Zoom (Doppeltippen, zwei Finger, Grenzen, Zurücksetzen beim Bildwechsel),
Blättern (nur unvergrößert, nicht bei kurzem Wackeln, nicht im Zoom), das
Wegtippen der Leisten samt Ersatz-Schließer, und die Geometrie des Dialogs
(bündig, Schaltflächen innerhalb des Schirms).

Gemessen wird über `DOMMatrix` auf dem berechneten Stil, nicht über den Text
der `style`-Eigenschaft: Chromium schreibt dort schon mal `2.28882e-05px`, und
ein Zahlenmuster ohne Exponent liest das als „kein Treffer".

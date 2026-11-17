# Den Spiegel vom Handy aus einstellen

Alles, was der Spiegel anzeigt, lässt sich über eine Webseite einstellen –
vom Handy, Tablet oder Computer aus. Eine App braucht es nicht. Nach dem
Speichern aktualisiert sich der Spiegel innerhalb von etwa zehn Sekunden
von selbst.

---

## 1. Die Verwaltung öffnen

**Das Handy muss im selben WLAN sein wie der Spiegel.**

Im Browser (Safari, Chrome, …) diese Adresse eingeben:

```text
http://mirror.local/admin/
```

**Klappt das nicht** – das ist auf vielen Android-Handys so –, die
Zahlenadresse des Spiegels verwenden. Sie steht auf dem Spiegel selbst,
ganz unten in der kleinen grauen Zeile, gleich nach „MIRROR“:

```text
MIRROR · 192.168.2.34 · 0.82 · 213 / 426 MB · 0 d 03:12 h
         ^^^^^^^^^^^^
```

Damit dann im Browser:

```text
http://192.168.2.34/admin/
```

(mit der Zahl vom eigenen Spiegel, `http://` vorne und `/admin/` hinten
nicht vergessen).

### Auf den Startbildschirm legen

Damit die Seite künftig wie eine App mit einem Tipp aufgeht:

* **iPhone (Safari):** unten auf das Teilen-Symbol (Quadrat mit Pfeil) →
  „Zum Home-Bildschirm“
* **Android (Chrome):** oben rechts auf die drei Punkte →
  „Zum Startbildschirm hinzufügen“

---

## 2. So wird gespeichert

Die Seite ist in Bereiche gegliedert: Module, Uhr, Wetter, Termine und so
weiter. Ändern, was man möchte, dann unten auf den blauen Knopf
**„Einstellungen speichern“** tippen. Er bleibt beim Scrollen immer am
unteren Rand.

Oben erscheint dann ein grüner Hinweis. Der Spiegel lädt sich kurz neu und
zeigt die Änderung.

> Fotos haben einen eigenen Knopf – siehe Abschnitt 4.

---

## 3. Die Bereiche im Einzelnen

### Module

Ein Häkchen heißt: wird angezeigt. Ohne Häkchen verschwindet der Bereich
ganz vom Spiegel. Je weniger Module aktiv sind, desto mehr Platz bekommen
die Fotos der Diashow.

### Uhr

* **Sekunden anzeigen** – die Uhr tickt sichtbar. Kostet den kleinen
  Rechner im Spiegel etwas Kraft, deshalb von Haus aus aus.
* **Kalenderwoche anzeigen** – zeigt „KW 47“ neben dem Datum.
* **Format** – 24 Stunden oder 12 Stunden mit AM/PM.

### Wetter

* **Stadt** – einfach den Ortsnamen eintragen, zum Beispiel `Hamburg`.
  Bei kleinen Orten mit mehrfach vorkommendem Namen die nächste größere
  Stadt nehmen.
* **Land** – zwei Buchstaben, für Deutschland `DE`.
* Darunter lässt sich einzeln abschalten: Vorhersage, Sonnenauf- und
  -untergang, Mondphase, Luftqualität.

### Termine

* **Darstellung** – eine Liste der nächsten Termine oder ein Monatskalender.
* **Vorausschau in Tagen** – wie weit die Liste in die Zukunft reicht.
* **Höchstens so viele Einträge** – damit die Liste nicht zu lang wird.
* **Feiertage anzeigen** und **Bundesland** – die gesetzlichen Feiertage,
  passend zum eigenen Bundesland.
* **ICS-Datei importieren** – die eigenen Termine, siehe unten.

#### Eigene Termine auf den Spiegel bringen

Der Spiegel liest eine Kalenderdatei im Format **ICS**. Geburtstage und
andere Serientermine werden dabei richtig wiederholt, mehrtägige Termine
wie ein Urlaub stehen mit „bis …“ da.

So kommt man an die Datei – am einfachsten am Computer:

* **Google Kalender:** calendar.google.com → Zahnrad → „Einstellungen“ →
  „Importieren und exportieren“ → „Exportieren“. Es kommt eine ZIP-Datei;
  darin liegt pro Kalender eine `.ics`-Datei.
* **Outlook:** Kalender öffnen → „Datei“ → „Kalender speichern“.

Dann in der Verwaltung bei „ICS-Datei importieren“ die Datei auswählen und
**speichern**.

> Der Import ist eine Momentaufnahme. Neue Termine kommen erst auf den
> Spiegel, wenn die Datei erneut importiert wird – die neue ersetzt dann
> die alte. Mit „Importierten Kalender entfernen“ verschwinden alle
> eigenen Termine wieder.

### Countdown

Zählt die Tage bis zu einem Termin herunter. Eine Zeile pro Eintrag, erst
das Datum, dann die Beschriftung:

```text
24.12.2026 Weihnachten
15.03.2027 Urlaub
1.5.27 Maifeiertag
```

Bis zu zehn Einträge. Steht vor einer Zeile kein gültiges Datum, wird sie
nicht übernommen – die Seite sagt dann Bescheid.

### Liste

Eine Einkaufs- oder To-do-Liste. Eine Zeile pro Eintrag. Ist etwas
erledigt, einfach die Zeile löschen und speichern. Die Überschrift lässt
sich frei wählen, zum Beispiel „Einkaufsliste“ oder „Heute erledigen“.

### Nachrichten

Zeigt die aktuellen Schlagzeilen. Voreingestellt ist die Tagesschau.
Wer andere Nachrichten möchte, trägt bei **RSS-Feed** die Feed-Adresse
einer anderen Seite ein. Die meisten Nachrichtenseiten nennen sie unter
„RSS“. **Anzahl Schlagzeilen** legt fest, wie viele erscheinen (1 bis 10).

### Zitate

Jeden Tag erscheint ein anderes Zitat aus dieser Liste. Eine Zeile pro
Zitat – eigene Sprüche einfach dazuschreiben.

### Diashow

**Bildwechsel in Sekunden** – wie lange jedes Foto stehen bleibt.

---

## 4. Fotos für die Diashow

1. Ganz unten bei **„Bilder hochladen“** auf „Dateien auswählen“ tippen.
2. „Fotomediathek“ beziehungsweise „Galerie“ wählen. **Mehrere Fotos auf
   einmal** auswählen geht auch.
3. Auf **„Hochladen“** tippen und warten, bis die Seite neu lädt.
   Bei mehreren Fotos zählt sie mit: „Bild 2 von 5 …“

Die Fotos werden schon auf dem Handy verkleinert. Das geht schnell und
schont den Spiegel – die Originale auf dem Handy bleiben unverändert.

**Querformat-Fotos** füllen die Diashow am besten aus. Hochformat geht
auch, wird aber kleiner dargestellt.

**Löschen:** Unter „Vorhandene Bilder“ steht jedes Foto mit einem roten
„Löschen“-Knopf.

> Die Diashow erscheint nur, wenn oben bei den Modulen „Diashow“
> angehakt ist.

---

## 5. Wenn etwas nicht klappt

| Problem | Lösung |
|---|---|
| Die Seite lädt nicht | Ist das Handy im selben WLAN wie der Spiegel? Statt `mirror.local` die Zahlenadresse vom Spiegel nehmen (Abschnitt 1). |
| Die Zahlenadresse steht nicht auf dem Spiegel | Das Modul „Systeminfo“ ist abgeschaltet. Dann steht die Adresse in der Übersicht des Routers bei den verbundenen Geräten, unter dem Namen „mirror“. |
| Eine Änderung erscheint nicht | Wurde gespeichert? Oben muss der grüne Hinweis stehen. Danach bis zu zehn Sekunden warten. |
| Beim Wetter stehen nur Striche | Ortsnamen prüfen, eventuell die nächste größere Stadt eintragen. Nach einer Änderung kann es einen Moment dauern. |
| Ein Foto lässt sich nicht hochladen | Noch einmal versuchen. Hilft das nicht, das Foto am Handy einmal als Bildschirmfoto oder Kopie speichern und diese hochladen. |
| Der Spiegel ist schwarz oder hängt | Stecker ziehen, zehn Sekunden warten, wieder einstecken. Nach etwa zwei Minuten erscheint der Spiegel wieder. |
| Der Spiegel steht an einem neuen Ort oder das WLAN hat sich geändert | Der Spiegel kennt nur die WLANs, die ihm eingerichtet wurden. Ein neues Netz muss einmal am Spiegel selbst eingetragen werden – siehe [Einrichtung](EINRICHTUNG.md#wlan-wechseln). |

---

## Gut zu wissen

* **Die Verwaltung hat kein Passwort.** Jeder, der im selben WLAN ist, kann
  den Spiegel einstellen. Im eigenen Zuhause ist das so gewollt.
* **Die Fotos liegen nur auf dem Spiegel**, und zwar verkleinert. Die
  Originale also auf dem Handy behalten.
* Der Spiegel braucht nachts nicht ausgeschaltet zu werden. Er ist für den
  Dauerbetrieb eingerichtet.

# Vinarium – Produktbeschreibung

**Stand:** 2026-04-12
**Status:** Entwurf v1
**Arbeitstitel:** Vinarium (lateinisch „Weinkeller")

---

## 1. Vision & Zielgruppe

### Vision
Eine native Nextcloud-App, mit der Weinliebhaber ihren privaten Bestand strukturiert verwalten, die physische Lagerposition flexibel abbilden, und ihre Verkostungserlebnisse dauerhaft dokumentieren können – alles privat, selbst-gehostet und ohne Abhängigkeit von kommerziellen Plattformen wie Vivino oder Wine-Searcher.

### Zielgruppe
- **Primär:** Private Weinsammler mit 50–500 Flaschen, die eigene Nextcloud-Instanzen betreiben und eine strukturierte Alternative zu Excel-Listen oder Vivino suchen.
- **Sekundär:** Nextcloud-Community, die nach Spezial-Apps für Hobby/Lifestyle sucht.

### Kernbedürfnisse, die die App adressiert
1. **Überblick behalten** – Was habe ich? Wie viel? Welcher Jahrgang?
2. **Position finden** – Wo genau liegt die bestimmte Flasche im Regal?
3. **Umsortieren** – Bestand verändert sich, Regale werden neu belegt (intuitiv per Drag & Drop)
4. **Erinnerung bewahren** – Wie hat der Wein geschmeckt? Wie hat er sich über mehrere Flaschen hinweg entwickelt?
5. **Trinkfenster nutzen** – Was sollte ich bald trinken, bevor es zu spät ist?

---

## 2. Name & Konzept

### Name
**Vinarium** – lateinisch für Weinkeller. Klangvoll, kurz genug, thematisch eindeutig.

- Nextcloud App-ID: `vinarium`
- Kein Name-Clash im Nextcloud App Store (Stand 2026-04)

### Leitgedanken
- **Privat und selbst-gehostet** – keine Cloud-Dependency, keine Tracker
- **Flexibles Lagermodell** – keine Annahme über feste Regal-Geometrie, passt auch für Weinschrank, Holzregal, Keller-Fach
- **Normalisierte Daten** – Weingut/Wein/Jahrgang/Kauf/Flasche getrennt, damit Historien und Wiederholkäufe sauber abgebildet sind
- **Barrierearme Erfassung** – wenige Pflichtfelder, Autocomplete, Barcode-Wiedererkennung
- **Bestehendes Nextcloud-Ökosystem nutzen** – Sharing via Nextcloud-Standard (Phase 2), Fotos im File-Storage des Users

---

## 3. User Stories

### MVP

**US-01 – Regal anlegen**
_Als User möchte ich mein Weinregal digital abbilden, indem ich Lagerböden, Stapel-Ebenen und Reihen konfiguriere, damit die App meine reale Lagersituation widerspiegelt._

**US-02 – Weingut & Wein erfassen**
_Als User möchte ich Weingüter und Weine getrennt erfassen, damit ich bei mehreren Jahrgängen desselben Weins nicht alles doppelt eintippen muss._

**US-03 – Jahrgang + Kauf erfassen**
_Als User möchte ich zu einem Wein mehrere Jahrgänge und zu jedem Jahrgang mehrere Käufe mit Preis und Händler festhalten, damit ich nachvollziehen kann, wann ich was zu welchem Preis gekauft habe._

**US-04 – Flaschen in Regal einräumen**
_Als User möchte ich nach einem Kauf die Flaschen an konkrete Positionen im Regal legen, damit ich später jede einzelne wiederfinde._

**US-05 – Flasche per Drag & Drop umsortieren**
_Als User möchte ich Flaschen per Drag & Drop zwischen Slots verschieben können, damit die App bei Umsortierungen nicht im Weg steht._

**US-06 – Flasche entnehmen und verkosten**
_Als User möchte ich beim Öffnen einer Flasche eine Verkostungsnotiz mit Datum, Bewertung und freiem Text erfassen, damit ich später nachvollziehen kann, wie mir jede Flasche geschmeckt hat._

**US-07 – Barcode-Wiedererkennung**
_Als User möchte ich beim Einräumen einer bereits bekannten Flasche den Barcode scannen und die Stammdaten automatisch ausgefüllt bekommen, damit Nachkäufe superschnell gehen._

**US-08 – Übersicht und Suche**
_Als User möchte ich meinen Gesamtbestand nach Farbe, Region, Jahrgang, Trinkfenster filtern, damit ich bei spontanen Anlässen schnell den passenden Wein finde._

**US-09 – Trinkfenster-Warnung**
_Als User möchte ich eine Übersicht „Was sollte ich bald trinken" sehen, damit mir keine Flasche über ihr Trinkfenster hinaus vergammelt._

**US-10 – CSV-Export**
_Als User möchte ich meinen gesamten Bestand als CSV exportieren können, damit ich Backups oder externe Analysen machen kann._

### Phase 2 (nach MVP)

**US-21 – Multi-User / Keller-Sharing** – Kellerbesitzer teilt Keller mit Ehepartner/Freunden (Read oder Read-Write).
**US-22 – Label-Foto + KI-OCR** – Foto vom Etikett → Stammdaten werden per Vision-LLM vorgeschlagen.
**US-23 – Externe Wein-Lookups** – Beim Scan eines unbekannten Barcodes Abfrage an Open Food Facts; Autocomplete für Weingüter/Regionen aus Wikidata.
**US-24 – Statistik-Dashboard** – Bestandsentwicklung über Zeit, Ausgaben, Top-Weingüter, Bewertungsverteilung.
**US-25 – Import aus CellarTracker** – CSV-Import für Migration bestehender Sammlungen.
**US-26 – Mehrere Keller pro User** – Keller-Container (z.B. Haupt-Weinkühlschrank + Zweitkeller).

### Ausdrücklich nicht geplant
- Social-Features (Freunde sehen Bewertungen, Sharing auf sozialen Plattformen)
- Native mobile Apps (iOS/Android) – Web-UI im Nextcloud-PWA-Wrapper reicht
- Wein-Handel oder Preis-Monitoring
- Scraping von Händler-Seiten

---

## 4. Funktionsumfang MVP

### 4.1 Lagermodell
- **Keller** (Container, 1 pro User im MVP, Datenmodell aber Multi-Keller-ready)
- **Regale** beliebig viele, mit Namen (z.B. „Weinschrank Küche", „Kellerregal links")
- Pro Regal: **Lagerböden** mit frei konfigurierbarer Anzahl
- Pro Lagerboden:
  - 1–3 **Stapel-Ebenen** übereinander (User definiert pro Lagerboden)
  - Fest: **vordere und hintere Reihe**
  - **Anzahl Flaschen pro Reihe frei konfigurierbar** (unterstützt Magnum/Burgunder neben Bordeaux, pro Lagerboden und pro Ebene unterschiedlich)
- **Slot** = konkrete Position (Lagerboden × Ebene × Reihe × Spalte)

#### Default-Regal beim ersten Start
Beim erstmaligen Aufruf schlägt die App folgende Standard-Konfiguration vor (vom User anpassbar):
- 1 Regal mit Namen „Weinschrank"
- 6 Lagerböden
- pro Lagerboden 3 Stapel-Ebenen, alternierend:
  - Ebene 1 (unten): vorne 6 / hinten 7
  - Ebene 2 (Mitte): vorne 7 / hinten 6
  - Ebene 3 (oben): vorne 6 / hinten 7
- **Kapazität gesamt: 234 Flaschen**

#### Umgang mit Regal-Konfigurations-Änderungen (wichtig!)
Wenn User nachträglich die Geometrie ändert (Ebenen reduzieren, Spalten verkleinern, Lagerboden löschen), dürfen **bestehende Flaschen niemals ungesichert verloren gehen**. Verhalten:

1. **Vor-Änderungs-Dialog:** App zeigt an, wie viele Flaschen in den betroffenen Slots liegen
2. **Automatisches Verschieben in „Parkzone":** Flaschen in entfernten Slots behalten ihren Datensatz, bekommen aber `slot_id = null` (Status bleibt `in_storage`)
3. **Parkzone-Ansicht:** eigene Sektion in der Regalansicht „Nicht zugeordnete Flaschen (N)" – immer sichtbar, wenn mindestens eine Flasche dort liegt
4. **Re-Platzierung via Drag & Drop:** Aus der Parkzone direkt in freie Slots ziehen
5. **Undo:** Für 10 Sekunden nach Konfig-Änderung kann der User die Änderung zurücknehmen

Diese Parkzone ist gleichzeitig nützlich beim ersten Einräumen: Nach einem Kauf werden alle neuen Flaschen erstmal in die Parkzone gelegt, von dort per Drag & Drop einsortiert.

### 4.2 Weindaten-Erfassung
**Weingut:** Name, Land, Region, Website (optional), Notizen (optional)

**Wein:** Name, Weingut (Referenz), Farbe (Rot/Weiß/Rosé/Schaum/Süß), Rebsorte(n), Appellation/Klassifikation, Notizen, Barcode (optional, für Wiedererkennung)

**Jahrgang:** Jahr, Alkoholgehalt, Trinkreife von–bis, externe Bewertung (0–100), Quelle der externen Bewertung (Freitext, z.B. „Lobenberg", „Parker"), Beschreibung (Freitext), Referenz-URL

**Kauf:** Datum, Händler, Stückpreis, Währung, Anzahl, Flaschengröße (0.375 / 0.5 / 0.75 / 1.0 / 1.5 / 3.0 Liter), Notiz

**Flasche** (einzelne Instanz): Kauf-Referenz, Position (Slot) oder `null` (wenn konsumiert/verschenkt/verloren), Status (eingelagert / getrunken / verschenkt / verloren), Foto (optional, im Nextcloud-File-Storage), Notizen

**Verkostung:** Datum, Bewertung 1–10 (halbe Schritte möglich), Notiz, Anlass (optional), Begleiter (optional), **Fotos** (optional, mehrere möglich – z.B. Glas, Menü, Verkostungsrunde). Mehrere Verkostungen pro Flasche möglich (z.B. Anstich + zweiter Tag).

**Bewertungs-UI:**
- **Eingabe:** klickbare 10-Punkt-Skala mit halben Schritten (keine manuelle Tastatur-Eingabe nötig)
- **Anzeige in Listen:** Zahlenwert + horizontaler Balken als Füllstand-Visualisierung
- **Externe Bewertung** (0–100): separates Feld am Jahrgang, Anzeige ebenfalls als Zahl + Balken, aber farblich abgegrenzt (z.B. goldgelb)

### 4.3 UI-Bausteine
- **Dashboard** – Startseite: Gesamt-Flaschenzahl, Verteilung nach Farbe/Region, „bald trinken" (Trinkfenster läuft bald ab), letzte Verkostungen, Hinweis auf Parkzonen-Flaschen
- **Regalansicht** – Visuelle Darstellung des/der Regale + Parkzone, Flaschen als klickbare Objekte, Drag & Drop zum Umsortieren (auch Parkzone ↔ Slot), Farbe/Icon je nach Weinfarbe
- **Weinbestand (Liste)** – Tabelle mit Sortierung und Filter: Farbe, Region, Jahrgang, Trinkfenster, Weingut, Status, Bewertung, Kaufdatum
- **Wein-Detail** – Zeigt alle Jahrgänge, Käufe, einzelne Flaschen, aggregierte Verkostungen
- **Erfassungs-Wizard** – Mehrstufige Maske: Weingut auswählen/neu → Wein auswählen/neu → Jahrgang auswählen/neu → Kauf erfassen → neue Flaschen landen in Parkzone (direktes Einsortieren optional)
- **Verkostungs-Dialog** – Modal beim „Flasche öffnen"-Flow (inkl. optionaler Foto-Upload)
- **Einstellungen** – Regal-Konfiguration (mit Sicherheitsdialog), Sprache, Währung, Export
- **Trinkfenster-Benachrichtigungen** – sowohl im Dashboard als Widget als auch als Nextcloud-Notification (einstellbare Vorlaufzeit, z.B. „3 Monate vor Ende")

### 4.4 Barcode-Scan (Wiedererkennung, kein externer Lookup)
- Scan über Handy-Kamera via `zxing-wasm`
- **Nur mobil** (keine Desktop-Webcam-Integration – Aufwand lohnt nicht)
- Erst-Erfassung: User scannt Flasche nach manuellem Anlegen des Weins → Barcode wird am Wein-Datensatz gespeichert
- Folge-Erfassung: Scan → App findet Wein in eigener DB → Stammdaten werden im Erfassungs-Wizard vorausgefüllt, nur noch Jahrgang/Kauf/Position nötig
- Bei unbekanntem Barcode: einfacher Fallback auf manuelle Eingabe (kein Frust-Moment)
- **Kein manuelles Eintippen von EAN** – Barcode wird ausschließlich per Scan erfasst

### 4.5 Export
- **CSV-Export** des gesamten Bestands (ein Zeile pro Flasche mit denormalisierten Spalten)
- **JSON-Export** für Vollbackup (Phase 2 optional)

---

## 5. Funktionsumfang Phase 2

| Feature | Begründung |
|---------|-----------|
| Multi-User-Sharing (Keller teilen) | Ehepartner/Freunde lesender oder schreibender Zugriff |
| KI-OCR für Label-Fotos | Erfassung neuer Weine beschleunigen, Erfahrung mit Claude Haiku/Sonnet vorhanden |
| Externer Barcode-Lookup (Open Food Facts) | Für Weine, die in OFF vorhanden sind; explizit als „beste Näherung" kommuniziert |
| Wikidata-Autocomplete | Weingüter, Regionen, Rebsorten standardisiert vorschlagen |
| Statistik-Dashboard | Bestandsverlauf, Ausgaben, Bewertungsverteilung |
| Mehrere Keller pro User | Für User mit mehr als einem Lagerort |
| CellarTracker-CSV-Import | Migration von bestehender Sammlung |
| JSON-Vollexport/-Import | Backup-Portabilität |

### Zukunftsidee (nicht geplant)
- **Lobenberg-Integration als separate App** – evtl. auf Basis eines Schnellwerk-artigen Ansatzes eine eigenständige Lobenberg-Anbindung bauen (bestellhistorie, Beschreibungen). Nicht Teil von Vinarium, sondern eigenständiges Projekt.

---

## 6. Datenmodell (ER-Skizze)

```
Cellar (Keller)
 ├─ id
 ├─ name
 ├─ owner_user_id        ← Multi-User-ready
 └─ created_at

Shelf (Regal) ──N:1── Cellar
 ├─ id
 ├─ cellar_id
 ├─ name
 └─ sort_order

Compartment (Lagerboden) ──N:1── Shelf
 ├─ id
 ├─ shelf_id
 ├─ label                ← z.B. "Lagerboden 3"
 ├─ sort_order
 ├─ levels               ← 1..3 (Stapel-Ebenen übereinander)
 ├─ columns_front        ← Anzahl Slots in vorderer Reihe
 └─ columns_back         ← Anzahl Slots in hinterer Reihe

Slot (Steckplatz) ──N:1── Compartment
 ├─ id
 ├─ compartment_id
 ├─ level                ← 0..(levels-1)
 ├─ row                  ← front | back
 └─ column               ← 0..(columns_x-1)

Producer (Weingut)
 ├─ id
 ├─ owner_user_id
 ├─ name
 ├─ country
 ├─ region
 ├─ website
 └─ notes

Wine (Wein) ──N:1── Producer
 ├─ id
 ├─ producer_id
 ├─ name
 ├─ color                ← red | white | rose | sparkling | sweet
 ├─ grape_varieties      ← JSON-Array oder Text
 ├─ appellation
 ├─ barcode              ← optional, unique per owner_user_id
 └─ notes

Vintage (Jahrgang) ──N:1── Wine
 ├─ id
 ├─ wine_id
 ├─ year
 ├─ alcohol_percent
 ├─ drink_from
 ├─ drink_until
 ├─ external_rating      ← 0..100
 ├─ external_rating_source  ← Freitext
 ├─ description          ← Freitext
 └─ reference_url

Purchase (Kauf) ──N:1── Vintage
 ├─ id
 ├─ vintage_id
 ├─ purchased_at
 ├─ vendor
 ├─ unit_price
 ├─ currency
 ├─ quantity             ← nur informativ; Wahrheit = COUNT(Bottle WHERE purchase_id=...)
 ├─ bottle_size_ml       ← 375 | 500 | 750 | 1000 | 1500 | 3000
 └─ notes

Bottle (Flasche) ──N:1── Purchase
 ├─ id
 ├─ purchase_id
 ├─ slot_id              ← nullable (null = nicht mehr eingelagert)
 ├─ status               ← in_storage | consumed | gifted | lost
 ├─ photo_file_id        ← Nextcloud-File-ID (optional)
 └─ notes

Tasting (Verkostung) ──N:1── Bottle
 ├─ id
 ├─ bottle_id
 ├─ tasted_at
 ├─ rating               ← 1.0..10.0 in 0.5er Schritten
 ├─ notes
 ├─ occasion
 ├─ companions
 └─ photo_file_ids       ← JSON-Array von Nextcloud-File-IDs (0..N Fotos)
```

**Wichtige Entscheidungen im Modell:**
- `owner_user_id` auf `Cellar` und `Producer` → Datenmodell ist Multi-User-ready, auch wenn MVP nur Single-User liefert. Phase-2-Sharing wird reine ACL-/UI-Arbeit, kein Daten-Refactoring.
- `Slot` vorab materialisiert (nicht implizit aus `Compartment` berechnet), damit `Bottle.slot_id` eine echte Foreign Key sein kann und Drag & Drop einfach bleibt.
- `barcode` wird am `Wine` gespeichert (nicht an der einzelnen Flasche), weil Wein-Barcodes über den Jahrgang hinweg typischerweise gleich bleiben.
- Mehrere `Tasting`-Einträge pro `Bottle` → deckt den Fall „1. Glas am Tag X, Rest am Tag X+1" ab.

---

## 7. Technische Architektur

### 7.1 Stack
| Schicht | Technologie |
|---------|-------------|
| Plattform | Nextcloud 31+ (getestet gegen 31, 32, 33) |
| Backend | PHP 8.2+, Nextcloud-App-Framework (Controller, Service, Entity, QBMapper) |
| Datenbank | Nextcloud-Standard: MySQL/MariaDB, PostgreSQL, SQLite – Migrations via Doctrine DBAL |
| Frontend | Vue 3 + Vite + TypeScript |
| UI-Komponenten | `@nextcloud/vue` v9.x |
| Drag & Drop | `vue-draggable-plus` (SortableJS-basis, Touch-Support) |
| Barcode | `zxing-wasm` (WASM, EAN-13/EAN-8/QR) |
| Datei-Storage | Nextcloud File-API (`IRootFolder::getUserFolder()`) – Fotos landen in `/Vinarium/` im Files-Bereich des Users |
| Lokalisierung | DE + EN von Anfang an (Nextcloud-Standard: gettext/Transifex) |
| Icon | Generisches Wein-Icon im Nextcloud-Stil (Platzhalter bis zum Release, Details im Plan) |

### 7.2 Startpunkt
- **Community-Boilerplate:** `cvorwerk/nextcloud-vue3-boilerplate` (der offizielle Nextcloud-Skeleton-Generator erzeugt Stand 2026-04 noch Vue 2 – daher nicht geeignet)
- **Referenz-Codebasis:** Inventory-App von Raimund Schlüssler (`raimund-schluessler/inventory`) als Vorbild für hierarchische Items, Barcode-Integration und Bild-Handling

### 7.3 Wichtige Architektur-Entscheidungen

**Fotos im Nextcloud-File-Storage statt in eigener Tabelle**
- Automatisches Backup, Versionierung, Sharing inklusive
- User kann Fotos auch via Files-App einsehen
- Nur Referenz (`file_id`) in der Vinarium-DB

**Keine externen API-Abhängigkeiten im MVP**
- App funktioniert 100% offline (abgesehen von Nextcloud selbst)
- Externe Lookups (OFF, Wikidata) sind Phase 2 und optional

**Barcode als Wiedererkennungs-Feature**
- Vermeidet Frust durch „nicht gefunden" bei externen Lookups
- Liefert sofortigen Mehrwert bei Nachkäufen

**Multi-User-ready von Anfang an**
- `owner_user_id` in jeder relevanten Tabelle
- Einfachere Phase-2-Erweiterung

### 7.4 Deployment
- **Phase 1:** Lokale Test-Installation per Git-Clone in `apps-extra/` der Nextcloud-Dev-Instanz, Dogfooding durch Primäruser
- **Phase 2:** Release im Nextcloud App Store (Signing, formaler Release-Prozess, EN-Übersetzung muss sauber sein)
- Versions-Pinning aller NPM/Composer-Dependencies (keine `:latest`)

---

## 8. UI-Konzept

### 8.1 Navigation
Primäre Navigation links (Nextcloud-Pattern):
1. **Dashboard** (Startseite)
2. **Regal** (visuelle Ansicht)
3. **Bestand** (Listenansicht)
4. **Weine** (Stammdaten: Weingüter, Weine, Jahrgänge)
5. **Verkostungen** (Historie aller Tastings)
6. **Einstellungen** (Regal-Konfiguration, Export)

### 8.2 Hauptworkflows

**Workflow: Neue Lieferung einräumen (Ideal-Flow)**
1. Bestand → „Neuer Kauf"
2. Scan der ersten Flasche oder Weingut-Suche (Autocomplete)
3. Wenn Wein neu: Wein + Jahrgang anlegen
4. Kauf-Daten erfassen (Datum, Preis, Händler, Anzahl)
5. Jede Flasche auf einen Slot legen (Click auf Slot in Regalansicht oder Auto-Fill-Button „Nächsten freien Slot")
6. Bei mehreren Flaschen desselben Weins: Scan der zweiten Flasche → Wein erkannt → direkter Sprung zu Schritt 5

**Workflow: Flasche trinken**
1. Regalansicht → Klick auf Flasche
2. Detail-Panel öffnet sich → Button „Flasche öffnen"
3. Verkostungs-Dialog: Datum (default heute), Bewertung 1–10, Notiz, optional Anlass/Begleiter
4. Bestätigen → Flasche Status = `consumed`, Slot wird frei

**Workflow: Umräumen (Drag & Drop)**
1. Regalansicht → Flasche mit gedrückter Maus/Finger ziehen
2. Zielslot hervorgehoben beim Hover
3. Drop → sofort gespeichert
4. Undo-Option im Toast-Message für 10 Sekunden

**Workflow: „Was trinke ich heute Abend?"**
1. Dashboard → „Bald trinken"-Widget oder Bestand → Filter „Trinkfenster endet dieses Jahr"
2. Evtl. Zusatzfilter: Farbe, Anlass
3. Klick auf Wein → Regalposition sichtbar

### 8.3 Design-Prinzipien
- Konsistent mit Nextcloud-Design-Sprache (Dark Mode, Accessibility)
- Responsive – Desktop für Erfassung, Mobile für Suche/Verkostung am Regal
- Weinfarbe visuell codiert (rote/weiße/rosé Flaschen-Icons)
- Wenige Pflichtfelder, klare Abgrenzung Pflicht/optional

---

## 9. Nicht-Ziele (MVP)

Explizit nicht gebaut wird:
- ❌ **Multi-User-Sharing** – Phase 2
- ❌ **Label-OCR / KI-Erkennung** – Phase 2
- ❌ **Externe Wein-Datenbanken** (OFF, Vivino, Wine-Searcher) – Phase 2 für OFF, Rest nie
- ❌ **Lobenberg-Integration** – dedizierte Zukunfts-App, nicht Vinarium
- ❌ **Native iOS/Android-App** – Nextcloud-PWA-Wrapper reicht
- ❌ **Social-Features** – keine Freunde, keine öffentlichen Bewertungen
- ❌ **Dedizierte PWA-Features (Service Worker, Offline-Cache)** – nur responsive UI
- ❌ **Mehrere Keller pro User** – Phase 2
- ❌ **Händler-/Preisvergleich, Einkaufslisten** – nicht geplant

---

## 10. Getroffene Entscheidungen (vormals offene Fragen)

| Thema | Entscheidung |
|-------|--------------|
| Bewertungs-Darstellung | Klick-Skala 1–10 (halbe Schritte), Anzeige als Zahl + horizontaler Balken |
| Icon/Logo | Generisches Wein-Icon im Nextcloud-Stil (Platzhalter für MVP, Release-Version offen) |
| App-Store-Release | Als Phase-2-Ziel nach MVP-Stabilisierung, MVP zunächst lokal |
| Default-Regal | 6 Lagerböden × 3 Ebenen, alternierend 6/7 vorne/hinten → 234 Flaschen gesamt, voll anpassbar |
| Regal-Änderungen | Parkzone statt Datenverlust, Warnung + Undo |
| Trinkfenster-Warnung | Dashboard-Widget + Nextcloud-Notification |
| Sprachsupport | DE + EN von Anfang an (wegen App-Store-Ziel) |
| Barcode-Scan | Nur mobile Browser (kein Desktop-Webcam-Support) |
| Verkostungs-Fotos | Mehrere Fotos pro Verkostung möglich (Glas/Menü/Runde) |

---

## 11. Nächste Schritte

1. Produktbeschreibung mit User reviewen, offene Fragen klären
2. **Technischen Plan** erstellen (`./research/vinarium/20260412_Vinarium-Technischer-Plan.md`) mit:
   - Phasierung MVP (z.B. 4–6 Iterationen)
   - Datenbank-Migrations-Skripte
   - API-Endpunkte (REST)
   - Komponenten-Hierarchie Frontend
3. Lokale Nextcloud-Dev-Instanz aufsetzen (Docker Compose, nach AI-First-Standards)
4. Projekt-Scaffold aus `cvorwerk/nextcloud-vue3-boilerplate` aufsetzen
5. MVP implementieren (PIV-Loop: Prime → Implement → Validate)

---

## Anhänge

- Recherche Nextcloud-Ecosystem: [`20260412_Recherche-Nextcloud-Ecosystem.md`](./20260412_Recherche-Nextcloud-Ecosystem.md)
- Recherche Weindatenquellen: [`20260412_Recherche-Weindatenquellen.md`](./20260412_Recherche-Weindatenquellen.md)
- Recherche Frontend-Libraries: [`20260412_Recherche-Frontend-Libraries.md`](./20260412_Recherche-Frontend-Libraries.md)

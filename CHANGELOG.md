# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [0.5.2] - 2026-08-08

### Fixed
- **0.5.1 ließ sich nicht benutzen: die App rendert nicht** — jede Datumsausgabe warf `TypeError: (0, ns.default) is not a function`. Betroffen war `formatDate`, und die steckt im Dashboard und fünf weiteren Ansichten, darunter der Standardroute; sichtbar blieb ein leerer Inhaltsbereich. Ursache war der Bundler-Wechsel in 0.5.1: `@nextcloud/moment` liefert ein UMD-Bundle ohne eigenen `default`-Export, und Vite 8 (Rolldown) greift bei solchen Paketen im Bibliotheks-Build direkt auf `.default` zu, wo Vite 7 zuvor einen Auspack-Helfer eingefügt hatte. `formatDate` kommt jetzt ohne die Bibliothek aus: für ein ISO-Datum als TT.MM.JJJJ genügen ein Muster und eine Kalenderprüfung über UTC. Damit hängt die Funktion an keiner Bundler-Interop mehr, und das Bundle verliert die komplette moment-Sprachdatenbank, rund 132 KB (Fixes #233)

### Changed
- Vite bleibt auf 8. Ein Rückbau auf 7 war zwischenzeitlich erwogen und wieder verworfen: die Interop ist nicht generell defekt, sondern nur für UMD-Pakete ohne `default`. Für gewöhnliche CommonJS-Pakete setzt der eingefügte Helfer `default` korrekt auf `module.exports`, nachgewiesen an `@nextcloud/event-bus`
- `@nextcloud/moment` als Abhängigkeit entfernt

## [0.5.1] - 2026-08-04

### Added
- **Etikett mit Vorder- und Rückseite** — das Etikettenfoto hängt jetzt am Jahrgang statt an der einzelnen Flasche, und es lassen sich zwei Seiten hinterlegen. Die Rückseite trägt in der Praxis Rebsorten, Lage und Abfüller. Bestehende Flaschenfotos wandern als Vorderseite mit; tragen mehrere Flaschen eines Jahrgangs unterschiedliche Fotos, gewinnt die zuerst aufgenommene, keine Datei wird gelöscht (Fixes #190)
- **Etiketten frei zuschneiden** — das feste 3:4-Hochformat entfällt für Etiketten. Rückenetiketten sind oft anders proportioniert; ein erzwungenes Verhältnis schnitt dort Text weg. Verkostungsfotos behalten ihr Verhalten
- **Regal-Reihenfolge per Tastatur** — am aktiven Regal verschieben zwei Knöpfe es eine Position nach links oder rechts. Drag & Drop war bisher der einzige Weg und ist per Tastatur nicht bedienbar (Fixes #211)

### Fixed
- **Vue lief im ausgelieferten Produktionsbundle im Entwicklungsmodus** — ein pauschales `'process.env': {}` in der Vite-Konfiguration ließ `NODE_ENV` als `undefined` durchlaufen, wodurch Warnungen, Prop-Typprüfungen und Devtools-Hooks im Bundle landeten. Nach der Korrektur ist das Bundle rund 100 KB kleiner (gzip 472 → 438 KB)
- **Aktivitäts-Widget zeigte das Datum ohne Jahr** — Einträge aus verschiedenen Jahren waren nicht unterscheidbar, ein Datum in der Zukunft las sich wie ein Sortierfehler. Das Widget nutzt jetzt denselben Datums-Helfer wie die Vollansicht; im Frontend gibt es kein zweites Datumsformat mehr (Fixes #205)
- **Verwaiste Daten nach dem Löschen eines Benutzers** — nach `occ user:delete` blieben Keller- und Weindaten in der Datenbank liegen, über kein UI mehr erreichbar. Ein Listener räumt beide Besitzwurzeln transaktional ab (Fixes #212)
- Aktivitätslog: Die Sortierung „neueste zuerst" verließ sich bei Einträgen ohne Uhrzeit (Geschenk/Verlust) auf einen Zufall der `strcmp`-String-Länge statt auf eine benannte Regel; das Ergebnis bleibt gleich, aber ein künftiges ISO-`T` oder Sekundenbruchteile in einer Quelle würden die Reihenfolge nicht mehr still verschieben (Fixes #214)
- Der Kauf-Wizard lud dasselbe Etikettenfoto einmal pro Flasche hoch — bei sechs Flaschen sechs identische Dateien. Jetzt ein Upload an den Jahrgang

### Changed
- **Migration auf Vite 8** — der Bump scheiterte bisher an einem Laufzeitfehler (`process is not defined`). Ursache waren die esm-bundler-Builds von Vue, Pinia und Vue Router, die eine Ersetzung von `process.env.NODE_ENV` durch den Bundler voraussetzen (Fixes #182)
- `vinarium_vintage` erhält `photo_front_file_id` und `photo_back_file_id`; `vinarium_bottle.photo_file_id` entfällt. Zwei getrennte Migrationsschritte, damit ein Abbruch beim Übertragen die Quelldaten intakt lässt
- Neue Etikettenfotos liegen unter `Vinarium/labels/`; das bisherige Verzeichnis bleibt beim Ausliefern zugelassen, vorhandene Bilder werden nicht verschoben
- Die Foto-Endpunkte sind von `/bottles/{id}/photo` nach `/vintages/{id}/photo/{side}` gewandert
- Unit-Tests 80 → 115; neuer Workflow schließt referenzierte Issues beim Merge nach `develop` (Fixes #213, #216)
- Dependency-Updates: ws, fast-uri, fast-xml-parser, @babel/core, postcss, immutable, js-cookie

## [0.5.0] - 2026-08-02

### Added
- **Weine-Tab zeigt „Im Bestand" neben „Gekauft"** — bisher stand dort nur die Summe aller je gekauften Flaschen, entkorkte, verschenkte und verlorene wurden nie abgezogen. Wer 12 gekauft und eine getrunken hatte, sah weiterhin 12. Die neue Spalte zählt die tatsächlich im Keller liegenden Flaschen und ist visuell führend; die Kaufhistorie steht gedämpft daneben (Fixes #189)
- **Süße-Grad am Jahrgang** — neues optionales Feld (trocken / halbtrocken / lieblich / süß) an der Vintage, weil derselbe Wein je Jahrgang unterschiedlich ausgebaut sein kann. Erfassbar im Kauf-Wizard, im Jahrgangs-Edit und im Detail-Panel, filterbar im Bestand (auch als Deep-Link), angezeigt als Chip in Bestands- und Verkostungsliste (Fixes #89)
- **Eigener Anlage-Wizard für Weine ohne Kauf** — „+ Wein" im Weine-Tab öffnet einen dreistufigen Wizard (Weingut / Wein / Jahrgang, Jahrgang überspringbar). Für geschenkte Flaschen, Altbestand aus der Zeit vor der App oder eine Wunschliste (Fixes #137)
- **Regal-Reihenfolge per Drag & Drop** — Regale lassen sich über einen Griff im Tab umsortieren; die Reihenfolge wird atomar persistiert (Fixes #191)
- **Aktivitätslog unter `#/activity`** — vollständige chronologische Übersicht über Käufe, Verkostungen, Geschenke und Verluste, mit Filter nach Art und Zeitraum sowie „Mehr laden". Der Dashboard-Link „Aktivität alle ›" führt jetzt dorthin statt ersatzweise auf die Verkostungen (Fixes #92)

### Fixed
- Esc schließt Modals auch dann, wenn der Fokus in einem Eingabefeld steht (Fixes #176)
- `composer.lock` war nicht mehr deckungsgleich mit `composer.json`: `nextcloud/ocp` und `doctrine/dbal` fehlten, `composer install` brach ab und die PHP-Testsuite lief lokal nicht mehr
- Zähler in Tabs und Kopfzeile des Bestands blieben nach dem Anlegen oder Löschen von Stammdaten stehen, bis die Seite neu geladen wurde

### Changed
- Zusätzlicher Endpoint `GET /api/v1/vintages/stock` liefert die Bestandszahlen als Aggregat — bewusst nicht aus der bereits geladenen Flaschenliste berechnet, die gefiltert ist und dadurch stillschweigend zu wenig angezeigt hätte
- Neuer Endpoint `PUT /api/v1/cellar/{cellarId}/shelves/order` nimmt die vollständige Zielreihenfolge entgegen und nummeriert in einer Transaktion durch; fremde, fehlende oder doppelte IDs werden abgewiesen
- Neuer Endpoint `GET /api/v1/activity` aggregiert Käufe, Verkostungen und Flaschen-Status-Ereignisse

### Migration
- `vinarium_vintage.sweetness` (nullable VARCHAR(16)) wird ergänzt. Kein Backfill — „nicht angegeben" ist für jede bestehende Zeile die zutreffende Aussage

## [0.4.2] - 2026-07-06

### Added
- **Nextcloud 34 wird unterstützt** (max-version 33 → 34)
- **Dashboard-Volltextsuche** über Weingüter, Weine und Jahrgänge — Relevanz-sortiertes Dropdown mit Tastatursteuerung (⌘K, ↑↓, Enter, Esc), Klick springt in den Bestand; Jahrgang-Treffer zeigen die Flaschenzahl im Bestand (Fixes #103)
- **Bestand: Filter in der URL** — alle Filter (Farbe, Status, Jahrgang, Weingut, Trinkfenster) sind deep-link-fähig und überleben ein Neuladen; neuer Filter „Trinkfenster läuft bis" als Preset-Auswahl; Dashboard-Kachel „Bald trinken → alle" verlinkt direkt auf den passend gefilterten Bestand (Fixes #90)
- Spenden-Link (Ko-fi) in den App-Metadaten

### Changed
- Test- und CI-Härtung: stabilere Frontend-Tests (flushPromises statt nextTick), Typecheck-Fixtures repariert, Release-bewusstes Bot-Review, Dependabot auf Security-Updates reduziert

## [0.4.1] - 2026-06-19

### Added
- **„Würde ich wieder kaufen"-Flag** bei Verkostungen — eigenständiges Empfehlungs-Signal (ja / nein / keine Angabe), unabhängig von der Bewertung; filigraner ✓/✗-Toggle im Entkork-Dialog, Badge im Verkostungs-Detail (Fixes #143)

### Fixed
- Kauf-Wizard Schritt 4: irreführendes Button-Label „📷 Foto aufnehmen" → „Foto hinzufügen" (am Desktop ist es ein Datei-Upload), konsistent mit den übrigen Foto-Buttons (Fixes #142)
- `l10n/de.json` und `l10n/en.json` waren durch ein gerades Anführungszeichen kein valides JSON — auf deutsches schließendes Anführungszeichen korrigiert (Fixes #146)
- CI: Bot-Review nutzt die korrekte PR-Nummer und eine breitere Lese-Allowlist

## [0.4.0] - 2026-06-11

Großes UI-Redesign über alle Ansichten („v4-Konzept") plus neue Detail- und Foto-Funktionen.

### Added
- **Dashboard-Redesign** — Bestand-Hero mit Farb-Verteilung, „Bald trinken"-Vorschläge anhand des Trinkfensters, „Top & Flop" (beste/schlechteste Bewertung pro Wein × Jahrgang) und Aktivitäts-Feed (Fixes #94, #99, #91)
- **Bestand komplett neu** — fünf flache Sub-Tabs (Flaschen · Weingüter · Weine · Käufe), v4-Kartentabellen mit Bewertungs-Spalte und Weingut-Filter (Fixes #111, #113, #114, #115)
- **Wein × Jahrgang** — der Weine-Tab zeigt Weine gruppiert mit ihren Jahrgängen und einer farbigen **Trinkfenster-Pille**; der separate Jahrgänge-Tab entfällt (Fixes #132)
- **Käufe** in den Stammdaten editier- und löschbar (CRUD-Konsistenz) (Fixes #116)
- **Flaschen-Detail-Modal** mit fünf Reitern (Flasche · Weingut · Wein · Jahrgang · Kauf) und Vor/Zurück-Navigation samt Tastatursteuerung (Fixes #131)
- **Etiketten-Fotos** — direkt beim Einlegen ins Regal aufnehmen, als Hintergrund im Regal-Slot anzeigen, jahrgangsweit geteilt mit Zuschnitt (Fixes #117, #118, #127, #128)
- **Verkostungen-Redesign** — Vier-KPI-Reihe und Bewertungs-Balken in der Tabelle (Fixes #97)
- **Weinkeller/Regal-Redesign** — links angeheftete Parkzone und gedämpfte Slot-Farben (Fixes #96)
- Kauf-Wizard: **Händler-Autocomplete** aus bestehenden Käufen (Fixes #86)
- Fächer werden beim Löschen automatisch neu durchnummeriert (Fixes #105)

### Changed
- Kauf-Wizard: Notiz-/Beschreibungsfelder klarer beschriftet (Cuvée vs. Jahrgang) (Fixes #87)
- App-Store-Screenshots auf den neuen v4-Stand aktualisiert

### Fixed
- Verkostungs-Datum erlaubt keine Zukunft mehr (Fixes #85)
- Tabellenkopf im Bestand bleibt korrekt sticky (Fixes #112)
- Foto rendert nicht (CSRF) plus zwei Layout-Bugs im Detail-Panel (Fixes #126)
- Diverse Review-Fixes: CSV-Export gegen Formel-Injection abgesichert, durchgängige UTC-Zeitzonen-Behandlung, vollständige englische l10n

### Removed
- Eigenständige Weine-View — in den Bestand als Stammdaten-Tab gefaltet (Fixes #95)
- Jahrgänge-Tab — in den Wein × Jahrgang-View integriert (Fixes #132)

## [0.3.0] - 2026-05-20

### Added
- Flaschen **verschenken** und als **verloren** markieren — Verschenken erfasst Empfänger (mit Autosuggest aus früheren Empfängern), Datum und Anlass; Verloren erfasst Datum und Grund. Buttons im Regal-Detail und im Bestand (nur bei Flaschen im Bestand); beide geben den Slot frei. Dashboard um Zähler „Verschenkt" und „Verloren" erweitert (Fixes #66)
- **Regal-Kopfbereich als sticky Tab-Leiste** — Regale als Tabs (immer sichtbar), Regal per Klick auf den Tab umbenennen, ✕ mit Bestätigungsdialog, „+"-Tab zum Anlegen eines neuen Regals. Der Kopfbereich (inkl. Parkzone) bleibt beim Scrollen langer Regale sichtbar (Fixes #64, #70)
- **Fächer umbenennen** per Inline-Edit (Klick auf den Fach-Titel) (Fixes #73)
- **Sticky Header/Filter/Tabellenkopf** in der Bestands-Ansicht — bei langen Listen bleiben Überschrift, Filter und Spaltenköpfe sichtbar (Fixes #65)

### Changed
- **Kauf-Wizard persistiert erst beim Abschluss** — „Weiter" legt nichts mehr vorzeitig an, Vor/Zurück lässt alle Felder editierbar, „Abbrechen" hinterlässt keine Geister-Daten. Producer/Wein/Jahrgang/Kauf werden am Ende in einer Transaktion erstellt (Fixes #68)
- Bestands-Aktionen kompakt: gefüllter „Entkorken"-Button + ⋮-Menü für Verschenken/Verloren statt drei gestapelter Buttons
- `formatDate` in eine gemeinsame Utility ausgelagert (statt in vier Komponenten dupliziert)

### Fixed
- Datumsanzeige im **deutschen Format dd.mm.yyyy** statt US-Format MM/DD/YYYY (Fixes #67)
- PHPUnit-Suite an aktuelle Code-Signaturen angepasst — volle Suite wieder grün (Fixes #72)
- Backend-Status-Validierung für Verschenken/Verloren (nur Flaschen im Bestand, sonst HTTP 400)
- Diverse Robustheits-Fixes aus Code-Reviews: Race-Condition beim Click-to-Move im Regal, Dateigrößen-Prüfung vor Foto-Upload, Regalname auf DB-Limit (255) begrenzt, Event-Felder in der Bottle-Detail-Response, sichtbares Fehler-Feedback beim Zurücksetzen

## [0.2.0] - 2026-05-19

### Added
- Fächer eines Regals lassen sich nachträglich hinzufügen und löschen — „+ Fach hinzufügen"-Button am Ende eines Regals, ✕-Button pro Fach mit Bestätigungsdialog; Flaschen aus gelöschten Fächern landen in der Parkzone (Fixes #49)
- Cellar-View: Parkzone ist jetzt unabhängig vom Cellar immer sichtbar; das letzte Regal lässt sich löschen — die View fällt dann auf einen schlanken Empty-State zurück (Fixes #48)

### Changed
- Native Browser-`confirm()`-Dialoge ersetzt durch NC-styled `ConfirmDialog` (knallroter Delete-Button, NC-Look) — durchgängig in Wine-Picker, Bottle-Move, Shelf-Delete, Compartment-Delete (Fixes #50)
- `NcButton`-Migration `type` → `variant` an 21 Stellen: Primary-Buttons sind jetzt wieder NC-blau, Tertiary transparent — die visuelle Hierarchie war zuvor verloren gegangen (Fixes #55)
- Bessere Fehlermeldungen im `TastingDialog` und beim Bottle-Picker bei API-Fehlern statt stiller Fehlschläge (Fixes #41)
- App-Beschreibung in `info.xml` gestrafft

### Fixed
- `TastingService::consumeWithTasting()` ist jetzt atomar — Flasche und Verkostung werden in einer DB-Transaktion erstellt; schlägt die Verkostung fehl, wird der Flaschen-Status zurückgerollt (Fixes #36)
- `DoesNotExistException` in den neuen Compartment-Endpoints wird jetzt korrekt zu HTTP 404 statt 500

### Removed
- Toter Code: `consumeBottle()` aus API und Store entfernt (Fixes #37)
- Toter Code: ungenutzter Frontend-Wrapper `createDefaultCellar()` aus `api/cellar.ts`
- Standard-Regal-Button im Empty-State entfernt — Anlegepfad ist jetzt einheitlich der „+ Neues Regal"-Wizard

### Refactored
- `cssColorFor` in `src/utils/wineColors.ts` extrahiert (6 Aufrufer statt zuvor 2 dupliziert) (Fixes #39)
- `restoreBottle` als Store-Action ergänzt (Fixes #40)

### Compliance
- Apache-2.0-Attribution für `mdi-grapes` in `THIRD_PARTY_NOTICES.md` ergänzt (Fixes #38)

## [0.1.2] - 2026-05-18

### Added
- App Store Screenshots (Dashboard, Wines, Inventory, Tasting-Detail, Tastings)

## [0.1.1] - 2026-05-18

### Fixed
- l10n: JSON-Dateien hatten keinen `translations`-Wrapper → HTTP 500 für Nutzer mit EN-Locale
- l10n: ASCII-Anführungszeichen `"` statt typografischem `"` in l10n-Keys → JS-Syntaxfehler beim Laden
- NcModal: `name`-Prop in TastingDialog, TastingDetailModal, EntityEditModal und PurchaseWizardModal ergänzt (Accessibility)

## [0.1.0] - 2026-05-18

Erste offizielle Veröffentlichung — Weinverwaltung End-to-End.

### Added

**Datenbank + Backend**
- 10 DB-Tabellen via Migration (Cellar, Shelf, Compartment, Slot, Producer, Wine, Vintage, Purchase, Bottle, Tasting)
- 10 Entities + 10 QBMapper-Subklassen mit Owner-Queries
- CellarService: Default-Regal (234 Slots), Reconfigure mit Parkzonen-Migration
- ProducerService, WineService, VintageService: CRUD mit Ownership-Checks
- PurchaseService: CRUD + Bottle-Size-Validation
- BottleService: Bulk-Insert, Move (409 SlotOccupied), Swap, Consume, Restore, Filter (4-Table JOIN)
- TastingService: Create, Update, ConsumeWithTasting, List (denormalisiert), Detail-View
- DashboardService: Stats-Aggregation (Bestand, Farb-Verteilung, Bald-Trinken)
- ExportService: CSV-Export (UTF-8 BOM, Semikolon, denormalisiert)
- PhotoService: Foto-Upload in NC-Files (IRootFolder), Thumbnail via NC Preview API
- Foto-Upload für Flaschen (1 Foto) und Verkostungen (mehrere Fotos)

**REST-API**
- 40+ Endpoints unter `/api/v1/` (Producer, Wine, Vintage, Purchase, Bottle, Tasting, Cellar, Dashboard, Export, Photo)
- DataResponse mit HTTP-Status-Mapping (404/403/400/409/201/204)
- CSRF-Schutz auf allen mutierenden Endpoints

**Frontend**
- Vue 3 + TypeScript + Vite + Pinia
- PurchaseWizardModal: 4-Step-Wizard (Weingut → Wein → Jahrgang → Kauf)
  - Single-Action-Flow (Weiter = speichert + advanced)
  - Form zeigt Daten bei Auswahl eines bestehenden Eintrags
  - Deutsche Farblabels (Rot/Weiß/Rosé/Schaumwein/Dessertwein/Likörwein)
- WinesView: 4 Tabs (Weingüter/Weine/Jahrgänge/Käufe) mit Edit/Delete
- InventoryView: Flaschen-Tabelle mit Filter (Farbe/Status/Jahrgang), Foto-Upload, Flasche zurücksetzen
- SimpleShelfView: Regal-Ansicht mit HTML5 Drag & Drop + Bottle-Swap, Detail-Panel mit Split-View
  - Slots mit 2-Zeilen-Weinname + Jahrgang
  - Parkzone mit Wein-Labels
  - Slot-Labels menschenlesbar (Fach/Ebene/Platz)
  - Regal-Einstellungen: Ebenen/Spalten/Versatz konfigurieren
- TastingDialog: Bewertungs-Slider (0.5–10), Notizen, Anlass, Begleitung, Foto-Upload (mehrere)
- TastingsView: Chronologische Tabelle mit Foto-Badge, Entkorken direkt aus Liste
- TastingDetailModal: Detail-Ansicht mit Foto-Strip, Lightbox-Vollbild, verwandte Verkostungen
- DashboardView: 4 Stat-Widgets, Farb-Verteilung, Bald-Trinken, letzte Verkostungen, CSV-Export
- Entkorken-Funktion: Flasche öffnen + Verkostung in einem Schritt
- Weingut standalone erfassen (ohne Wizard)
- App-Icon: Weintraube (MDI `mdi-grapes`)

**Internationalisierung**
- Vollständige Zweisprachigkeit DE + EN

**Infrastruktur**
- Vue 3.5 + Composition API + TypeScript 5.7 + Vite 7.3
- @nextcloud/vue 9.6, @nextcloud/axios, @nextcloud/router
- Pinia Stores mit Optimistic Updates + Rollback
- 88 PHPUnit-Tests + 24 Vitest-Tests (112 gesamt)
- Pre-Commit-Hook für OCP-only API-Enforcement

[Unreleased]: https://github.com/cpcMomentum/vinarium/compare/v0.5.2...HEAD
[0.5.2]: https://github.com/cpcMomentum/vinarium/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/cpcMomentum/vinarium/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/cpcMomentum/vinarium/compare/v0.4.2...v0.5.0
[0.4.2]: https://github.com/cpcMomentum/vinarium/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/cpcMomentum/vinarium/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/cpcMomentum/vinarium/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/cpcMomentum/vinarium/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/cpcMomentum/vinarium/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/cpcMomentum/vinarium/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/cpcMomentum/vinarium/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/cpcMomentum/vinarium/releases/tag/v0.1.0

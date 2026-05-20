# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Added
- Fächer lassen sich per Inline-Edit umbenennen — Klick auf den Fach-Titel öffnet ein Eingabefeld (Enter speichert, Esc bricht ab); leere Eingabe verwirft die Änderung. Neuer Endpoint `PATCH /compartments/{id}` mit Ownership-Prüfung (403/404) und Integrationstests (Fixes #73)

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

[Unreleased]: https://github.com/cpcMomentum/vinarium/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/cpcMomentum/vinarium/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/cpcMomentum/vinarium/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/cpcMomentum/vinarium/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/cpcMomentum/vinarium/releases/tag/v0.1.0

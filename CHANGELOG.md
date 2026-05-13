# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Added
- App-Icon: Weintraube (MDI `mdi-grapes`, Apache 2.0) ersetzt Platzhalter in `img/app.svg` und `img/app-dark.svg`

### Geplant
- Verkostung bearbeiten (PATCH /tastings/{id})
- Verkostungs-Detail-View (Kaufdaten, Historie)
- Slot-Nummern als menschenlesbare Labels (Fach/Ebene/Platz)
- Weingut standalone erfassen (ohne Wizard)
- Regal-Einstellungen (Ebenen, Spalten, Versatz-Alternierung)
- Foto-Upload bei Verkostung (NC IRootFolder)
- i18n (DE + EN vollständig)
- NC Signing-Zertifikat + App-Store-Upload

## [0.1.0] - 2026-04-27

Erster funktionaler MVP — Weinverwaltung End-to-End.

### Added

**Schema + Backend (Phase 1)**
- 10 DB-Tabellen via Migration (Cellar, Shelf, Compartment, Slot, Producer, Wine, Vintage, Purchase, Bottle, Tasting)
- 10 Entities + 10 QBMapper-Subklassen mit Owner-Queries
- CellarService: Default-Regal (234 Slots), Reconfigure mit Parkzonen-Migration
- ProducerService, WineService, VintageService: CRUD mit Ownership-Checks
- PurchaseService: CRUD + Bottle-Size-Validation
- BottleService: Bulk-Insert, Move (409 SlotOccupied), Swap, Consume, Filter (4-Table JOIN)
- TastingService: Create, ConsumeWithTasting, List (denormalisiert)
- DashboardService: Stats-Aggregation (Bestand, Farb-Verteilung, Bald-Trinken)
- ExportService: CSV-Export (UTF-8 BOM, Semikolon, denormalisiert)

**REST-API (Phase 2-5)**
- 35+ Endpoints unter /api/v1/ (Producer, Wine, Vintage, Purchase, Bottle, Tasting, Cellar, Dashboard, Export)
- DataResponse mit HTTP-Status-Mapping (404/403/400/409/201/204)
- CSRF-Schutz auf allen mutierenden Endpoints

**Frontend (Phase 2-5)**
- Vue 3 + TypeScript + Vite + Pinia
- PurchaseWizardModal: 4-Step-Wizard (Weingut → Wein → Jahrgang → Kauf)
  - Single-Action-Flow (Weiter = speichert + advanced)
  - Form zeigt Daten bei Auswahl eines bestehenden Eintrags
  - Deutsche Farblabels (Rot/Weiß/Rosé/Schaumwein/Dessertwein/Likörwein)
- WinesView: 4 Tabs (Weingüter/Weine/Jahrgänge/Käufe) mit Edit/Delete
- InventoryView: Flaschen-Tabelle mit Filter (Farbe/Status/Jahrgang), Default "Im Bestand"
- SimpleShelfView: Regal-Ansicht mit Fächern untereinander, Ebenen bottom-up
  - Slots mit 2-Zeilen-Weinname + Jahrgang
  - HTML5 Drag & Drop + Click-Fallback
  - Bottle-Swap bei Drop auf belegten Slot
  - Parkzone mit Wein-Labels
- TastingDialog: Bewertungs-Slider (0.5-10), Notizen, Anlass, Begleitung
- TastingsView: Chronologische Tabelle mit expandierbaren Notizen
- DashboardView: 4 Stat-Widgets, Farb-Verteilung, Bald-Trinken, letzte Verkostungen, CSV-Export-Link
- EntityEditModal: Bearbeiten/Löschen für alle Stammdaten-Entities

**Schema-Korrekturen (Pre-Release)**
- grape_varieties von Wine nach Vintage verschoben (jahrgangsspezifisch)
- drink_from/drink_until von DATETIME auf INTEGER (Jahr) umgestellt

**Infrastruktur**
- Vue 3.5 + Composition API + TypeScript 5.7 + Vite 7.3
- @nextcloud/vue 9.6, @nextcloud/axios, @nextcloud/router
- Pinia Stores mit Optimistic Updates + Rollback
- 88 PHPUnit-Tests + 24 Vitest-Tests (112 gesamt)
- Pre-Commit-Hook für OCP-only API-Enforcement

# Design: Vinarium MVP

**Datum:** 2026-04-15
**Status:** Finalisiert (bereit für `/create-plan`)
**Basis:** `docs/produktbeschreibung.md` (v1, 2026-04-12) + 3 Recherche-Dokumente (Frontend-Libraries, NC-Ecosystem, Weindatenquellen)

---

## 1. Problem Statement

Private Weinsammler mit 50-500 Flaschen brauchen eine Alternative zu Excel/Vivino: strukturierte Bestandsverwaltung inklusive physischer Lagerposition, Verkostungsnotizen und Trinkfenster-Übersicht — selbstgehostet in der eigenen Nextcloud, ohne Cloud-Dependency.

Aktueller Zustand: Es existiert **keine Wein-App im Nextcloud App Store**. Die nächstliegende Referenz ist die Inventory-App (Raimund Schlüssler) — generisches Inventar, aber nicht wein-spezifisch.

---

## 2. Gewählter Ansatz

**Native Nextcloud-App auf modernem Frontend-Stack.** Super-MVP-Scope (9 von 10 User Stories) im ersten Release, Barcode-Scan und NC-Notifications als separate Phasen nach v0.1.0.

### Stack-Entscheidungen

| Schicht | Technologie | Begründung |
|---|---|---|
| Backend | PHP 8.2+ mit `declare(strict_types=1)` | NC-Standard für NC 32-33 |
| Framework | Nextcloud AppFramework (Controller, Service, Entity, QBMapper) | OCP-only (per Pre-Commit-Hook erzwungen) |
| Datenbank | MySQL/MariaDB/PostgreSQL/SQLite via DBAL | Host-vorgegeben, Migrations in `lib/Migration/` |
| Frontend | **Vue 3 + Composition API + TypeScript** | Modernes Setup, Typsicherheit bei komplexem Datenmodell |
| UI-Komponenten | `@nextcloud/vue` v9.x | Vue-3-native, seit Sep 2025 stable |
| State | **Pinia** | Vue-3-Standard, einfacher als Vuex |
| Build | **Vite** | Schneller HMR, moderne Build-Pipeline |
| Boilerplate | **`cvorwerk/nextcloud-vue3-boilerplate`** | Aktuellste Community-Basis mit vollem Vue-3-Stack |
| Drag & Drop | `vue-draggable-plus` | Aktiv gewartet, Touch-Support, SortableJS-basiert |
| Datei-Storage | `IRootFolder` (NC File-API) | Fotos in `/Vinarium/` im Files-Bereich des Users |
| i18n | `@nextcloud/l10n` | DE + EN von Anfang an |

### NC-Kompatibilität

- **min-version: 32**
- **max-version: 33**
- PHP-Ziel: 8.2 (lowest common denominator)

### Multi-User-Modell (Datenmodell-ready, Sharing in Phase 2)

- `owner_user_id` auf `Cellar` und `Producer` — Multi-User-ready schon im MVP-Schema
- ACL-Tabellen (`cellar_acl`) und Sharees-API-Integration → Phase 2
- MVP: jeder User sieht nur eigene Daten

---

## 3. Architektur

### Backend-Layer (PHP)

```
Controller      ← HTTP-Request-Handling, AuthN
   ↓
Service         ← Business-Logic (z.B. Regal-Umbau mit Parkzonen-Migration)
   ↓
Mapper (QBMapper) ← Datenbankzugriff via QueryBuilder (kein raw SQL)
   ↓
Entity          ← Datenklassen mit Getter/Setter
```

**Konkrete Controller (MVP):**
- `CellarController` — Keller + Regal-Konfiguration
- `ProducerController` — Weingüter CRUD
- `WineController` — Weine CRUD
- `VintageController` — Jahrgänge CRUD
- `PurchaseController` — Käufe CRUD
- `BottleController` — Flaschen CRUD + Platzierung
- `TastingController` — Verkostungen CRUD
- `ExportController` — CSV-Export
- `PageController` — SPA-Entry-Point

### Frontend-Layer (Vue 3 + TS)

```
Views (Route-Level Komponenten)
   ↓
Components (NcButton, NcModal, custom ShelfView, BottleCard ...)
   ↓
Composables (useBottles, useCellar, useFilter)
   ↓
Pinia Stores (cellarStore, bottleStore, tastingStore)
   ↓
Services (api/*.ts — typed fetch via @nextcloud/axios)
```

### Datenfluss bei Drag & Drop (Beispiel)

```
User zieht Flasche in Regalansicht
  ↓ vue-draggable-plus emittiert Event
Component ruft bottleStore.moveToSlot(bottleId, newSlotId)
  ↓ Optimistic Update im Store
Store ruft api.bottle.move(bottleId, slotId)
  ↓ PATCH /apps/vinarium/api/bottles/{id}
BottleController → BottleService → BottleMapper
  ↓ UPDATE oc_vinarium_bottle SET slot_id = ?
Erfolg → Store behält Update
Fehler → Rollback + Toast-Error
```

---

## 4. Datenmodell

### Entscheidung: Slots materialisiert (Variante A)

**Jeder Steckplatz ist eine DB-Zeile.** Bei Default-Regal = 234 Slot-Rows. Bei typischem Setup (1-4 User pro Instanz) absolut irrelevant für Performance.

**Vorteile:**
- `bottle.slot_id` ist echte FK → referentielle Integrität garantiert
- Drag & Drop = ein UPDATE-Statement
- "Slot frei?" = simple Query (`LEFT JOIN bottle ... WHERE b.id IS NULL`)
- Regal-Umbau mit Parkzone: Slots löschen → `slot_id = null` der betroffenen Flaschen = Parkzone-Eintrag

### ER-Diagramm (MVP-Scope)

```
Cellar                  (1 pro User im MVP)
 ├─ id
 ├─ owner_user_id       ← Multi-User-ready
 ├─ name
 └─ created_at

Shelf ──N:1── Cellar
 ├─ id
 ├─ cellar_id
 ├─ name
 └─ sort_order

Compartment ──N:1── Shelf
 ├─ id, shelf_id, label, sort_order
 ├─ levels              ← 1..3
 ├─ columns_front
 └─ columns_back

Slot ──N:1── Compartment           ← materialisiert
 ├─ id, compartment_id
 ├─ level (0..levels-1)
 ├─ row ('front' | 'back')
 └─ column (0..columns_x-1)

Producer
 ├─ id, owner_user_id
 ├─ name, country, region, website, notes

Wine ──N:1── Producer
 ├─ id, producer_id
 ├─ name, color, grape_varieties, appellation, notes
 └─ barcode             ← Phase 2 befüllt, Feld MVP-ready

Vintage ──N:1── Wine
 ├─ id, wine_id
 ├─ year, alcohol_percent
 ├─ drink_from, drink_until
 ├─ external_rating, external_rating_source
 ├─ description, reference_url

Purchase ──N:1── Vintage
 ├─ id, vintage_id
 ├─ purchased_at, vendor
 ├─ unit_price, currency, quantity
 ├─ bottle_size_ml (375|500|750|1000|1500|3000)
 └─ notes

Bottle ──N:1── Purchase
 ├─ id, purchase_id
 ├─ slot_id             ← nullable = Parkzone
 ├─ status (in_storage|consumed|gifted|lost)
 ├─ photo_file_id       ← NC file_id, nullable
 └─ notes

Tasting ──N:1── Bottle
 ├─ id, bottle_id
 ├─ tasted_at, rating (1.0..10.0, 0.5-Schritte)
 ├─ notes, occasion, companions
 └─ photo_file_ids      ← JSON-Array von NC file_ids
```

### Migrations-Strategie

- Eine Migration pro Tabellen-Paket (oder eine zusammenfassende für MVP)
- `lib/Migration/Version000100Date20260415000000.php` — Schema-Initial
- Keine `appinfo/database.xml` (deprecated seit NC 21)

---

## 5. API-Design

### REST-Endpoints (MVP)

Alle unter `/apps/vinarium/api/v1/`:

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/cellar` | Aktiven Keller laden (inkl. Regale + Compartments) |
| POST | `/cellar` | Keller anlegen (Erstnutzung) |
| PATCH | `/cellar/{id}` | Regal-Konfiguration ändern (mit Parkzonen-Handling) |
| GET | `/producers` | Weingüter-Liste |
| POST | `/producers` | Weingut anlegen |
| PATCH/DELETE | `/producers/{id}` | — |
| GET/POST | `/wines` | Weine + Suche |
| PATCH/DELETE | `/wines/{id}` | — |
| GET/POST | `/wines/{id}/vintages` | Jahrgänge |
| GET/POST | `/vintages/{id}/purchases` | Käufe |
| POST | `/purchases/{id}/bottles` | Flaschen anlegen (Bulk: N Stück) |
| GET | `/bottles` | Bestand mit Filter (color, region, year, status) |
| PATCH | `/bottles/{id}/move` | Slot zuweisen (Drag & Drop) |
| POST | `/bottles/{id}/consume` | Flasche öffnen + Verkostung anlegen |
| GET/POST | `/bottles/{id}/tastings` | Verkostungen pro Flasche |
| GET | `/export/csv` | CSV-Export |

### Authentifizierung

- NC-Standard: Session-Cookie + CSRF-Token (`requestToken`)
- Alle Endpoints per `@NoAdminRequired` geschützt
- Keine OCS-API im MVP nötig (kein Mobile-Client)

---

## 6. UI-Komponenten

### Navigation (Nextcloud-Standard-Pattern)

1. Dashboard (Startseite)
2. Regal (visuelle Ansicht)
3. Bestand (Listenansicht mit Filter)
4. Weine (Stammdaten: Weingüter, Weine, Jahrgänge)
5. Verkostungen (Historie)
6. Einstellungen (Regal-Konfig, Export)

### Views (Vue-Komponenten-Hierarchie)

```
App.vue
├── NcContent
│   ├── NcAppNavigation
│   │   └── NcAppNavigationItem (6× nav)
│   └── NcAppContent
│       └── RouterView
│           ├── DashboardView
│           ├── ShelfView          ← Haupt-Drag-&-Drop-Canvas
│           ├── InventoryView      ← Liste/Tabelle mit Filter
│           ├── WinesView          ← Stammdaten-Tabs (Producer/Wine/Vintage)
│           ├── TastingsView       ← Historie
│           └── SettingsView
├── PurchaseWizardModal            ← Erfassungs-Wizard
├── TastingDialogModal             ← "Flasche öffnen"
└── ShelfConfigDialog              ← Regal-Umbau (mit Parkzonen-Warnung)
```

### Design-Prinzipien

- **Farbe nach Weinfarbe** — visuelles Codieren in Listen + Regal
- **Responsive**: Desktop für Erfassung, Mobile für Suche/Lesen am Regal
- **NC-Standard-Dialoge**: NcModal, NcDialog, NcButton
- **Dark Mode** via CSS-Variablen von `@nextcloud/vue` v9

---

## 7. Super-MVP-Scope (v0.1.0)

### Enthalten

| US | Feature |
|---|---|
| US-01 | Regal anlegen (Default + manuelle Konfig) |
| US-02 | Weingut + Wein erfassen |
| US-03 | Jahrgang + Kauf erfassen |
| US-04 | Flaschen in Regal einräumen (via Parkzone) |
| US-05 | Drag & Drop Umsortieren |
| US-06 | Verkosten (inkl. optionaler Fotos) |
| US-08 | Übersicht + Filter + Suche |
| US-10 | CSV-Export |

Plus: Regal-Umbau mit **Parkzonen-Migration** (Pflicht laut Produktbeschreibung) und **Trinkfenster-Widget** im Dashboard (ohne NC-Notification).

### Zurückgestellt auf v0.2+

| US | Feature | Grund |
|---|---|---|
| US-07 | Barcode-Scan | Mobile-only, zxing-wasm-Integration, Kamera-Permissions — eigener Komplex |
| US-09 | NC-Notifications | RegistrationService + Notifier + BackgroundJob — eigenes Subsystem |

### Explizit nicht im MVP

- Multi-User-Sharing (Phase 2)
- Label-OCR (Phase 2)
- Externe Wein-Datenbanken (Phase 2+)
- Mehrere Keller pro User (Phase 2)
- Native Mobile Apps

---

## 8. Verworfene Alternativen

### Vue 2 statt Vue 3
**Verworfen weil:** Learning-Projekt, Vue 2 EOL seit Dez 2023, `@nextcloud/vue` v9 seit Sep 2025 stable, künftige Migrationsbasis für worktime/contractmanager/brandmail.

### JavaScript statt TypeScript
**Verworfen weil:** Datenmodell mit 8 Entities und tiefen Beziehungen profitiert massiv von Typsicherheit. Refactoring-Produktivität und IDE-Autocomplete überwiegen die TS-Lernkurve.

### Webpack statt Vite
**Verworfen weil:** Wenn Vue 3 + TS, dann konsequent auf moderne Build-Tools — Vite passt zum Rest. HMR-Geschwindigkeit bei Frontend-Heavy-App (Regalansicht) merklich spürbar.

### Slots virtuell (Bottle.compartment_id + level + row + column ohne Slot-Tabelle)
**Verworfen weil:** Privater Einsatz mit max. 3-4 Usern pro Instanz — Slot-Rows sind triviales Volumen. Gewinn durch FK-Integrität, einfachere Queries und klare Parkzonen-Semantik (`slot_id IS NULL`) überwiegt minimale DB-Zeilen-Ersparnis.

### Voller MVP (US-01 bis US-10 inkl. Barcode + Notifications)
**Verworfen weil:** Barcode-Scan und NC-Notifications sind isolierte Subsysteme, die nach MVP ohne Refactoring nachgeschoben werden können. Super-MVP bringt Dogfooding 2-3 Wochen früher — Feedback aus echter Nutzung wird die App besser formen als Vorplanung.

### Offizielle NC-App-Skeleton-Generator
**Verworfen weil:** Erzeugt noch Vue 2 (Stand 2026-04). cvorwerk-Boilerplate ist Community, aber moderner Stack vollständig integriert.

### File-basiertes Sharing (Cookbook-Pattern) für Phase 2
**Verworfen weil:** Relationale Struktur mit vielen Queries. Deck-Pattern (eigene ACL-Tabellen + Sharees-API) ist langfristig richtiger Weg. Relevant für Phase 2, hier vermerkt damit MVP-Schema kompatibel bleibt (`owner_user_id` schon enthalten).

---

## 9. Offene Fragen

**Keine Design-blockierenden.** Folgende Punkte werden in `/create-plan` bzw. Phase-Details geklärt:

- Genaue Aufteilung in Phasen (typisch 4-6 für Super-MVP)
- Test-Strategie: PHPUnit-Abdeckung für Service-Layer, minimal für Controller (Integration statt Unit)
- Icon für App Store: Platzhalter generisches Wein-Icon — finales Design später (nicht release-blockierend)
- Foto-Upload-Flow bei Verkostung: Datei-Browser-Dialog via Nextcloud Files Picker oder eigener Upload?

---

## 10. Akzeptanzkriterien (v0.1.0 Release)

### Funktional
- [ ] Erstnutzer sieht Willkommens-Screen mit "Default-Regal übernehmen" oder "Eigenes Regal anlegen"
- [ ] Default-Regal mit 234 Kapazität wird korrekt angelegt (6 × 3 × 6/7-alternierend)
- [ ] Weingut + Wein + Jahrgang + Kauf können über Wizard erfasst werden
- [ ] Neu gekaufte Flaschen landen in Parkzone, von dort per D&D ins Regal ziehbar
- [ ] Drag & Drop funktioniert auf Desktop und Touch-Geräten
- [ ] Regal-Umbau zeigt Warnung, bewegt betroffene Flaschen in Parkzone, Undo 10 Sek. möglich
- [ ] "Flasche öffnen" → Verkostungs-Dialog → Status wechselt auf `consumed`, Slot wird frei
- [ ] Bestandsliste mit Filter: Farbe, Region, Jahrgang, Trinkfenster, Status
- [ ] Dashboard zeigt Gesamt-Flaschen, Farb-Verteilung, "bald trinken", Parkzonen-Hinweis
- [ ] CSV-Export enthält alle Flaschen mit denormalisierten Spalten

### Technisch
- [ ] Code-Coverage Service-Layer ≥ 60%
- [ ] PHP linting grün (`php -l`)
- [ ] ESLint grün
- [ ] TypeScript strict mode grün
- [ ] NC Integrity Check grün nach Install
- [ ] Funktioniert auf NC 32 und NC 33
- [ ] DE + EN Übersetzungen vollständig
- [ ] Pre-Commit-Hook (OCP-only) aktiv

### Nicht-funktional
- [ ] Regalansicht mit 234 Slots rendert in < 500ms
- [ ] CSV-Export für 500 Flaschen in < 2s
- [ ] Mobile-Viewport (375px) funktional, Regal horizontal scrollbar

---

## Nächster Schritt

```
/ai-first-dev:create-plan
```

**Erwartetes Output:** `docs/plan.md` mit Phasen-Aufteilung:
- **Phase 1:** Skelett-Austausch (cvorwerk-Boilerplate integrieren)
- **Phase 2:** DB-Schema + Migration + Stamm-Entities (Cellar, Shelf, Compartment, Slot)
- **Phase 3:** Wein-Stammdaten (Producer, Wine, Vintage) + Erfassungs-Wizard
- **Phase 4:** Kauf + Flaschen + Platzierung inkl. D&D-Regalansicht
- **Phase 5:** Verkostung + Bestandsfilter + Dashboard + CSV-Export
- **Phase 6:** Polishing, i18n, Tests, Release v0.1.0

(Tatsächliche Phasierung entscheidet `/create-plan` — das hier ist Einschätzung.)

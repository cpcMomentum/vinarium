# Vinarium — Technischer Plan

> **Erstellt:** 2026-04-15
> **Basiert auf:** `docs/produktbeschreibung.md` (v1) + `docs/design-vinarium-mvp.md`
> **Tech-Baseline:** April 2026 (NC 33, @nextcloud/vue v9.5, Vue 3.4, Vite 5)

---

## 1. Übersicht

### 1.1 Vision
Native Nextcloud-App zur privaten Weinverwaltung (Bestand, Lagerposition, Verkostungen) — selbstgehostet, ohne Cloud-Dependency.

### 1.2 MVP-Scope (v0.1.0 — Super-MVP)
9 von 10 User Stories: Regal anlegen, Weingut/Wein/Jahrgang/Kauf erfassen, Flaschen platzieren (inkl. Parkzone), Drag & Drop, Verkosten mit Fotos, Bestandsfilter, CSV-Export, Trinkfenster-Widget im Dashboard.

**Zurückgestellt auf v0.2+:** Barcode-Scan (US-07), NC-Notifications (US-09).

### 1.3 Ziel-Architektur
Nextcloud-App mit PHP 8.2+ Backend (OCP AppFramework: Controller → Service → Mapper → Entity) und Vue 3 + TypeScript + Vite Frontend. Pinia für State, `@nextcloud/vue` v9 für UI, `vue-draggable-plus` für Regal-Drag-&-Drop. Multi-User-ready im Datenmodell (`owner_user_id`), aber Sharing erst in Phase 2.

---

## 2. Tech-Stack

| Layer | Technologie | Version | Begründung |
|---|---|---|---|
| Laufzeit | PHP | 8.2+ | NC 32-33 Kompatibilität |
| Framework | Nextcloud AppFramework (OCP) | NC 32-33 | Pflicht, keine Alternative |
| DB-Abstraktion | Doctrine DBAL via OCP QueryBuilder | 3.x | Host-vorgegeben |
| Frontend | Vue | 3.4+ | Composition API, Vue 2 EOL |
| Sprache | TypeScript | 5.x | strict mode |
| Build | Vite | 7+ | HMR-Geschwindigkeit (cvorwerk-Boilerplate liefert Vite 7.1.3) <!-- ✨ Update aus Phase 0 Detail-Planung --> |
| UI | @nextcloud/vue | 9.5+ | NC-native Komponenten |
| State | Pinia | 2.x | Vue-3-Standard |
| HTTP | @nextcloud/axios | 2.5+ | CSRF-Handling gratis |
| Routing | vue-router | 4.x | Hash-Mode |
| i18n | @nextcloud/l10n | 3.x | gettext-basiert |
| Drag & Drop | vue-draggable-plus | 0.6+ | Touch-Support, SortableJS-Basis |
| Tests (PHP) | PHPUnit + NC TestCase | 9.x | NC-Standard |
| Tests (TS) | Vitest | 1.x | Vite-native |
| Boilerplate | cvorwerk/nextcloud-vue3-boilerplate | HEAD | Vue-3-Startpunkt |

### 2.1 Abweichungen von techstack.md
- **techstack.md erlaubt Vue 2 ODER Vue 3** — Vinarium wählt **Vue 3 + TypeScript**. Begründung: Learning-Projekt, Investment in künftigen Migrationspfad (siehe `design-vinarium-mvp.md` §8).
- **Build-Tool Vite statt @nextcloud/webpack-vue-config** — konsequente Vue-3-Moderne, cvorwerk-Boilerplate bringt das mit.

### 2.2 Verifizierte OCP-APIs (Blocker-Check bestanden)

Alle APIs sind in worktime/contractmanager bereits im Einsatz und verifiziert:

- `OCP\AppFramework\Controller` + `ApiController`
- `OCP\AppFramework\Http\JSONResponse` + `DataResponse`
- `OCP\AppFramework\Http\Attribute\NoAdminRequired` (NC 27+)
- `OCP\AppFramework\Db\Entity` + `QBMapper`
- `OCP\IDBConnection` (QueryBuilder)
- `OCP\IUserSession` + `IUser`
- `OCP\Files\IRootFolder` + `Folder` + `File`
- `OCP\Migration\IMigrationStep` + `ISchemaWrapper`
- `OCP\AppFramework\App` + `IBootstrap`
- `OCP\AppFramework\Utility\ITimeFactory`
- `OCP\IL10N` + `IL10NFactory`

**Keine verbotenen APIs im Plan:** kein `OC\*`, kein raw SQL, kein History-Routing.

---

## 3. Architektur

### 3.1 System-Diagramm

```
┌──────────────────────────────────────────────────────────────────┐
│                        Nextcloud Host                            │
│                                                                  │
│  ┌─────────────┐       ┌────────────────────────────────────┐   │
│  │ Browser     │       │         Vinarium App               │   │
│  │ (Vue 3 SPA) │◀─────▶│                                    │   │
│  │             │ HTTP  │  ┌──────────────┐  ┌────────────┐  │   │
│  │ Pinia Store │ /api  │  │  Controller  │─▶│  Service   │  │   │
│  │  @nextcloud │       │  │  (JSON API)  │  │ (Logic)    │  │   │
│  │   /vue v9   │       │  └──────────────┘  └────┬───────┘  │   │
│  │             │       │                         │          │   │
│  └─────────────┘       │  ┌──────────────┐  ┌────▼───────┐  │   │
│                        │  │    Entity    │◀─│  QBMapper  │  │   │
│                        │  └──────────────┘  └────┬───────┘  │   │
│                        │                         │          │   │
│                        │                   ┌─────▼──────┐   │   │
│                        │                   │  DB (SQL)  │   │   │
│                        │                   └────────────┘   │   │
│                        │                                    │   │
│                        │  ┌──────────────────────────────┐  │   │
│                        │  │  IRootFolder (NC File API)   │  │   │
│                        │  │  /Vinarium/ im User-Files    │  │   │
│                        │  └──────────────────────────────┘  │   │
│                        └────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────┘
```

### 3.2 Komponenten

| Komponente | Verantwortung | Tech |
|---|---|---|
| PageController | SPA-Entry, liefert Template mit script-Include | PHP |
| `*Controller` (9 Stück) | JSON-API, AuthZ via `@NoAdminRequired` | PHP |
| `*Service` | Business-Logik (Regalumbau mit Parkzonen-Migration, Bottle-Move-Validierung) | PHP |
| `*Mapper` (QBMapper) | Datenbankzugriff ausschließlich via QueryBuilder | PHP |
| `*Entity` | Datenklassen, Typen erzwingen | PHP |
| Migration `Version000100…` | Schema-Init, alle Tabellen + Indexe | PHP |
| Vue-Views (6) | Dashboard, Shelf, Inventory, Wines, Tastings, Settings | TS/Vue |
| Pinia Stores (4) | cellarStore, wineStore, bottleStore, tastingStore | TS |
| Composables | useApi, useFilter, useDragDrop | TS |
| API-Layer | typed fetch-Wrapper um @nextcloud/axios | TS |

### 3.3 Datenfluss (Beispiel: Flasche öffnen)

```
User klickt "Flasche öffnen" in ShelfView
  → TastingDialogModal öffnet sich (optional Foto-Upload)
  → Form-Submit ruft bottleStore.consume(bottleId, tastingData)
  → api.bottle.consume() → POST /api/v1/bottles/{id}/consume
  → BottleController → BottleService.consume()
      1. Bottle laden, AuthZ-Check (owner_user_id == currentUser)
      2. Tasting anlegen (TastingMapper.insert)
      3. Bottle.status = 'consumed', slot_id = null (BottleMapper.update)
      4. Falls Fotos: via IRootFolder in /Vinarium/tastings/ speichern, file_ids zurück
      5. Transaktion commiten
  → 200 OK + { bottle, tasting }
  → bottleStore updated, ShelfView re-rendered (Slot wird frei, Toast-Erfolg)
```

### 3.4 API-Pfade (Übersicht)

Alle unter `/apps/vinarium/api/v1/`:

- `/cellar` — GET (aktiver Keller), POST (anlegen), PATCH `{id}` (Regal-Umbau)
- `/producers` — GET, POST, PATCH/DELETE `{id}`
- `/wines` — GET (mit Suche), POST, PATCH/DELETE `{id}`
- `/wines/{id}/vintages` — GET, POST
- `/vintages/{id}/purchases` — GET, POST
- `/purchases/{id}/bottles` — POST (Bulk-Anlage: N Flaschen in Parkzone)
- `/bottles` — GET (mit Filter: color, region, year, status, drink_until_before)
- `/bottles/{id}/move` — PATCH (slot_id setzen)
- `/bottles/{id}/consume` — POST (Verkostung + Status-Wechsel)
- `/bottles/{id}/tastings` — GET, POST
- `/export/csv` — GET

---

## 4. Phasen-Übersicht

| Phase | Titel | Fokus | Abhängigkeit | Schätzung |
|---|---|---|---|---|
| 0 | Skelett-Austausch | cvorwerk-Boilerplate integrieren | — | 1 Session |
| 1 | Schema + Stammdaten-Backend | DB-Migration, 6 Mapper, 3 Services | Phase 0 | 2 Sessions |
| 2 | Wein-Stammdaten-Frontend | Erfassungs-Wizard, Weingut/Wein/Jahrgang-CRUD | Phase 1 | 2 Sessions |
| 3 | Kauf + Flaschen + Parkzone | Purchase/Bottle-Flow, Parkzone-Logik | Phase 2 | 2 Sessions |
| 4 | Regalansicht + Drag & Drop | Visual ShelfView, vue-draggable-plus, Regal-Umbau | Phase 3 | 2 Sessions |
| 5 | Verkostung + Filter + Dashboard + Export | TastingDialog, InventoryView mit Filter, Dashboard-Widgets, CSV | Phase 4 | 2 Sessions |
| 6 | Polishing + i18n + Release | DE/EN-Übersetzung, App-Icon, Release v0.1.0 | Phase 5 | 1 Session |

Gesamt: ~12 Entwickler-Sessions (je ~3-4h).

---

## 5. Phase-Details

### Phase 0: Skelett-Austausch

**Ziel:** Vinarium läuft lokal auf cvorwerk-Boilerplate-Basis (Vue 3 + TS + Vite), zeigt Willkommens-View.

**Abhängigkeiten:** Keine.

**Schritte:**

- **0.1 Skelett-Inventur (RED):**
  - Smoke-Test schreiben: `tests/unit/AppFrameworkTest.php` — prüft dass `Application::register()` fehlerfrei durchläuft und alle erwarteten Services im Container sind (PageController, später *Controller).
  - Test schlägt fehl solange Boilerplate noch nicht integriert.
- **0.2 Altes Skelett-Backup:** Bestehende Vue-2-Dateien (src/, js/, templates/main.php, package.json, webpack.config.js, composer.json) nach `archive/initial-skeleton-20260415/` verschieben. Behalten bleibt: `.git`, `.github/`, `.claude/`, `.githooks/`, `docs/`, `CLAUDE.md`, `LICENSE`, `README.md`, `CHANGELOG.md`, `appinfo/info.xml` (wird ggf. angepasst).
- **0.3 cvorwerk-Boilerplate klonen:** Repo in temp-Ordner clonen, relevante Dateien nach vinarium/ übernehmen (package.json, vite.config.js, tsconfig.json, src/, templates/, appinfo/routes.php, lib/ (komplett inkl. AppInfo/Application.php + Controller/PageController.php), composer.json). <!-- ✨ Update aus Phase 0: Boilerplate nutzt vite.config.js, lib/ komplett kopieren -->
- **0.4 Identifizierung anpassen:** App-ID `vinarium`, Namespace `OCA\Vinarium`, Titel, Beschreibung, Autor-Infos in allen Boilerplate-Dateien durch `grep -r` + Replace setzen.
- **0.5 `info.xml` zusammenführen:** Bestehende Metadaten mit Boilerplate-Anforderungen abgleichen (min-version=32, max-version=33, PHP 8.2).
- **0.6 Dependencies installieren:** `npm install` + `composer install`, Fehler beheben.
- **0.7 Build:** `npm run build` grün.
- **0.8 Deploy lokal:** App nach Docker-Volume kopieren, `occ app:enable vinarium`, im Browser öffnen — Willkommens-View muss erscheinen.
- **0.9 Smoke-Test (GREEN):** PHPUnit-Test aus 0.1 läuft grün.
- **0.10 Git-Commit:** `feat: replace initial skeleton with cvorwerk vue3 boilerplate`.

**Deliverables:**
- [ ] Alte Skelett-Dateien archiviert in `archive/initial-skeleton-20260415/`
- [ ] cvorwerk-Boilerplate integriert, Namespace/App-ID korrekt ersetzt
- [ ] `npm run build` grün
- [ ] `composer install` grün
- [ ] App enable ohne Fehler auf NC 33.0.2 lokal
- [ ] Willkommens-View im Browser sichtbar unter `/apps/vinarium/`
- [ ] AppFrameworkTest grün

**Tech-Fokus:** Vite, TypeScript, @nextcloud/vue v9, OCP AppFramework Bootstrap

**Akzeptanz-Test:** Manueller Browser-Test + PHPUnit Smoke-Test.

---

### Phase 1: Schema + Stammdaten-Backend

**Ziel:** Komplettes DB-Schema existiert, alle 8 Entities + Mapper implementiert, 3 Services (Cellar, Producer, Wine) mit Unit-Tests.

**Abhängigkeiten:** Phase 0.

**Schritte:**

- **1.1 Migration schreiben (RED):**
  - Migrations-Test `MigrationTest.php`: prüft nach Apply dass alle 8 Tabellen existieren, korrekte Spalten + Indexe haben. Läuft gegen SQLite-In-Memory.
  - Test schlägt fehl ohne Migration.
- **1.2 Migration implementieren (GREEN):** `lib/Migration/Version000100Date20260415120000.php` erstellt Tabellen: `vinarium_cellar`, `vinarium_shelf`, `vinarium_compartment`, `vinarium_slot`, `vinarium_producer`, `vinarium_wine`, `vinarium_vintage`, `vinarium_purchase`, `vinarium_bottle`, `vinarium_tasting`. Alle Foreign Keys definiert, Indexe auf `owner_user_id`, `wine_id`, `slot_id`.
- **1.3 Entities (GREEN):** `lib/Db/Cellar.php`, `Shelf.php`, `Compartment.php`, `Slot.php`, `Producer.php`, `Wine.php`, `Vintage.php`, `Purchase.php`, `Bottle.php`, `Tasting.php` — jeweils `Entity` mit typisierten Gettern/Settern.
- **1.4 Mapper (RED→GREEN):** Für jede Entity ein `QBMapper`-Subclass. Pro Mapper: `findAll(userId)`, `find(id, userId)`, Insert/Update/Delete geerbt. Tests in `tests/unit/Db/*MapperTest.php` via NC TestCase, SQLite in-memory.
- **1.5 CellarService (RED→GREEN):**
  - Test: `createDefaultCellar($userId)` legt Cellar + 1 Shelf + 6 Compartments (3 Ebenen alternierend 6/7 Slots) an → 234 Slot-Rows.
  - Test: `getActiveCellar($userId)` liefert komplette Struktur.
  - Test: `reconfigureCompartment($id, $newLevels, $newColsFront, $newColsBack)` migriert betroffene Bottles in Parkzone (`slot_id = null`), gibt Anzahl zurück.
  - Implementation.
- **1.6 ProducerService + WineService (RED→GREEN):** CRUD mit owner-AuthZ-Check (User darf nur eigene Daten).
- **1.7 Lint + Typecheck:** `php -l`, phpstan (falls eingerichtet).

**Deliverables:**
- [ ] 10 Tabellen per Migration angelegt, Schema-Test grün
- [ ] 10 Entities typisiert
- [ ] 10 Mapper mit Unit-Tests (≥1 Test pro Mapper)
- [ ] CellarService mit 3 Unit-Tests (default-Anlage, aktiv laden, reconfigure)
- [ ] ProducerService + WineService mit CRUD-Tests
- [ ] `occ db:add-missing-indices` findet keine fehlenden Indexe
- [ ] Coverage Service-Layer ≥ 60%

**Tech-Fokus:** IMigrationStep, QBMapper, Entity, PHPUnit NC TestCase

---

### Phase 2: Wein-Stammdaten-Frontend

**Ziel:** Erfassungs-Wizard für Weingut → Wein → Jahrgang funktioniert End-to-End, WinesView zeigt alle Stammdaten.

**Abhängigkeiten:** Phase 1.

**Schritte:**

- **2.1 Producer/Wine/Vintage-Controller (RED→GREEN):**
  - Controller-Tests: GET/POST/PATCH/DELETE, CSRF-Check, 403 für fremde User.
  - Implementation mit `DataResponse` + Error-Handling.
- **2.2 Routes eintragen:** `appinfo/routes.php` mit allen Endpoints aus §3.4.
- **2.3 TypeScript-Types (RED):** `src/types/api.ts` — Interfaces für Producer, Wine, Vintage, Purchase, Bottle, Tasting (spiegeln DB-Entities). Vitest-Test validiert JSON-Shape aus Mock-Responses.
- **2.4 API-Layer:** `src/api/producers.ts`, `wines.ts`, `vintages.ts` — typed fetch-Wrapper via @nextcloud/axios und generateUrl aus @nextcloud/router.
- **2.5 Pinia Store `wineStore` (RED→GREEN):** State: `producers`, `wines`, `vintages`. Actions: fetch, create, update, delete. Tests mit Mock-API.
- **2.6 PurchaseWizardModal (RED→GREEN):**
  - Schritt 1: Weingut suchen oder neu anlegen (NcSelect mit async-Options, "+ Neu"-Button)
  - Schritt 2: Wein zu Weingut suchen oder neu anlegen
  - Schritt 3: Jahrgang zu Wein auswählen oder neu anlegen
  - (Schritt 4 Kauf-Daten wird in Phase 3 ergänzt — Wizard kann in Phase 2 an Schritt 3 enden)
  - Component-Test mit Vitest + @vue/test-utils.
- **2.7 WinesView:** Tabs (Weingüter, Weine, Jahrgänge) mit NcListItem-Listen. Klick öffnet Detail-Modal.
- **2.8 E2E-Manual-Test:** Neuen Wein end-to-end erfassen, in DB verifizieren.

**Deliverables:**
- [ ] 3 Controller + Tests grün
- [ ] TypeScript-Types vollständig
- [ ] wineStore mit Tests grün
- [ ] PurchaseWizardModal Schritte 1-3 funktional
- [ ] WinesView listet Producer/Wine/Vintage
- [ ] Manueller E2E-Flow: Weingut + Wein + Jahrgang anlegen erfolgreich

**Tech-Fokus:** Controller + Routes, Pinia, @nextcloud/vue NcModal/NcSelect/NcListItem

---

### Phase 3: Kauf + Flaschen + Parkzone

**Ziel:** Nach Kauf landen N Flaschen in der Parkzone. Flaschen lassen sich per Click auf einen freien Slot zuweisen. Flaschen-Liste rendert korrekt.

**Abhängigkeiten:** Phase 2.

**Schritte:**

- **3.1 PurchaseController + BottleController (RED→GREEN):**
  - Tests: POST `/purchases` + Bulk-POST `/purchases/{id}/bottles` legt N Bottle-Rows mit `slot_id=null, status=in_storage` an.
  - Tests: GET `/bottles` mit Filter (status, color-via-join, year-via-join).
  - Tests: PATCH `/bottles/{id}/move` setzt slot_id, 409 wenn Slot belegt, 404 wenn Slot existiert nicht.
  - Implementation.
- **3.2 BottleService (RED→GREEN):**
  - `createBottlesForPurchase($purchaseId, $count)` → N Bottles in Parkzone.
  - `moveBottle($bottleId, $slotId, $userId)` → AuthZ-Check, Slot-Frei-Check, Update.
  - `getParkedBottles($userId)` → alle Bottles mit `slot_id=null, status=in_storage`.
  - `getFilteredBottles($userId, $filter)` → JOIN über Purchase/Vintage/Wine/Producer.
- **3.3 bottleStore (RED→GREEN):** State: `bottles`, `parkedBottles`, `filter`. Actions: fetch, move, filter.
- **3.4 Wizard Schritt 4 + 5 (Kauf + Flaschen):** PurchaseWizardModal ergänzen um Kauf-Form (Händler, Datum, Stückpreis, Anzahl, Flaschengröße). Submit legt Purchase + N Bottles an.
- **3.5 InventoryView (Basis):** Tabelle mit Filter (Farbe, Jahrgang, Status). Parkzone als separater Bereich oben ("Nicht zugeordnete Flaschen (N)"). Click auf Bottle → Detail-Modal.
- **3.6 Platzierung via Click:** In Parkzone Bottle markieren, dann in simple-Regal-Ansicht (Phase 4 ist das richtige D&D, hier Vorstufe) freien Slot klicken → Move.

**Deliverables:**
- [ ] Purchase + Bottle CRUD funktional
- [ ] bottleStore mit Tests grün
- [ ] Wizard Schritt 4+5 (Kauf + Bulk-Bottles) funktional
- [ ] InventoryView mit Filter + Parkzonen-Bereich
- [ ] Click-basierte Platzierung (D&D erst Phase 4)
- [ ] Manueller E2E: Kauf → Flaschen in Parkzone → eine Flasche in Slot verschoben

**Tech-Fokus:** Bulk-Insert, Filter-Queries mit JOINs, Pinia-Actions mit Error-Handling

---

### Phase 4: Regalansicht + Drag & Drop + Regal-Umbau

**Ziel:** Visuelle Regalansicht mit Drag & Drop zwischen Parkzone und Slots. Regal-Umbau-Dialog mit Parkzonen-Migration + Undo.

**Abhängigkeiten:** Phase 3.

**Schritte:**

- **4.1 ShelfView-Layout (RED→GREEN):**
  - Vitest-Test: Component rendert bei gegebenem Cellar-State genau N Slots pro Compartment.
  - Implementation: pro Compartment eine Grid-Section, farbcodierte Bottle-Chips.
- **4.2 vue-draggable-plus Integration (RED→GREEN):**
  - Test: Drop-Event aus einer Container zu anderer triggert `bottleStore.move`.
  - Implementation: pro Slot ein `VueDraggable`-Container mit `group="shelf"` und max. 1 Item, Parkzone als eigener Container.
- **4.3 Optimistic Update + Rollback:** bottleStore.move macht zuerst lokalen State-Wechsel, dann API-Call. Fehler → Rollback + NcToast-Error.
- **4.4 Slot-Belegt-Handling:** Drop auf belegten Slot → Tausch (swap) der Bottles, transactional im Backend (Extension von PATCH `/bottles/{id}/move` → Service macht 2-Bottle-Update in Transaction).
- **4.5 ShelfConfigDialog (RED→GREEN):**
  - Dialog für Regal-Umbau: Compartments bearbeiten (levels, columns).
  - Pre-Save-Dialog: "N Flaschen landen in Parkzone — fortfahren?"
  - Nach Save: 10-Sek-Undo-Toast über bottleStore-Undo-Snapshot.
  - Tests: Service-Test `reconfigureCompartment` + Component-Test.
- **4.6 Responsive:** Mobile-Test (375px): Regalansicht horizontal scrollbar, Touch-D&D funktioniert.

**Deliverables:**
- [ ] ShelfView mit allen Slots korrekt gerendert
- [ ] Drag & Drop Parkzone ↔ Slot + Slot ↔ Slot funktional (Desktop + Touch)
- [ ] Swap bei belegtem Ziel-Slot funktioniert transaktional
- [ ] ShelfConfigDialog mit Parkzonen-Migration + 10-Sek-Undo
- [ ] Optimistic Updates mit Error-Rollback
- [ ] Mobile-Viewport funktional

**Tech-Fokus:** vue-draggable-plus `group`/`pull`/`put`, Pinia optimistic updates, DB-Transaction in Service

---

### Phase 5: Verkostung + Dashboard + Export

**Ziel:** Verkostung mit Foto-Upload funktioniert. Dashboard zeigt Widgets. CSV-Export liefert korrekte Daten.

**Abhängigkeiten:** Phase 4.

**Schritte:**

- **5.1 TastingController + Service (RED→GREEN):**
  - Tests: POST `/bottles/{id}/consume` legt Tasting an, setzt Bottle.status='consumed', slot_id=null in Transaction.
  - Tests: POST `/bottles/{id}/tastings` erlaubt zusätzliche Tastings (z.B. 2. Tag).
  - Implementation.
- **5.2 Foto-Upload via IRootFolder (RED→GREEN):**
  - Service-Test: Foto-Blob landet in `/Vinarium/tastings/{bottleId}_{timestamp}.jpg`, file_id wird zurückgegeben.
  - Implementation mit `IRootFolder::getUserFolder()` + `newFile()`.
- **5.3 TastingDialogModal (RED→GREEN):**
  - Form: Datum (default heute), Bewertung (klickbare 10-Punkt-Skala mit halben Schritten), Notiz, Anlass, Begleiter, Fotos (NcFilePicker oder Native-Upload).
  - Submit → bottleStore.consume() oder .addTasting().
  - Component-Tests.
- **5.4 InventoryView-Erweiterung:** Rating-Spalte mit Balken-Visualisierung, externe Bewertung farblich abgegrenzt (Jahrgang-Feld).
- **5.5 TastingsView:** Historie aller Tastings sortiert nach Datum.
- **5.6 Dashboard-Widgets (RED→GREEN):**
  - Tests: Dashboard-Service-Methoden liefern korrekte Counts/Aggregates.
  - Widgets: Gesamt-Flaschen, Farb-Verteilung (Pie/Bar), "Bald trinken" (Vintages mit drink_until < now + 6 Monate), Parkzonen-Hinweis, letzte 5 Tastings.
- **5.7 CSV-Export (RED→GREEN):**
  - Service-Test: Export für User mit N Flaschen liefert N Zeilen mit denormalisierten Spalten (producer, wine, vintage, year, status, slot_label, drink_until, rating, etc.).
  - Controller: GET `/export/csv` liefert `text/csv` mit `Content-Disposition: attachment`.
- **5.8 SettingsView:** Regal-Konfig-Link (öffnet ShelfConfigDialog), Export-Button, Währung (default EUR).

**Deliverables:**
- [ ] Verkostung mit/ohne Foto funktional
- [ ] Mehrere Tastings pro Bottle möglich
- [ ] Dashboard 5 Widgets funktional
- [ ] CSV-Export korrekt, denormalisiert, UTF-8 BOM für Excel
- [ ] Bewertungs-UI mit halben Schritten + Balken-Anzeige

**Tech-Fokus:** IRootFolder File-Handling, CSV-Serialisierung, Dashboard-Aggregate-Queries

---

### Phase 6: Polishing + i18n + Release v0.1.0

**Ziel:** DE + EN Übersetzungen vollständig, App-Icon, CHANGELOG, Release-Prozess nach `/release` Skill-Konvention durchgelaufen, Tarball im App Store.

**Abhängigkeiten:** Phase 5.

**Schritte:**

- **6.1 i18n-Extraktion:** Alle Strings durch `t('vinarium', 'text')` (JS) bzw. `$this->l->t('text')` (PHP). Generate `.pot`-Datei.
- **6.2 Übersetzungen:** `l10n/de.json` (native) + `l10n/en.json`. Transifex-Integration optional für spätere Crowd-Translation.
- **6.3 App-Icon:** Generisches Wein-Icon in `img/app.svg` (Platzhalter für Release, Details in v0.2).
- **6.4 README + CHANGELOG:** README mit Screenshots, CHANGELOG-Eintrag für v0.1.0 nach Keep-a-Changelog-Format.
- **6.5 Validate-Lauf:** `/ai-first-dev:validate` komplett grün (PHP lint, ESLint, TypeScript strict, PHPUnit, Vitest, npm build).
- **6.6 Security-Scan:** `/ai-first-dev:security-scan` grün (OWASP-Top-10, CSRF auf allen State-ändernden Endpoints verifiziert).
- **6.7 Upgrade-Test:** Dummy-Vorversion installieren → v0.1.0 drüberziehen → `occ integrity:check-app vinarium` grün.
- **6.8 Release-Branch:** `release/v0.1.0` von `develop` abzweigen. Versionen synchronisieren (`appinfo/info.xml` + `package.json`).
- **6.9 Tarball:** `git archive` aus Release-Branch, Whitelist-Check (nur erlaubte Top-Level-Einträge), Signature mit `~/.nextcloud/certificates/vinarium.key` (Zertifikat muss vorher bei Nextcloud beantragt werden — eigener Step).
- **6.10 App-Store-Upload:** Via API-Token (aus Memory `reference_*`), HTTP 200/201 erwartet.
- **6.11 Merge-Back:** Release-Branch → main → develop zurücksyncen.
- **6.12 GitHub-Release:** Tag `v0.1.0` + Release-Notes auf GitHub.

**Deliverables:**
- [ ] DE + EN Übersetzungen vollständig (alle Strings extrahiert)
- [ ] App-Icon vorhanden
- [ ] CHANGELOG.md enthält v0.1.0-Eintrag
- [ ] `/validate` grün
- [ ] `/security-scan` grün
- [ ] Upgrade-Test grün
- [ ] Tarball signiert und im App Store akzeptiert
- [ ] GitHub-Release publiziert

**Tech-Fokus:** Release-Prozess, i18n, Signing

**Parallele Vorarbeit für Phase 6:** Nextcloud-Code-Signing-Zertifikat für `vinarium` beantragen — Lead-Time bis zu 1 Woche. Früh in Phase 0-1 anstoßen.

---

## 6. Offene Entscheidungen

| Entscheidung | Optionen | Deadline | Status |
|---|---|---|---|
| Foto-Upload-UI (Verkostung) | NcFilePicker (nutzt bestehende Nextcloud-Files) vs. native `<input type="file">` mit direktem Upload | Phase 5 | Offen — Empfehlung: native Input für MVP (einfacher), NcFilePicker optional |
| App-Icon finales Design | generisches SVG (MVP) vs. Custom-Design | Phase 6 für v0.1.0 ok, finales Design in v0.2 | Deferred |
| Coverage-Ziel | 60% (Plan-Default) vs. 80% (ambitioniert) | Phase 1 | 60% für MVP, Steigerung in Folge-Releases |

---

## 7. Risiken

| Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|---|---|---|---|
| `@nextcloud/vue` v9 API-Breaking zwischen Minor-Releases | Mittel | Mittel | Version pinnen (z.B. `^9.5.0`), nicht `latest`; Changelogs bei Update lesen |
| cvorwerk-Boilerplate veraltet zwischenzeitlich | Niedrig | Mittel | Nach Integration nur noch punktuelle Updates per Hand; kein Dependency-Lock auf Boilerplate-Commits |
| vue-draggable-plus Touch-Probleme auf iOS Safari | Mittel | Hoch (Kern-UX) | Früh in Phase 4 auf echtem iPhone testen; Fallback: click-basierte Platzierung aus Phase 3 bleibt nutzbar |
| NC-Signing-Zertifikat-Ausstellung dauert länger als geplant | Niedrig | Hoch (blockiert Release) | Zertifikat sofort in Phase 0 beantragen (parallel zur Entwicklung) |
| Regal-Umbau mit gleichzeitigen Drag-Operationen führt zu Race Conditions | Niedrig | Mittel | DB-Transaktionen im Service, Optimistic Locking per `updated_at`-Vergleich falls nötig (erst bei tatsächlichen Bugs einführen) |
| TypeScript strict mode führt zu mehr Friktion als erwartet | Mittel | Niedrig | Bei Bedarf einzelne Rules lockern (z.B. `noUncheckedIndexedAccess` abschalten); kein Projekt-Breaker |
| Foto-Storage im User-Files bei Sync-Clients: versehentliches Löschen bricht Referenz | Mittel | Niedrig | Bei fehlender Datei in `photo_file_id` im Frontend Placeholder-Bild zeigen, nicht crashen |
| NC 33 ist aktuell 33.0.2 — weitere Patches können Breaking Changes enthalten | Niedrig | Mittel | Vor Release gegen aktuellstes NC 33.0.x testen |

---

## 8. Definition of Done pro Phase

Für jede Phase gilt:

- [ ] Alle Deliverables als erledigt markiert
- [ ] Tests grün (Unit + ggf. Component + PHPUnit NC TestCase)
- [ ] `/ai-first-dev:validate` grün
- [ ] Keine OCP-Hook-Verletzungen (Pre-Commit-Hook)
- [ ] Manueller Smoke-Test im Browser auf lokalem NC 33
- [ ] PR gemergt nach `develop` (Feature-Branch)
- [ ] Kein TODO/FIXME im Phasen-Scope, der den Akzeptanz-Kriterien widerspricht

---

## Anhang

### A. Datenmodelle (Übersicht)

Siehe `docs/design-vinarium-mvp.md §4` — vollständiges ER-Diagramm mit 10 Entities.

### B. API-Übersicht (geplant)

Siehe §3.4 dieses Plans — 11 Endpoint-Gruppen.

### C. Referenzen

- `docs/produktbeschreibung.md` — Produktvision, User Stories, Datenmodell-Basis
- `docs/design-vinarium-mvp.md` — Architektur-Entscheidungen, verworfene Alternativen
- Externe Recherche: `/Users/axel/nextcloud_cpcMomentum/AAA_Allgemeiner_Claude_Code_Chat/research/vinarium/` (3 Dokumente: Nextcloud-Ecosystem, Frontend-Libraries, Weindatenquellen)
- Referenz-Codebasis: Inventory-App (Raimund Schlüssler) — https://github.com/raimund-schluessler/inventory
- Boilerplate: https://github.com/cvorwerk/nextcloud-vue3-boilerplate

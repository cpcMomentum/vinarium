# Phase 1: Schema + Stammdaten-Backend

**Status:** ✅ Abgeschlossen (Feature-Branch `feat/phase1-schema-backend`, 34 Tests grün)
**Abhängigkeiten:** Phase 0 abgeschlossen (Merge PR #1, develop @ 3542f54)
**Erstellt:** 2026-04-15
**Tech-Baseline:** NC 33.0.2, PHP 8.4, OCP (NC 30+)

## Implementierungs-Abweichungen vom Plan

1. **Migration-Test (1.1):** `\Test\TestCase` existiert im NC-Release-Container nicht → Mock-basierter Unit-Test mit PHPUnit-TestCase + ISchemaWrapper/Table-Mocks (Option A aus Abstimmung).
2. **Foreign Keys (1.2):** Entfernt — keine der shipped NC-Apps nutzt `addForeignKeyConstraint` (Cross-DB-Handling unsauber). Integrität wird in den Services erzwungen (Ownership-Checks, Bottle-Parkzone via explizitem `clearSlotForSlotIds` statt `ON DELETE SET NULL`).
3. **Mapper-Tests (1.4):** Statt 10 einzelner Test-Dateien mit SQLite-in-memory → eine `MappersIntegrationTest`-Datei mit 10 Test-Methoden gegen die reale Postgres-Dev-DB (Transaction-Rollback in setUp/tearDown via `IntegrationTestCase`-Basisklasse).
4. **Migration-Deploy (1.8 → vorgezogen):** `occ app:disable/enable` vor 1.4 ausgeführt, damit Integration-Tests echte Tabellen haben.

---

## 1. Übersicht

### 1.1 Ziel
10 DB-Tabellen per Migration, 10 Entities, 10 Mapper, 3 Services (Cellar, Producer, Wine) mit Unit-Tests auf SQLite in-memory. Kein Frontend, kein Controller — nur Backend-Foundation.

### 1.2 Kontext aus Phase 0
- `lib/AppInfo/Application.php` registriert DI (leer; Services werden in 1.5/1.6 registriert falls nötig)
- `composer.json` hat `phpunit/phpunit ^10.5` + Autoload `OCA\Vinarium\Tests\`
- `tests/bootstrap.php` lädt `/var/www/html/lib/base.php` (kein OC_App)
- Pre-Commit-Hook blockt `OC_*`, `\OC::`, `\OC\`, `use OC\` — alle Backend-Klassen MÜSSEN OCP-only bleiben
- Smoke-Test in `tests/Unit/AppFrameworkTest.php` (2 Assertions grün)

### 1.3 Deliverables
- [ ] `lib/Migration/Version000100Date20260415120000.php` — erstellt 10 Tabellen + Indexe + FKs
- [ ] `lib/Db/*.php` (10 Entities): Cellar, Shelf, Compartment, Slot, Producer, Wine, Vintage, Purchase, Bottle, Tasting
- [ ] `lib/Db/*Mapper.php` (10 Mapper) — alle extends `QBMapper<T>`
- [ ] `lib/Service/CellarService.php` + `ProducerService.php` + `WineService.php`
- [ ] `lib/Exception/` — `NotFoundException`, `PermissionDeniedException`, `ReconfigureInProgressException`
- [ ] PHPUnit-Suite `tests/Unit/Db/` + `tests/Unit/Service/` mit Coverage ≥ 60% auf Service-Layer
- [ ] `occ db:add-missing-indices` → „nothing to do" für vinarium_*
- [ ] Migration per `occ upgrade` grün, 10 Tabellen im Container sichtbar

---

## 2. Tech-Stack (April 2026)

| Tech | Version/Quelle | Status |
|---|---|---|
| OCP\AppFramework\Db\QBMapper | NC 33, verifiziert `/var/www/html/lib/public/AppFramework/Db/QBMapper.php` | ✅ since 14.0.0, `@template T of Entity` |
| OCP\AppFramework\Db\Entity | NC 33 | ✅ |
| OCP\Migration\SimpleMigrationStep | NC 33 | ✅ `#[\Override] changeSchema()` |
| OCP\DB\ISchemaWrapper | NC 33 | ✅ `createTable`, `addColumn`, `addIndex`, `addForeignKeyConstraint` |
| OCP\DB\Types | NC 33 | ✅ Types::BIGINT, INTEGER, STRING, TEXT, DATETIME, BOOLEAN, FLOAT, JSON |
| PHPUnit | 10.5.63 (phar via composer) | ✅ |
| SQLite in-memory | `:memory:` für Unit-Tests | ✅ |

**Legende:** ✅ Stabil | ⚠️ Anpassung nötig | 🔴 Breaking

---

## 3. Subagent-Einsatzplan

| Subagent | Steps | Key Deliverables |
|---|---|---|
| php-entwickler | 1.2, 1.3, 1.4, 1.5, 1.6 | Migration, Entities, Mapper, Services |
| unit-test-automator | 1.1, 1.4, 1.5, 1.6 | PHPUnit-Tests (Migration, Mapper, Services) |
| code-reviewer | 1.7 | OCP-only + Type-Hints + AGPL-Header + FK/Index-Review |
| git-workflow-manager | 1.8 | Branch `feat/phase1-schema-backend`, Commit, PR |

---

## 4. Schritt-für-Schritt Umsetzung

### Gesamtübersicht

| # | Schritt | Subagent | Parallel? | Abhängig von |
|---|---|---|---|---|
| 1.1 | Migration-Test (RED) | unit-test-automator | — | — |
| 1.2 | Migration (GREEN) | php-entwickler | — | 1.1 |
| 1.3 | 10 Entities | php-entwickler | ∥ 1.2 | 1.1 |
| 1.4 | 10 Mapper + Tests | php-entwickler + unit-test-automator | — | 1.2, 1.3 |
| 1.5 | CellarService + Tests | php-entwickler + unit-test-automator | — | 1.4 |
| 1.6 | ProducerService + WineService + Tests | php-entwickler + unit-test-automator | ∥ 1.5 | 1.4 |
| 1.7 | Lint/Review | code-reviewer | — | 1.5, 1.6 |
| 1.8 | Deploy + Migration-Run + PR | git-workflow-manager | — | 1.7 |

---

### Step 1.1: Migration-Test (RED)

**Subagent:** unit-test-automator
**Parallelisierung:** Nein

#### Subtasks

**1.1.1 SQLite-Test-Bootstrap für Schema-Tests**
- `tests/Unit/Migration/MigrationTest.php` — nutzt `\OCP\IDBConnection` aus NC-Container (SQLite in-memory, Container stellt automatisch bereit via `base.php`)
- Acceptance: Test-Klasse erbt von `\Test\TestCase` (NC-Test-Base, in `/var/www/html/tests/lib/TestCase.php`) — NICHT `\PHPUnit\Framework\TestCase` direkt
- Acceptance: Test instanziiert `Version000100Date20260415120000` und führt `changeSchema()` gegen ISchemaWrapper-Mock aus

**1.1.2 Tabellen-Existenz-Assertions**
- Für jede der 10 Tabellen: `$this->assertTrue($schema->hasTable('vinarium_<name>'))`
- Liste: `vinarium_cellar`, `vinarium_shelf`, `vinarium_compartment`, `vinarium_slot`, `vinarium_producer`, `vinarium_wine`, `vinarium_vintage`, `vinarium_purchase`, `vinarium_bottle`, `vinarium_tasting`

**1.1.3 Spalten + FK-Assertions (Stichproben)**
- 3 Stichproben-Tabellen: `vinarium_cellar` (owner_user_id + name), `vinarium_bottle` (slot_id nullable + purchase_id FK), `vinarium_tasting` (photo_file_ids JSON)
- Acceptance: Spalten-Typen via `$table->getColumn('…')->getType()->getName()` geprüft

**1.1.4 Index-Assertions**
- `vinarium_shelf` → Index auf `cellar_id`
- `vinarium_slot` → Unique-Index auf `(compartment_id, level, row, column)`
- `vinarium_bottle` → Index auf `slot_id`
- `vinarium_cellar` → Index auf `owner_user_id`

**1.1.5 Test-Lauf RED bestätigen**
- `docker exec -u www-data nextcloud-dev bash -c "cd /var/www/html/custom_apps/vinarium && ./vendor/bin/phpunit -c tests/phpunit.xml --filter Migration"`
- Acceptance: Test FAILT mit „class Version000100Date20260415120000 not found"

---

### Step 1.2: Migration implementieren (GREEN)

**Subagent:** php-entwickler
**Parallelisierung:** Parallel mit 1.3

#### Subtasks

**1.2.1 Skelett-Datei**
- `lib/Migration/Version000100Date20260415120000.php`
- Namespace `OCA\Vinarium\Migration`, `extends SimpleMigrationStep`
- `name()` = „Schema v0.1.0 (Vinarium MVP)", `description()` = kurzer Satz
- `#[\Override] changeSchema(IOutput, Closure, array): ?ISchemaWrapper`

**1.2.2 Tabellen-Definitionen (10 Stück)**
- Für jede Tabelle: `if (!$schema->hasTable(...)) { $table = $schema->createTable(...); $table->addColumn(...); ... $table->setPrimaryKey(['id']); }`
- Alle `id`-Spalten: `Types::BIGINT`, `autoincrement=true`, `notnull=true`, `length=20`
- Alle `owner_user_id`-Spalten: `Types::STRING`, `length=64`, `notnull=true`
- Alle `_at`-Timestamps: `Types::DATETIME` (nicht DATETIME_MUTABLE wegen DB-Portabilität)
- Texte: `Types::TEXT` für Freitext, `Types::STRING` mit `length` für Kurz-Felder
- JSON-Spalten (`tasting.photo_file_ids`): `Types::JSON`
- Enum-artige (bottle.status, wine.color): `Types::STRING` mit `length=20` (KEIN ENUM, DB-portabel)

**1.2.3 Foreign Keys**
- `shelf.cellar_id` → `vinarium_cellar.id` (ON DELETE CASCADE)
- `compartment.shelf_id` → `vinarium_shelf.id` (CASCADE)
- `slot.compartment_id` → `vinarium_compartment.id` (CASCADE)
- `wine.producer_id` → `vinarium_producer.id` (CASCADE)
- `vintage.wine_id` → `vinarium_wine.id` (CASCADE)
- `purchase.vintage_id` → `vinarium_vintage.id` (CASCADE)
- `bottle.purchase_id` → `vinarium_purchase.id` (CASCADE)
- `bottle.slot_id` → `vinarium_slot.id` (ON DELETE SET NULL — Parkzone!)
- `tasting.bottle_id` → `vinarium_bottle.id` (CASCADE)
- `bottle.photo_file_id`: KEIN FK (NC file_id existiert in anderer Tabelle, referentielle Integrität über NC-Hook-Mechanismus statt DB-FK)

**1.2.4 Indexe**
- `vinarium_cellar` (owner_user_id)
- `vinarium_shelf` (cellar_id), `vinarium_shelf` (cellar_id, sort_order)
- `vinarium_compartment` (shelf_id)
- `vinarium_slot` UNIQUE (compartment_id, level, row, column)
- `vinarium_producer` (owner_user_id)
- `vinarium_wine` (producer_id), `vinarium_wine` (barcode)
- `vinarium_vintage` (wine_id)
- `vinarium_purchase` (vintage_id, purchased_at)
- `vinarium_bottle` (purchase_id), `vinarium_bottle` (slot_id), `vinarium_bottle` (status)
- `vinarium_tasting` (bottle_id, tasted_at)

**1.2.5 Test GREEN**
- Erneuter Lauf aus 1.1.5 → alle Assertions grün

---

### Step 1.3: Entities

**Subagent:** php-entwickler
**Parallelisierung:** Parallel mit 1.2

#### Subtasks

**1.3.1 Entity-Basis pro Klasse**
- `lib/Db/<Name>.php` extends `OCP\AppFramework\Db\Entity`
- AGPL-Header via SPDX-Comment (wie Phase 0)
- `namespace OCA\Vinarium\Db`
- `public function __construct() { $this->addType('field', Types::INTEGER); … }` für alle nicht-String-Felder
- Property-Deklarationen mit `protected ?<type> $field = null;`
- Getter/Setter werden über Entity-Magic automatisch via `@method`-PHPDoc dokumentiert

**1.3.2 Feld-Mapping pro Entity** (Kurz-Referenz, vollständiges Schema in `design-vinarium-mvp.md §4`)
- **Cellar:** ownerUserId, name, createdAt (DATETIME)
- **Shelf:** cellarId, name, sortOrder (INTEGER)
- **Compartment:** shelfId, label, sortOrder, levels (INTEGER), columnsFront, columnsBack
- **Slot:** compartmentId, level, row (STRING enum-via-convention), column
- **Producer:** ownerUserId, name, country, region, website, notes (TEXT)
- **Wine:** producerId, name, color, grapeVarieties (TEXT), appellation, notes, barcode
- **Vintage:** wineId, year (INTEGER), alcoholPercent (FLOAT), drinkFrom/drinkUntil (DATETIME), externalRating (FLOAT), externalRatingSource, description (TEXT), referenceUrl
- **Purchase:** vintageId, purchasedAt (DATETIME), vendor, unitPrice (FLOAT), currency, quantity (INTEGER), bottleSizeMl (INTEGER), notes (TEXT)
- **Bottle:** purchaseId, slotId (nullable), status, photoFileId (INTEGER nullable), notes (TEXT)
- **Tasting:** bottleId, tastedAt (DATETIME), rating (FLOAT), notes (TEXT), occasion, companions, photoFileIds (JSON → als Array serialisiert)

**1.3.3 Type-Erzwingung**
- `@method <type> get<Field>()` + `@method void set<Field>(<type>)` PHPDoc-Annotations auf der Klasse (damit statische Analyse funktioniert)
- Acceptance: `php -l` im Container grün für alle 10 Dateien

---

### Step 1.4: Mapper + Tests

**Subagent:** php-entwickler (Impl) + unit-test-automator (Tests)
**Parallelisierung:** Nein

#### Subtasks

**1.4.1 Mapper-Klasse pro Entity**
- `lib/Db/<Name>Mapper.php` extends `QBMapper` mit `@template-extends QBMapper<<Name>>`
- `__construct(IDBConnection $db) { parent::__construct($db, 'vinarium_<tablename>', <Name>::class); }`
- Insert/Update/Delete/Find via `QBMapper`-Default (erben)
- Custom-Methoden:
  - **OwnerAware** (Cellar, Producer): `findByOwner(string $userId): array` + `findOneByOwner(int $id, string $userId): <Entity>`
  - **Child-Listing** (Shelf, Compartment, Slot, Wine, Vintage, Purchase, Bottle, Tasting): `findByParentId(int $parentId): array`
  - **Cross-Owner-Query** (via JOIN) nur im ShelfMapper + CompartmentMapper: `findByCellarOwner(int $cellarId, string $userId)` — validiert Ownership via JOIN auf `vinarium_cellar.owner_user_id`

**1.4.2 QueryBuilder-Pattern (1x dokumentieren, für alle Mapper gleich)**
```php
public function findByOwner(string $userId): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')->from($this->tableName)
        ->where($qb->expr()->eq('owner_user_id', $qb->createNamedParameter($userId)));
    return $this->findEntities($qb);
}
```

**1.4.3 Mapper-Tests (1 Test pro Mapper minimum)**
- `tests/Unit/Db/<Name>MapperTest.php` — SQLite in-memory, Test-Base `\Test\TestCase`
- Pro Mapper: Insert → Find → Delete-Cycle
- Acceptance: 10 Mapper, 10 Tests, alle grün

---

### Step 1.5: CellarService + Tests

**Subagent:** php-entwickler (Impl) + unit-test-automator (Tests)
**Parallelisierung:** Parallel mit 1.6

#### Subtasks

**1.5.1 Service-Skelett**
- `lib/Service/CellarService.php`, `namespace OCA\Vinarium\Service`
- Dependencies via Constructor-Injection: `CellarMapper`, `ShelfMapper`, `CompartmentMapper`, `SlotMapper`, `IDBConnection`, `ITimeFactory`
- `createDefaultCellar(string $userId): Cellar` (public)
- `getActiveCellar(string $userId): array` mit nested Shelves + Compartments (public)
- `reconfigureCompartment(int $compartmentId, int $levels, int $colsFront, int $colsBack, string $userId): int` gibt Anzahl verschobener Flaschen zurück (public)

**1.5.2 Default-Cellar-Logik**
- Innerhalb einer Transaction (`$this->db->beginTransaction(); try { … commit(); } catch { rollback(); throw; }`)
- 1 Cellar („Mein Weinkeller"), 1 Shelf („Regal 1"), 6 Compartments („Fach 1..6"), je 3 Ebenen × 13 Slots (alternierend 6+7/7+6/6+7 Spalten front/back) = 234 Slots total
- Acceptance: Test prüft `count(slots) === 234` nach `createDefaultCellar`

**1.5.3 Reconfigure mit Parkzone-Migration**
- Algorithmus:
  1. Alle Bottles finden, die aktuell in einem Slot dieses Compartments liegen (`slot_id IN …`)
  2. Bottles mit `slot_id = NULL` markieren (Parkzone)
  3. Alle alten Slot-Rows löschen
  4. Neue Slot-Rows nach `(levels, colsFront, colsBack)` anlegen
  5. Anzahl betroffener Bottles zurückgeben
- Acceptance: Test mit 2 Bottles in altem Compartment → nach Reconfigure beide `slot_id === null`, Rückgabewert === 2

**1.5.4 AuthZ-Check**
- Jede public-Methode prüft Cellar-Ownership vor Aktion: `if ($cellar->getOwnerUserId() !== $userId) throw new PermissionDeniedException(...)`
- Acceptance: Test ruft `reconfigureCompartment` mit falschem User auf → `PermissionDeniedException`

**1.5.5 Tests (3 Pflicht aus plan.md §5 + 1 AuthZ)**
- `tests/Unit/Service/CellarServiceTest.php` — 4 Tests: createDefault, getActive, reconfigure mit Parkzone, AuthZ-Verletzung

---

### Step 1.6: ProducerService + WineService + Tests

**Subagent:** php-entwickler + unit-test-automator
**Parallelisierung:** Parallel mit 1.5

#### Subtasks

**1.6.1 ProducerService**
- CRUD: `list(userId)`, `create(data, userId)`, `update(id, data, userId)`, `delete(id, userId)`, `get(id, userId)`
- Ownership-Check in jeder Methode gegen `owner_user_id`
- Exception: `NotFoundException`, `PermissionDeniedException`

**1.6.2 WineService**
- CRUD analog ProducerService, jedoch indirekte Ownership-Prüfung: Wine hat keine `owner_user_id`, Zugriff nur wenn übergeordneter Producer dem User gehört
- Validierung: `wine.color IN ('red','white','rose','sparkling','dessert','fortified')`
- Acceptance: Test mit falschem Color-Wert → InvalidArgumentException

**1.6.3 Tests**
- `tests/Unit/Service/ProducerServiceTest.php` — CRUD + AuthZ (5 Tests)
- `tests/Unit/Service/WineServiceTest.php` — CRUD + Producer-Ownership + Color-Validierung (6 Tests)

---

### Step 1.7: Lint + Review

**Subagent:** code-reviewer
**Parallelisierung:** Nein

#### Subtasks

**1.7.1 PHP-Lint im Container**
- `docker exec nextcloud-dev bash -c "find /var/www/html/custom_apps/vinarium/lib -name '*.php' -exec php -l {} \;" | grep -v 'No syntax errors'`
- Acceptance: leere Ausgabe (alle Dateien syntaktisch valide)

**1.7.2 PHPUnit-Gesamtlauf**
- Kompletter Suite-Run: Smoke-Test (Phase 0) + Migration + 10 Mapper + 3 Service = ≥ 22 Tests grün
- Acceptance: Exit-Code 0, 0 failures, 0 errors

**1.7.3 Coverage-Check (optional, falls xdebug verfügbar)**
- `./vendor/bin/phpunit --coverage-text` — Service-Layer ≥ 60%
- Acceptance: CellarService + ProducerService + WineService jeweils ≥ 60%

**1.7.4 OCP-Hook-Verify**
- `grep -rn "\\\\OC_\|\\\\OC\\\\" lib/` → 0 Treffer
- Acceptance: Pre-Commit-Hook darf beim Commit in 1.8 nicht blocken

**1.7.5 AGPL-Header-Check**
- `grep -L "SPDX-License-Identifier: AGPL-3.0-or-later" lib/**/*.php` → leere Ausgabe
- Acceptance: Alle PHP-Dateien haben Header

---

### Step 1.8: Deploy + Migration-Run + PR

**Subagent:** git-workflow-manager
**Parallelisierung:** Nein

#### Subtasks

**1.8.1 Branch + Commit**
- `git checkout -b feat/phase1-schema-backend` von `develop`
- Conventional Commits (atomar pro logischem Schritt): `feat(db): add migration v000100`, `feat(db): add 10 entities + mappers`, `feat(service): add CellarService with reconfigure`, `feat(service): add ProducerService + WineService`, `test: add unit tests for service layer`

**1.8.2 Deploy ins Container**
- `docker cp` nach `/var/www/html/custom_apps/vinarium/` (oder rsync — altes Deploy säubern, Pattern siehe Phase 0 Step 0.8)
- `docker exec -u www-data nextcloud-dev php occ upgrade 2>&1` → Migration läuft
- Acceptance: Exit-Code 0, Logs zeigen „Running Version000100Date20260415120000", 10 Tabellen danach sichtbar

**1.8.3 DB-Check im Container**
- `docker exec nextcloud-dev bash -c "mysql … -e 'SHOW TABLES LIKE \"oc_vinarium_%\"'"` oder via occ:
- `docker exec -u www-data nextcloud-dev php occ db:add-missing-indices` → „Done"
- Acceptance: 10 Tabellen mit `oc_vinarium_`-Prefix, keine fehlenden Indexe

**1.8.4 PR öffnen**
- `gh pr create --base develop --title "feat: Phase 1 Schema + Stammdaten-Backend"`
- PR-Body mit Checklist aus §1.3 Deliverables
- Kein Draft — direkt Review-ready

---

## 5. Datenmodelle & APIs

### 5.1 Entity-Referenz

Vollständiges ER-Diagramm: `docs/design-vinarium-mvp.md §4` (Cellar → Shelf → Compartment → Slot; Producer → Wine → Vintage → Purchase → Bottle; Bottle → Tasting).

**Namenskonvention (Tabelle → PHP):**
- SQL snake_case → PHP camelCase via Entity-Auto-Mapping
- `vinarium_bottle.slot_id` → `Bottle::getSlotId()`
- Status-Enums als STRING mit Convention: `bottle.status ∈ {in_storage, consumed, gifted, lost}`, `wine.color ∈ {red, white, rose, sparkling, dessert, fortified}`, `slot.row ∈ {front, back}`

### 5.2 Services (interne API, keine REST-Endpoints)

| Service | Methoden | Phase |
|---|---|---|
| CellarService | createDefaultCellar, getActiveCellar, reconfigureCompartment | 1 |
| ProducerService | list, create, update, delete, get | 1 |
| WineService | list, create, update, delete, get (mit Producer-Ownership) | 1 |

**REST-Endpoints kommen erst in Phase 2** (Controller-Ebene). Phase 1 stellt nur die Service-Foundation bereit.

---

## 6. Risiken & Testing

### 6.1 Top 3 Risks

| Risk | Impact | Mitigation |
|---|---|---|
| ⚠️ MySQL/SQLite/Postgres-Inkompatibilität bei `JSON`-Typ + Enum-Werten | Migration läuft lokal (SQLite), crasht auf Postgres | Nur `Types::*` aus `OCP\DB\Types`, keine nativen DBMS-Specifics; Enums als STRING |
| ⚠️ Reconfigure-Transaction-Deadlock bei großer Parkzonen-Migration | Regalumbau friert App ein | In `CellarService::reconfigureCompartment` explizite Transaction + Batch-Update statt Einzel-Updates |
| ⚠️ Mapper-Tests mit echtem SQLite benötigen NC-TestCase-Basisklasse (nicht PHPUnit-Default) | Tests schlagen mit „class Test\TestCase not found" fehl | `tests/bootstrap.php` lädt `/var/www/html/lib/base.php` (bereits in Phase 0 gesetzt) + `autoload-dev` in `composer.json` muss `OCA\Vinarium\Tests\` korrekt mappen (verifiziert in Phase 0 §composer.json) |

### 6.2 Testing-Strategie

**Manual (Phase 1):** `occ upgrade` grün, `db:add-missing-indices` „done", SHOW TABLES zeigt 10 Einträge, App enable/disable/enable cycle ohne Fehler.

**Automated (Phase 1):** PHPUnit Unit-Tests — Migration-Schema (1), Mapper CRUD (10), Services (≥15 Tests). **Ziel: ≥ 22 Tests grün**. Coverage-Gate 60% auf Service-Layer.

**Deferred (Phase 2+):** Controller-Tests, CSRF-Tests, HTTP-Integration.

---

## 7. Acceptance Criteria

- [ ] `occ upgrade` führt Migration aus, 10 Tabellen im Container existieren
- [ ] `occ db:add-missing-indices` meldet keine fehlenden Indexe für `vinarium_*`
- [ ] PHPUnit: Migration-Test + 10 Mapper-Tests + ≥ 15 Service-Tests alle grün
- [ ] Service-Coverage ≥ 60% (CellarService, ProducerService, WineService)
- [ ] Pre-Commit-Hook blockt keinen Vinarium-Backend-File (0 OC_*-Treffer in `lib/`)
- [ ] PR gegen `develop` gemerged, `develop` enthält 10 Entities/Mapper/Services + 1 Migration

---

## 8. Referenzen

- `docs/plan.md` §5 Phase 1 (Zeilen 204-235)
- `docs/design-vinarium-mvp.md` §4 Datenmodell (ER-Diagramm)
- Phase 0: `docs/Plan-Phase0-Details.md` (PHPUnit-Setup, Bootstrap, Pre-Commit-Hook)
- NC-OCP-Referenz im Container: `/var/www/html/lib/public/AppFramework/Db/QBMapper.php`, `/var/www/html/lib/public/Migration/IMigrationStep.php`
- Referenz-App: Activity (`/var/www/html/apps/activity/lib/Migration/Version2006Date20170808154933.php`) — kanonisches NC-Migration-Pattern

# Phase 3: Kauf + Flaschen + Parkzone

**Status:** Geplant
**Abhängigkeiten:** Phase 2 abgeschlossen (PR #3 gemerged, develop @ 22fbe5b)
**Erstellt:** 2026-04-15
**Tech-Baseline:** NC 33.0.2, PHP 8.4, Vue 3.5, TypeScript 5.7

---

## 1. Übersicht

### 1.1 Ziel
Nach Kauf landen N Flaschen in der **Parkzone** (`bottle.slot_id = NULL`, `status = 'in_storage'`). InventoryView zeigt Parkzone + alle Flaschen mit Filter. Click-basierte Platzierung in freie Slots (echtes Drag & Drop folgt in Phase 4).

### 1.2 Kontext aus Phase 2
**Backend-Services bereits da:**
- Producer/Wine/Vintage CRUD + Ownership-Checks
- CellarService.createDefaultCellar (234 Slots erzeugt)

**Entities vorhanden:** Purchase, Bottle, Slot, Cellar, Shelf, Compartment.

**Neu in Phase 3:** PurchaseService, BottleService, 2 Controller, bottleStore, InventoryView, Wizard Step 4+5.

### 1.3 Deliverables
- [ ] `lib/Service/PurchaseService.php` (neu, bulk-bottle-creation)
- [ ] `lib/Service/BottleService.php` (neu, move/park/filter)
- [ ] `lib/Exception/SlotOccupiedException.php` (neu, 409)
- [ ] `lib/Controller/PurchaseController.php` + `BottleController.php`
- [ ] Routes: 6 neue Endpoints (purchases, bottles, move)
- [ ] PHPUnit: Service-Tests (Bulk-Insert, Move, Filter) + Controller-Tests
- [ ] `src/api/purchases.ts` + `bottles.ts` + `bottleStore.ts`
- [ ] PurchaseWizardModal Step 4 (Kauf-Form: Händler/Datum/Preis/Anzahl/Flaschengröße)
- [ ] `src/views/InventoryView.vue` — Tabelle mit Filter + Parkzonen-Bereich
- [ ] `src/views/SimpleShelfView.vue` (Phase-4-Vorstufe) — Click-basierte Slot-Zuweisung
- [ ] Router: `/inventory` + `/shelf`
- [ ] Manueller E2E: Kauf durchlaufen → 6 Flaschen in Parkzone → eine in Slot verschoben

---

## 2. Tech-Stack (April 2026)

| Tech | Version/Quelle | Status |
|---|---|---|
| OCP\AppFramework\Db\QBMapper::insert | NC 33 | ✅ Bulk per Schleife (kein `insertMany` in OCP) |
| IDBConnection::beginTransaction | NC 33 | ✅ für Bulk-Bottle-Insert |
| QueryBuilder JOIN | NC 33 | ✅ für Filter-Queries |
| Pinia Composition API | 2.3 | ✅ |
| @nextcloud/vue NcTable-Pattern | 9.6 | ⚠️ NcTable existiert nicht — eigene Tabelle oder NcListItem |

**Legende:** ✅ Stabil | ⚠️ Anpassung nötig | 🔴 Breaking

---

## 3. Subagent-Einsatzplan

| Subagent | Steps | Key Deliverables |
|---|---|---|
| php-entwickler | 3.1, 3.2, 3.3 | 2 Services + 2 Controller + Routes + Exception |
| unit-test-automator | 3.1, 3.2 | Service-Integration-Tests + Controller-Unit-Tests |
| typescript-pro | 3.4, 3.5 | api-module, bottleStore, Pinia-Tests |
| vue-entwickler | 3.6, 3.7, 3.8 | Wizard Step 4, InventoryView, SimpleShelfView |
| code-reviewer | 3.9 | Lint + OCP-Hook + AGPL |
| git-workflow-manager | 3.10 | Feature-Branch, Commits, PR |

---

## 4. Schritt-für-Schritt Umsetzung

### Gesamtübersicht

| # | Schritt | Subagent | Parallel? | Abhängig von |
|---|---|---|---|---|
| 3.1 | PurchaseService + Tests | php + unit-test | ∥ 3.2 | — |
| 3.2 | BottleService + Tests | php + unit-test | ∥ 3.1 | — |
| 3.3 | 2 Controller + Routes + Tests | php + unit-test | — | 3.1, 3.2 |
| 3.4 | TS Types erweitern + API-Module | typescript-pro | ∥ 3.5 | 3.3 |
| 3.5 | bottleStore + Tests | typescript-pro + unit-test | — | 3.4 |
| 3.6 | Wizard Step 4 (Kauf-Form) | vue-entwickler | ∥ 3.7 | 3.5 |
| 3.7 | InventoryView mit Filter | vue-entwickler | ∥ 3.6 | 3.5 |
| 3.8 | SimpleShelfView (Click-Platzierung) | vue-entwickler | — | 3.7 |
| 3.9 | Lint + Review | code-reviewer | — | 3.6-3.8 |
| 3.10 | Build + Deploy + PR | git-workflow-manager | — | 3.9 |

---

### Step 3.1: PurchaseService + Tests

**Subagent:** php-entwickler + unit-test-automator

#### Subtasks

**3.1.1 PurchaseMapper::find ergänzen**
- Analog zu CellarMapper: `find(int $id): Purchase` (fehlt noch, da nicht in Phase 1 verwendet)

**3.1.2 PurchaseService**
- `lib/Service/PurchaseService.php` mit VintageService-Injection
- `listByVintage(int $vintageId, string $userId): Purchase[]` → VintageService.get verifiziert Ownership
- `get(int $id, string $userId): Purchase`
- `create(string $userId, int $vintageId, array $data): Purchase` — Validierung: `quantity >= 1`, `bottleSizeMl IN (375, 500, 750, 1000, 1500, 3000)`
- `update(int $id, string $userId, array $data): Purchase`
- `delete(int $id, string $userId): Purchase`

**3.1.3 Tests `tests/Integration/Service/PurchaseServiceTest.php`**
- ≥ 5 Tests: create happy, invalid bottleSize, listByVintage, foreign user blocked, delete

---

### Step 3.2: BottleService + Tests

**Subagent:** php-entwickler + unit-test-automator

#### Subtasks

**3.2.1 Exception + Service-Skelett**
- `lib/Exception/SlotOccupiedException.php` extends RuntimeException
- `lib/Service/BottleService.php` mit PurchaseService + CellarMapper/ShelfMapper/CompartmentMapper/SlotMapper-Injection

**3.2.2 createBottlesForPurchase (Bulk)**
- `createBottlesForPurchase(int $purchaseId, string $userId): Bottle[]` — aus Purchase.quantity N Bottles anlegen, alle `slot_id=null, status='in_storage'`
- In Transaction; Return-Array mit den N erzeugten Bottles

**3.2.3 moveBottle (Slot-Zuweisung)**
- `moveBottle(int $bottleId, ?int $slotId, string $userId): Bottle`
- Wenn `$slotId !== null`: prüfe Slot-Ownership (via Slot→Compartment→Shelf→Cellar.owner_user_id)
- Prüfe: Slot ist frei (keine andere Bottle mit `slot_id=X, status='in_storage'`) → sonst SlotOccupiedException
- Update bottle.slot_id

**3.2.4 getParkedBottles / getFilteredBottles**
- `getParkedBottles(string $userId): array` — Bottles mit `slot_id IS NULL AND status='in_storage'`, JOIN auf Purchase→Vintage→Wine→Producer für Anzeige
- `getFilteredBottles(string $userId, array $filter): array` — Filter: status, wine.color, vintage.year, drink_until_before
- Return: Array denormalisierter DTOs (bottle + vintage.year + wine.name/color + producer.name) statt nur Bottle-Entities

**3.2.5 consumeBottle**
- `consumeBottle(int $bottleId, string $userId): Bottle` — setzt status='consumed' + slot_id=NULL (Slot wird frei). Tasting-Erfassung folgt Phase 5.

**3.2.6 Tests `tests/Integration/Service/BottleServiceTest.php`**
- ≥ 7 Tests: Bulk-Create (N=3 → 3 Bottles), Move happy, Move to occupied slot → SlotOccupiedException, Move foreign user → PermissionDenied, Parked-Filter, Status-Filter, Consume

---

### Step 3.3: Controller + Routes

**Subagent:** php-entwickler + unit-test-automator

#### Subtasks

**3.3.1 PurchaseController**
- `index(int $vintageId)` → GET `/api/v1/purchases?vintageId=`
- `create(int $vintageId, int $quantity, int $bottleSizeMl, ?string $purchasedAt, array $data)` → POST `/api/v1/purchases` — legt Purchase an UND direkt Bulk-Bottles (Convenience)
- `show/update/destroy` wie übrige Controller

**3.3.2 BottleController**
- `index(?array $filter)` → GET `/api/v1/bottles?status=…&color=…&year=…`
- `parked()` → GET `/api/v1/bottles/parked`
- `move(int $id, ?int $slotId)` → PATCH `/api/v1/bottles/{id}/move`
- `consume(int $id)` → POST `/api/v1/bottles/{id}/consume`

**3.3.3 Routes**
- 7 neue Zeilen in `appinfo/routes.php`

**3.3.4 Controller-Tests**
- Mock-basiert analog Phase 2 — ≥ 12 Tests (Purchase: 4, Bottle: 8)

---

### Step 3.4: TS Types + API-Module

**Subagent:** typescript-pro

#### Subtasks

**3.4.1 Types**
- `src/types/api.ts` erweitern um `BottleListItem` (denormalisiert: producerName, wineName, color, year, drinkUntil)
- `BottleFilter` Type: `{ status?, color?, year?, drinkUntilBefore? }`

**3.4.2 API-Module**
- `src/api/purchases.ts`: CRUD + `createPurchaseWithBottles(data)` (POST /purchases erzeugt beide)
- `src/api/bottles.ts`: listFiltered, listParked, move, consume

---

### Step 3.5: bottleStore + Tests

**Subagent:** typescript-pro + unit-test-automator

#### Subtasks

**3.5.1 Store `src/stores/bottleStore.ts`**
- State: `parkedBottles`, `bottles` (filtered list), `filter`, `loading`
- Actions: `fetchParked`, `fetchFiltered(filter)`, `moveBottle(id, slotId)` (optimistic), `consumeBottle(id)`
- Getters: `parkedCount`

**3.5.2 Tests ≥ 5**
- Fetch + state population, move optimistic + rollback, filter query, parked count, consume

---

### Step 3.6: Wizard Step 4 (Kauf-Form)

**Subagent:** vue-entwickler

#### Subtasks

**3.6.1 PurchaseWizardModal erweitern**
- Step-Counter auf 4 hochziehen
- Step 4 Felder: purchasedAt (date, default heute), vendor (Händler, optional), unitPrice (optional), currency (3-Letter, default EUR), quantity (Pflicht, default 6), bottleSizeMl (select: 375/500/750/1000/1500/3000, default 750), notes

**3.6.2 Submit**
- Beim Klick „Fertig" API-Call `createPurchaseWithBottles` — erzeugt Purchase + N Bottles atomar
- Auf Erfolg: emit `complete` mit `{ purchaseId, bottleCount }` + Toast „N Flaschen in Parkzone"

**3.6.3 Component-Test**
- 1 neuer Test: Step 4 rendert Kauf-Form; Submit ruft API mit korrekten Werten auf

---

### Step 3.7: InventoryView

**Subagent:** vue-entwickler

#### Subtasks

**3.7.1 `src/views/InventoryView.vue`**
- Parkzonen-Bereich oben: „Nicht zugeordnet (N)" mit Liste der Bottles
- Filter-Bar: Farbe (Multi-Select), Jahrgang (Range), Status (Select)
- Tabelle: Producer | Wein | Jahrgang | Status | Slot (oder „Parkzone") | Actions (In Regal, Öffnen)

**3.7.2 Router-Eintrag**
- `/inventory` → InventoryView
- Sidebar: neues NcAppNavigationItem „Bestand" mit Icon (FormatListBulleted oder ähnlich)

---

### Step 3.8: SimpleShelfView (Click-Platzierung)

**Subagent:** vue-entwickler

#### Subtasks

**3.8.1 `src/views/SimpleShelfView.vue`**
- Render Cellar → Shelves → Compartments → Slots als Grid (CSS grid-template)
- Legende: leere Slots weiß, belegte dunkelgrau mit Wein-Farb-Dot
- Parkzonen-Liste oben als abwählbare Bottle-Cards (Radio-Auswahl: nur eine gleichzeitig markiert)
- Click auf leeren Slot → `bottleStore.moveBottle(selectedBottleId, slotId)` → Optimistic Update + Rollback auf 409

**3.8.2 Router + Nav**
- `/shelf` → SimpleShelfView, Sidebar-Icon WineShelf (oder Grid)

---

### Step 3.9: Lint + Review

- PHP lint, OCP-Hook-Grep, AGPL-Header, `npm run typecheck`, beide Test-Suiten grün
- Ziel: ≥ 79 + 12 Controller + 7 Bottle + 5 Purchase + 5 Store = **≥ 108 Tests grün**

---

### Step 3.10: Build + Deploy + PR

- App-Version auf `0.1.2` bumpen (keine Migration nötig, aber saubere Version pro Phase)
- 5-6 atomare Commits (backend-service, backend-controller+routes, frontend-api+store, frontend-wizard, frontend-views, docs)
- PR gegen `develop` mit Test-Plan-Checklist

---

## 5. Datenmodelle & APIs

### 5.1 Neue TS-Types

```typescript
export interface BottleListItem extends Bottle {
	// denormalisiert aus JOIN
	producerName: string
	wineName: string
	wineColor: WineColor
	year: number
	drinkUntil: string | null
}

export interface BottleFilter {
	status?: BottleStatus
	color?: WineColor
	year?: number
	drinkUntilBefore?: string
}

export interface PurchaseWithBottlesCreate {
	vintageId: number
	purchasedAt: string
	vendor?: string | null
	unitPrice?: number | null
	currency?: string
	quantity: number
	bottleSizeMl: 375 | 500 | 750 | 1000 | 1500 | 3000
	notes?: string | null
}
```

### 5.2 Neue REST-Endpoints

| Methode | Pfad | Controller#Action |
|---|---|---|
| GET | `/api/v1/purchases?vintageId=` | purchase#index |
| GET | `/api/v1/purchases/{id}` | purchase#show |
| POST | `/api/v1/purchases` | purchase#create (inkl. Bulk-Bottles) |
| PATCH | `/api/v1/purchases/{id}` | purchase#update |
| DELETE | `/api/v1/purchases/{id}` | purchase#destroy |
| GET | `/api/v1/bottles` | bottle#index (Filter via Query) |
| GET | `/api/v1/bottles/parked` | bottle#parked |
| GET | `/api/v1/bottles/{id}` | bottle#show |
| PATCH | `/api/v1/bottles/{id}/move` | bottle#move |
| POST | `/api/v1/bottles/{id}/consume` | bottle#consume |
| DELETE | `/api/v1/bottles/{id}` | bottle#destroy |

---

## 6. Risiken & Testing

### 6.1 Top 3 Risks

| Risk | Impact | Mitigation |
|---|---|---|
| ⚠️ Race Condition bei parallelen Move-Operationen auf gleichen Slot | Zwei Bottles im selben Slot | SlotOccupiedException über echten SELECT-FOR-UPDATE oder einfach UNIQUE INDEX auf `slot_id WHERE status='in_storage'` (Partial Index, Postgres-spezifisch) — Alternative: Transaction + SELECT-Check vor UPDATE |
| ⚠️ Bulk-Insert von 24 Flaschen ohne Transaction schlägt teilweise fehl | Inkonsistenter Kauf | `$this->db->beginTransaction()` + rollBack im catch |
| ⚠️ Filter-Query mit 4 JOINs auf großer DB langsam | UI hängt | Indexe aus Phase 1 decken die FK-Spalten; bei > 1000 Flaschen Limit+Pagination nachziehen (Phase 5) |

### 6.2 Testing-Strategie

**Manual (Phase 3):** Kauf durchlaufen (6 Flaschen 2021er) → erscheint in InventoryView-Parkzone → im SimpleShelfView eine Flasche auf Slot setzen → erscheint im Slot mit Farb-Dot.

**Automated:** ≥ 108 Tests grün (60 + 19 bisher + 29 neue).

---

## 7. Acceptance Criteria

- [ ] Wizard durchläuft alle 4 Steps; Submit erzeugt Purchase + N Bottles atomar
- [ ] InventoryView zeigt Parkzone separiert + gefilterte Gesamtliste
- [ ] SimpleShelfView: Click auf freien Slot platziert markierte Flasche; 409-Fehler korrekt behandelt
- [ ] Consume-Action wechselt Status + gibt Slot frei
- [ ] PR gegen `develop` gemerged, CSRF + OCP-Hook clean

---

## 8. Referenzen

- `docs/plan.md` §5 Phase 3 (Zeilen 275-306)
- `docs/design-vinarium-mvp.md` §4 Datenmodell (Bottle/Purchase)
- Phase 2: `docs/Plan-Phase2-Details.md` (REST-Controller-Pattern)

# Phase 2: Wein-Stammdaten-Frontend

**Status:** Geplant
**Abhängigkeiten:** Phase 1 abgeschlossen (PR #2 gemerged, develop @ 281ec60)
**Erstellt:** 2026-04-15
**Tech-Baseline:** NC 33.0.2, PHP 8.4, Vue 3.5, TypeScript 5.7, Vite 7.3

---

## 1. Übersicht

### 1.1 Ziel
End-to-End-Erfassung Weingut → Wein → Jahrgang funktioniert: 3 REST-Controller mit DataResponse, TypeScript-Types, Pinia-Store, PurchaseWizardModal (Schritte 1-3) und WinesView mit Tabs.

### 1.2 Kontext aus Phase 1
**Backend-Services verfügbar (alle mit Ownership-Checks):**
- `ProducerService::list|get|create|update|delete($id, $userId)`
- `WineService::listByProducer|get|create|update|delete($id, $userId)` + Color-Validation
- (Vintage-Service existiert noch nicht — wird in 2.1.3 neu angelegt)

**Exceptions:** `NotFoundException`, `PermissionDeniedException`, `ValidationException` — werden in Controllern in HTTP-Status 404/403/400 gemappt.

### 1.3 Deliverables
- [ ] `lib/Service/VintageService.php` (neu, CRUD + indirekte Ownership via Wine→Producer)
- [ ] `lib/Controller/ProducerController.php`, `WineController.php`, `VintageController.php`
- [ ] Routes in `appinfo/routes.php` für alle REST-Endpoints unter `/api/v1/`
- [ ] Controller-Tests (mock service layer): CSRF, 404, 403, 400, 200
- [ ] `src/types/api.ts` — TypeScript-Interfaces spiegeln Entities 1:1
- [ ] `src/api/producers.ts`, `wines.ts`, `vintages.ts` — typed fetch-Wrapper
- [ ] `src/stores/wineStore.ts` — Pinia-Store mit producers/wines/vintages State + Actions
- [ ] `src/components/PurchaseWizardModal.vue` (Schritte 1-3: Producer → Wine → Vintage)
- [ ] `src/views/WinesView.vue` — Tabs mit NcListItem-Listen
- [ ] Vitest + @vue/test-utils: Store-Tests + Component-Test für Wizard
- [ ] Manueller E2E-Test: Weingut anlegen → Wein zuordnen → Jahrgang anlegen

---

## 2. Tech-Stack (April 2026)

| Tech | Version/Quelle | Status |
|---|---|---|
| OCP\AppFramework\Controller | NC 33, `/var/www/html/lib/public/AppFramework/Controller.php` | ✅ Basis-Klasse |
| OCP\AppFramework\Http\DataResponse | NC 33 | ✅ JSON-Wrapper für API |
| OCP\AppFramework\Http\Attribute\NoAdminRequired | NC 30+ | ✅ PHP 8 Attribute |
| OCP\IUserSession | NC 33 | ✅ `getUser()?->getUID()` |
| @nextcloud/axios | ^2.5 (installed) | ✅ CSRF-Header auto |
| @nextcloud/router | ^3.1 (installed) | ✅ `generateUrl` für API-Pfade |
| pinia | ^2.3 (installed) | ✅ Composition API Style |
| @nextcloud/vue (NcModal, NcSelect, NcListItem, NcButton) | ^9.6 | ✅ |
| vitest + @vue/test-utils | 4.1 / 2.4 (installed) | ✅ |

**Legende:** ✅ Stabil | ⚠️ Anpassung nötig | 🔴 Breaking

---

## 3. Subagent-Einsatzplan

| Subagent | Steps | Key Deliverables |
|---|---|---|
| php-entwickler | 2.1, 2.2 | VintageService, 3 Controller, routes.php |
| typescript-pro | 2.3, 2.4, 2.5 | api.ts Types, 3 API-Module, wineStore |
| vue-entwickler | 2.6, 2.7 | PurchaseWizardModal, WinesView |
| unit-test-automator | 2.1, 2.5, 2.6 | Controller-Tests, Store-Tests, Wizard-Test |
| code-reviewer | 2.9 | OCP-Hook, AGPL-Header, CSRF-Review |
| git-workflow-manager | 2.10 | Feature-Branch, Commits, PR |

---

## 4. Schritt-für-Schritt Umsetzung

### Gesamtübersicht

| # | Schritt | Subagent | Parallel? | Abhängig von |
|---|---|---|---|---|
| 2.1 | VintageService + 3 Controller + Tests | php-entwickler + unit-test-automator | — | — |
| 2.2 | Routes registrieren | php-entwickler | — | 2.1 |
| 2.3 | TypeScript-Types (RED→GREEN) | typescript-pro | ∥ 2.1 | — |
| 2.4 | API-Layer (fetch-Wrapper) | typescript-pro | — | 2.3 |
| 2.5 | Pinia wineStore + Tests | typescript-pro + unit-test-automator | — | 2.4 |
| 2.6 | PurchaseWizardModal + Test | vue-entwickler + unit-test-automator | — | 2.5 |
| 2.7 | WinesView mit Tabs | vue-entwickler | ∥ 2.6 | 2.5 |
| 2.8 | Deploy + manueller E2E | — | — | 2.6, 2.7 |
| 2.9 | Lint + Review | code-reviewer | — | 2.8 |
| 2.10 | Commit + PR | git-workflow-manager | — | 2.9 |

---

### Step 2.1: VintageService + 3 Controller + Tests

**Subagent:** php-entwickler + unit-test-automator
**Parallelisierung:** Nein

#### Subtasks

**2.1.1 VintageService anlegen**
- `lib/Service/VintageService.php` analog `WineService` (indirekte Ownership via `WineService::get`)
- Methoden: `listByWine(wineId, userId)`, `get(id, userId)`, `create(userId, wineId, year, data)`, `update(id, userId, data)`, `delete(id, userId)`
- Jahr-Validierung: `year >= 1900 && year <= <currentYear + 2>` → sonst `ValidationException`
- Integration-Test in `tests/Integration/Service/VintageServiceTest.php` — ≥ 5 Tests

**2.1.2 ProducerController**
- `lib/Controller/ProducerController.php` extends `Controller`
- Constructor-Injection: `IRequest`, `IUserSession`, `ProducerService`
- Methoden (alle `#[NoAdminRequired]`): `index()` → GET list, `show($id)` → GET single, `create()` → POST, `update($id)` → PATCH, `destroy($id)` → DELETE
- Return-Typ `DataResponse` mit Entity (via JsonSerializable) oder Error-Array
- Error-Mapping: `NotFoundException` → 404, `PermissionDeniedException` → 403, `ValidationException` → 400
- Kein `#[NoCSRFRequired]` → CSRF-Schutz aktiv für alle mutierenden Methoden

**2.1.3 WineController + VintageController**
- Analog ProducerController
- `WineController::index()` erwartet Query-Param `producerId`
- `VintageController::index()` erwartet Query-Param `wineId`
- `VintageController::create()` bekommt `wineId` aus Body

**2.1.4 Controller-Tests (Mock-Service-Layer)**
- `tests/Unit/Controller/ProducerControllerTest.php` etc. (3 Dateien)
- Pro Controller: 5-6 Tests (list happy, get 404, create valid, create invalid body, update forbidden, delete ok)
- Mock `ProducerService` + `IUserSession`, `IRequest`
- ≥ 15 Tests total

---

### Step 2.2: Routes registrieren

**Subagent:** php-entwickler

#### Subtasks

**2.2.1 `appinfo/routes.php` erweitern**
```php
'routes' => [
    ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
    // Producer
    ['name' => 'producer#index',   'url' => '/api/v1/producers',       'verb' => 'GET'],
    ['name' => 'producer#show',    'url' => '/api/v1/producers/{id}',  'verb' => 'GET'],
    ['name' => 'producer#create',  'url' => '/api/v1/producers',       'verb' => 'POST'],
    ['name' => 'producer#update',  'url' => '/api/v1/producers/{id}',  'verb' => 'PATCH'],
    ['name' => 'producer#destroy', 'url' => '/api/v1/producers/{id}',  'verb' => 'DELETE'],
    // Wine + Vintage analog
],
```
- Controller-Namen-Konvention: kleinbuchstabig ohne `Controller`-Suffix (`producer`, `wine`, `vintage`)
- Acceptance: `occ app:enable vinarium` lädt Routes ohne Warnung

---

### Step 2.3: TypeScript-Types (RED→GREEN)

**Subagent:** typescript-pro

#### Subtasks

**2.3.1 `src/types/api.ts`**
- Interface pro Entity: `Producer`, `Wine`, `Vintage`, `Purchase`, `Bottle`, `Tasting`
- Camel-Case, nullable mit `?:` oder `| null`
- Union für Enums: `WineColor = 'red' | 'white' | 'rose' | 'sparkling' | 'dessert' | 'fortified'`
- `BottleStatus = 'in_storage' | 'consumed' | 'gifted' | 'lost'`

**2.3.2 Vitest-Test `src/types/api.spec.ts`**
- Mock-Response-Objekt → Type-Assertion via `satisfies Producer`
- Acceptance: `npx vitest run` grün, `npx tsc --noEmit` grün

---

### Step 2.4: API-Layer (fetch-Wrapper)

**Subagent:** typescript-pro

#### Subtasks

**2.4.1 `src/api/client.ts`** (gemeinsamer Wrapper)
- `import axios from '@nextcloud/axios'`
- `generateUrl` aus `@nextcloud/router` für App-Pfade
- Helper: `apiGet<T>(path)`, `apiPost<T,B>(path, body)`, `apiPatch<T,B>(path, body)`, `apiDelete(path)`
- Error-Extraktion: wirft `{ status: number, message: string }` bei non-2xx

**2.4.2 `src/api/producers.ts`, `wines.ts`, `vintages.ts`**
- Je CRUD-Funktionen: `listProducers()`, `getProducer(id)`, `createProducer(data)`, etc.
- Return-Typen aus `api.ts`

---

### Step 2.5: Pinia wineStore + Tests

**Subagent:** typescript-pro + unit-test-automator

#### Subtasks

**2.5.1 `src/stores/wineStore.ts`**
- `defineStore('wine', () => { ... })` Composition-API-Style
- State: `producers: Ref<Producer[]>`, `wines: Ref<Wine[]>`, `vintages: Ref<Vintage[]>`, `loading: Ref<boolean>`
- Getters: `producerById(id)`, `winesByProducer(id)`, `vintagesByWine(id)`
- Actions (async): `fetchProducers`, `createProducer(data)`, `updateProducer(id, data)`, `deleteProducer(id)`; analog für wines/vintages
- Optimistic Update + Rollback bei API-Error

**2.5.2 Store-Tests `src/stores/__tests__/wineStore.spec.ts`**
- Mock API-Layer via `vi.mock('@/api/producers')` etc.
- ≥ 6 Tests: createProducer happy, updateProducer optimistic-rollback, fetchWines dedup, etc.

---

### Step 2.6: PurchaseWizardModal + Test

**Subagent:** vue-entwickler + unit-test-automator

#### Subtasks

**2.6.1 `src/components/PurchaseWizardModal.vue`**
- `<NcModal>` mit 3 Steps via v-if/Stepper-Pattern
- Step 1 „Weingut": `<NcSelect>` mit async-Loading + „+ Neu"-Button öffnet Inline-Form
- Step 2 „Wein": gleiche Mechanik auf `winesByProducer`
- Step 3 „Jahrgang": Liste bestehender Vintages + „+ Neu"-Button mit Year-Input
- Emit-Event `<wizard-complete>` mit `{ vintageId }` bei Abschluss
- Validation vor „Weiter": alle Felder in aktuellem Schritt befüllt

**2.6.2 Component-Test**
- `src/components/__tests__/PurchaseWizardModal.spec.ts`
- ≥ 4 Tests: mount renders, step-navigation, producer-creation inline, complete emits event
- Mock wineStore via `createPinia()` + `setActivePinia()`

---

### Step 2.7: WinesView mit Tabs

**Subagent:** vue-entwickler

#### Subtasks

**2.7.1 `src/views/WinesView.vue`**
- 3 Tabs via einfacher `<button>`-Bar oder `<NcAppSidebarTab>`
- Je Tab `<NcListItem>`-Liste aus wineStore
- Klick auf Item öffnet Detail-Modal (wiederverwendbare Edit-Form pro Entity)
- Edit-Form als Sub-Component `ProducerEditDialog.vue`, `WineEditDialog.vue`, `VintageEditDialog.vue`

**2.7.2 Router-Eintrag in `src/router.ts` (falls nicht vorhanden)**
- Prüfen ob Router aus Phase 0 existiert, sonst minimal anlegen: `/wines` → WinesView
- Acceptance: Klick auf NcAppNavigationItem „Weine" navigiert zur View

---

### Step 2.8: Deploy + manueller E2E

#### Subtasks

**2.8.1 Build + Deploy**
- `npm run build` — `dist/` aktualisiert
- `docker cp` nach `custom_apps/vinarium/`
- App-Neuenable nicht nötig (keine Migration)

**2.8.2 Browser-Test Checkliste**
- [ ] Weingut anlegen (Name + Land) → erscheint in WinesView Tab „Weingüter"
- [ ] Weingut editieren, ändern bleibt erhalten nach Reload
- [ ] Wein zu bestehendem Weingut anlegen (mit Farb-Wahl) → erscheint in Tab „Weine"
- [ ] Ungültige Farbe → Fehler-Toast (400-Response durchgereicht)
- [ ] Jahrgang zu Wein anlegen → erscheint in Tab „Jahrgänge"
- [ ] Löschen funktioniert in jeder Entity-Ebene
- [ ] PurchaseWizardModal Steps 1-3 durchlaufen, schließt mit `vintageId`
- [ ] Fremder User sieht eigene Daten nicht (Inkognito-Tab mit anderem NC-User)

---

### Step 2.9: Lint + Review

**Subagent:** code-reviewer

#### Subtasks

- `docker exec` PHP-Lint für neue Controller-Dateien
- `npx tsc --noEmit` grün
- `npx eslint src/` grün
- `npx vitest run` grün (≥ 10 neue Frontend-Tests)
- `./vendor/bin/phpunit` grün (≥ 34 + 15 neue Controller-Tests = ≥ 49 Tests)
- OCP-Hook-Grep: 0 Treffer in `lib/`
- AGPL-Header-Check: alle neuen Dateien

---

### Step 2.10: Commit + PR

**Subagent:** git-workflow-manager

#### Subtasks

- `git checkout -b feat/phase2-wine-frontend` von `develop`
- Atomare Commits:
  1. `feat(service): add VintageService with indirect ownership`
  2. `feat(controller): add producer, wine, vintage REST controllers`
  3. `feat(api): register Phase 2 REST routes`
  4. `feat(frontend): add typed API client and Pinia wineStore`
  5. `feat(frontend): add PurchaseWizardModal and WinesView`
  6. `test: add controller + store + component tests`
  7. `docs: mark Phase 2 plan as implemented`
- PR gegen `develop` mit Test-Plan-Checklist aus 2.8.2

---

## 5. Datenmodelle & APIs

### 5.1 TypeScript-Types (Kern)

```typescript
export type WineColor = 'red' | 'white' | 'rose' | 'sparkling' | 'dessert' | 'fortified';

export interface Producer {
  id: number;
  ownerUserId: string;
  name: string;
  country: string | null;
  region: string | null;
  website: string | null;
  notes: string | null;
}

export interface Wine {
  id: number;
  producerId: number;
  name: string;
  color: WineColor;
  grapeVarieties: string | null;
  appellation: string | null;
  notes: string | null;
  barcode: string | null;
}

export interface Vintage {
  id: number;
  wineId: number;
  year: number;
  alcoholPercent: number | null;
  drinkFrom: string | null;
  drinkUntil: string | null;
  externalRating: number | null;
  externalRatingSource: string | null;
  description: string | null;
  referenceUrl: string | null;
}
```

Purchase/Bottle/Tasting: Referenz in `lib/Db/*.php`, werden in Phase 3/5 im Frontend gebraucht.

### 5.2 REST-Endpoints Phase 2

| Methode | Pfad | Controller#Action | Phase |
|---|---|---|---|
| GET | `/api/v1/producers` | producer#index | 2 |
| GET | `/api/v1/producers/{id}` | producer#show | 2 |
| POST | `/api/v1/producers` | producer#create | 2 |
| PATCH | `/api/v1/producers/{id}` | producer#update | 2 |
| DELETE | `/api/v1/producers/{id}` | producer#destroy | 2 |
| GET | `/api/v1/wines?producerId=` | wine#index | 2 |
| GET | `/api/v1/wines/{id}` | wine#show | 2 |
| POST | `/api/v1/wines` | wine#create | 2 |
| PATCH | `/api/v1/wines/{id}` | wine#update | 2 |
| DELETE | `/api/v1/wines/{id}` | wine#destroy | 2 |
| GET | `/api/v1/vintages?wineId=` | vintage#index | 2 |
| GET | `/api/v1/vintages/{id}` | vintage#show | 2 |
| POST | `/api/v1/vintages` | vintage#create | 2 |
| PATCH | `/api/v1/vintages/{id}` | vintage#update | 2 |
| DELETE | `/api/v1/vintages/{id}` | vintage#destroy | 2 |

Cellar/Bottle/Tasting/Export: kommen in Phasen 3-5.

---

## 6. Risiken & Testing

### 6.1 Top 3 Risks

| Risk | Impact | Mitigation |
|---|---|---|
| ⚠️ CSRF-Token wird von @nextcloud/axios nicht automatisch gesetzt | POST/PATCH/DELETE liefern 412 | Test mit echter NC-Session im Browser, bei Bedarf `axios.defaults.headers['requesttoken']` setzen |
| ⚠️ Pinia optimistic-update bei API-Fehler inkonsistent | Store-State driftet von DB weg | Rollback in catch-Block + Integration-Test der Rollback-Pfade |
| ⚠️ NcSelect async-options mit schnellem Wechsel race-conditions | Falsche Producer-Liste sichtbar | AbortController pro Request, letzter Request gewinnt |

### 6.2 Testing-Strategie

**Backend (Unit):** Controller mit Mock-Services, ≥ 15 Tests. Services bleiben Integration (siehe Phase 1).

**Frontend (Unit):** Vitest + @vue/test-utils. Store-Tests mit Mock-API (≥ 6 Tests). Component-Test für PurchaseWizardModal (≥ 4 Tests).

**E2E (manuell):** 8-Punkt-Checkliste aus 2.8.2, durchlaufen auf Dev-Instanz.

**Deferred:** Playwright/Cypress E2E kommt in Phase 6.

---

## 7. Acceptance Criteria

- [ ] 15 REST-Endpoints live, CSRF-Schutz aktiv
- [ ] Controller-Tests + Store-Tests + Component-Tests: ≥ 25 neue Tests grün
- [ ] TypeScript strict mode + ESLint grün
- [ ] Manueller E2E-Flow (Weingut → Wein → Jahrgang) läuft fehlerfrei auf Dev-Instanz
- [ ] PR gegen `develop` gemerged, OCP-Hook + AGPL-Check grün

---

## 8. Referenzen

- `docs/plan.md` §5 Phase 2 (Zeilen 239-272)
- `docs/design-vinarium-mvp.md` §5 API-Design, §6 UI-Komponenten
- Phase 1: `docs/Plan-Phase1-Details.md` (Services + Entities)
- NC-Controller-Referenz: `/var/www/html/apps/activity/lib/Controller/ActivitiesController.php`
- NC-OCP: `/var/www/html/lib/public/AppFramework/Controller.php`, `…/Http/DataResponse.php`

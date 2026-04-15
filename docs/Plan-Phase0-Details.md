# Phase 0: Skelett-Austausch

**Status:** Geplant
**Abhängigkeiten:** Keine (erste Phase)
**Erstellt:** 2026-04-15
**Tech-Baseline:** April 2026

---

## 1. Übersicht

### 1.1 Ziel
Vue-2-Skelett archivieren, cvorwerk/nextcloud-vue3-boilerplate (HEAD 2026-04-10) als neue Basis integrieren. Vinarium rendert Willkommens-View auf NC 33 lokal; AppFramework-Smoke-Test grün.

### 1.2 Kontext aus Vorphasen
Keine Vorphasen. Abhängigkeiten stammen ausschließlich aus `plan.md` §5 (Phase 0), `design-vinarium-mvp.md` und CLAUDE.md.

### 1.3 Deliverables
- [ ] Alte Vue-2-Dateien in `archive/initial-skeleton-20260415/`
- [ ] Boilerplate-Dateien integriert, Namespace/App-ID ersetzt
- [ ] `composer install` grün
- [ ] `npm install` + `npm run build` grün, `js/` + `css/` enthalten Artefakte
- [ ] `occ app:enable vinarium` auf lokalem NC 33.0.2 ohne Fehler
- [ ] `/apps/vinarium/` zeigt Willkommens-View
- [ ] `tests/unit/AppFrameworkTest.php` grün (PHPUnit)
- [ ] Pre-Commit-Hook (OCP-Check) aktiv und greifend
- [ ] Git-Commit + Push auf `feat/phase0-skeleton-swap`
- [x] ~~NC-Signing-Zertifikat beantragen~~ → verschoben nach Phase 6 (Release)

---

## 2. Tech-Stack (April 2026)

| Tech | Version (plan.md) | Version (Boilerplate HEAD) | Status |
|---|---|---|---|
| Vite | 5+ | 7.1.3 | ⚠️ Update-Recommendation |
| Vue | 3.4+ | 3.5.13 | ✅ |
| TypeScript | 5.x | 5.7.2 | ✅ |
| @nextcloud/vue | 9.5+ | ^9.0.0 (auf 9.5.x pinnen) | ✅ |
| @nextcloud/axios | 2.5+ | ^2.5.2 | ✅ |
| @nextcloud/router | — | ^3.1.0 | ✅ |
| @vitejs/plugin-vue | — | ^6.0.0 | ✅ |
| Node.js | — | ≥20 | ✅ |
| PHP | 8.2+ | 8.3 (Boilerplate README) | ⚠️ prüfen |

**Legende:** ✅ Stabil | ⚠️ Anpassung nötig | 🔴 Breaking

Konsequenz für `plan.md` §2: Vite-Version auf „7+" aktualisieren (siehe Schritt 9).

**Nachzurüsten in Phase 0 (nicht in Boilerplate enthalten):**
- `pinia@^2.x` (State, ab Phase 2 gebraucht — bereits jetzt installieren für konsistentes Lockfile)
- `vue-router@^4.x` (Hash-Mode, ab Phase 2)
- `vue-draggable-plus@^0.6` (ab Phase 4 — optional jetzt oder später)
- `vitest@^1.x` + `@vue/test-utils@^2.x` + `happy-dom` (ab Phase 1)
- Dev: `eslint@^9.x` mit `@nextcloud/eslint-config`
- PHPUnit + Nextcloud TestCase (über `composer.json` `require-dev`)

---

## 3. Subagent-Einsatzplan

| Subagent | Steps | Key Deliverables |
|---|---|---|
| nextcloud-spezialist | 0.2, 0.5, 0.8 | Archivierung, `info.xml`-Merge, Deploy-Check |
| typescript-pro | 0.3, 0.4, 0.6 | Boilerplate-Integration, Namespace-Replace, TS-Setup |
| php-entwickler | 0.1, 0.9 | Smoke-Test + `Application::register()`-Anpassung |
| devops-engineer | 0.6, 0.8 | npm/composer-Install, Deploy-Script, Cert-Antrag |
| git-workflow-manager | 0.10 | Branch `feat/phase0-skeleton-swap`, Commit, PR draft |

---

## 4. Schritt-für-Schritt Umsetzung

### Gesamtübersicht

| # | Schritt | Subagent | Parallel? | Abhängig von |
|---|---|---|---|---|
| 0.1 | Smoke-Test RED | php-entwickler | — | — |
| 0.2 | Altes Skelett archivieren | nextcloud-spezialist | — | 0.1 |
| 0.3 | Boilerplate importieren | typescript-pro | — | 0.2 |
| 0.4 | Namespace/App-ID ersetzen | typescript-pro | — | 0.3 |
| 0.5 | `info.xml` zusammenführen | nextcloud-spezialist | ∥ 0.4 | 0.3 |
| 0.6 | Dependencies installieren + Extras | devops-engineer | — | 0.4, 0.5 |
| 0.7 | `npm run build` | devops-engineer | — | 0.6 |
| 0.8 | Deploy + `occ app:enable` | nextcloud-spezialist | — | 0.7 |
| 0.9 | Smoke-Test GREEN | php-entwickler | — | 0.8 |
| 0.10 | Commit + PR draft | git-workflow-manager | — | 0.9 |
| ~~0.11~~ | ~~NC-Signing-Zertifikat~~ | → verschoben nach Phase 6 | — | — |

---

### Step 0.1: Smoke-Test schreiben (RED)

**Subagent:** php-entwickler
**Parallelisierung:** Nein

#### Subtasks

**0.1.1 Test-Skeleton anlegen**
- Datei: `tests/unit/AppFrameworkTest.php`, erweitert `\Test\TestCase` (NC TestCase)
- Namespace `OCA\Vinarium\Tests\Unit`

**0.1.2 Container-Check schreiben**
- Acceptance: Test instanziiert `\OCA\Vinarium\AppInfo\Application`, ruft `register(IRegistrationContext)` auf, danach wird `PageController` aus dem DI-Container aufgelöst und ist Instanz von `\OCA\Vinarium\Controller\PageController`.
- Acceptance: Test FAILT solange Vue-2-Skelett noch aktiv ist (erwartet weil Application-Klasse refaktoriert wird).

**0.1.3 PHPUnit-Config verifizieren**
- Acceptance: `composer require --dev phpunit/phpunit` + `phpunit.xml.dist` vorhanden (falls nicht: anlegen, `tests/` als testsuite)
- Acceptance: `./vendor/bin/phpunit tests/unit` läuft durch und zeigt den RED-Fail

---

### Step 0.2: Altes Skelett archivieren

**Subagent:** nextcloud-spezialist
**Parallelisierung:** Nein

#### Subtasks

**0.2.1 Archiv-Ordner anlegen**
- `mkdir -p archive/initial-skeleton-20260415`
- Acceptance: Ordner existiert, in `.gitignore` NICHT enthalten

**0.2.2 Alte Dateien verschieben**
- Verschieben nach `archive/initial-skeleton-20260415/`: `src/`, `js/`, `css/`, `templates/`, `package.json`, `package-lock.json`, `webpack.config.js`, `composer.json`, `node_modules/` (nicht committen, siehe 0.2.3), `lib/` (Komplett-Backup; neue `lib/`-Struktur kommt aus Boilerplate)
- Behalten (NICHT verschieben): `.git/`, `.github/`, `.claude/`, `.githooks/`, `docs/`, `appinfo/` (wird in 0.5 gemergt), `img/`, `l10n/`, `tests/`, `CLAUDE.md`, `LICENSE`, `README.md`, `CHANGELOG.md`, `.gitignore`
- Acceptance: `git status` zeigt Verschiebungen sauber, keine doppelten Dateien

**0.2.3 `node_modules` + Build-Artefakte säubern**
- `rm -rf node_modules` (nicht archivieren — blähen Repo auf)
- Acceptance: `archive/initial-skeleton-20260415/` nicht in `.gitignore` (Backup soll committed werden); `node_modules/` bleibt in `.gitignore`

---

### Step 0.3: Boilerplate importieren

**Subagent:** typescript-pro
**Parallelisierung:** Nein

#### Subtasks

**0.3.1 Boilerplate in Temp klonen**
```bash
git clone --depth 1 https://github.com/cvorwerk/nextcloud-vue3-boilerplate \
  /tmp/vinarium-boilerplate
```
- Acceptance: Temp-Klon existiert, Commit-Hash notiert in `docs/Plan-Phase0-Details.md` unter §8

**0.3.2 Relevante Dateien kopieren**
- Aus `/tmp/vinarium-boilerplate/` nach `vinarium/`:
  - `package.json`, `package-lock.json`
  - `vite.config.js` (NICHT `.ts` — Boilerplate nutzt `.js`)
  - `tsconfig.json`
  - `src/` (Vue-Entrypoint + App.vue)
  - `templates/main.php`
  - `appinfo/routes.php`
  - `lib/` (komplett — enthält Boilerplate-`Application.php` + `PageController`)
  - `composer.json`
  - `img/app.svg` NUR übernehmen falls in Boilerplate vorhanden und das vorhandene `img/app.svg` überschrieben werden soll — sonst das alte generische Icon behalten bis Phase 6
- Acceptance: Dateien liegen im vinarium-Root, Git sieht „new files"

**0.3.3 `.gitignore` mergen**
- `dist/` und `node_modules/` müssen ignoriert bleiben; `js/` + `css/` müssen committed werden (Build-Output ist Pflicht für App-Store)
- Acceptance: `git check-ignore dist/ node_modules/` → positiv; `js/` + `css/` NICHT ignoriert

---

### Step 0.4: Namespace und App-ID ersetzen

**Subagent:** typescript-pro
**Parallelisierung:** Parallel mit 0.5

#### Subtasks

**0.4.1 App-ID ersetzen (`todo_boilerplate` → `vinarium`)**
- Betroffene Dateien auffinden:
  ```bash
  grep -rln 'todo_boilerplate\|todoboilerplate' --include='*.php' --include='*.ts' \
    --include='*.vue' --include='*.json' --include='*.xml' --include='*.js' .
  ```
- Acceptance: String `todo_boilerplate` kommt 0-mal vor nach Replace; `vinarium` überall konsistent

**0.4.2 PHP-Namespace ersetzen (`TodoBoilerplate` → `Vinarium`)**
- Replace in `lib/AppInfo/Application.php`, `lib/Controller/PageController.php`, ggf. weitere
- Namespace muss `OCA\Vinarium\*` sein
- Acceptance: `composer dump-autoload` läuft fehlerfrei; PHPStan/`php -l` auf allen Dateien grün

**0.4.3 `package.json` aktualisieren**
- `name` → `vinarium`
- `version` → `0.1.0` (MVP-Ziel)
- `description` + `author` + `license` setzen (`AGPL-3.0-or-later`)
- `repository.url` → `https://github.com/cpcMomentum/vinarium`
- Acceptance: `npm pkg fix` meldet nichts

**0.4.4 Template-Bezug korrigieren**
- In `templates/main.php`: App-ID und `script()`/`style()`-Includes zeigen auf `vinarium`
- Acceptance: Template enthält keine Boilerplate-Referenzen

---

### Step 0.5: `info.xml` zusammenführen

**Subagent:** nextcloud-spezialist
**Parallelisierung:** Parallel mit 0.4

#### Subtasks

**0.5.1 Existierende `appinfo/info.xml` öffnen**
- Quelle: aktuelle Vinarium-info.xml (in `appinfo/` nicht verschoben in 0.2)
- Acceptance: Enthält `<id>vinarium</id>`, `<name>`, `<summary>`, `<author>`, `<license>agpl</license>`

**0.5.2 Felder gegen Boilerplate-Muster spiegeln**
- `<dependencies>`:
  - `<nextcloud min-version="32" max-version="33"/>`
  - `<php min-version="8.2"/>`
- `<types>` falls nötig leer lassen
- `<namespace>Vinarium</namespace>`
- `<bugs>https://github.com/cpcMomentum/vinarium/issues</bugs>`
- Acceptance: `occ integrity:check-app vinarium` bemängelt keine Felder

**0.5.3 Version synchron halten**
- `<version>0.1.0</version>` in `info.xml`, `package.json`, `composer.json` identisch
- Acceptance: `grep -E '"?version"?' appinfo/info.xml package.json composer.json` zeigt 3× `0.1.0`

---

### Step 0.6: Dependencies installieren

**Subagent:** devops-engineer
**Parallelisierung:** Nein

#### Subtasks

**0.6.1 Composer-Install**
- `composer install --no-dev` + `composer install` (inkl. dev)
- Acceptance: `vendor/` erzeugt, kein Error-Exit

**0.6.2 npm-Install (Boilerplate-Baseline)**
- `npm install`
- Acceptance: `node_modules/` erzeugt, `package-lock.json` konsistent

**0.6.3 Extras nachinstallieren**
```bash
npm install --save pinia@^2 vue-router@^4
npm install --save-dev vitest@^1 @vue/test-utils@^2 happy-dom \
  @nextcloud/eslint-config @types/node
```
- `vue-draggable-plus` erst in Phase 4 (bewusst entkoppelt, reduziert Lockfile-Churn)
- Acceptance: `npm ls pinia vue-router vitest` zeigt alle drei

**0.6.4 `@nextcloud/vue` auf 9.5.x pinnen**
- In `package.json`: `"@nextcloud/vue": "^9.5.0"` setzen, `npm install` erneut
- Acceptance: `npm ls @nextcloud/vue` zeigt 9.5.x

---

### Step 0.7: Build

**Subagent:** devops-engineer
**Parallelisierung:** Nein

#### Subtasks

**0.7.1 Erstbuild**
- `npm run build`
- Acceptance: `js/main.js` + `css/main.css` (oder analoge Dateinamen nach Boilerplate-Schema) existieren, Größe > 0

**0.7.2 TypeScript strict prüfen**
- `npx tsc --noEmit`
- Acceptance: 0 Errors (Boilerplate sollte clean sein)

**0.7.3 Build-Warnings sichten**
- Warnings protokollieren, keine blocken Release; Chunk-Size > 500 kB nur als Hinweis

---

### Step 0.8: Deploy lokal

**Subagent:** nextcloud-spezialist
**Parallelisierung:** Nein

#### Subtasks

**0.8.1 Docker-Pfad prüfen**
- Acceptance: OrbStack-Container `nextcloud-dev` läuft (`docker ps | grep nextcloud-dev`)
- Acceptance: Alter `vinarium/` Ordner ist aus `/var/www/html/apps/` UND `/var/www/html/custom_apps/` entfernt (Memory `feedback_docker_apps_path.md` beachten)

**0.8.2 Deploy nach custom_apps**
- `docker cp ./vinarium nextcloud-dev:/var/www/html/custom_apps/` (oder `deploy-nc-app.sh vinarium` falls existent)
- Acceptance: Ordner vorhanden, Besitzer `www-data`

**0.8.3 App aktivieren**
- `docker exec -u www-data nextcloud-dev php occ app:enable vinarium`
- Acceptance: Exit 0, keine Stack-Traces im `data/nextcloud.log`

**0.8.4 Browser-Test**
- `http://localhost:8080/apps/vinarium/` im Browser öffnen
- Acceptance: Willkommens-View rendert (Boilerplate-Default-Screen), Browser-Console zeigt keine JS-Errors, Network-Tab zeigt `200` für JS/CSS-Assets

---

### Step 0.9: Smoke-Test GREEN

**Subagent:** php-entwickler
**Parallelisierung:** Nein

#### Subtasks

**0.9.1 Test ausführen**
- `docker exec nextcloud-dev ./vendor/bin/phpunit tests/unit/AppFrameworkTest.php` (oder lokal via Docker-exec gegen Container)
- Acceptance: GREEN — `Application::register()` läuft fehlerfrei, `PageController` aus Container auflösbar

**0.9.2 Pre-Commit-Hook verifizieren**
- Testweise `OC_App::` in temporäre Datei schreiben, `git commit` versuchen
- Acceptance: Hook blockt mit Exit ≠ 0; Datei wieder entfernen

---

### Step 0.10: Git-Commit + PR

**Subagent:** git-workflow-manager
**Parallelisierung:** Nein

#### Subtasks

**0.10.1 Feature-Branch anlegen**
- `git checkout -b feat/phase0-skeleton-swap` (ausgehend von `develop`)
- Acceptance: `git branch --show-current` = `feat/phase0-skeleton-swap`

**0.10.2 Staged Commit**
- Conventional Commit: `feat: replace initial skeleton with cvorwerk vue3 boilerplate`
- Commit-Body: Commit-Hash der Boilerplate + Liste der Extras (pinia, vue-router, vitest)
- Acceptance: `git log -1 --stat` zeigt sinnvollen Diff; kein `.env`, kein `node_modules/`, kein `dist/` im Commit

**0.10.3 Push + PR-Draft**
- `git push -u origin feat/phase0-skeleton-swap`
- `gh pr create --draft --base develop --title "feat: Phase 0 Skeleton Swap" --body "Closes Phase 0 of plan.md. See docs/Plan-Phase0-Details.md"`
- Acceptance: PR-URL verfügbar, Status = Draft

---

### Step 0.11: ~~NC-Signing-Zertifikat~~ — VERSCHOBEN NACH PHASE 6

**Entscheidung 2026-04-15:** Für MVP-Entwicklung auf lokaler NC-Instanz (OrbStack) nicht nötig — `occ app:enable` läuft ohne Store-Signatur. Zertifikat erst für App-Store-Upload in Phase 6 relevant.

**Verfahren (dokumentiert für Phase 6):**
1. `openssl genrsa -out ~/.nextcloud/certificates/vinarium.key 2048`
2. `openssl req -new -key ~/.nextcloud/certificates/vinarium.key -out ~/.nextcloud/certificates/vinarium.csr -subj "/CN=vinarium"`
3. PR gegen <https://github.com/nextcloud/app-certificate-requests> mit Pfad `vinarium/vinarium.csr`
4. Nach Merge (1–2 Tage): `vinarium.crt` downloaden → `~/.nextcloud/certificates/`

Quelle: `nextcloud-app-dev-guide.md` §10.1 / `nextcloud-app-summary.md` §Code Signing.

---

## 5. Datenmodelle & APIs

### 5.1 Schnittstellen (Phase 0)

Keine DB-Entities, keine neuen Routes. Vorhandene Boilerplate-Route:

```php
// appinfo/routes.php
['name' => 'page#index', 'url' => '/', 'verb' => 'GET']
```

Daraus folgt: `GET /apps/vinarium/` → `PageController::index()` → Rendert `main.php` Template.

### 5.2 API Endpoints

Keine neuen Endpoints in Phase 0. Alle Endpoints aus `plan.md` §3.4 werden in Phase 1+ implementiert.

---

## 6. Risiken & Testing

### 6.1 Top 3 Risks

| Risk | Impact | Mitigation |
|---|---|---|
| ⚠️ Signing-Zertifikat-PR-Merge dauert 1–2 Tage | Verzögert Phase-6-Release | Zertifikat zu Beginn von Phase 6 beantragen (Verfahren in §4 Step 0.11 dokumentiert) |
| ⚠️ Boilerplate-Integration erzeugt Namespace-Reste (übersehene Strings) | App-enable crasht | Nach 0.4 `grep -rln 'todo_boilerplate\|TodoBoilerplate'` = 0 erzwingen |
| ⚠️ NC lädt altes Vinarium aus `apps/` statt `custom_apps/` | Build unsichtbar | In 0.8.1 explizit beide Pfade bereinigen (Memory `feedback_docker_apps_path.md`) |

### 6.2 Testing-Strategie

**Manual Tests (Phase 0):** App-enable ohne Fehler, Willkommens-View rendert, JS-Console clean, Pre-Commit-Hook blockt OC_*.
**Automated (Phase 0):** 1 PHPUnit-Smoke-Test (AppFrameworkTest).
**Automated (später):** Vitest Unit ab Phase 1, Component-Tests ab Phase 2, E2E manuell bis Release.

---

## 7. Acceptance Criteria

- [ ] `archive/initial-skeleton-20260415/` enthält vollständiges Vue-2-Skelett, ist committed
- [ ] `grep -rln 'todo_boilerplate\|TodoBoilerplate'` → 0 Treffer im Repo (außer `archive/`)
- [ ] `npm run build` + `composer install` grün, `js/` + `css/` committed
- [ ] `occ app:enable vinarium` auf NC 33.0.2 ohne Fehler, Willkommens-View im Browser sichtbar
- [ ] `tests/unit/AppFrameworkTest.php` PHPUnit grün
- [ ] PR `feat/phase0-skeleton-swap` gegen `develop` existiert
- [ ] NC-Signing-Zertifikat beantragt, Ticket-ID dokumentiert

---

## 8. Referenzen & Notizen

- **Boilerplate-Quelle:** `https://github.com/cvorwerk/nextcloud-vue3-boilerplate`, default_branch `main`, HEAD `88158b5e3d9210af2481afafe2c3d7baf553eb78` (Initial Commit, 2025-12-24 14:04:56 +0100 — das Repo hat nur diesen einen Commit).
- **Boilerplate-Abweichungen vom Plan entdeckt in 0.3:**
  - Boilerplate hat keine `lib/AppInfo/Application.php` → wird in 0.4 frisch geschrieben (NC-30+ `IBootstrap`)
  - Template heißt `templates/index.php`, nicht `main.php` (belassen)
  - Task-Example-Clutter (`TaskApiController`, `Db/`, `Service/`, `Migration/`) wurde beim Kopieren AUSGELASSEN — Vinarium-Domain nutzt eigene Entities ab Phase 1
  - `composer.json` NICHT kopiert (unseres behält `phpunit/phpunit` dev-dep aus 0.1)
  - `info.xml` NICHT kopiert (Merge in 0.5)
  - `img/app.svg` NICHT kopiert (existierendes bleibt bis Phase 6)
- **Memory-Referenzen:** `feedback_docker_apps_path.md`, `feedback_immer_vollstaendig_deployen.md`, `feedback_ocp_only_enforced.md`, `reference_app_store_integrity_fix.md`.
- **Plan-Quelle:** `docs/plan.md` §5 Phase 0 (Zeilen 168-200).
- **Design-Quelle:** `docs/design-vinarium-mvp.md`.

# Entwicklungsphase (Development)

In dieser Phase wird mithilfe einer KI auf Basis des technischen Plans mit der konkreten Entwicklung begonnen.

> **Nächste Phase:** `35-validation.md` (Validierung)

---

## Übersicht: Entwicklungs-Subphasen

```
┌─────────────────────────────────────────────────────────────────────┐
│                     ENTWICKLUNGSPHASE                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  1. EINRICHTUNG                                                     │
│     ├── Agenten auswählen                                          │
│     ├── Commands/Hooks anpassen                                     │
│     ├── claude.md erstellen                                         │
│     └── Repository einrichten                                       │
│                                                                     │
│  2. PHASEN DETAILLIEREN                                            │
│     └── /ai-first-dev:prepare-phase [N] → Plan-PhaseN-Details.md                │
│                                                                     │
│  3. CODING & TESTING (PIV-Loop pro Feature)                        │
│                                                                     │
│     PIV-Loop:                                                      │
│     ┌──────────────────────────────────────────────────┐           │
│     │  /ai-first-dev:prime → Coding → /ai-first-dev:validate        │           │
│     │                         → /ai-first-dev:review → Commit        │           │
│     └──────────────────────────────────────────────────┘           │
│                                                                     │
│     Read-only Variante (mehr Kontrolle):                           │
│     ┌──────────────────────────────────────────────────┐           │
│     │  /ai-first-dev:validate --check-only                           │           │
│     │  /ai-first-dev:review --manual → manuell fixen → re-review     │           │
│     └──────────────────────────────────────────────────┘           │
│                                                                     │
│  4. PHASE ABSCHLIESSEN                                             │
│     └── /ai-first-dev:execution-report [N] → Dokumentation                      │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 1. Einrichtung

### 1.1 Auswahl der Claude-Code Agenten

Verfuegbare Agenten aus dem Plugin:

| Kategorie | Agenten | Wann einsetzen (basierend auf techstack.md) |
|-----------|---------|----------------------------------------------|
| **Frontend** | nextjs-developer | Wenn Next.js/React im Stack |
| **Frontend** | typescript-pro | Wenn TypeScript im Stack |
| **Backend** | python-pro | Wenn Python im Stack |
| **Backend** | api-designer | Bei REST/GraphQL API (alle Stacks) |
| **Backend** | ai-engineer | Bei AI/ML Integration |
| **Infrastruktur** | devops-engineer, deployment-engineer | Docker, CI/CD (alle Stacks) |
| **Qualitaet** | code-reviewer, security-auditor | Reviews, Security (alle Stacks) |
| **Testing** | unit-test-automator, test-automator | Tests (alle Stacks) |
| **Datenbank** | postgres-pro | Wenn PostgreSQL im Stack |
| **DevEx** | git-workflow-manager, documentation-engineer | Git, Docs (alle Stacks) |
| **Research** | research-analyst, debugger | Recherche, Debugging (alle Stacks) |

> **Hinweis:** Nicht alle Agenten sind fuer jeden Stack relevant. Waehle Agenten basierend auf dem in `techstack.md` definierten Stack. Fuer Stacks ohne spezialisierten Agent (z.B. PHP, Go, Java) nutze die stack-agnostischen Agenten (code-reviewer, debugger, research-analyst).

### 1.2 Skills & Commands

Verfuegbare **Skills** (vom Plugin):

| Kategorie | Skill | Zweck | Schutz |
|-----------|-------|-------|--------|
| **Init** | `/ai-first-dev:setup` | Neues Projekt initialisieren | - |
| **Init** | `/ai-first-dev:adopt` | Bestehendes Projekt konfigurieren | - |
| **Dev** | `/ai-first-dev:scaffold [type]` | Dateien nach Standards scaffolden | - |
| **Dev** | `/ai-first-dev:api-sync` | OpenAPI + TS-Types synchronisieren | - |
| **Validation** | `/ai-first-dev:validate` | Validate + Auto-Fix (`--check-only` for read-only) | - |
| **Validation** | `/ai-first-dev:review` | Review + Auto-Fix (`--manual` for stop-and-report) | - |
| **Bugfix** | `/ai-first-dev:bugfix [issue]` | RCA + Fix (`--rca-only` for analysis only) | - |
| **Bugfix** | `/ai-first-dev:bugfix [issue]` | End-to-End: RCA + Fix + Validate | - |
| **Security** | `/ai-first-dev:security-scan` | Trivy, npm/pip audit | read-only |
| **Security** | `/ai-first-dev:privacy-audit` | Datenschutz-Bestandsaufnahme | - |

Weitere **Skills** (Workflow-Schritte):

| Kategorie | Command | Zweck |
|-----------|---------|-------|
| **Planning** | `/ai-first-dev:prepare-phase [N]` | Phase-Details erstellen |
| **Planning** | `/ai-first-dev:prime [beschreibung]` | Kontext laden vor Coding |
| **Reporting** | `/ai-first-dev:execution-report [N]` | Phase-Abschlussbericht |
| **Reporting** | `/ai-first-dev:system-review` | Meta-Review nach Release |

**Plugin:** `/frontend-design` für produktionsreife UI-Komponenten

### 1.3 Claude.md erstellen

Erstelle projektspezifische `claude.md` basierend auf:
- `dev.md` als Prozess-Referenz
- `.claude/techstack.md` als Technologie-Referenz
- Projektspezifische Abweichungen dokumentieren

**Template:** Erstelle basierend auf `.claude/dev.md` und `.claude/techstack.md`

### 1.4 Repository einrichten

```bash
# Repository erstellen
gh repo create [project-name] --private --clone

# Basis-Dateien
touch README.md LICENSE.md .gitignore

# Dokumentation
mkdir docs
cp produktbeschreibung.md docs/
cp plan.md docs/

# Initial Commit
git add .
git commit -m "chore: initial project setup"
```

### 1.5 Branch Protection einrichten

Nach dem Initial Commit **PFLICHT**: Branch Protection konfigurieren.

```bash
# Development Branch erstellen
git checkout -b development
git push -u origin development

# Zurück zu main
git checkout main
```

**Branch Protection via GitHub CLI:**

```bash
REPO="[owner]/[project-name]"

# Main Branch Protection
gh api --method PUT "/repos/$REPO/branches/main/protection" \
  -f 'required_status_checks[strict]=true' \
  -f 'required_status_checks[contexts][]=lint' \
  -f 'required_status_checks[contexts][]=test' \
  -f 'required_status_checks[contexts][]=build' \
  -F 'required_pull_request_reviews[required_approving_review_count]=1' \
  -F 'required_pull_request_reviews[dismiss_stale_reviews]=true' \
  -F 'allow_force_pushes=false' \
  -F 'allow_deletions=false'

# Development Branch Protection
gh api --method PUT "/repos/$REPO/branches/development/protection" \
  -f 'required_status_checks[strict]=true' \
  -f 'required_status_checks[contexts][]=lint' \
  -f 'required_status_checks[contexts][]=test' \
  -f 'required_status_checks[contexts][]=build' \
  -F 'allow_force_pushes=false' \
  -F 'allow_deletions=false'
```

**Ergebnis:**
- `main`: Kein direkter Push, PR + 1 Review + CI erforderlich
- `development`: Kein direkter Push, PR + CI erforderlich

> **Vollständige Dokumentation:** `.claude/standards/github-branch-protection.md`

---

## 2. Phasen detaillieren

### /ai-first-dev:prepare-phase Command

Bevor eine Phase implementiert wird, detaillierte Spezifikation erstellen:

```bash
/ai-first-dev:prepare-phase 1
```

**Output:** `Plan-Phase1-Details.md` mit:
- Übersicht & Ziele
- Tech Stack (aktuelle Best Practices)
- Subagent-Zuweisung
- Schritt-für-Schritt Implementation
- Datenmodelle & APIs
- Risiken & Testing
- Akzeptanzkriterien

---

## 3. Coding & Testing: PIV-Loop

Der **PIV-Loop** (Prime → Implement → Validate) ist der Mikro-Zyklus für jedes Feature.

### 3.1 Prime: Kontext laden

```bash
/ai-first-dev:prime "User Registration Feature"
```

**Was passiert:**
1. Relevante Dateien werden identifiziert
2. Standards werden geladen
3. Kontext-Dokument wird für die Session erstellt
4. Nächste Schritte werden vorgeschlagen

### 3.2 Implement: Code schreiben

Mit geladenem Kontext die Implementierung durchführen:
- Nutze die vorgeschlagenen Agenten
- Folge den geladenen Standards
- Schreibe Tests parallel zum Code
- **Für Frontend-UI:** Nutze `/frontend-design` für produktionsreife Komponenten

### 3.3 Validate: Qualität sichern

```bash
/ai-first-dev:validate
```

`/ai-first-dev:validate` prüft UND fixt automatisch in einem Loop (max 3 Iterationen).

**Checks:** Linting, Type Check, Tests, Build, Security

**Read-only Variante (mehr Kontrolle):**

```bash
/ai-first-dev:validate --check-only
```

`/ai-first-dev:validate --check-only` kann nur prüfen, nie Code ändern.
**Bei Fehlern:** Manuell fixen, dann erneut `/ai-first-dev:validate --check-only`.

### 3.3b Browser-Testing: UI interaktiv verifizieren (Optional)

Nach erfolgreicher Validierung kann Claude die UI über den **Playwright MCP-Server** interaktiv testen:

```
Voraussetzung: App läuft lokal (docker-compose up / npm run dev)
```

**Was Claude testen kann:**
- Buttons klicken und prüfen ob die erwartete Aktion passiert
- Formulare ausfüllen und Submit verifizieren
- Navigation zwischen Seiten testen
- Login/Logout Flows durchspielen
- Fehlermeldungen bei ungültiger Eingabe prüfen
- Responsive Verhalten über Accessibility-Tree validieren

**Wann sinnvoll:**
- Nach Frontend-Implementierung mit User-Interaktion
- Bei komplexen Multi-Step Flows (Checkout, Onboarding, Wizards)
- Wenn `/ai-first-dev:validate` grün ist aber UI-Verhalten unklar

> **Konfiguration:** `playwright` Plugin wird bei `/ai-first-dev:setup` automatisch installiert.
> **Technologie:** Playwright Plugin vom Anthropic Marketplace

### 3.4 Code Review

```bash
/ai-first-dev:review
```

`/ai-first-dev:review` reviewt, fixt CRITICAL/HIGH automatisch, und re-reviewt (max 3 Zyklen).

**Read-only Variante (mehr Kontrolle):**

```bash
/ai-first-dev:review --manual
```

`/ai-first-dev:review --manual` ist ein read-only Review - er kann nur reviewen, nie Code ändern.

**Bei NEEDS_FIX:** Manuell fixen, dann erneut `/ai-first-dev:review --manual --scope=changed`.

---

## 4. Phase abschließen

### Execution Report erstellen

```bash
/ai-first-dev:execution-report 1
```

**Dokumentiert:**
- Implementierte Features
- Abweichungen vom Plan
- Offene Punkte
- Lessons Learned

**Output:** `reports/ai-first-dev:execution-report-phase1.md`

---

## PIV-Loop Beispiele

### Standard: Automatisch (PIV-Loop)

```
┌─────────────────────────────────────────────────────────────┐
│ Feature: User Authentication                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. /ai-first-dev:prime "User Authentication mit JWT"       │
│     → Kontext geladen                                       │
│                                                             │
│  2. Implement                                               │
│     → Login/Register Endpoints, JWT, Password Hashing       │
│                                                             │
│  3. /ai-first-dev:validate  (auto-fix loop)                 │
│     🔧 Iteration 1: 3 Lint-Fixes auto-applied              │
│     🔧 Iteration 2: 1 Type-Fix                             │
│     ✅ Iteration 3: Alles grün                              │
│                                                             │
│  4. /ai-first-dev:review  (auto-fix loop)                   │
│     🔧 Zyklus 1: CR-001 (HIGH) auto-gefixt                 │
│     ✅ Zyklus 2: PASSED                                     │
│                                                             │
│  ✅ Feature Complete → Commit                               │
└─────────────────────────────────────────────────────────────┘
```

### Read-only Variante (mehr Kontrolle)

```
┌─────────────────────────────────────────────────────────────┐
│ Feature: User Authentication                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. /ai-first-dev:prime "User Authentication mit JWT"       │
│     → Kontext: auth-routes.ts, user-model.ts, standards     │
│                                                             │
│  2. Implement                                               │
│     → Login/Register Endpoints, JWT, Password Hashing       │
│                                                             │
│  3. /ai-first-dev:validate --check-only  (read-only)        │
│     ✅ Lint, Types, Tests, Build passed                     │
│                                                             │
│  4. /ai-first-dev:review --manual  (read-only)              │
│     ⚠️ CR-001: Missing rate limiting (HIGH)                 │
│     ⚠️ CR-002: Password policy nicht dokumentiert (MEDIUM)  │
│                                                             │
│  5. Manuell fixen: Rate limiting hinzugefügt                │
│                                                             │
│  6. /ai-first-dev:review --manual --scope=changed           │
│     ✅ PASSED                                               │
│                                                             │
│  ✅ Feature Complete → Commit                               │
└─────────────────────────────────────────────────────────────┘
```

---

## Best Practices

### Do's

- ✅ Immer `/ai-first-dev:prime` vor neuem Feature
- ✅ `/frontend-design` für UI-Komponenten nutzen
- ✅ Browser-Testing nach Frontend-Features mit User-Interaktion
- ✅ Kleine, fokussierte Commits
- ✅ Tests parallel zum Code schreiben
- ✅ `/ai-first-dev:validate` vor jedem Push
- ✅ `/ai-first-dev:review` vor Merge
- ✅ `/compact` nach Plan-Erstellung und Phasen-Übergängen
- ✅ `/ai-first-dev:validate` + `/ai-first-dev:review` (auto-fix) für Routine-Code
- ✅ `/ai-first-dev:validate --check-only` + `/ai-first-dev:review --manual` für kritische Features

### Don'ts

- ❌ Ohne Kontext coden
- ❌ Validierung überspringen
- ❌ Review-Issues ignorieren
- ❌ Große monolithische Commits
- ❌ Context-Clearing nach großen Skills/Commands vergessen

---

## Referenzen

- `.claude/workflow/35-validation.md` - Detaillierter Validierungs-Workflow
- `.claude/standards/reference/` - Best-Practice Patterns
- `.claude/dev.md` - Entwicklungs-Standards
- `.claude/techstack.md` - Technologie-Referenz

# Validierungsphase (Validation)

Diese Phase beschreibt den systematischen Validierungsprozess für Code-Qualität.

> **Vorherige Phase:** `30-development.md` (Entwicklung)
> **Naechste Phase:** Release & Deployment

---

## Übersicht

```
┌─────────────────────────────────────────────────────────────────────┐
│                    VALIDIERUNGS-PIPELINE                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐              │
│  │   LINTING   │──▶│ TYPE CHECK  │──▶│   TESTS     │              │
│  └─────────────┘   └─────────────┘   └─────────────┘              │
│         │                │                  │                      │
│         ▼                ▼                  ▼                      │
│    ESLint/Ruff      TypeScript/mypy    Jest/pytest                │
│                                                                     │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐              │
│  │    BUILD    │──▶│CODE REVIEW  │──▶│  SECURITY   │              │
│  └─────────────┘   └─────────────┘   └─────────────┘              │
│         │                │                  │                      │
│         ▼                ▼                  ▼                      │
│   Production Build   Manuelle Prüfung  /ai-first-dev:security-scan             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 1. Automatische Validierung

### 1.1 /ai-first-dev:validate

```bash
/ai-first-dev:validate               # Alle Checks mit Auto-Fix (Standard)
/ai-first-dev:validate --quick       # Nur Lint + Types
/ai-first-dev:validate --full        # Alle Checks inkl. Build + Security
/ai-first-dev:validate --tests       # Nur Tests
/ai-first-dev:validate --check-only  # Nur prüfen, nichts ändern (read-only)
```

> `/ai-first-dev:validate` fixt Lint-Errors automatisch, analysiert Type-Errors, und fixt Tests wenn Root Cause klar ist. Max 3 Iterationen.
> Mit `--check-only` wird nur geprüft, nichts geändert.

### 1.2 Pipeline-Schritte

| Schritt | Tool | Fehler-Handling |
|---------|------|-----------------|
| **1. Linting** | ESLint / Ruff | Auto-fixable → Fix, sonst Stop |
| **2. Type Check** | tsc / mypy | Stop bei Fehlern |
| **3. Tests** | Jest / pytest | Stop bei Failures |
| **4. Build** | next build / docker | Stop bei Fehlern |

### 1.3 Output-Beispiel

```
═══════════════════════════════════════════════════════════════
                    📊 VALIDATION REPORT
═══════════════════════════════════════════════════════════════

📋 Validierter Scope: src/**/*.ts, src/**/*.tsx

┌─────────────────────┬──────────┬─────────────┐
│ Check               │ Status   │ Details     │
├─────────────────────┼──────────┼─────────────┤
│ Linting             │ ✅       │ 0 errors    │
│ Type Check          │ ✅       │ 0 errors    │
│ Tests               │ ✅       │ 24/24 passed│
│ Build               │ ✅       │ Success     │
└─────────────────────┴──────────┴─────────────┘

✅ VALIDATION PASSED

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

➡️ Nächster Schritt: /ai-first-dev:review

═══════════════════════════════════════════════════════════════
```

---

## 2. Code Review

### /ai-first-dev:review

| Modus | Zweck | Verhalten |
|-------|-------|-----------|
| `/ai-first-dev:review` | Review + Auto-Fix + Re-Review Loop (max 3x) | **auto-fix** (Standard) |
| `/ai-first-dev:review --manual` | Nur reviewen, nichts ändern | **read-only** (stop-and-report) |

> `/ai-first-dev:review` fixt CRITICAL/HIGH Issues automatisch und re-reviewt. Mit `--manual` wird nur geprüft, nichts geändert.

### 2.1 Review-Kategorien

| Kategorie | Priorität | Beschreibung |
|-----------|-----------|--------------|
| **🔴 Critical** | Blocker | Security, Data Loss, Crashes |
| **🟠 Major** | High | Performance, Logic Errors |
| **🟡 Minor** | Medium | Code Quality, Readability |
| **🔵 Suggestion** | Low | Best Practices, Optimierungen |

### 2.2 Review-Scope

```bash
# Standard: Geänderte Dateien
/ai-first-dev:review

# Spezifischer Scope
/ai-first-dev:review --scope=src/auth/
/ai-first-dev:review --scope=changed  # Git diff

# Read-only
/ai-first-dev:review --manual
```

### 2.3 Review-Checkliste

**Automatisch geprüft:**
- [ ] Code folgt Project-Standards
- [ ] Keine Security-Vulnerabilities
- [ ] Tests für neue Funktionalität
- [ ] Error Handling vorhanden
- [ ] Keine TODO/FIXME ohne Issue

**Manuell zu prüfen:**
- [ ] Business Logic korrekt
- [ ] Edge Cases behandelt
- [ ] Performance akzeptabel
- [ ] Dokumentation ausreichend

### 2.4 Issue-Behebung

Issues werden automatisch durch `/ai-first-dev:review` behoben (CRITICAL/HIGH). Bei `--manual` Modus manuell fixen und erneut reviewen:

```bash
# Re-Review nach manuellem Fix
/ai-first-dev:review --manual --scope=changed
```

---

## 3. Security Scan (/ai-first-dev:security-scan)

### 3.1 Scan-Bereiche

| Bereich | Tool/Methode | Prüft |
|---------|--------------|-------|
| **Dependencies** | npm audit / pip-audit | Bekannte CVEs |
| **Code** | Statische Analyse | OWASP Top 10 |
| **Secrets** | Pattern Matching | Hardcoded Credentials |
| **Config** | Best Practices | Fehlkonfigurationen |

### 3.2 Command

```bash
/ai-first-dev:security-scan

# Nur Dependencies
/ai-first-dev:security-scan --deps-only

# Mit Auto-Fix
/ai-first-dev:security-scan --fix
```

### 3.3 Schweregrade

| Severity | Aktion | SLA |
|----------|--------|-----|
| **Critical** | Sofort beheben | Vor Merge |
| **High** | Zeitnah beheben | 24h |
| **Medium** | Planen | Sprint |
| **Low** | Dokumentieren | Backlog |

---

## 4. Privacy Audit (/ai-first-dev:privacy-audit)

### 4.1 Prüfbereiche

- **Datenerhebung** - Welche Daten werden gesammelt?
- **Datenverarbeitung** - Wie werden sie verarbeitet?
- **Datenspeicherung** - Wo und wie lange?
- **Datenweitergabe** - An wen?
- **Nutzerrechte** - Auskunft, Löschung, Export

### 4.2 Command

```bash
/ai-first-dev:privacy-audit

# Spezifischer Bereich
/ai-first-dev:privacy-audit --scope=user-registration
```

---

## 5. Validation Workflow

### 5.1 Vor jedem Commit

```bash
/ai-first-dev:validate --quick
```

### 5.2 Vor jedem Push

```bash
/ai-first-dev:validate
```

### 5.3 Vor jedem Merge/PR

```bash
/ai-first-dev:validate
/ai-first-dev:review
/ai-first-dev:security-scan
```

### 5.4 Vor Release

```bash
/ai-first-dev:validate --full
/ai-first-dev:review --scope=all
/ai-first-dev:security-scan
/ai-first-dev:privacy-audit
/ai-first-dev:deps-audit
/ai-first-dev:env-check
```

---

## 6. CI/CD Integration

### 6.1 GitHub Actions

```yaml
# .github/workflows/ai-first-dev:validate.yml
name: Validate

on: [push, pull_request]

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Setup Node
        uses: actions/setup-node@v6
        with:
          node-version: '22'

      - name: Install
        run: npm ci

      - name: Lint
        run: npm run lint

      - name: Type Check
        run: npm run type-check

      - name: Test
        run: npm test

      - name: Build
        run: npm run build
```

### 6.2 Pre-commit Hook

```bash
# .husky/pre-commit
#!/bin/sh
npm run lint
npm run type-check
npm test
```

---

## 7. Report-Templates

### 7.1 Validation Report

Siehe `.claude/templates/reports/validation-report.md`

### 7.2 Code Review Report

Siehe `.claude/templates/reports/code-review-report.md`

---

## Checkliste: Validation Complete

Vor dem Merge in `main`:

- [ ] `/ai-first-dev:validate` ✅
- [ ] `/ai-first-dev:review` - PASSED
- [ ] `/ai-first-dev:security-scan` - Keine Critical/High
- [ ] `/ai-first-dev:deps-audit` - Versions gepinnt, keine CVEs
- [ ] Tests Coverage ≥ 80%
- [ ] Build erfolgreich
- [ ] Dokumentation aktuell

---

## Referenzen

- `30-development.md` - PIV-Loop Integration
- `.claude/standards/code-style-linting.md` - Linting Config
- `.claude/standards/security.md` - Security Standards
- `.claude/standards/reference/testing-patterns.md` - Test Patterns

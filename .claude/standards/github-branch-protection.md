# GitHub Branch Protection & Rulesets

Diese Standards definieren einheitliche Branch Protection Rules für alle Projekte.

## 1. Übersicht

### Branch-Strategie

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           BRANCH PROTECTION                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  main (PROTECTED)                                                            │
│  ════════════════                                                            │
│  │ ✗ Kein direkter Push                                                     │
│  │ ✓ Nur via PR von development                                             │
│  │ ✓ Require: CI passed + Review                                            │
│  │                                                                           │
│  └──── development (PROTECTED)                                               │
│        ═══════════════════════                                               │
│        │ ✗ Kein direkter Push                                               │
│        │ ✓ Nur via PR von Feature-Branches                                  │
│        │ ✓ Require: CI passed                                               │
│        │                                                                     │
│        ├──── feature/* (Entwicklung)                                        │
│        ├──── bugfix/* (Bug-Fixes)                                           │
│        ├──── release/* (Releases → direkt nach main)                        │
│        └──── hotfix/* (Kritische Fixes → direkt nach main erlaubt)          │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Merge-Flow

| Von | Nach | Erlaubt via |
|-----|------|-------------|
| `feature/*` | `development` | PR (CI muss passieren) |
| `bugfix/*` | `development` | PR (CI muss passieren) |
| `development` | `main` | PR (CI + Review muss passieren) |
| `release/*` | `main` | PR (CI + Review) |
| `hotfix/*` | `main` | PR (CI + Review, schnelle Genehmigung) |
| `hotfix/*` | `development` | PR oder direkter Merge nach main-Release |

---

## 2. GitHub Rulesets (Empfohlen - Neu)

GitHub Rulesets sind die moderne Alternative zu Branch Protection Rules und bieten mehr Flexibilität.

### 2.1 Ruleset für `main` Branch

```json
{
  "name": "main-protection",
  "target": "branch",
  "enforcement": "active",
  "conditions": {
    "ref_name": {
      "include": ["refs/heads/main"],
      "exclude": []
    }
  },
  "rules": [
    {
      "type": "pull_request",
      "parameters": {
        "required_approving_review_count": 1,
        "dismiss_stale_reviews_on_push": true,
        "require_code_owner_review": false,
        "require_last_push_approval": true,
        "required_review_thread_resolution": true
      }
    },
    {
      "type": "required_status_checks",
      "parameters": {
        "strict_required_status_checks_policy": true,
        "required_status_checks": [
          { "context": "lint" },
          { "context": "test" },
          { "context": "build" },
          { "context": "security" }
        ]
      }
    },
    {
      "type": "non_fast_forward"
    },
    {
      "type": "deletion"
    }
  ],
  "bypass_actors": []
}
```

### 2.2 Ruleset für `development` Branch

```json
{
  "name": "development-protection",
  "target": "branch",
  "enforcement": "active",
  "conditions": {
    "ref_name": {
      "include": ["refs/heads/development"],
      "exclude": []
    }
  },
  "rules": [
    {
      "type": "pull_request",
      "parameters": {
        "required_approving_review_count": 0,
        "dismiss_stale_reviews_on_push": true,
        "require_code_owner_review": false,
        "require_last_push_approval": false,
        "required_review_thread_resolution": false
      }
    },
    {
      "type": "required_status_checks",
      "parameters": {
        "strict_required_status_checks_policy": true,
        "required_status_checks": [
          { "context": "lint" },
          { "context": "test" },
          { "context": "build" }
        ]
      }
    },
    {
      "type": "non_fast_forward"
    },
    {
      "type": "deletion"
    }
  ],
  "bypass_actors": []
}
```

---

## 3. GitHub CLI Setup (Automatisierung)

### 3.1 Rulesets via GitHub CLI erstellen

```bash
#!/bin/bash
# setup-branch-protection.sh

REPO="${1:-$(gh repo view --json nameWithOwner -q '.nameWithOwner')}"

echo "🔒 Setting up branch protection for: $REPO"

# Main Branch Ruleset
gh api \
  --method POST \
  -H "Accept: application/vnd.github+json" \
  -H "X-GitHub-Api-Version: 2022-11-28" \
  "/repos/$REPO/rulesets" \
  -f name='main-protection' \
  -f target='branch' \
  -f enforcement='active' \
  -f 'conditions[ref_name][include][]=refs/heads/main' \
  -f 'rules[][type]=pull_request' \
  -F 'rules[0][parameters][required_approving_review_count]=1' \
  -F 'rules[0][parameters][dismiss_stale_reviews_on_push]=true' \
  -F 'rules[0][parameters][require_last_push_approval]=true' \
  -f 'rules[][type]=required_status_checks' \
  -F 'rules[1][parameters][strict_required_status_checks_policy]=true' \
  -f 'rules[1][parameters][required_status_checks][][context]=lint' \
  -f 'rules[1][parameters][required_status_checks][][context]=test' \
  -f 'rules[1][parameters][required_status_checks][][context]=build' \
  -f 'rules[1][parameters][required_status_checks][][context]=security' \
  -f 'rules[][type]=non_fast_forward' \
  -f 'rules[][type]=deletion'

echo "✅ main branch ruleset created"

# Development Branch Ruleset
gh api \
  --method POST \
  -H "Accept: application/vnd.github+json" \
  -H "X-GitHub-Api-Version: 2022-11-28" \
  "/repos/$REPO/rulesets" \
  -f name='development-protection' \
  -f target='branch' \
  -f enforcement='active' \
  -f 'conditions[ref_name][include][]=refs/heads/development' \
  -f 'rules[][type]=pull_request' \
  -F 'rules[0][parameters][required_approving_review_count]=0' \
  -F 'rules[0][parameters][dismiss_stale_reviews_on_push]=true' \
  -f 'rules[][type]=required_status_checks' \
  -F 'rules[1][parameters][strict_required_status_checks_policy]=true' \
  -f 'rules[1][parameters][required_status_checks][][context]=lint' \
  -f 'rules[1][parameters][required_status_checks][][context]=test' \
  -f 'rules[1][parameters][required_status_checks][][context]=build' \
  -f 'rules[][type]=non_fast_forward' \
  -f 'rules[][type]=deletion'

echo "✅ development branch ruleset created"

# Create development branch if not exists
git checkout -b development 2>/dev/null || git checkout development
git push -u origin development 2>/dev/null || true

echo "🎉 Branch protection setup complete!"
```

### 3.2 Vereinfachtes Setup Script

```bash
#!/bin/bash
# quick-setup-protection.sh
# Minimales Script für schnelles Setup

REPO="${1:-$(gh repo view --json nameWithOwner -q '.nameWithOwner')}"

# Erstelle development Branch falls nicht vorhanden
git checkout -b development 2>/dev/null && git push -u origin development || true

# Main Protection (klassische Branch Protection als Fallback)
gh api \
  --method PUT \
  "/repos/$REPO/branches/main/protection" \
  -f 'required_status_checks[strict]=true' \
  -f 'required_status_checks[contexts][]=lint' \
  -f 'required_status_checks[contexts][]=test' \
  -f 'required_status_checks[contexts][]=build' \
  -F 'enforce_admins=false' \
  -F 'required_pull_request_reviews[required_approving_review_count]=1' \
  -F 'required_pull_request_reviews[dismiss_stale_reviews]=true' \
  -f 'restrictions=null' \
  -F 'allow_force_pushes=false' \
  -F 'allow_deletions=false'

# Development Protection
gh api \
  --method PUT \
  "/repos/$REPO/branches/development/protection" \
  -f 'required_status_checks[strict]=true' \
  -f 'required_status_checks[contexts][]=lint' \
  -f 'required_status_checks[contexts][]=test' \
  -f 'required_status_checks[contexts][]=build' \
  -F 'enforce_admins=false' \
  -f 'required_pull_request_reviews=null' \
  -f 'restrictions=null' \
  -F 'allow_force_pushes=false' \
  -F 'allow_deletions=false'

echo "✅ Branch protection configured for $REPO"
```

---

## 4. PR Templates

### 4.1 Standard PR Template

Erstelle `.github/PULL_REQUEST_TEMPLATE.md`:

```markdown
## Beschreibung

<!-- Was wurde geändert und warum? -->

## Änderungstyp

- [ ] Feature (neue Funktionalität)
- [ ] Bugfix (Fehlerkorrektur)
- [ ] Hotfix (kritische Korrektur)
- [ ] Refactoring (keine funktionalen Änderungen)
- [ ] Dokumentation
- [ ] CI/CD / Infrastruktur

## Checklist

- [ ] Code folgt den Projekt-Standards (`/ai-first-dev:validate` bestanden)
- [ ] Tests wurden hinzugefügt/aktualisiert
- [ ] Dokumentation wurde aktualisiert (falls nötig)
- [ ] CHANGELOG.md wurde aktualisiert
- [ ] Keine Secrets im Code

## Verknüpfte Issues

<!-- Closes #123, Fixes #456 -->

## Screenshots (falls UI-Änderungen)

<!-- Screenshots hier einfügen -->

---

> 🤖 Diese PR wurde mit [Claude Code](https://claude.ai/code) erstellt
```

### 4.2 Feature PR Template

Erstelle `.github/PULL_REQUEST_TEMPLATE/feature.md`:

```markdown
## Feature: [NAME]

### Beschreibung
<!-- Was macht dieses Feature? -->

### User Story
Als [ROLLE] möchte ich [FUNKTION], damit [NUTZEN].

### Akzeptanzkriterien
- [ ] Kriterium 1
- [ ] Kriterium 2
- [ ] Kriterium 3

### Implementierungsdetails
<!-- Technische Details der Implementierung -->

### Tests
- [ ] Unit Tests hinzugefügt
- [ ] Integration Tests (falls nötig)

### Checklist
- [ ] `/ai-first-dev:validate` bestanden
- [ ] `/ai-first-dev:review` durchgeführt
- [ ] CHANGELOG.md aktualisiert
- [ ] Dokumentation aktualisiert

---

> Phase: [PHASE_NUMBER] | Task: [TASK_ID]
```

### 4.3 Hotfix PR Template

Erstelle `.github/PULL_REQUEST_TEMPLATE/hotfix.md`:

```markdown
## 🚨 HOTFIX: [KURZBESCHREIBUNG]

### Problem
<!-- Was ist das Problem? Wie wurde es entdeckt? -->

### Ursache
<!-- Root Cause des Problems -->

### Lösung
<!-- Wie wurde das Problem behoben? -->

### Betroffene Bereiche
- [ ] Frontend
- [ ] Backend
- [ ] Datenbank
- [ ] Infrastruktur

### Risiko-Bewertung
- **Auswirkung des Problems:** Hoch / Mittel / Niedrig
- **Risiko der Lösung:** Hoch / Mittel / Niedrig

### Tests
- [ ] Problem reproduziert
- [ ] Fix verifiziert
- [ ] Keine Regression

### Rollback-Plan
<!-- Wie kann die Änderung zurückgenommen werden? -->

---

> ⚠️ HOTFIX - Priorisierte Review erforderlich
```

---

## 5. Branch Naming Conventions

### 5.1 Naming Schema

```
<type>/<beschreibung>
<type>/<issue-id>-<beschreibung>
```

Issue-ID ist optional (empfohlen wenn ein GitHub Issue existiert).

| Typ | Verwendung | Beispiel |
|-----|------------|----------|
| `feature/` | Neue Funktionalität | `feature/new-ui-design` |
| `bugfix/` | Fehlerbehebung | `bugfix/87-login-token-expiry` |
| `release/` | Release-Branch | `release/v1.2.0` |
| `hotfix/` | Kritische Fixes | `hotfix/critical-security-patch` |
| `chore/` | Maintenance | `chore/update-dependencies` |
| `docs/` | Dokumentation | `docs/api-documentation` |
| `refactor/` | Code-Refactoring | `refactor/optimize-queries` |

### 5.2 Automatische Branch-Validierung

Erstelle `.github/workflows/branch-naming.yml`:

```yaml
name: Branch Naming

on:
  push:
    branches-ignore:
      - main
      - development

jobs:
  check-branch-name:
    runs-on: ubuntu-latest
    steps:
      - name: Check branch name
        run: |
          BRANCH_NAME="${GITHUB_REF#refs/heads/}"
          PATTERN="^(feature|bugfix|hotfix|release|chore|docs|refactor)\/[a-z0-9.-]+$"

          if [[ ! "$BRANCH_NAME" =~ $PATTERN ]]; then
            echo "❌ Invalid branch name: $BRANCH_NAME"
            echo ""
            echo "Branch names must follow the pattern:"
            echo "  <type>/<beschreibung>"
            echo "  <type>/<issue-id>-<beschreibung>"
            echo ""
            echo "Valid types: feature, bugfix, hotfix, release, chore, docs, refactor"
            echo "Examples: feature/new-ui-design, bugfix/87-login-error, release/v1.2.0"
            exit 1
          fi

          echo "✅ Valid branch name: $BRANCH_NAME"
```

---

## 6. CODEOWNERS

Erstelle `.github/CODEOWNERS`:

```
# Standard CODEOWNERS für AI-First Projekte

# Globale Owner (alle Dateien)
* @project-lead

# Frontend
/frontend/ @frontend-team
*.tsx @frontend-team
*.css @frontend-team

# Backend
/backend/ @backend-team
*.py @backend-team

# Infrastructure
/docker* @devops-team
/.github/ @devops-team
Dockerfile @devops-team

# Dokumentation
*.md @documentation-team
/docs/ @documentation-team

# Sicherheitskritische Dateien
.env* @security-team @project-lead
**/auth/** @security-team
**/security/** @security-team
```

---

## 7. GitHub Actions für PR Checks

### 7.1 PR Validation Workflow

Erstelle `.github/workflows/pr-validation.yml`:

```yaml
name: PR Validation

on:
  pull_request:
    branches: [main, development]

jobs:
  pr-checks:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Check PR title
        run: |
          PR_TITLE="${{ github.event.pull_request.title }}"
          # Conventional Commits Pattern
          PATTERN="^(feat|fix|docs|style|refactor|test|chore|perf|ci|build|revert)(\(.+\))?: .{1,72}$"

          if [[ ! "$PR_TITLE" =~ $PATTERN ]]; then
            echo "❌ PR title doesn't follow Conventional Commits"
            echo ""
            echo "Format: <type>(<scope>): <description>"
            echo "Example: feat(auth): add login functionality"
            exit 1
          fi

          echo "✅ PR title is valid"

      - name: Check PR description
        run: |
          PR_BODY="${{ github.event.pull_request.body }}"

          if [ -z "$PR_BODY" ] || [ ${#PR_BODY} -lt 50 ]; then
            echo "❌ PR description is too short or empty"
            echo "Please provide a meaningful description"
            exit 1
          fi

          echo "✅ PR description is valid"

      - name: Check for CHANGELOG update
        if: github.base_ref == 'main'
        run: |
          if git diff --name-only origin/${{ github.base_ref }}...HEAD | grep -q "CHANGELOG.md"; then
            echo "✅ CHANGELOG.md has been updated"
          else
            echo "⚠️ Warning: CHANGELOG.md has not been updated"
            echo "Consider updating the changelog for this release"
          fi
```

### 7.2 Auto-Label Workflow

Erstelle `.github/workflows/auto-label.yml`:

```yaml
name: Auto Label

on:
  pull_request:
    types: [opened, edited]

jobs:
  label:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/github-script@v7
        with:
          script: |
            const title = context.payload.pull_request.title.toLowerCase();
            const labels = [];

            // Type labels
            if (title.startsWith('feat')) labels.push('feature');
            if (title.startsWith('fix')) labels.push('bugfix');
            if (title.startsWith('docs')) labels.push('documentation');
            if (title.startsWith('refactor')) labels.push('refactoring');
            if (title.startsWith('test')) labels.push('testing');
            if (title.startsWith('chore')) labels.push('maintenance');

            // Size labels (based on changes)
            const { data: files } = await github.rest.pulls.listFiles({
              owner: context.repo.owner,
              repo: context.repo.repo,
              pull_number: context.payload.pull_request.number
            });

            const changes = files.reduce((sum, f) => sum + f.changes, 0);
            if (changes < 50) labels.push('size/S');
            else if (changes < 200) labels.push('size/M');
            else if (changes < 500) labels.push('size/L');
            else labels.push('size/XL');

            // Apply labels
            if (labels.length > 0) {
              await github.rest.issues.addLabels({
                owner: context.repo.owner,
                repo: context.repo.repo,
                issue_number: context.payload.pull_request.number,
                labels: labels
              });
            }
```

---

## 8. Required Labels

Erstelle Labels im Repository mit GitHub CLI:

```bash
#!/bin/bash
# create-labels.sh

REPO="${1:-$(gh repo view --json nameWithOwner -q '.nameWithOwner')}"

# Type Labels
gh label create "feature" --description "New feature" --color "0E8A16" --repo "$REPO" 2>/dev/null || true
gh label create "bugfix" --description "Bug fix" --color "D93F0B" --repo "$REPO" 2>/dev/null || true
gh label create "hotfix" --description "Critical fix" --color "B60205" --repo "$REPO" 2>/dev/null || true
gh label create "documentation" --description "Documentation changes" --color "0075CA" --repo "$REPO" 2>/dev/null || true
gh label create "refactoring" --description "Code refactoring" --color "FEF2C0" --repo "$REPO" 2>/dev/null || true
gh label create "testing" --description "Testing related" --color "BFD4F2" --repo "$REPO" 2>/dev/null || true
gh label create "maintenance" --description "Maintenance work" --color "C5DEF5" --repo "$REPO" 2>/dev/null || true

# Size Labels
gh label create "size/S" --description "Small change (<50 lines)" --color "C2E0C6" --repo "$REPO" 2>/dev/null || true
gh label create "size/M" --description "Medium change (50-200 lines)" --color "FEF2C0" --repo "$REPO" 2>/dev/null || true
gh label create "size/L" --description "Large change (200-500 lines)" --color "F9D0C4" --repo "$REPO" 2>/dev/null || true
gh label create "size/XL" --description "Extra large change (500+ lines)" --color "E99695" --repo "$REPO" 2>/dev/null || true

# Priority Labels
gh label create "priority/critical" --description "Critical priority" --color "B60205" --repo "$REPO" 2>/dev/null || true
gh label create "priority/high" --description "High priority" --color "D93F0B" --repo "$REPO" 2>/dev/null || true
gh label create "priority/medium" --description "Medium priority" --color "FBCA04" --repo "$REPO" 2>/dev/null || true
gh label create "priority/low" --description "Low priority" --color "0E8A16" --repo "$REPO" 2>/dev/null || true

# Status Labels
gh label create "needs-review" --description "Awaiting review" --color "FBCA04" --repo "$REPO" 2>/dev/null || true
gh label create "approved" --description "Approved for merge" --color "0E8A16" --repo "$REPO" 2>/dev/null || true
gh label create "changes-requested" --description "Changes requested" --color "D93F0B" --repo "$REPO" 2>/dev/null || true

echo "✅ Labels created for $REPO"
```

---

## 9. Zusammenfassung: Branch Protection Regeln

### Main Branch

| Regel | Wert | Begründung |
|-------|------|------------|
| Direkter Push | ❌ Verboten | Nur via PR |
| Required Reviews | 1 | Quality Gate |
| Dismiss Stale Reviews | ✅ | Sicherheit |
| Required Status Checks | lint, test, build, security | CI muss passieren |
| Strict Status Checks | ✅ | Branch muss aktuell sein |
| Force Push | ❌ Verboten | Historie schützen |
| Deletion | ❌ Verboten | Branch schützen |

### Development Branch

| Regel | Wert | Begründung |
|-------|------|------------|
| Direkter Push | ❌ Verboten | Nur via PR |
| Required Reviews | 0 | Schnellere Iteration |
| Required Status Checks | lint, test, build | CI muss passieren |
| Strict Status Checks | ✅ | Branch muss aktuell sein |
| Force Push | ❌ Verboten | Historie schützen |
| Deletion | ❌ Verboten | Branch schützen |

---

## 10. Checkliste für neue Projekte

```markdown
## GitHub Repository Setup Checklist

### Branches
- [ ] `main` Branch erstellt (Standard)
- [ ] `development` Branch erstellt
- [ ] Default Branch auf `development` gesetzt (optional)

### Branch Protection
- [ ] `main` Protection Rules aktiviert
- [ ] `development` Protection Rules aktiviert
- [ ] Status Checks konfiguriert (lint, test, build)
- [ ] Review Requirements gesetzt

### Workflows
- [ ] CI Workflow (`.github/workflows/ci.yml`)
- [ ] Security Workflow (`.github/workflows/security.yml`)
- [ ] PR Validation (`.github/workflows/pr-validation.yml`)
- [ ] Auto-Label (`.github/workflows/auto-label.yml`)
- [ ] Branch Naming Check (`.github/workflows/branch-naming.yml`)

### Templates
- [ ] PR Template (`.github/PULL_REQUEST_TEMPLATE.md`) — aus Plugin-Templates
- [ ] Issue Templates (`.github/ISSUE_TEMPLATE/`) — aus Plugin-Templates
- [ ] CODEOWNERS (`.github/CODEOWNERS`)

### Labels
- [ ] Type Labels erstellt
- [ ] Size Labels erstellt
- [ ] Priority Labels erstellt

### Dokumentation
- [ ] CHANGELOG.md erstellt
- [ ] Contributing Guidelines (optional)
```

---

**Letzte Aktualisierung:** 2025-01
**Owner:** DevOps Team

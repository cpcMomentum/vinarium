# CI/CD Pipeline Standards

Diese Standards definieren einheitliche CI/CD Pipelines fuer alle Projekte.

---

## 1. Universelle Pipeline-Struktur (alle Stacks)

### Standard Workflow

```
Push → Lint → Test → Build → Security Scan → Deploy (conditional)
```

Dieser Ablauf ist **stack-agnostisch**. Die konkreten Befehle fuer jeden Schritt kommen aus den **Command Mappings** in `techstack.md`:

| Pipeline-Schritt | Command Mapping |
|-----------------|-----------------|
| Lint | `lint_frontend` + `lint_backend` |
| Test | `test_frontend` + `test_backend` |
| Build | `build_frontend` + `build_backend` |
| Security | `dep_audit_fe` + `dep_audit_be` |
| Install | `install_deps_fe` + `install_deps_be` |

### Branch-Strategien (alle Stacks)

| Branch | Trigger | Actions |
|--------|---------|---------|
| `feature/*` | Push, PR | Lint, Test, Build |
| `development` | Push, PR Merge | Lint, Test, Build, Deploy to Dev |
| `main` | PR Merge | Lint, Test, Build, Security, Deploy to Staging |
| `release/*` | Tag | Full Pipeline + Deploy to Production |

> **Hinweis:** Die folgenden Sektionen sind **stack-spezifische CI-Templates**. Nur das Template verwenden das zum eigenen Stack passt (siehe `techstack.md`). Bei `/setup-ci` wird das passende Template automatisch gewaehlt.

---

## 2. GitHub Actions - Next.js (TypeScript)

### .github/workflows/ci.yml

```yaml
name: CI

on:
  push:
    branches: [main, development]
  pull_request:
    branches: [main, development]

env:
  NODE_VERSION: '22'

jobs:
  lint:
    name: Lint & Type Check
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Setup Node.js
        uses: actions/setup-node@v6
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Run ESLint
        run: npm run lint

      - name: Run TypeScript check
        run: npm run typecheck

      - name: Check formatting
        run: npm run format:check

  test:
    name: Test
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Setup Node.js
        uses: actions/setup-node@v6
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Run tests
        run: npm run test -- --coverage

      - name: Upload coverage
        uses: codecov/codecov-action@v5
        with:
          token: ${{ secrets.CODECOV_TOKEN }}
          fail_ci_if_error: false

  build:
    name: Build
    runs-on: ubuntu-latest
    needs: test
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Setup Node.js
        uses: actions/setup-node@v6
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Build
        run: npm run build
        env:
          NEXT_TELEMETRY_DISABLED: 1

      - name: Upload build artifact
        uses: actions/upload-artifact@v6
        with:
          name: build
          path: .next/
          retention-days: 7

  security:
    name: Security Scan
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Setup Node.js
        uses: actions/setup-node@v6
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Run npm audit
        run: npm audit --audit-level=high

      - name: Run Snyk (optional)
        uses: snyk/actions/node@master
        continue-on-error: true
        env:
          SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}

  docker:
    name: Build Docker Image
    runs-on: ubuntu-latest
    needs: [test, security]
    if: github.event_name == 'push' && (github.ref == 'refs/heads/main' || github.ref == 'refs/heads/development')
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/${{ github.repository }}
          tags: |
            type=ref,event=branch
            type=sha,prefix=

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
```

### .github/workflows/deploy.yml

Deployment wird **nicht** automatisch bei Push auf main getriggert.
Stattdessen: Release Branch erstellen → manuell deployen via `workflow_dispatch`.

Der Server baut die Images selbst aus dem Source Code (kein Registry-Push).

```
Flow: release/v1.2.3 Branch erstellen
      → GitHub Actions > Deploy > Run workflow > Branch + Environment waehlen
      → SSH auf Server: git checkout release branch
      → docker compose down → docker compose up --build
      → Health Check
```

**Vollstaendiges Template:** `.claude/templates/github/workflows/deploy.yml`

**Benoetigte Secrets:**

| Secret | Beschreibung |
|--------|-------------|
| `SERVER_HOST` | IP oder Hostname des Servers |
| `SERVER_USER` | SSH-Benutzer (z.B. root) |
| `SSH_PRIVATE_KEY` | Privater SSH-Key |
| `SERVER_APP_DIR` | Pfad zur App auf dem Server (z.B. /opt/<projektname>) |

**Benoetigte Repository Variables** (Settings > Variables > Actions):

| Variable | Beschreibung |
|----------|-------------|
| `DEPLOY_DIR` | Unterordner mit docker-compose.yml (z.B. deploy) - leer lassen wenn im Root |
| `HEALTH_CHECK_URL` | Health Check URL (z.B. http://localhost:8000/health) |

**Benoetigte GitHub Environments:** `staging`, `production` (optional mit Required Reviewers)

## 3. GitHub Actions - FastAPI (Python)

### .github/workflows/ci-python.yml

```yaml
name: CI Python

on:
  push:
    branches: [main, development]
  pull_request:
    branches: [main, development]

env:
  PYTHON_VERSION: '3.12'

jobs:
  lint:
    name: Lint & Type Check
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Setup Python
        uses: actions/setup-python@v6
        with:
          python-version: ${{ env.PYTHON_VERSION }}

      - name: Install dependencies
        run: |
          python -m pip install --upgrade pip
          pip install ruff mypy
          pip install -r requirements.txt

      - name: Run Ruff
        run: ruff check .

      - name: Check formatting
        run: ruff format --check .

      - name: Run MyPy
        run: mypy app/ --ignore-missing-imports

  test:
    name: Test
    runs-on: ubuntu-latest
    needs: lint
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
          POSTGRES_DB: test_db
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Setup Python
        uses: actions/setup-python@v6
        with:
          python-version: ${{ env.PYTHON_VERSION }}

      - name: Install dependencies
        run: |
          python -m pip install --upgrade pip
          pip install -r requirements.txt
          pip install pytest pytest-cov pytest-asyncio

      - name: Run tests
        run: pytest --cov=app --cov-report=xml
        env:
          DATABASE_URL: postgresql://test:test@localhost:5432/test_db
          APP_ENV: test

      - name: Upload coverage
        uses: codecov/codecov-action@v5
        with:
          token: ${{ secrets.CODECOV_TOKEN }}
          files: ./coverage.xml

  security:
    name: Security Scan
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Setup Python
        uses: actions/setup-python@v6
        with:
          python-version: ${{ env.PYTHON_VERSION }}

      - name: Install pip-audit
        run: pip install pip-audit

      - name: Run pip-audit
        run: pip-audit -r requirements.txt

      - name: Run Bandit
        run: |
          pip install bandit
          bandit -r app/ -ll

  docker:
    name: Build Docker Image
    runs-on: ubuntu-latest
    needs: [test, security]
    if: github.event_name == 'push'
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ghcr.io/${{ github.repository }}:${{ github.sha }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
```

## 3b. Monorepo CI (Frontend + Backend kombiniert)

Fuer Projekte mit `frontend/` und `backend/` im selben Repo:

### .github/workflows/ci.yml (Monorepo)

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  frontend-lint:
    name: Frontend Lint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6
      - uses: actions/setup-node@v6
        with:
          node-version: "22"
          cache: "npm"
          cache-dependency-path: frontend/package-lock.json
      - run: cd frontend && npm ci
      - run: cd frontend && npm run lint

  frontend-build:
    name: Frontend Build
    runs-on: ubuntu-latest
    needs: frontend-lint
    steps:
      - uses: actions/checkout@v6
      - uses: actions/setup-node@v6
        with:
          node-version: "22"
          cache: "npm"
          cache-dependency-path: frontend/package-lock.json
      - run: cd frontend && npm ci
      - run: cd frontend && npm run build

  backend-lint:
    name: Backend Lint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6
      - uses: actions/setup-python@v6
        with:
          python-version: "3.12"
      - run: pip install ruff
      - run: cd backend && ruff check .
      - run: cd backend && ruff format --check .
```

> **Hinweis:** Kein Docker-Build-Job hier — Deploy-Workflows sind separat.
> Kein Codecov/MyPy — nur im MVP wenn eingerichtet.

## 4. Dockerfile Templates

### Next.js Dockerfile

```dockerfile
# Dockerfile
FROM node:22-alpine AS base

# Install dependencies only when needed
FROM base AS deps
RUN apk add --no-cache libc6-compat
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Rebuild the source code only when needed
FROM base AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .

ENV NEXT_TELEMETRY_DISABLED 1

RUN npm run build

# Production image
FROM base AS runner
WORKDIR /app

ENV NODE_ENV production
ENV NEXT_TELEMETRY_DISABLED 1

RUN addgroup --system --gid 1001 nodejs
RUN adduser --system --uid 1001 nextjs

COPY --from=builder /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static

USER nextjs

EXPOSE 3000
ENV PORT 3000
ENV HOSTNAME "0.0.0.0"

CMD ["node", "server.js"]
```

### FastAPI Dockerfile

```dockerfile
# Dockerfile
FROM python:3.12-slim AS base

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

WORKDIR /app

# Install dependencies
FROM base AS deps
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Production image
FROM base AS runner

# Create non-root user
RUN addgroup --system --gid 1001 appgroup && \
    adduser --system --uid 1001 --gid 1001 appuser

COPY --from=deps /usr/local/lib/python3.12/site-packages /usr/local/lib/python3.12/site-packages
COPY --from=deps /usr/local/bin /usr/local/bin

COPY --chown=appuser:appgroup ./app ./app

USER appuser

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
```

### .dockerignore

```
# Git
.git
.gitignore

# Dependencies
node_modules
.npm
__pycache__
*.pyc
.venv
venv

# Build
.next
dist
build
*.egg-info

# IDE
.idea
.vscode
*.swp

# Testing
coverage
.coverage
htmlcov
.pytest_cache

# Environment
.env
.env.*
!.env.example

# Misc
*.md
!README.md
Makefile
docker-compose*.yml
```

## 5. Vercel Deployment (Next.js)

### vercel.json

```json
{
  "buildCommand": "npm run build",
  "outputDirectory": ".next",
  "framework": "nextjs",
  "regions": ["fra1"],
  "env": {
    "NEXT_TELEMETRY_DISABLED": "1"
  },
  "headers": [
    {
      "source": "/api/(.*)",
      "headers": [
        { "key": "Cache-Control", "value": "no-store, max-age=0" }
      ]
    }
  ]
}
```

### GitHub Actions for Vercel

```yaml
name: Vercel Deploy

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Deploy to Vercel
        uses: amondnet/vercel-action@v25
        with:
          vercel-token: ${{ secrets.VERCEL_TOKEN }}
          vercel-org-id: ${{ secrets.VERCEL_ORG_ID }}
          vercel-project-id: ${{ secrets.VERCEL_PROJECT_ID }}
          vercel-args: ${{ github.event_name == 'push' && '--prod' || '' }}
```

## 6. Release Workflow

### .github/workflows/release.yml

```yaml
name: Release

on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v6
        with:
          fetch-depth: 0

      - name: Generate changelog
        id: changelog
        uses: orhun/git-cliff-action@v3
        with:
          config: cliff.toml
          args: --latest --strip header

      - name: Create Release
        uses: softprops/action-gh-release@v1
        with:
          body: ${{ steps.changelog.outputs.content }}
          draft: false
          prerelease: ${{ contains(github.ref, '-rc') || contains(github.ref, '-beta') }}
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push Docker
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: |
            ghcr.io/${{ github.repository }}:${{ github.ref_name }}
            ghcr.io/${{ github.repository }}:latest
```

## 7. Dependabot Configuration

### .github/dependabot.yml

```yaml
version: 2
updates:
  # NPM dependencies for frontend
  - package-ecosystem: "npm"
    directory: "/frontend"
    schedule:
      interval: "weekly"
      day: "monday"
    open-pull-requests-limit: 5
    labels:
      - "dependencies"
      - "frontend"
    commit-message:
      prefix: "deps(frontend)"
    groups:
      frontend-minor-patch:
        patterns:
          - "*"
        update-types:
          - "minor"
          - "patch"
    # WICHTIG: Major Updates ausschliessen - Breaking Changes manuell behandeln!
    # Ohne diesen Filter gruppiert Dependabot ALLE Updates (inkl. Major) in einem PR,
    # was z.B. openai 1.x→2.x + stripe 11.x→14.x gleichzeitig bumpen kann.
    ignore:
      - dependency-name: "*"
        update-types: ["version-update:semver-major"]

  # Python dependencies for backend
  - package-ecosystem: "pip"
    directory: "/backend"
    schedule:
      interval: "weekly"
      day: "monday"
    open-pull-requests-limit: 5
    labels:
      - "dependencies"
      - "backend"
    commit-message:
      prefix: "deps(backend)"
    groups:
      backend-minor-patch:
        patterns:
          - "*"
        update-types:
          - "minor"
          - "patch"
    # WICHTIG: Major Updates ausschliessen - Breaking Changes manuell behandeln!
    ignore:
      - dependency-name: "*"
        update-types: ["version-update:semver-major"]

  # Docker dependencies
  - package-ecosystem: "docker"
    directory: "/"
    schedule:
      interval: "monthly"
    labels:
      - "dependencies"
      - "docker"
    commit-message:
      prefix: "deps(docker)"

  # GitHub Actions
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "monthly"
    labels:
      - "dependencies"
      - "ci"
    commit-message:
      prefix: "deps(ci)"
    groups:
      ci-all:
        patterns:
          - "*"
```

### Dependabot Best Practices

| Feature | Beschreibung |
|---------|--------------|
| **Labels** | Ermöglicht Filterung und automatische Zuweisung in GitHub Projects |
| **Commit-Message Prefix** | Semantische Commits (`deps(frontend)`, `deps(backend)`) |
| **Grouping** | Fasst alle Updates pro Ecosystem zusammen → weniger PRs |
| **Monthly für Infrastructure** | Docker/CI ändern sich seltener, reduziert Noise |

## 8. Branch Protection Rules

> **Vollständige Dokumentation:** Siehe `.claude/standards/github-branch-protection.md`

### Übersicht

| Branch | Direkter Push | PR Required | Reviews | Status Checks |
|--------|---------------|-------------|---------|---------------|
| `main` | ❌ | ✅ | 1 | lint, test, build, security |
| `development` | ❌ | ✅ | 0 | lint, test, build |

### Quick Setup

```bash
# Setup Script ausführen (nach Repository-Erstellung)
./scripts/setup-branch-protection.sh
```

Oder manuell via GitHub CLI:

```bash
REPO="owner/repo-name"

# Main Branch Protection
gh api --method PUT "/repos/$REPO/branches/main/protection" \
  -f 'required_status_checks[strict]=true' \
  -f 'required_status_checks[contexts][]=lint' \
  -f 'required_status_checks[contexts][]=test' \
  -f 'required_status_checks[contexts][]=build' \
  -f 'required_status_checks[contexts][]=security' \
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

### Empfohlene Settings für `main`

```yaml
# Via GitHub API or UI
branch_protection:
  required_status_checks:
    strict: true
    contexts:
      - "lint"
      - "test"
      - "build"
      - "security"
  enforce_admins: false
  required_pull_request_reviews:
    required_approving_review_count: 1
    dismiss_stale_reviews: true
    require_code_owner_reviews: false
  restrictions: null
  allow_force_pushes: false
  allow_deletions: false
```

### Weiterführende Dokumentation

- **Branch Protection Details:** `.claude/standards/github-branch-protection.md`
- **PR Templates:** `.claude/standards/github-branch-protection.md#4-pr-templates`
- **Branch Naming:** `.claude/standards/github-branch-protection.md#5-branch-naming-conventions`
- **CODEOWNERS:** `.claude/standards/github-branch-protection.md#6-codeowners`

## 9. Environment Secrets Checklist

### Required Secrets (GitHub)

| Secret | Description | Required For |
|--------|-------------|--------------|
| `GITHUB_TOKEN` | Auto-provided | Docker Registry |
| `VERCEL_TOKEN` | Vercel API Token | Vercel Deploy |
| `VERCEL_ORG_ID` | Vercel Organization | Vercel Deploy |
| `VERCEL_PROJECT_ID` | Vercel Project | Vercel Deploy |
| `CODECOV_TOKEN` | Codecov Upload | Coverage Reports |
| `SNYK_TOKEN` | Snyk Security | Security Scans |

## 10. Security Workflow

### .github/workflows/security.yml

Dedizierter Security-Workflow für automatische Vulnerability-Checks bei jedem Push/PR.

```yaml
name: Security

on:
  push:
    branches: [main, development]
  pull_request:
    branches: [main, development]

jobs:
  npm-audit:
    name: NPM Security Audit
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Setup Node.js
        uses: actions/setup-node@v6
        with:
          node-version: "22"
          cache: "npm"
          cache-dependency-path: frontend/package-lock.json

      - name: Install dependencies
        run: cd frontend && npm ci

      - name: Run npm audit
        run: cd frontend && npm audit --audit-level=high

  pip-audit:
    name: Python Security Audit
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Setup Python
        uses: actions/setup-python@v6
        with:
          python-version: "3.12"

      - name: Install pip-audit
        run: pip install pip-audit

      - name: Run pip-audit
        run: pip-audit -r backend/requirements.txt

  trivy-secret-scan:
    name: Secret Detection
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Trivy Secret Scan
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          scanners: 'secret'
          severity: 'CRITICAL,HIGH'
```

### Security Workflow Features

| Job | Beschreibung | Fail-Kriterium |
|-----|--------------|----------------|
| **npm-audit** | Prüft JavaScript-Dependencies auf bekannte Vulnerabilities | High/Critical CVE |
| **pip-audit** | Prüft Python-Dependencies auf bekannte Vulnerabilities | Jede CVE |
| **trivy-secret-scan** | Scannt Repository auf versehentlich committete Secrets (Trivy Secret Scanner, kein License Key nötig) | Secret gefunden |

### Vulnerability Ignore (falls nötig)

Bei False Positives oder akzeptierten Risiken:

```yaml
# pip-audit mit Ignore
- name: Run pip-audit
  run: pip-audit -r backend/requirements.txt --ignore-vuln GHSA-xxxx-xxxx-xxxx
```

```json
// package.json - npm audit ignore (nur für verifizierte False Positives!)
{
  "overrides": {
    "vulnerable-package": "^2.0.0"
  }
}
```

## 11. Claude GitHub Action (PFLICHT)

Alle Projekte muessen die Claude GitHub Action integrieren. Dies ermoeglicht:
- **@claude in Issues:** Claude analysiert das Problem und kann Branches/PRs erstellen
- **@claude in PRs:** Claude reagiert auf Anfragen in PR-Kommentaren und Reviews
- **Automatisches Code-Review:** Bei jedem PR fuehrt Claude ein Review durch

### Voraussetzungen

| Voraussetzung | Beschreibung |
|---------------|--------------|
| **Claude GitHub App** | Installieren unter https://github.com/apps/claude |
| **Repository Secret** | `CLAUDE_CODE_OAUTH_TOKEN` (via `claude setup-token`) |
| **CLAUDE.md** | Projektspezifische Anweisungen fuer Claude |

### .github/workflows/claude.yml (Interaktiver Bot)

```yaml
name: Claude Code

on:
  issue_comment:
    types: [created]
  pull_request_review_comment:
    types: [created]
  issues:
    types: [opened, assigned]
  pull_request_review:
    types: [submitted]

jobs:
  claude:
    if: |
      (github.event_name == 'issue_comment' && contains(github.event.comment.body, '@claude')) ||
      (github.event_name == 'pull_request_review_comment' && contains(github.event.comment.body, '@claude')) ||
      (github.event_name == 'pull_request_review' && contains(github.event.review.body, '@claude')) ||
      (github.event_name == 'issues' && (contains(github.event.issue.body, '@claude') || contains(github.event.issue.title, '@claude')))
    runs-on: ubuntu-latest
    permissions:
      contents: write
      pull-requests: write
      issues: write
      id-token: write
      actions: read
    steps:
      - name: Checkout repository
        uses: actions/checkout@v6
        with:
          fetch-depth: 1

      - name: Run Claude Code
        id: claude
        uses: anthropics/claude-code-action@v1
        with:
          claude_code_oauth_token: ${{ secrets.CLAUDE_CODE_OAUTH_TOKEN }}
          additional_permissions: |
            actions: read
```

### .github/workflows/claude-code-review.yml (Automatisches PR-Review)

```yaml
name: Claude Code Review

on:
  pull_request:
    types: [opened, synchronize]

jobs:
  claude-review:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      pull-requests: write
      issues: read
      id-token: write
      actions: read
    steps:
      - name: Checkout repository
        uses: actions/checkout@v6
        with:
          fetch-depth: 1

      - name: Run Claude Code Review
        id: claude-review
        uses: anthropics/claude-code-action@v1
        with:
          claude_code_oauth_token: ${{ secrets.CLAUDE_CODE_OAUTH_TOKEN }}
          # Erlaubt Claude Bot als Trigger (wenn claude.yml PRs erstellt/pusht)
          allowed_bots: "claude[bot],github-actions[bot]"
          prompt: |
            Reviewe diesen PR fuer Code-Qualitaet, Bugs, Performance,
            Security und Test-Abdeckung. Nutze CLAUDE.md und
            .claude/standards/ als Referenz.
            Poste dein Review via `gh pr comment`.
          claude_args: '--allowed-tools "Bash(gh issue view:*),Bash(gh search:*),Bash(gh issue list:*),Bash(gh pr comment:*),Bash(gh pr diff:*),Bash(gh pr view:*),Bash(gh pr list:*)"'
```

### Authentifizierung

| Methode | Secret | Beschreibung |
|---------|--------|--------------|
| **OAuth Token** | `CLAUDE_CODE_OAUTH_TOKEN` | Max Plan, generieren via `claude setup-token` |
| **Custom GitHub App** | `APP_ID` + `APP_PRIVATE_KEY` | Fuer Orgs die eigene Bot-Identitaet benoetigen |

### Sicherheitshinweise

- Claude hat **Schreibzugriff** auf das Repository (kann Branches erstellen, Code aendern, PRs oeffnen)
- Im Code-Review Workflow hat Claude **nur Lesezugriff** (kann nur kommentieren)
- Claude liest die `CLAUDE.md` und `.claude/standards/` fuer Kontext und Regeln
- Secrets werden **nie** in Logs oder Kommentaren exponiert
- Die Action laeuft komplett auf dem GitHub Runner — Code verlasst nie die GitHub-Infrastruktur

### Setup via Skill

```bash
/setup-ci    # Generiert ci.yml + claude.yml + claude-code-review.yml
```

### Templates

Vorgefertigte Workflow-Dateien liegen in `.claude/templates/github/workflows/`:
- `claude.yml` — Interaktiver @claude Bot
- `claude-code-review.yml` — Automatisches PR-Review
- `security.yml` — npm audit + pip-audit

---

**Letzte Aktualisierung**: 2026-02
**Owner**: DevOps Team

# TechStack Preset: Custom

> **Preset-ID:** custom
> **Beschreibung:** Flexibles Template fuer beliebige Tech-Stacks. Wird von `/adopt` und `/setup` automatisch ausgefuellt.

## 1. Frontend

| Technologie | Version | Zweck |
|-------------|---------|-------|
| [Framework] | [Version] | Framework (z.B. Next.js, Vue.js, Svelte, Angular, keine) |
| [Sprache] | [Version] | Sprache (z.B. TypeScript, JavaScript) |
| [UI Library] | [Version] | UI Komponenten (z.B. shadcn/ui, Vuetify, Bootstrap) |
| [Styling] | [Version] | Styling (z.B. Tailwind, UnoCSS, SCSS, plain CSS) |

## 2. Backend

| Technologie | Version | Zweck |
|-------------|---------|-------|
| [Framework] | [Version] | Framework (z.B. FastAPI, Laravel, Spring Boot, Express, Gin) |
| [Sprache] | [Version] | Sprache (z.B. Python, PHP, Java, Go, Ruby, C#) |
| [ORM] | [Version] | Datenbank-Zugriff (z.B. SQLAlchemy, Doctrine, GORM, ActiveRecord) |

## 3. Datenbank

| Technologie | Zweck |
|-------------|-------|
| [Datenbank] | Primaere Datenbank (z.B. PostgreSQL, MySQL, SQLite, MongoDB) |
| [Migration Tool] | Schema-Management (z.B. Alembic, Doctrine Migrations, Flyway) |

## 4. Authentifizierung

| Technologie | Zweck |
|-------------|-------|
| [Auth Library] | Authentifizierung (z.B. NextAuth, Laravel Sanctum, Spring Security) |

## 5. Infrastruktur

| Technologie | Zweck | Benoetigt? |
|-------------|-------|------------|
| [Container] | Containerisierung (z.B. Docker, Podman, keine) | Optional |
| [Orchestrierung] | Lokale Orchestrierung (z.B. Docker Compose, keine) | Optional |
| [Hosting] | Deployment (z.B. Vercel, Self-hosted, App Store, AWS) | Ja |
| [CI/CD] | CI/CD (z.B. GitHub Actions, GitLab CI, Jenkins) | Ja |

## 6. Code Quality

| Tool | Bereich |
|------|---------|
| [Linter Frontend] | Frontend Linting (z.B. ESLint, Biome) |
| [Linter Backend] | Backend Linting (z.B. Ruff, PHP_CodeSniffer, golangci-lint) |
| [Formatter] | Formatting (z.B. Prettier, PHP-CS-Fixer, gofmt) |

## 7. Testing

| Tool | Bereich |
|------|---------|
| [Test Frontend] | Frontend Tests (z.B. Vitest, Jest) |
| [Test Backend] | Backend Tests (z.B. pytest, PHPUnit, JUnit, go test) |

## 8. Monitoring

| Tool | Bereich |
|------|---------|
| [Logging] | Structured Logging (z.B. structlog, Monolog, Zap) |
| [API Docs] | API Dokumentation (z.B. Swagger/OpenAPI, keine) |

## Projektstruktur

```
project/
├── [frontend-dir]/
├── [backend-dir]/
└── [ci-config]/
```

## Command Mappings

> **PFLICHT.** Siehe `_profile-schema.md` fuer Details. Skills lesen diese Tabelle.

| Concept | Command |
|---------|---------|
| `lint_frontend` | [z.B. npm run lint] |
| `lint_backend` | [z.B. ruff check . / php vendor/bin/phpcs / golangci-lint run] |
| `format_frontend` | [z.B. npx prettier --write .] |
| `format_backend` | [z.B. ruff format . / php vendor/bin/phpcbf / gofmt -w .] |
| `typecheck` | [z.B. npx tsc --noEmit / npm run typecheck / N/A] |
| `test_frontend` | [z.B. npm test / N/A] |
| `test_backend` | [z.B. pytest / php vendor/bin/phpunit / go test ./...] |
| `build_frontend` | [z.B. npm run build / N/A] |
| `build_backend` | [z.B. composer install --no-dev / go build ./... / N/A] |
| `dep_audit_fe` | [z.B. npm audit / N/A] |
| `dep_audit_be` | [z.B. pip-audit / composer audit / N/A] |
| `install_deps_fe` | [z.B. npm ci / N/A] |
| `install_deps_be` | [z.B. pip install -r requirements.txt / composer install / go mod download] |

## Plugins

| Kategorie | Plugin | Benoetigt? |
|-----------|--------|------------|
| Recherche | `context7` | Ja |
| Security | `security-guidance` | Ja |
| Testing | `playwright` | Wenn Frontend |
| Type-Check Frontend | [z.B. typescript-lsp / N/A] | Wenn TypeScript |
| Type-Check Backend | [z.B. pyright-lsp / N/A] | Wenn Python |
| UI Design | `frontend-design` | Wenn Frontend |

## Begruendung fuer Custom-Stack

[Begruendung hier einfuegen]

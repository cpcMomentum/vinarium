# TechStack Preset: Next.js + FastAPI

> **Preset-ID:** nextjs-fastapi
> **Beschreibung:** Full-Stack mit Next.js Frontend und FastAPI Backend. Standard-Preset.

## 1. Frontend

| Technologie | Version | Zweck |
|-------------|---------|-------|
| Next.js | 15.x (App Router) | Framework |
| TypeScript | 5.x | Sprache |
| shadcn/ui | latest | UI Komponenten |
| Tailwind CSS | 4.x | Styling |
| Zod | 3.x | Validation |
| openapi-typescript | latest | API-Types aus OpenAPI |

## 2. Backend

| Technologie | Version | Zweck |
|-------------|---------|-------|
| Python | 3.12+ | Sprache |
| FastAPI | 0.115+ | Framework |
| Pydantic | 2.x | Validation / Schemas |
| SQLAlchemy | 2.x | ORM |
| Alembic | 1.x | DB Migrations |
| uvicorn | 0.30+ | ASGI Server |

## 3. Datenbank

| Option | Wann verwenden |
|--------|----------------|
| PostgreSQL | Relationale Daten, strikte Schemas (Standard) |
| MongoDB | Dokumentenbasiert, flexible Schemas |

**ORM:** SQLAlchemy 2.x (PostgreSQL) / Motor (MongoDB)

## 4. Authentifizierung

| Technologie | Zweck |
|-------------|-------|
| NextAuth.js (Auth.js) | Frontend Auth |
| FastAPI Security | Backend Auth |
| JWT / OAuth2 | Token-basiert |

## 5. Infrastruktur

| Technologie | Zweck |
|-------------|-------|
| Docker | Containerisierung (mandatory) |
| Docker Compose | Lokale Orchestrierung |
| Vercel | Frontend Hosting (optional) |
| GitHub Actions | CI/CD |

## 6. Code Quality

| Tool | Bereich |
|------|---------|
| ESLint | JS/TS Linting |
| Prettier | JS/TS Formatting |
| Ruff | Python Linting + Formatting |
| tsc | TypeScript Type-Checking |
| mypy | Python Type-Checking (optional) |

## 7. Testing

| Tool | Bereich |
|------|---------|
| Vitest / Jest | Frontend Unit Tests |
| pytest | Backend Unit Tests |
| Playwright | E2E Tests (post-MVP) |

## 8. Monitoring

| Tool | Bereich |
|------|---------|
| structlog | Python Structured Logging |
| Pino / Winston | Node.js Logging |
| Swagger/OpenAPI | API Dokumentation (in FastAPI integriert) |

## Projektstruktur

```
project/
├── frontend/          # Next.js App
│   ├── app/           # App Router
│   ├── components/    # UI Komponenten
│   └── lib/           # Utilities
├── backend/           # FastAPI App
│   ├── app/
│   │   ├── api/       # Router
│   │   ├── models/    # SQLAlchemy Models
│   │   ├── schemas/   # Pydantic Schemas
│   │   └── core/      # Config, DB, Auth
│   └── tests/
├── docker-compose.yml
└── .github/workflows/
```

## Command Mappings

| Concept | Command |
|---------|---------|
| `lint_frontend` | `cd frontend && npm run lint` |
| `lint_backend` | `cd backend && ruff check .` |
| `format_frontend` | `cd frontend && npx prettier --write .` |
| `format_backend` | `cd backend && ruff format .` |
| `typecheck` | `cd frontend && npx tsc --noEmit` |
| `test_frontend` | `cd frontend && npm test -- --coverage` |
| `test_backend` | `cd backend && pytest --cov=app` |
| `build_frontend` | `cd frontend && npm run build` |
| `build_backend` | `cd backend && python -m py_compile app/**/*.py` |
| `dep_audit_fe` | `cd frontend && npm audit` |
| `dep_audit_be` | `cd backend && pip-audit` |
| `install_deps_fe` | `cd frontend && npm ci` |
| `install_deps_be` | `cd backend && pip install -r requirements.txt` |

## Plugins

| Kategorie | Plugin | Benoetigt? |
|-----------|--------|------------|
| Recherche | `context7` | Ja |
| Security | `security-guidance` | Ja |
| Testing | `playwright` | Ja |
| Type-Check Frontend | `typescript-lsp` | Ja |
| Type-Check Backend | `pyright-lsp` | Ja |
| UI Design | `frontend-design` | Ja |

# TechStack Preset: Next.js + Strapi

> **Preset-ID:** nextjs-strapi
> **Beschreibung:** CMS-basierter Full-Stack. Ideal fuer Content-driven Projekte, Blogs, Marketing-Seiten.

## 1. Frontend

| Technologie | Version | Zweck |
|-------------|---------|-------|
| Next.js | 15.x (App Router) | Framework |
| TypeScript | 5.x | Sprache |
| shadcn/ui | latest | UI Komponenten |
| Tailwind CSS | 4.x | Styling |

## 2. Backend (CMS)

| Technologie | Version | Zweck |
|-------------|---------|-------|
| Strapi | 5.x | Headless CMS |
| Node.js | 22.x LTS | Runtime |
| TypeScript | 5.x | Sprache |

## 3. Datenbank

| Option | Wann verwenden |
|--------|----------------|
| PostgreSQL | Produktion (empfohlen) |
| SQLite | Lokale Entwicklung |

**ORM:** Strapi integriert (Knex.js)

## 4. Authentifizierung

| Technologie | Zweck |
|-------------|-------|
| Strapi Users & Permissions | CMS Auth |
| NextAuth.js (Auth.js) | Frontend Auth |

## 5. Infrastruktur

| Technologie | Zweck |
|-------------|-------|
| Docker | Containerisierung (mandatory) |
| Docker Compose | Lokale Orchestrierung |
| Vercel | Frontend Hosting |
| GitHub Actions | CI/CD |

## 6. Code Quality

| Tool | Bereich |
|------|---------|
| ESLint | Linting |
| Prettier | Formatting |
| tsc | Type-Checking |

## 7. Testing

| Tool | Bereich |
|------|---------|
| Vitest / Jest | Unit Tests |
| Playwright | E2E Tests (post-MVP) |

## 8. Monitoring

| Tool | Bereich |
|------|---------|
| Pino | Structured Logging |
| Strapi Admin | Content Monitoring |

## Projektstruktur

```
project/
├── frontend/          # Next.js App
│   ├── app/
│   ├── components/
│   └── lib/
├── backend/           # Strapi CMS
│   ├── src/
│   │   ├── api/       # Content Types
│   │   └── plugins/   # Custom Plugins
│   └── config/
├── docker-compose.yml
└── .github/workflows/
```

## Wann dieses Preset waehlen

- Content-Management ist Kernfunktion
- Nicht-technische User muessen Inhalte pflegen
- Blog, Marketing-Seite, Dokumentation
- Schnelles Backend ohne Custom API-Logik

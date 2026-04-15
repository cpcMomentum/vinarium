# TechStack Preset: Next.js + Supabase

> **Preset-ID:** nextjs-supabase
> **Beschreibung:** BaaS-Variante mit Vercel + Supabase Cloud. Minimaler Infrastruktur-Aufwand, schnellstes Time-to-Market.

## 1. Frontend

| Technologie | Version | Zweck |
|-------------|---------|-------|
| Next.js | 15.x (App Router) | Framework |
| TypeScript | 5.x | Sprache |
| shadcn/ui | latest | UI Komponenten |
| Tailwind CSS | 4.x | Styling |
| Zod | 3.x | Validation |

## 2. Backend (BaaS)

| Technologie | Zweck |
|-------------|-------|
| Supabase | Backend-as-a-Service |
| Supabase Auth | Authentifizierung |
| Supabase Realtime | Echtzeit-Updates |
| Supabase Storage | Datei-Upload |
| Supabase Edge Functions | Custom Server-Logik (Deno) |
| Next.js Server Actions | Server-seitige Logik |

## 3. Datenbank

| Technologie | Zweck |
|-------------|-------|
| PostgreSQL (Supabase) | Primaere Datenbank |
| Supabase Migrations | Schema-Management |
| Row Level Security (RLS) | Daten-Autorisierung |

## 4. Authentifizierung

| Technologie | Zweck |
|-------------|-------|
| Supabase Auth | Auth Provider |
| @supabase/ssr | Server-Side Auth |
| OAuth2 Providers | Social Login |

## 5. Infrastruktur

| Technologie | Zweck |
|-------------|-------|
| Vercel | Frontend Hosting + Edge |
| Supabase Cloud | Backend + DB Hosting |
| GitHub Actions | CI/CD |
| Docker | Lokale Entwicklung (supabase start) |

**Hinweis:** Docker ist fuer lokale Supabase-Instanz noetig, aber Produktion laeuft auf Supabase Cloud.

## 6. Code Quality

| Tool | Bereich |
|------|---------|
| ESLint | Linting |
| Prettier | Formatting |
| tsc | Type-Checking |
| supabase gen types | DB-Types generieren |

## 7. Testing

| Tool | Bereich |
|------|---------|
| Vitest / Jest | Unit Tests |
| Playwright | E2E Tests (post-MVP) |

## 8. Monitoring

| Tool | Bereich |
|------|---------|
| Supabase Dashboard | DB + Auth Monitoring |
| Vercel Analytics | Frontend Performance |

## Projektstruktur

```
project/
├── app/               # Next.js App Router
├── components/        # UI Komponenten
├── lib/
│   ├── supabase/      # Supabase Client Config
│   └── utils/
├── supabase/
│   ├── migrations/    # SQL Migrations
│   ├── functions/     # Edge Functions
│   └── seed.sql       # Test-Daten
├── docker-compose.yml # Lokale Supabase
└── .github/workflows/
```

## Wann dieses Preset waehlen

- Schnellstes Time-to-Market gewuenscht
- Kein eigenes Backend-Team
- Echtzeit-Features (Chat, Collaboration)
- Auth + Storage + DB aus einer Hand
- Budget fuer Managed Services vorhanden

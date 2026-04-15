# Vinarium — Nextcloud App

## Projekt

Vinarium ("Weinkeller") ist eine native Nextcloud-App fuer private Weinsammler. Sie verwaltet Bestand, physische Lagerposition (Regal/Slot), Verkostungsnotizen und Trinkfenster — privat, selbstgehostet.

Vollstaendige Produktbeschreibung: `docs/produktbeschreibung.md`

## Tech-Stack

| Schicht | Tech |
|---------|------|
| Backend | PHP 8.1+ mit OCP-APIs |
| Frontend | Vue.js 2 oder 3 + `@nextcloud/vue` |
| Database | NC Query Builder (MySQL/PostgreSQL/SQLite je nach Host) |
| Build | webpack + `@nextcloud/webpack-vue-config` |
| Lizenz | AGPL-3.0-or-later |

Details: `.claude/techstack.md`

## Methodik

Dieses Projekt nutzt das **ai-first-dev** Plugin (cpcMomentum) v4.0.1.
Skills, Agents und Commands kommen aus dem Plugin — projekt-spezifisch sind nur `.claude/techstack.md`, `.claude/dev.md` und dieses Dokument.

## Workflow (PIV-Loop)

```
/ai-first-dev:prime [task] → Coding → /ai-first-dev:validate → /ai-first-dev:review → Commit
```

## Wichtige Commands

| Zweck | Command |
|-------|---------|
| Session-Start | `/ai-first-dev:session-resume` |
| Kontext laden | `/ai-first-dev:prime` |
| Plan erstellen | `/ai-first-dev:create-plan` |
| Code validieren | `/ai-first-dev:validate` |
| Code-Review | `/ai-first-dev:review` |
| Release bauen | `/ai-first-dev:release` |
| Issue anlegen | `/ai-first-dev:create-issue` |

## Git-Workflow

- `main` — produktiv, nur via PR
- `develop` — Integration, sammelt bis zum Release
- Feature-Branches: `feat/<issue>-<beschreibung>`, `fix/<issue>-<beschreibung>`
- Conventional Commits (`feat:`, `fix:`, `chore:`, etc.)

## Pflicht-Konventionen (NC-spezifisch)

- **Nur `OCP\*` APIs, niemals `OC\*`** — private API-Verstoesse werden vom Pre-Commit-Hook blockiert
- **Kein raw SQL** — nur Query Builder
- **AGPL-3.0-or-later** — App-Store-Pflicht
- **Hash-Routing** im Vue-Router (keine History API)
- **`npm run build` vor Commit** bei Vue-Aenderungen — `js/` muss committed sein
- **Keine `.htaccess` / `.user.ini`** im Release-Tarball

Siehe `.claude/techstack.md` fuer Details.

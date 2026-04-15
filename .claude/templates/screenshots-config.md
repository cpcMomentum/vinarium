# Screenshot-Konfiguration

> **Datei:** `docs/screenshots.config.md`
> **Verwendung:** `/ai-first-dev:docs-audit` liest diese Datei und erstellt automatisch Screenshots mit Playwright.

## Konfiguration

Passe die Tabelle an dein Projekt an. Entferne nicht benötigte Zeilen, füge neue hinzu.

| Name | URL | Beschreibung | Viewport |
|------|-----|--------------|----------|
| landing-page | / | Startseite | 1280x720 |
| login | /login | Login-Formular | 1280x720 |
| dashboard | /dashboard | Haupt-Dashboard nach Login | 1280x720 |
| dashboard-mobile | /dashboard | Dashboard in mobiler Ansicht | 390x844 |

## Regeln

- **Name:** Wird als Dateiname verwendet (`docs/screenshots/{name}.png`) - nur Kleinbuchstaben, Bindestriche
- **URL:** Relativer Pfad, wird an `http://localhost:3000` angehängt (Port aus `.env` oder `docker-compose.yml`)
- **Viewport:** `{breite}x{höhe}` in Pixel
- **Login:** Falls eine Seite Auth erfordert, loggt sich `/ai-first-dev:docs-audit` automatisch ein (Credentials aus `.env`)
- **Reihenfolge:** Screenshots werden in Tabellenreihenfolge aufgenommen und in der README referenziert

# TechStack Profile Schema

> Dieses Dokument definiert die **Pflicht-Struktur** jeder `techstack.md`. Skills wie `/validate`, `/scaffold`, `/setup-ci` und `/setup-plugins` lesen die Command Mappings um stack-agnostisch zu arbeiten.

---

## Pflicht-Sektionen

Jede `techstack.md` (ob Preset oder Custom) MUSS folgende Sektionen enthalten:

### 1-8: Stack-Beschreibung

| Sektion | Inhalt | Pflicht? |
|---------|--------|----------|
| 1. Frontend | Framework, Sprache, UI Library, Styling | Wenn Frontend existiert |
| 2. Backend | Framework, Sprache, ORM | Wenn Backend existiert |
| 3. Datenbank | Primaere DB, Migration Tool | Wenn DB genutzt |
| 4. Authentifizierung | Auth Library/Strategie | Optional |
| 5. Infrastruktur | Container, Hosting, CI/CD | Ja |
| 6. Code Quality | Linter, Formatter pro Sprache | Ja |
| 7. Testing | Test-Frameworks pro Bereich | Ja |
| 8. Monitoring | Logging, API-Docs | Optional |

### Projektstruktur

ASCII-Baum der wichtigsten Verzeichnisse.

### Command Mappings (PFLICHT)

> Diese Tabelle ist der **Vertrag zwischen Methodik und Tooling**. Skills lesen diese Tabelle und fuehren die passenden Befehle aus.

| Concept | Beschreibung | Beispiel Next.js+FastAPI | Beispiel PHP+Vue |
|---------|-------------|--------------------------|-------------------|
| `lint_frontend` | Frontend Linting | `npm run lint` | `npm run lint` |
| `lint_backend` | Backend Linting | `ruff check .` | `php vendor/bin/phpcs --standard=PSR12 lib/` |
| `format_frontend` | Frontend Formatting | `npx prettier --write .` | `npx prettier --write .` |
| `format_backend` | Backend Formatting | `ruff format .` | `php vendor/bin/phpcbf --standard=PSR12 lib/` |
| `typecheck` | Type-Checking | `npx tsc --noEmit` | `npm run typecheck` (vue-tsc) |
| `test_frontend` | Frontend Tests | `npm test -- --coverage` | `npm run test` |
| `test_backend` | Backend Tests | `pytest --cov=app` | `php vendor/bin/phpunit` |
| `build_frontend` | Frontend Build | `npm run build` | `npm run build` |
| `build_backend` | Backend Build/Check | `python -m py_compile app/**/*.py` | `composer install --no-dev` |
| `dep_audit_fe` | Frontend Dependency Audit | `npm audit` | `npm audit` |
| `dep_audit_be` | Backend Dependency Audit | `pip-audit` | `composer audit` |
| `install_deps_fe` | Frontend Dependencies installieren | `npm ci` | `npm ci` |
| `install_deps_be` | Backend Dependencies installieren | `pip install -r requirements.txt` | `composer install` |

**Regeln:**
- Concept-Namen sind fix (Skills referenzieren sie)
- Command kann `N/A` sein wenn nicht zutreffend (z.B. `lint_frontend: N/A` bei reinem Backend-Projekt)
- Commands muessen **ausfuehrbar** sein (keine Platzhalter)
- Bei Monorepos: Pfad-Prefix angeben (z.B. `cd frontend && npm run lint`)

### Plugins (PFLICHT)

> Welche Claude Code Plugins fuer diesen Stack relevant sind.

| Kategorie | Plugin | Benoetigt? |
|-----------|--------|------------|
| Recherche | `context7` | Immer |
| Security | `security-guidance` | Immer |
| Testing | `playwright` | Wenn Frontend vorhanden |
| Type-Check Frontend | `typescript-lsp` | Wenn TypeScript |
| Type-Check Backend | `pyright-lsp` | Wenn Python |
| UI Design | `frontend-design` | Wenn Frontend vorhanden |

**Regeln:**
- Nur Plugins listen die fuer den Stack relevant sind
- Unbekannte Stacks (z.B. PHP, Java, Go) haben moeglicherweise keine LSP-Plugins -- das ist OK
- `context7` und `security-guidance` sind **immer** dabei

---

## Wie Skills die Command Mappings nutzen

### `/validate`
Liest die Tabelle, fuehrt jeden Command aus der nicht `N/A` ist:
1. `lint_frontend` + `lint_backend` (mit --fix wenn moeglich)
2. `format_frontend` + `format_backend`
3. `typecheck`
4. `test_frontend` + `test_backend`
5. `build_frontend` + `build_backend`
6. `dep_audit_fe` + `dep_audit_be`

### `/scaffold`
Liest Frontend/Backend Framework aus Sektion 1+2, waehlt passende Templates.

### `/setup-ci`
Generiert CI-Workflow basierend auf `install_deps_*`, `lint_*`, `test_*`, `build_*` Commands.

### `/setup-plugins`
Installiert Plugins aus der Plugins-Tabelle.

---

## Skill-Konventionen (OPTIONAL)

> Stack-spezifische Regeln die bestehende Skills ergaenzen. Skills lesen diese Sektion automatisch und wenden die Regeln an. Falls nicht vorhanden, wird der Skill im Standard-Modus ausgefuehrt.

Struktur: Fuer jeden Skill eine Unter-Sektion mit Tabelle aus Regel + Details.

```markdown
## Skill-Konventionen

### /release
| Regel | Details |
|-------|---------|
| ... | ... |

### /prime
| Regel | Details |
|-------|---------|
| ... | ... |
```

Typische Anwendung: Stack-spezifische Pre-/Post-Checks, zusaetzliche Validierungen, angepasste Workflows. Das Standard-Verhalten des Skills bleibt erhalten — die Konventionen kommen **zusaetzlich** dazu.

---

## Agent-Kontext (OPTIONAL)

> Informationen die alle Agenten (code-reviewer, debugger, research-analyst, etc.) lesen wenn sie an diesem Projekt arbeiten. Gibt Agenten Stack-spezifisches Wissen ohne dass neue Agenten noetig sind.

Struktur: Kern-Wissen (fuer alle) + optionale Unter-Sektionen pro Agent-Typ.

```markdown
## Agent-Kontext

### Kern-Wissen fuer alle Agenten
- Runtime-Umgebung, Einschraenkungen, Konventionen

### Fuer code-reviewer
- Stack-spezifische Review-Punkte

### Fuer debugger
- Stack-spezifische Debug-Strategien
```

---

## Fallback-Verhalten

Wenn eine `techstack.md` **keine** Command Mappings Sektion hat (alte Projekte):
1. Skills fallen auf Auto-Detection zurueck (package.json -> npm, requirements.txt -> pip)
2. Warnung: "techstack.md hat keine Command Mappings. Fuehre `/adopt` aus fuer vollstaendige Konfiguration."

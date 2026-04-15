# AI-First Development Workflow

Dieser Workflow beschreibt den Entwicklungsprozess von der Planung bis zur Auslieferung.

---

## Workflow-Phasen

```
                    AI-FIRST DEVELOPMENT WORKFLOW

  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
  │  20-PLANNING │───▶│30-DEVELOPMENT│───▶│35-VALIDATION │
  │              │    │              │    │              │
  │  Technischer │    │  Coding &    │    │  Quality     │
  │  Plan        │    │  Testing     │    │  Gates       │
  └──────────────┘    └──────────────┘    └──────────────┘
```

---

## Phasen-Uebersicht

| Phase | Datei | Zweck | Output |
|-------|-------|-------|--------|
| **20** | `20-planning.md` | Technischen Plan erstellen | plan.md |
| **30** | `30-development.md` | Entwicklung mit PIV-Loop | Funktionierendes Produkt |
| **35** | `35-validation.md` | Qualitaetssicherung & Code Review | Validierter Code |

---

## Kern-Workflow: PIV-Loop

```
/ai-first-dev:prime → Coding → /ai-first-dev:validate → /ai-first-dev:review → Commit
```

**Erweitert (vor Release):**
```
+ /ai-first-dev:security-scan + /ai-first-dev:deps-audit + /ai-first-dev:env-check
```

---

## Querverweis

| Dokument | Zweck |
|----------|-------|
| `dev.md` | Source of Truth fuer HOW (Prozess, Constraints) |
| `techstack.md` | Source of Truth fuer WHAT (Technologien) |
| `standards/` | Implementierungs-Standards |

---

## Naechste Schritte

1. `/ai-first-dev:setup` fuer ein neues Projekt
2. `/ai-first-dev:adopt` fuer ein bestehendes Projekt
3. `/ai-first-dev:create-plan` fuer die Planungsphase
4. `30-development.md` fuer den PIV-Loop Workflow

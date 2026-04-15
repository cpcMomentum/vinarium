# Phase Completion Checklist

> **Projekt:** [Projektname]
> **Phase:** [N]
> **Titel:** [Phase-Titel aus plan.md]
> **Datum:** [DATUM]

---

## Status

```
┌────────────────────────────────────────────────────────────────┐
│  Phase Status: [ ] IN PROGRESS  [ ] READY FOR REVIEW  [ ] DONE │
└────────────────────────────────────────────────────────────────┘
```

---

## 1. Deliverables (aus Plan-Phase)

Aus `docs/plan.md` → Phase [N] → Deliverables:

| # | Deliverable | Status | Nachweis |
|---|-------------|--------|----------|
| 1 | [Deliverable aus plan.md] | [ ] Erledigt | [Link/Pfad] |
| 2 | [Deliverable aus plan.md] | [ ] Erledigt | [Link/Pfad] |
| 3 | [Deliverable aus plan.md] | [ ] Erledigt | [Link/Pfad] |
| 4 | [Deliverable aus plan.md] | [ ] Erledigt | [Link/Pfad] |

**Alle Deliverables erfüllt:** [ ] Ja / [ ] Nein

---

## 2. Quality Gates

### 2.1 Validation (`/ai-first-dev:validate`)

| Check | Status | Letzte Ausführung |
|-------|--------|-------------------|
| Lint (ESLint/Ruff) | [ ] Bestanden | [DATUM] |
| Type-Check (TypeScript) | [ ] Bestanden | [DATUM] |
| Unit Tests | [ ] Bestanden | [DATUM] |
| Build | [ ] Bestanden | [DATUM] |

**Validation Report:** `reports/validation-phase-[N].md`

### 2.2 Code Review (`/ai-first-dev:review`)

| Metrik | Wert |
|--------|------|
| Status | [ ] PASSED / [ ] NEEDS FIX |
| Critical Issues | 0 |
| High Issues | 0 |
| Medium Issues | [X] |
| Low Issues | [X] |

**Code Review Report:** `reports/ai-first-dev:code-review-phase-[N].md`

### 2.3 Security (`/ai-first-dev:security-scan`)

| Check | Status |
|-------|--------|
| Dependency Audit | [ ] Bestanden |
| OWASP Checks | [ ] Bestanden |
| Secrets Scan | [ ] Bestanden |

**Security Report:** `reports/security-phase-[N].md`

---

## 3. Dokumentation

| Dokument | Status | Aktualisiert |
|----------|--------|--------------|
| README.md | [ ] Aktuell | [DATUM] |
| API-Dokumentation | [ ] Aktuell | [DATUM] |
| Phase-Details.md | [ ] Vollständig | [DATUM] |
| Code-Kommentare | [ ] Wo nötig | - |

---

## 4. Tests

| Bereich | Abdeckung | Status |
|---------|-----------|--------|
| Unit Tests | [X]% | [ ] Ausreichend |
| Kernfunktionen getestet | - | [ ] Ja |
| Edge Cases abgedeckt | - | [ ] Ja |

**Test Report:** `reports/test-coverage-phase-[N].md`

---

## 5. Git Status

| Check | Status |
|-------|--------|
| Alle Änderungen committed | [ ] Ja |
| Branch aktuell mit main | [ ] Ja |
| Keine Merge-Konflikte | [ ] Ja |
| Commit-Messages semantisch | [ ] Ja |

```bash
# Letzte Commits dieser Phase
git log --oneline -10
```

---

## 6. Execution Report

| Status | Beschreibung |
|--------|--------------|
| [ ] Erstellt | `/ai-first-dev:execution-report [N]` wurde ausgeführt |
| [ ] Geprüft | Report enthält alle Informationen |
| [ ] Abgelegt | `reports/ai-first-dev:execution-report-phase-[N].md` |

---

## 7. Offene Punkte

### 7.1 Bekannte Limitierungen

| # | Limitation | Geplant für Phase |
|---|------------|-------------------|
| 1 | [Beschreibung] | Phase [X] |
| 2 | [Beschreibung] | Backlog |

### 7.2 Technical Debt

| # | Beschreibung | Priorität |
|---|--------------|-----------|
| 1 | [Beschreibung] | Hoch/Mittel/Niedrig |

### 7.3 Offene Entscheidungen

| # | Entscheidung | Deadline |
|---|--------------|----------|
| 1 | [Beschreibung] | Phase [X] |

---

## 8. Signoff

### Checkliste für Abschluss

- [ ] Alle Deliverables erfüllt (Sektion 1)
- [ ] Alle Quality Gates bestanden (Sektion 2)
- [ ] Dokumentation aktuell (Sektion 3)
- [ ] Tests ausreichend (Sektion 4)
- [ ] Git Status sauber (Sektion 5)
- [ ] Execution Report erstellt (Sektion 6)
- [ ] Offene Punkte dokumentiert (Sektion 7)

### Abschluss

```
┌────────────────────────────────────────────────────────────────┐
│                                                                │
│  Phase [N]: [Titel]                                           │
│                                                                │
│  ☐ ABGESCHLOSSEN am [DATUM]                                   │
│                                                                │
│  Nächster Schritt:                                            │
│  → /ai-first-dev:prepare-phase [N+1]                                       │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

## Automatisierungs-Hinweise

### Exit-Kriterien für automatische Phase-Transition

Eine Phase gilt als **abgeschlossen**, wenn:

```json
{
  "phase_complete": true,
  "conditions": {
    "all_deliverables_done": true,
    "validation_passed": true,
    "code_review_passed": true,
    "security_scan_passed": true,
    "execution_report_created": true
  },
  "next_action": "prepare-phase:[N+1]"
}
```

### Blockierende Bedingungen

Phase kann **NICHT** abgeschlossen werden, wenn:
- `/ai-first-dev:validate` fehlgeschlagen
- `/ai-first-dev:review` Status = NEEDS_FIX
- Deliverables unvollständig
- Execution Report fehlt

---

*Erstellt basierend auf plan.md Phase [N]*

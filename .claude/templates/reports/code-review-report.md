# Code Review Report

> **Generiert:** [DATUM]
> **Projekt:** [PROJEKT-NAME]
> **Scope:** All | Changed | File: [path]
> **Reviewer:** Claude Code (`/ai-first-dev:review`)

---

## 1. Übersicht

| Severity | Anzahl | Status |
|----------|--------|--------|
| 🔴 Critical | X | Muss behoben werden |
| 🟠 High | X | Sollte behoben werden |
| 🟡 Medium | X | Empfohlen |
| 🔵 Low | X | Optional |

**Gesamt:** X Issues gefunden

**Review-Status:** ✅ Bestanden | ⚠️ Mit Anmerkungen | ❌ Blockiert (Critical/High Issues)

---

## 2. Issue-Liste

### 2.1 Critical Issues 🔴

| ID | Datei | Zeile | Kategorie | Issue |
|----|-------|-------|-----------|-------|
| CR-001 | [file.ts] | [42] | Security | [Beschreibung] |

**CR-001: [Kurztitel]**

```
Datei: [file.ts]
Zeilen: [42-50]
Kategorie: Security
Standard: .claude/standards/security.md
```

**Problem:**
[Detaillierte Beschreibung des Problems]

**Betroffener Code:**
```typescript
// Problematischer Code
[code snippet]
```

**Empfohlene Lösung:**
```typescript
// Korrigierter Code
[code snippet]
```

**Begründung:**
[Warum ist das ein Problem? Verweis auf Standard.]

---

### 2.2 High Issues 🟠

| ID | Datei | Zeile | Kategorie | Issue |
|----|-------|-------|-----------|-------|
| CR-002 | [file.ts] | [X] | Performance | [Beschreibung] |

[Details analog zu Critical]

---

### 2.3 Medium Issues 🟡

| ID | Datei | Zeile | Kategorie | Issue |
|----|-------|-------|-----------|-------|
| CR-003 | [file.ts] | [X] | Code Quality | [Beschreibung] |

[Details analog zu Critical]

---

### 2.4 Low Issues 🔵

| ID | Datei | Zeile | Kategorie | Issue |
|----|-------|-------|-----------|-------|
| CR-004 | [file.ts] | [X] | Style | [Beschreibung] |

[Details analog zu Critical]

---

## 3. Kategorien-Analyse

| Kategorie | Anzahl | Prozent |
|-----------|--------|---------|
| Security | X | XX% |
| Performance | X | XX% |
| Logic | X | XX% |
| Code Quality | X | XX% |
| Standards | X | XX% |

---

## 4. Betroffene Dateien

| Datei | Critical | High | Medium | Low | Gesamt |
|-------|----------|------|--------|-----|--------|
| [file1.ts] | X | X | X | X | X |
| [file2.ts] | X | X | X | X | X |

---

## 5. Positive Aspekte ✨

- ✅ [Positiver Aspekt 1]
- ✅ [Positiver Aspekt 2]
- ✅ [Positiver Aspekt 3]

---

## 6. Referenzierte Standards

| Standard | Verweis | Relevante Issues |
|----------|---------|------------------|
| `security.md` | Section X | CR-001 |
| `code-style-linting.md` | Section Y | CR-003 |
| `api-design.md` | Section Z | CR-002 |

---

## 7. Nächste Schritte

### Blockierende Issues (vor Merge beheben)

- [ ] CR-001: [Kurztitel] → wird von `/ai-first-dev:review` automatisch gefixt
- [ ] CR-002: [Kurztitel] → wird von `/ai-first-dev:review` automatisch gefixt

### Empfohlene Verbesserungen

- [ ] CR-003: [Kurztitel]
- [ ] CR-004: [Kurztitel]

### Nach Fixes

```bash
# Validierung erneut ausführen
/ai-first-dev:validate --quick

# Re-Review der geänderten Dateien
/ai-first-dev:review --scope=changed
```

---

## 8. Anhang

### 8.1 Review-Konfiguration

| Setting | Wert |
|---------|------|
| Scope | [all/changed/file] |
| Standards Version | [Datum] |
| Ignored Patterns | [falls vorhanden] |

### 8.2 Changelog

| Datum | Reviewer | Änderung |
|-------|----------|----------|
| [DATUM] | Claude Code | Initial Review |

---

*Report erstellt mit `/ai-first-dev:review`*

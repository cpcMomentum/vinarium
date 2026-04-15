# Validation Report

> **Generiert:** [DATUM]
> **Projekt:** [PROJEKT-NAME]
> **Modus:** Full | Quick | Tests | Lint | Build

---

## 1. Übersicht

| Check | Status | Details |
|-------|--------|---------|
| Linting | ✅/❌ | [X Errors, Y Warnings] |
| Formatting | ✅/❌ | [X Dateien nicht formatiert] |
| Type Check | ✅/❌ | [X Errors] |
| Unit Tests | ✅/❌ | [X passed, Y failed, Z skipped] |
| Coverage | ✅/⚠️/❌ | [XX%] (Ziel: 80%) |
| Build | ✅/❌ | [Build Status] |
| Security | ✅/⚠️/❌ | [X vulnerabilities] |
| Dependencies | ✅/⚠️ | [X outdated] |

**Gesamt-Status:** ✅ Bestanden | ⚠️ Mit Warnungen | ❌ Fehlgeschlagen

---

## 2. Linting & Formatting

### 2.1 Linting Ergebnisse

| Severity | Anzahl | Trend |
|----------|--------|-------|
| Errors | X | ↗️/↘️/→ |
| Warnings | X | ↗️/↘️/→ |

**Top Issues:**

| Datei | Zeile | Regel | Beschreibung |
|-------|-------|-------|--------------|
| [file.ts] | [42] | [rule-name] | [Beschreibung] |

### 2.2 Formatting

| Status | Anzahl Dateien |
|--------|----------------|
| ✅ Formatiert | X |
| ❌ Nicht formatiert | X |

---

## 3. Type Checking

| Status | Anzahl |
|--------|--------|
| ✅ Ohne Fehler | X Dateien |
| ❌ Mit Fehlern | X Dateien |

**Type Errors:**

| Datei | Zeile | Error |
|-------|-------|-------|
| [file.ts] | [42] | [Error Message] |

---

## 4. Tests

### 4.1 Test-Ergebnisse

| Metrik | Wert |
|--------|------|
| Total | X |
| Passed | X (XX%) |
| Failed | X |
| Skipped | X |
| Duration | X.Xs |

### 4.2 Fehlgeschlagene Tests

| Test | Datei | Error |
|------|-------|-------|
| [test name] | [file.test.ts] | [Error] |

### 4.3 Coverage

| Bereich | Coverage | Status |
|---------|----------|--------|
| Statements | XX% | ✅/⚠️/❌ |
| Branches | XX% | ✅/⚠️/❌ |
| Functions | XX% | ✅/⚠️/❌ |
| Lines | XX% | ✅/⚠️/❌ |

**Niedrige Coverage:**

| Datei | Coverage | Empfehlung |
|-------|----------|------------|
| [file.ts] | XX% | Tests hinzufügen für [Bereich] |

---

## 5. Build

| Aspekt | Status | Details |
|--------|--------|---------|
| Compilation | ✅/❌ | [Details] |
| Bundle Size | ✅/⚠️ | [X MB] |
| Build Time | ✅/⚠️ | [X.Xs] |

---

## 6. Security

### 6.1 Vulnerabilities

| Severity | Anzahl |
|----------|--------|
| Critical | X |
| High | X |
| Medium | X |
| Low | X |

### 6.2 Details

| Package | Severity | Vulnerability | Fix |
|---------|----------|---------------|-----|
| [pkg] | Critical | [CVE-XXXX] | Upgrade to X.Y.Z |

---

## 7. Dependencies

### 7.1 Outdated

| Package | Current | Latest | Type |
|---------|---------|--------|------|
| [pkg] | X.Y.Z | A.B.C | Major/Minor/Patch |

### 7.2 Audit

| Issue | Count |
|-------|-------|
| Deprecated | X |
| Outdated (Major) | X |
| Outdated (Minor) | X |

---

## 8. Empfehlungen

### Sofort beheben (Blocker)

1. [Kritisches Issue 1]
2. [Kritisches Issue 2]

### Sollte behoben werden

1. [Wichtiges Issue 1]
2. [Wichtiges Issue 2]

### Nice to have

1. [Verbesserung 1]
2. [Verbesserung 2]

---

## 9. Nächste Schritte

- [ ] Kritische Issues beheben
- [ ] `/ai-first-dev:review` ausführen
- [ ] Tests für niedrige Coverage hinzufügen

---

*Report erstellt mit `/ai-first-dev:validate`*

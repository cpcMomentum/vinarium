# Root Cause Analysis Report

> **Issue-ID:** [RCA-YYYY-MM-DD-NNN]
> **Erstellt:** [DATUM]
> **Status:** [Offen | In Analyse | Fix implementiert | Verifiziert | Geschlossen]

---

## 1. Issue-Übersicht

### 1.1 Zusammenfassung

[1-2 Sätze die das Problem beschreiben]

### 1.2 Severity

| Level | Beschreibung |
|-------|--------------|
| **[X]** | [Kritisch / Hoch / Mittel / Niedrig] |

**Severity-Definitionen:**
- **Kritisch:** System nicht nutzbar, Datenverlust, Sicherheitslücke
- **Hoch:** Kernfunktion betroffen, Workaround möglich
- **Mittel:** Feature eingeschränkt, User Experience beeinträchtigt
- **Niedrig:** Kosmetisch, Edge Case, keine Kernfunktion betroffen

### 1.3 Betroffene Komponenten

| Komponente | Pfad |
|------------|------|
| [Komponente 1] | `src/...` |
| [Komponente 2] | `src/...` |

---

## 2. Symptome

### 2.1 Beobachtetes Verhalten

[Was passiert? Wie äußert sich das Problem?]

### 2.2 Erwartetes Verhalten

[Was sollte stattdessen passieren?]

### 2.3 Reproduktion

**Schritte:**
1. [Schritt 1]
2. [Schritt 2]
3. [Schritt 3]

**Reproduzierbarkeit:** [Immer / Intermittierend / Selten]

**Umgebung:**
- OS: [z.B. macOS 14.0]
- Browser/Runtime: [z.B. Chrome 120 / Node 20.x]
- Relevante Konfiguration: [z.B. Feature Flag X aktiviert]

### 2.4 Fehlermeldungen

```
[Exakte Fehlermeldung / Stack Trace]
```

---

## 3. Root Cause Analyse

### 3.1 5-Why Analyse

| # | Frage | Antwort |
|---|-------|---------|
| 1 | Warum tritt das Problem auf? | [Antwort] |
| 2 | Warum [Antwort 1]? | [Antwort] |
| 3 | Warum [Antwort 2]? | [Antwort] |
| 4 | Warum [Antwort 3]? | [Antwort] |
| 5 | Warum [Antwort 4]? | **ROOT CAUSE:** [Fundamentale Ursache] |

### 3.2 Root Cause Kategorie

| Kategorie | Beschreibung |
|-----------|--------------|
| [ ] Code-Fehler | Bug in der Implementierung |
| [ ] Design-Fehler | Architektur-/Design-Problem |
| [ ] Fehlende Validierung | Input nicht geprüft |
| [ ] Race Condition | Timing-Problem |
| [ ] Externe Abhängigkeit | Third-Party Problem |
| [ ] Konfiguration | Falsche/fehlende Konfiguration |
| [ ] Dokumentation | Fehlerhafte/fehlende Docs |
| [ ] Test-Lücke | Nicht getesteter Pfad |

### 3.3 Betroffene Dateien

| Datei | Zeilen | Problem |
|-------|--------|---------|
| `src/file1.ts` | 42-56 | [Beschreibung] |
| `src/file2.ts` | 123 | [Beschreibung] |

---

## 4. Fix-Strategie

### 4.1 Lösungsansatz

[Beschreibung der geplanten Lösung]

### 4.2 Änderungen

| Datei | Änderung |
|-------|----------|
| `src/file1.ts` | [Was wird geändert] |
| `src/file2.ts` | [Was wird geändert] |

### 4.3 Risiko-Bewertung

| Risiko | Wahrscheinlichkeit | Mitigation |
|--------|-------------------|------------|
| [Risiko 1] | [Hoch/Mittel/Niedrig] | [Strategie] |

### 4.4 Alternativen (betrachtet)

| Alternative | Pro | Con | Entscheidung |
|-------------|-----|-----|--------------|
| [Alternative A] | [Pro] | [Con] | Gewählt / Verworfen |
| [Alternative B] | [Pro] | [Con] | Gewählt / Verworfen |

---

## 5. Präventionsmaßnahmen

### 5.1 Kurzfristig (dieser Fix)

- [ ] [Maßnahme 1]
- [ ] [Maßnahme 2]

### 5.2 Langfristig (Prozess-Verbesserung)

| Maßnahme | Verantwortlich | Deadline |
|----------|---------------|----------|
| [z.B. Test hinzufügen] | [Wer] | [Wann] |
| [z.B. Code Review Checklist erweitern] | [Wer] | [Wann] |
| [z.B. Monitoring hinzufügen] | [Wer] | [Wann] |

### 5.3 Erkenntnisse für plan.md

[Falls relevant: Welche Risiken/Learnings sollten in plan.md aufgenommen werden?]

```markdown
## 7. Risiken (Update)

| ID | Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|----|--------|-------------------|--------|------------|
| RX | [Neues Risiko] | [W] | [I] | [Strategie] |
```

---

## 6. Verifikation

### 6.1 Test-Plan

| Test | Typ | Status |
|------|-----|--------|
| [Test 1] | Unit | [ ] Bestanden |
| [Test 2] | Integration | [ ] Bestanden |
| [Test 3] | Manuell | [ ] Bestanden |

### 6.2 Regressions-Check

- [ ] Bestehende Tests laufen durch
- [ ] `/ai-first-dev:validate` bestanden
- [ ] `/ai-first-dev:review` bestanden

### 6.3 Deployment-Verifikation

- [ ] Staging getestet
- [ ] Production getestet
- [ ] Monitoring zeigt keine neuen Fehler

---

## 7. Timeline

| Datum | Event |
|-------|-------|
| [DATUM] | Issue entdeckt |
| [DATUM] | RCA gestartet |
| [DATUM] | Root Cause identifiziert |
| [DATUM] | Fix implementiert |
| [DATUM] | Fix verifiziert |
| [DATUM] | Issue geschlossen |

---

## 8. Referenzen

- **Related Issues:** [Links zu verwandten Issues]
- **PR/Commit:** [Link zum Fix-Commit]
- **Dokumentation:** [Links zu relevanter Dokumentation]

---

*Erstellt mit `/ai-first-dev:bugfix --rca-only` basierend auf Issue-Beschreibung*

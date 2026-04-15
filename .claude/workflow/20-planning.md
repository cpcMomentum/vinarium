# Planungsphase (Planning)

In dieser Phase wird aus der Produktbeschreibung ein technischer Plan erstellt. Der Plan definiert Architektur, Tech-Stack und strukturierte Entwicklungsphasen.

> **Nächste Phase:** `30-development.md` (Entwicklung)

---

## Übersicht

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PLANUNGSPHASE                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  INPUT                                                                  │
│  ─────                                                                  │
│  • Produktbeschreibung.md (aus Phase 1)                                 │
│  • .claude/techstack.md (Technologie-Vorgaben)                                  │
│  • dev.md (Entwicklungs-Standards)                                      │
│                                                                         │
│  PROZESS                                                                │
│  ───────                                                                │
│                                                                         │
│  ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐       │
│  │  Analyse der    │──▶│  Tech-Stack     │──▶│   Phasen        │       │
│  │  Anforderungen  │   │  Entscheidungen │   │   definieren    │       │
│  └─────────────────┘   └─────────────────┘   └─────────────────┘       │
│           │                    │                      │                 │
│           ▼                    ▼                      ▼                 │
│     Kernfunktionen       Architektur-           MVP-Scope +            │
│     priorisieren         Entscheidungen         Folge-Phasen           │
│                                                                         │
│  OUTPUT                                                                 │
│  ──────                                                                 │
│  • plan.md (Technischer Masterplan)                                     │
│                                                                         │
│  COMMAND                                                                │
│  ───────                                                                │
│  /ai-first-dev:create-plan → Erstellt strukturierten plan.md                         │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 1. Voraussetzungen

Bevor die Planungsphase beginnt:

| Voraussetzung | Prüfung |
|---------------|---------|
| Produktbeschreibung | `docs/produktbeschreibung.md` existiert und ist vollständig |
| Struktur | Alle 7 Sektionen aus Template ausgefüllt |
| Klarheit | Vision, Zielgruppe, Kernfunktionen klar definiert |

---

## 2. Der /ai-first-dev:create-plan Command

### 2.1 Aufruf

```bash
/ai-first-dev:create-plan
```

**Was passiert:**
1. Lädt Produktbeschreibung
2. Konsultiert .claude/techstack.md und dev.md
3. Analysiert Anforderungen
4. Schlägt Architektur vor
5. Definiert Phasen (MVP + Erweiterungen)
6. Erstellt plan.md

### 2.2 Interaktiver Modus

Der Command fragt bei kritischen Entscheidungen nach:

```
┌─────────────────────────────────────────────────────────────┐
│                  ARCHITEKTUR-ENTSCHEIDUNG                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Für [Feature X] gibt es mehrere Optionen:                  │
│                                                             │
│  A) [Option A] - [Kurzbeschreibung]                         │
│     Pro: [Vorteile]                                         │
│     Con: [Nachteile]                                        │
│                                                             │
│  B) [Option B] - [Kurzbeschreibung]                         │
│     Pro: [Vorteile]                                         │
│     Con: [Nachteile]                                        │
│                                                             │
│  Empfehlung: [A/B] weil [Begründung]                        │
│                                                             │
│  Welche Option bevorzugst du?                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Plan-Struktur

### 3.1 Pflicht-Sektionen

Jeder plan.md muss folgende Struktur haben:

```markdown
# [Projektname] - Technischer Plan

## 1. Übersicht
- Vision (1-2 Sätze)
- MVP-Scope
- Ziel-Architektur

## 2. Tech-Stack
- Frontend
- Backend
- Datenbank
- Infrastructure
- Begründung für Abweichungen von .claude/techstack.md

## 3. Architektur
- System-Diagramm
- Komponenten-Übersicht
- Datenfluss

## 4. Phasen-Übersicht
- Phase 0: Setup
- Phase 1: MVP Core
- Phase 2-N: Erweiterungen

## 5. Phase-Details
### Phase 0: Projekt-Setup
### Phase 1: [MVP-Titel]
### Phase N: [Feature-Titel]

## 6. Offene Entscheidungen
- [Entscheidung die noch getroffen werden muss]

## 7. Risiken
- Technische Risiken
- Abhängigkeiten
```

### 3.2 Phase-Format

Jede Phase in plan.md muss diesem Format folgen (damit `/ai-first-dev:prepare-phase` funktioniert):

```markdown
### Phase N: [Titel]

**Ziel:** [1-2 Sätze was diese Phase erreicht]

**Abhängigkeiten:** Phase N-1 abgeschlossen

**Schritte:**
- N.1 [Schritt-Titel]: [Kurzbeschreibung]
- N.2 [Schritt-Titel]: [Kurzbeschreibung]
- N.3 [Schritt-Titel]: [Kurzbeschreibung]

**Deliverables:**
- [Konkret, messbar 1]
- [Konkret, messbar 2]

**Tech-Fokus:** [Haupttechnologien dieser Phase]
```

---

## 4. Planungs-Prinzipien

### 4.1 MVP-First

```
┌─────────────────────────────────────────────────────────────┐
│                      MVP-FIRST PRINZIP                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Phase 1 (MVP) sollte:                                      │
│  ✅ Kernproblem lösen                                       │
│  ✅ In 1-2 Wochen umsetzbar sein                            │
│  ✅ Lauffähiges Produkt liefern                             │
│  ✅ Feedback ermöglichen                                    │
│                                                             │
│  Phase 1 sollte NICHT:                                      │
│  ❌ Alle Features enthalten                                 │
│  ❌ Perfekt sein                                            │
│  ❌ Skalierbar für 1M User sein                             │
│  ❌ Alle Edge Cases abdecken                                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 4.2 Inkrementelle Phasen

```
Phase 0: Setup
    └─▶ Repository, Docker, CI/CD Basis

Phase 1: MVP Core
    └─▶ Kernfunktion lauffähig

Phase 2: Essential Features
    └─▶ Must-Have Features für Launch

Phase 3: Nice-to-Have
    └─▶ Zusätzliche Features

Phase 4+: Skalierung
    └─▶ Performance, Monitoring, etc.
```

### 4.3 Abhängigkeiten beachten

```
┌─────────────────────────────────────────────────────────────┐
│                   ABHÄNGIGKEITS-MATRIX                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Jede Phase definiert:                                      │
│                                                             │
│  1. Was sie BRAUCHT (von vorherigen Phasen)                 │
│     - APIs, Datenmodelle, Services                          │
│                                                             │
│  2. Was sie LIEFERT (für folgende Phasen)                   │
│     - Neue APIs, Modelle, Komponenten                       │
│                                                             │
│  3. Was PARALLEL laufen kann                                │
│     - Unabhängige Features                                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 5. Tech-Stack Entscheidungen

### 5.1 Standard-Stack (aus .claude/techstack.md)

| Layer | Standard | Wann abweichen |
|-------|----------|----------------|
| Frontend | Next.js 15+ | Nie ohne Begründung |
| Backend | FastAPI | Strapi für CMS-lastige Apps |
| Database | PostgreSQL | SQLite für einfache MVPs |
| Auth | State-of-art Library | Nie custom bauen |
| Infra | Docker Compose | Kubernetes nur bei Bedarf |

### 5.2 Entscheidungs-Dokumentation

Jede Abweichung vom Standard muss dokumentiert werden:

```markdown
## Tech-Stack Abweichungen

### [Technologie]
- **Standard:** [Was .claude/techstack.md vorgibt]
- **Gewählt:** [Was wir nutzen]
- **Begründung:** [Warum]
- **Trade-offs:** [Was wir dafür aufgeben]
```

---

## 6. Architektur-Patterns

### 6.1 Typische MVP-Architektur

```
┌─────────────────────────────────────────────────────────────┐
│                     MVP ARCHITEKTUR                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────┐      ┌─────────────┐      ┌─────────────┐ │
│  │   Browser   │◀────▶│   Next.js   │◀────▶│   FastAPI   │ │
│  │   (User)    │      │  (Frontend) │      │  (Backend)  │ │
│  └─────────────┘      └─────────────┘      └──────┬──────┘ │
│                                                   │        │
│                                            ┌──────▼──────┐ │
│                                            │  PostgreSQL │ │
│                                            │ (Database)  │ │
│                                            └─────────────┘ │
│                                                             │
│  Alles in Docker Compose                                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 6.2 Entscheidungspunkte

| Entscheidung | Optionen | Kriterien |
|--------------|----------|-----------|
| **Rendering** | SSR vs. CSR vs. Static | SEO-Bedarf, Dynamik |
| **API-Style** | REST vs. GraphQL | Komplexität, Team-Erfahrung |
| **Auth** | Session vs. JWT | Use Case, Skalierung |
| **State** | Server vs. Client | Echtzeit-Bedarf |
| **Database** | SQL vs. NoSQL | Datenstruktur, Relationen |

---

## 7. Qualitätskriterien für plan.md

### 7.1 Checkliste

Nach Erstellung von plan.md prüfen:

- [ ] **Vollständig:** Alle Pflicht-Sektionen ausgefüllt
- [ ] **Konsistent:** Tech-Stack passt zu Anforderungen
- [ ] **Umsetzbar:** Phasen sind realistisch geschnitten
- [ ] **Klar:** Jede Phase hat klare Deliverables
- [ ] **Abhängig:** Phasen-Abhängigkeiten dokumentiert
- [ ] **Begründet:** Abweichungen von Standards erklärt

### 7.2 Anti-Patterns vermeiden

| Anti-Pattern | Problem | Lösung |
|--------------|---------|--------|
| **Mega-MVP** | Phase 1 zu groß | Radikal kürzen |
| **Tech-Overkill** | Zu viele Technologien | Auf Standard-Stack beschränken |
| **Vage Phasen** | "Irgendwie fertig machen" | Konkrete Deliverables |
| **Fehlende Deps** | Phase 3 braucht was aus Phase 5 | Abhängigkeiten prüfen |

---

## 8. Übergang zur Entwicklung

Nach Abschluss der Planungsphase:

```
┌─────────────────────────────────────────────────────────────┐
│                    PHASE 2 → PHASE 3                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. plan.md ist vollständig                                 │
│     └─▶ Alle Phasen definiert                               │
│     └─▶ Tech-Stack festgelegt                               │
│     └─▶ Architektur klar                                    │
│                                                             │
│  2. Projekt konfigurieren                                    │
│     └─▶ /ai-first-dev:setup (neues Projekt)                 │
│     └─▶ /ai-first-dev:adopt (bestehendes Projekt)           │
│     └─▶ CLAUDE.md wird automatisch erstellt                 │
│                                                             │
│  3. Erste Phase detaillieren                                │
│     └─▶ /ai-first-dev:prepare-phase 0 (Setup)                            │
│     └─▶ /ai-first-dev:prepare-phase 1 (MVP)                              │
│                                                             │
│  4. Entwicklung starten                                     │
│     └─▶ PIV-Loop für jedes Feature                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Referenzen

- `30-development.md` - Naechste Phase
- `techstack.md` - Standard Tech-Stack (aus Plugin oder Projekt)
- `dev.md` - Entwicklungs-Standards
- Plan-Template aus Plugin

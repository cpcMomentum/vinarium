# [Projektname] - Technischer Plan

> **Erstellt:** [DATUM]
> **Basiert auf:** docs/produktbeschreibung.md
> **Tech-Baseline:** [DATUM]

---

## 1. Übersicht

### 1.1 Vision

[1-2 Sätze die das Kernziel des Produkts beschreiben. Aus Produktbeschreibung Sektion 1.]

### 1.2 MVP-Scope

[Was Phase 1 konkret liefert - die minimale Version die Wert schafft.]

### 1.3 Ziel-Architektur

[Kurze Beschreibung der technischen Architektur in 2-3 Sätzen.]

---

## 2. Tech-Stack

| Layer | Technologie | Version | Begründung |
|-------|-------------|---------|------------|
| Frontend | Next.js | 15.x | Standard (App Router, Server Components) |
| Styling | Tailwind CSS | 3.x | Standard |
| UI Components | shadcn/ui | - | Standard |
| Backend | FastAPI | 0.11x | Standard |
| Database | PostgreSQL | 16.x | Standard |
| ORM | Prisma / SQLAlchemy | - | [Je nach Stack] |
| Auth | [Library] | x.x | [Begründung] |
| Validation | Zod / Pydantic | - | Standard |
| Infra | Docker Compose | - | Standard |

### 2.1 Abweichungen von techstack.md

[Falls Abweichungen vom Standard-Stack nötig sind, hier dokumentieren:]

**[Technologie]:**
- **Standard:** [Was techstack.md vorgibt]
- **Gewählt:** [Was wir stattdessen nutzen]
- **Begründung:** [Warum die Abweichung]
- **Trade-offs:** [Was wir dafür aufgeben]

[Falls keine Abweichungen: "Keine - Standard-Stack wird verwendet."]

---

## 3. Architektur

### 3.1 System-Diagramm

```
┌─────────────────────────────────────────────────────────────┐
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
│  [Anpassen an tatsächliche Architektur]                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Komponenten

| Komponente | Verantwortung | Technologie |
|------------|---------------|-------------|
| Frontend | UI, Client-Side Logic | Next.js, React |
| API | Business Logic, REST Endpoints | FastAPI |
| Database | Datenpersistenz | PostgreSQL |
| [Weitere] | [Beschreibung] | [Tech] |

### 3.3 Datenfluss

**Hauptflows:**

1. **[Flow-Name]:** [Kurzbeschreibung des Datenflusses]
2. **[Flow-Name]:** [Kurzbeschreibung]

---

## 4. Phasen-Übersicht

| Phase | Titel | Fokus | Abhängigkeit |
|-------|-------|-------|--------------|
| 0 | Projekt-Setup | Infrastruktur, Basis | - |
| 1 | MVP Core | [Kernfunktion] | Phase 0 |
| 2 | [Titel] | [Feature-Gruppe] | Phase 1 |
| 3 | [Titel] | [Feature-Gruppe] | Phase 2 |
| N | [Titel] | [Feature-Gruppe] | Phase N-1 |

---

## 5. Phase-Details

### Phase 0: Projekt-Setup

**Ziel:** Lauffähige Entwicklungsumgebung mit allen Basis-Komponenten

**Abhängigkeiten:** Keine

**Schritte:**
- 0.1 Repository Setup: GitHub Repo erstellen, .gitignore, README.md, LICENSE.md
- 0.2 Docker Setup: docker-compose.yml mit allen Services
- 0.3 Frontend Basis: Next.js 15 Projekt mit App Router, Tailwind, shadcn/ui
- 0.4 Backend Basis: FastAPI Projekt mit Basis-Struktur
- 0.5 Database Setup: PostgreSQL Container, initiales Schema, Migrations-Setup

**Deliverables:**
- [ ] Repository mit vollständiger Projektstruktur
- [ ] `docker compose up` startet alle Services fehlerfrei
- [ ] Frontend erreichbar unter http://localhost:3000
- [ ] Backend API erreichbar unter http://localhost:8000
- [ ] Database-Migrations funktionieren
- [ ] Health-Check Endpoints implementiert

**Tech-Fokus:** Docker, Next.js, FastAPI, PostgreSQL

---

### Phase 1: [MVP-Titel]

**Ziel:** [1-2 Sätze was diese Phase erreicht - der Kernwert für User]

**Abhängigkeiten:** Phase 0 abgeschlossen

**Schritte:**
- 1.1 [Schritt-Titel]: [Kurzbeschreibung was gemacht wird]
- 1.2 [Schritt-Titel]: [Kurzbeschreibung]
- 1.3 [Schritt-Titel]: [Kurzbeschreibung]
- 1.4 [Schritt-Titel]: [Kurzbeschreibung]

**Deliverables:**
- [ ] [Konkretes, messbares Ergebnis 1]
- [ ] [Konkretes, messbares Ergebnis 2]
- [ ] [Konkretes, messbares Ergebnis 3]
- [ ] Unit Tests für Kernlogik
- [ ] Basis-Dokumentation

**Tech-Fokus:** [Haupttechnologien dieser Phase]

---

### Phase 2: [Titel]

**Ziel:** [Was diese Phase erreicht]

**Abhängigkeiten:** Phase 1 abgeschlossen

**Schritte:**
- 2.1 [Schritt-Titel]: [Kurzbeschreibung]
- 2.2 [Schritt-Titel]: [Kurzbeschreibung]
- 2.3 [Schritt-Titel]: [Kurzbeschreibung]

**Deliverables:**
- [ ] [Ergebnis 1]
- [ ] [Ergebnis 2]

**Tech-Fokus:** [Technologien]

---

### Phase N: [Titel]

[Analog zu vorherigen Phasen]

---

## 6. Offene Entscheidungen

| ID | Entscheidung | Optionen | Betrifft Phase | Status |
|----|--------------|----------|----------------|--------|
| D1 | [Beschreibung] | A, B | Phase X | Offen |
| D2 | [Beschreibung] | A, B, C | Phase Y | Offen |

**Details:**

### D1: [Entscheidungs-Titel]

**Kontext:** [Warum diese Entscheidung getroffen werden muss]

**Optionen:**
- **A:** [Beschreibung] - Pro: [Vorteile], Con: [Nachteile]
- **B:** [Beschreibung] - Pro: [Vorteile], Con: [Nachteile]

**Empfehlung:** [A/B] weil [Begründung]

**Deadline:** Vor Start von Phase [X]

---

## 7. Risiken

| ID | Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|----|--------|-------------------|--------|------------|
| R1 | [Beschreibung] | Hoch/Mittel/Niedrig | Hoch/Mittel/Niedrig | [Strategie] |
| R2 | [Beschreibung] | ... | ... | [Strategie] |

**Details:**

### R1: [Risiko-Titel]

**Beschreibung:** [Was könnte passieren]
**Trigger:** [Woran erkennen wir, dass es eintritt]
**Mitigation:** [Was tun wir dagegen]
**Fallback:** [Was tun wir, wenn es eintritt]

---

## Anhang

### A. Datenmodelle (Übersicht)

Basierend auf Produktbeschreibung Sektion 6:

| Entity | Beschreibung | Wichtige Felder |
|--------|--------------|-----------------|
| [Entity 1] | [Was sie repräsentiert] | id, name, ... |
| [Entity 2] | [Beschreibung] | id, ... |

**Beziehungen:**
- [Entity 1] → [Entity 2]: [Beziehungstyp, z.B. 1:n]

### B. API-Übersicht (geplant)

| Phase | Method | Endpoint | Beschreibung |
|-------|--------|----------|--------------|
| 1 | POST | /api/auth/register | User Registration |
| 1 | POST | /api/auth/login | User Login |
| 1 | GET | /api/[resource] | [Beschreibung] |
| 2 | ... | ... | ... |

### C. UI-Screens (geplant)

| Phase | Screen | Route | Beschreibung |
|-------|--------|-------|--------------|
| 1 | Landing | / | Startseite |
| 1 | Login | /login | Anmeldeseite |
| 1 | Dashboard | /dashboard | Hauptansicht |
| 2 | ... | ... | ... |

---

*Erstellt mit `/ai-first-dev:create-plan` basierend auf docs/produktbeschreibung.md*

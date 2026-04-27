# Phase 4: Regalansicht + Drag & Drop + Regal-Umbau

**Status:** Geplant
**Abhängigkeiten:** Phase 3 abgeschlossen (PR #4 gemerged, develop @ 07c77dc)
**Erstellt:** 2026-04-27
**Tech-Baseline:** NC 33.0.2, PHP 8.4, Vue 3.5, vue-draggable-plus

---

## 1. Übersicht

### 1.1 Ziel
Drag & Drop zwischen Parkzone und Slots. Regal-Konfigurationsdialog (Ebenen, Spalten vorne/hinten pro Fach, Versatz-Alternierung). Bottle-Swap bei belegtem Ziel-Slot.

### 1.2 Kontext aus Phase 3
- SimpleShelfView: Click-Platzierung funktioniert, Fächer untereinander, Ebenen bottom-up
- CellarService.reconfigureCompartment: bereits implementiert (Parkzonen-Migration, Transaction)
- BottleService.moveBottle: Slot-Frei-Check mit SlotOccupiedException
- 234 Slots im Default-Regal (6 Fächer × 3 Ebenen × 13 Slots)

### 1.3 Deliverables
- [ ] vue-draggable-plus: Drag & Drop Parkzone ↔ Slot + Slot ↔ Slot
- [ ] Bottle-Swap: Drop auf belegten Slot tauscht beide Flaschen
- [ ] ShelfConfigDialog: Ebenen/Spalten bearbeiten pro Fach, Versatz-Toggle
- [ ] Alternierung: ungerade Ebenen front=7/back=6, gerade front=6/back=7
- [ ] +/- Buttons für Spaltenanzahl pro Reihe (perspektivisch)
- [ ] Parkzonen-Warnung + 10-Sek-Undo nach Reconfigure
- [ ] Mobile-Viewport: horizontal scrollbar

---

## 4. Schritt-für-Schritt Umsetzung

### Step 4.1: vue-draggable-plus installieren + ShelfView auf D&D umbauen

**Subtasks:**
- `npm install vue-draggable-plus`
- ShelfView: jeder Slot wird ein `<VueDraggable>` Container mit max 1 Item
- Parkzone wird ein `<VueDraggable>` mit `group="shelf"`
- Drop-Event → `bottleStore.moveBottle(bottleId, targetSlotId)`

### Step 4.2: Bottle-Swap (Drop auf belegten Slot)

**Subtasks:**
- Backend: `BottleService::swapBottles(bottleA, bottleB, userId)` — tauscht slot_ids in Transaction
- Controller: neuer Endpoint PATCH `/bottles/{id}/swap` mit `targetBottleId`
- Frontend: wenn Drop-Ziel belegt → API-Call swap statt move

### Step 4.3: ShelfConfigDialog

**Subtasks:**
- `src/components/ShelfConfigDialog.vue`: Modal pro Fach
- Felder: Ebenen (1-5), Spalten Vorne/Hinten, Versatz-Toggle (alternierend ja/nein)
- Pre-Save: "N Flaschen werden in die Parkzone verschoben — fortfahren?"
- API-Call: `CellarService.reconfigureCompartment` (bereits implementiert)
- Post-Save: 10-Sek-Undo-Toast (Snapshot vorher, bei Undo → reconfigure zurück)

### Step 4.4: Versatz-Alternierung in Slot-Erzeugung

**Subtasks:**
- `CellarService.createSlotsForCompartment`: Parameter `alternating: bool`
- Wenn true: ungerade Ebenen (0, 2, …) → front=columnsFront, back=columnsBack; gerade (1, 3, …) → front=columnsBack, back=columnsFront
- Compartment-Entity: neues Feld `alternating` (BOOLEAN, default true) — Migration v000103

### Step 4.5: Mobile + Touch

**Subtasks:**
- vue-draggable-plus: Touch-Support ist eingebaut (SortableJS)
- CSS: `.compartment` horizontal scrollbar bei kleinem Viewport
- Test: 375px Viewport im DevTools

### Step 4.6: Lint + Tests + PR

- ≥ 5 neue Tests (swap, reconfigure, D&D component)
- PR gegen develop

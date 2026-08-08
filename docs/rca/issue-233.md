# RCA: Issue #233 — Version 0.5.1 rendert nicht (TypeError in formatDate)

> **Status:** Analyse abgeschlossen, Fix verifiziert
> **Datum:** 2026-08-08
> **Severity:** Critical — die ausgelieferte Version ist unbenutzbar

---

## 1. Problem-Zusammenfassung

**Symptom:** Die App zeigt nach dem Laden nur die Nextcloud-Rahmen an. In der Konsole:

```
TypeError: (0 , Cne.default) is not a function
    at C9 (vinarium-main.js:454)
```

`C9` ist die minifizierte Form von `formatDate` aus `src/utils/date.ts`.

**Impact:** Alle Nutzer der Version 0.5.1. `formatDate` wird in **sechs** Ansichten
aufgerufen (Dashboard, Bestand, Verkostungen, Aktivität, BottleDetailPanel,
MasterDataPanel). Das Dashboard ist die Standardroute, also scheitert schon der
erste Seitenaufbau.

**Reproduktion:**
1. vinarium 0.5.1 installieren (oder `develop` bauen und ausliefern)
2. App öffnen
3. Ergebnis: leerer Inhaltsbereich, TypeError in der Konsole

---

## 2. Timeline

| Zeitpunkt | Event |
|-----------|-------|
| vor 0.5.1 | Vite-Sprung 7 → 8 (Dependabot) |
| 0.5.1 | Release mit dem Defekt, App Store und GitHub |
| 2026-08-08 | Beim Anschließen von ESLint aufgefallen: App rendert lokal nicht |
| 2026-08-08 | A/B gegen unverändertes `develop`-Bundle: Defekt bestand vorher |
| 2026-08-08 | Ursache eingegrenzt, Fix verifiziert |

---

## 3. Root Cause

**Ursache:** `@nextcloud/moment` liefert ein UMD-Bundle (`module.exports = …`) ohne
ESM-Feld und ohne eigenen `default`-Export. Der Standardimport
`import moment from '@nextcloud/moment'` ist damit auf die CommonJS-Interop des
Bundlers angewiesen. Diese Interop hat sich mit Vite 8 (Rolldown) verändert: der
Wert, der im gebauten Bundle ankommt, ist nicht aufrufbar.

**Kategorie:** External Dependency / Build-Toolchain

**Eingeführt:** mit dem Vite-Sprung 7 → 8, sichtbar ab Version 0.5.1

### Five Whys

1. **Warum rendert die App nicht?**
   → `formatDate` wirft einen TypeError, und die Funktion steckt in sechs Ansichten,
   darunter die Standardroute.

2. **Warum wirft `formatDate`?**
   → Der importierte Wert ist keine Funktion. Im Bundle steht
   `(0, ns.default)(e)`, und `ns.default` ist nicht aufrufbar.

3. **Warum ist der Wert nicht aufrufbar?**
   → `@nextcloud/moment` ist ein UMD/CommonJS-Paket ohne eigenen `default`-Export.
   Der Bundler muss `module.exports` auspacken. Vite 7 tat das mit einem Helfer
   (`getDefaultExportFromCjs`), Vite 8 nicht.

4. **Warum tut Vite 8 das nicht mehr?**
   → Vite 8 bündelt mit Rolldown und richtet die CommonJS-Interop nach esbuild aus.
   Die alte Vite-Interop war laut Vite-Doku uneinheitlich; das neue Verhalten ist
   Absicht, nicht ein Versehen.

5. **Root Cause:** Der Code verlässt sich für eine triviale Aufgabe
   (ISO-Datum als TT.MM.JJJJ) auf ein CommonJS-Paket und damit auf eine
   Bundler-Eigenschaft, die niemand zugesichert hat. Ein Werkzeug-Update konnte
   deshalb die App zum Stillstand bringen.

### Grenze dieser Analyse

Warum genau im gebauten Bundle **weder** `ns.default` **noch** der Namensraum
aufrufbar ist, ist nicht abschließend geklärt. Zwei Beobachtungen dazu:

- Der Interop-Helfer im Bundle (`u = (n,r,o) => … defineProperty(o,'default',{value:n}) …`,
  esbuilds `__toESM` mit `isNodeMode = 1`) **setzt** `default` normalerweise auf
  `module.exports`. Für andere CommonJS-Pakete im selben Bundle (z. B. `semver`
  innerhalb von `@nextcloud/event-bus`) funktioniert das nachweislich.
- `@nextcloud/moment` hält `moment` als externe Abhängigkeit und lädt sie per
  `require`. Eine isolierte Nachstellung der UMD-Weiche war deshalb nicht möglich.

Für den Fix ist diese letzte Frage ohne Belang: die Abhängigkeit wird entfernt.
Sie ist notiert, damit niemand später eine unbelegte Erklärung übernimmt.

---

## 4. Betroffener Code

**Dateien:**
- `src/utils/date.ts` — einzige Nutzung von `@nextcloud/moment`
- `package.json` — Abhängigkeit `@nextcloud/moment`

**Vorher:**

```ts
import moment from '@nextcloud/moment'

export function formatDate(iso: string): string {
	const m = moment(iso)
	return m.isValid() ? m.format('DD.MM.YYYY') : iso
}
```

**Emission Vite 7 (v0.5.0, funktioniert):**

```js
var Jse = Zse()        // module.exports
const Qse = Hu(Jse)    // Hu = getDefaultExportFromCjs
function rl(e) { const t = Qse(e); return t.isValid() ? … }
```

**Emission Vite 8 (v0.5.1, defekt):**

```js
function C9(e) { let t = (0, Cne.default)(e); return t.isValid() ? … }
```

---

## 5. Empfohlener Fix

**Approach:** `formatDate` ohne Bibliothek. Für „ISO-Datum oder Zeitstempel als
TT.MM.JJJJ, Unlesbares unverändert durchlassen" genügen ein regulärer Ausdruck und
eine Kalenderprüfung über UTC. Danach hängt die Funktion an keiner Bundler-Interop
mehr, und `@nextcloud/moment` fällt als Abhängigkeit weg.

**Risiko:** Niedrig. Die vier bestehenden Tests beschreiben das Verhalten
vollständig (Jahr, Zeitstempel mit Leerzeichen, unlesbare Eingabe) und bleiben
unverändert grün.

**Nebeneffekt:** Bundle **132 kB kleiner** (1495 → 1363 kB), eine Abhängigkeit
weniger.

**Verworfene Alternativen:**

| Alternative | Ergebnis |
|---|---|
| `build.commonjsOptions.defaultIsModuleExports: true` | wirkungslos — Rollup-Option, in Vite 8 ausdrücklich als No-op markiert |
| Interop von Hand (`ns.default ?? ns`) | scheitert: auch der Namensraum ist nicht aufrufbar |
| `legacy.inconsistentCjsInterop: true` | wirkungslos, Bundle unverändert |
| Rückbau auf Vite 7 | wirkt, aber unnötig: mit entferntem moment läuft Vite 8 einwandfrei (vier Ansichten geprüft). Ein Rückbau hieße Werkzeug-Schuld ohne Gegenwert |

**Tests / Nachweise:**
- [x] 34 Unit-Tests unverändert grün
- [x] Typecheck, Build grün
- [x] Vier Ansichten am laufenden System (NC 34): rendern, Datumsangaben erscheinen, keine Konsolenfehler
- [x] Gegenprobe: derselbe Defekt mit unverändertem `develop`-Bundle → Fehler lag nicht an begleitenden Änderungen

---

## 6. Prävention

**Was den Bug verhindert hätte:**

- [ ] **Klicktest am gebauten Artefakt.** Die 34 Unit-Tests waren grün, während die
      App nicht startete: vitest transformiert anders als der Bibliotheks-Build, der
      Defekt existierte **nur im Bundle**. Ein Werkzeug-Major ohne Klicktest ist
      ungeprüft, egal wie grün die Testliste ist.
- [ ] **Major-Updates einzeln und bedient.** Der Vite-Sprung kam als
      Dependabot-Update durch. In rechnungswerk wurde derselbe Sprung am selben Tag
      einzeln geprüft (Bundle-Eigenschaften gemessen, Entwurf bis Festschreiben
      durchgeklickt) — dort fiel entsprechend nichts aus.
- [ ] **Keine Bibliothek für Aufgaben, die drei Zeilen sind.** Die Abhängigkeit
      brachte 132 kB und eine Bundler-Annahme, für ein Datumsformat.

**Action Items:**

- [ ] `dependabot.yml` für vinarium anlegen (existiert nicht). Ohne sie kommen
      Majors ungruppiert und ohne Zielbranch-Steuerung.
- [ ] Prüfen, ob weitere Standardimporte von CommonJS-Paketen bestehen. Stand
      heute: `vue-cropperjs` (Standardimport, CJS) — im Bundle unauffällig, aber
      derselbe Importtyp. Beim nächsten Werkzeug-Sprung mitprüfen.
- [ ] Release 0.5.2 mit dem Fix, da 0.5.1 im App Store unbenutzbar ist.

---

*RCA erstellt mit `/rca` am 2026-08-08*

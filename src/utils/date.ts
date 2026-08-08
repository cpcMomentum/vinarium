/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * Ohne @nextcloud/moment, und zwar aus einem konkreten Grund.
 *
 * Das Paket liefert ein UMD-Bundle (`module.exports = …`) ohne ESM-Feld und ohne
 * eigenen `default`-Export. Mit Vite 8 (Rolldown) aenderte sich die Interop fuer
 * solche Pakete: aus dem Standardimport wurde im Bibliotheks-Build
 * `(0, ns.default)(…)`, und weder `ns.default` noch der Namensraum selbst waren
 * aufrufbar. Jede Datumsausgabe warf einen TypeError, und weil formatDate im
 * Dashboard und fuenf weiteren Ansichten steckt, rendert die App gar nicht mehr.
 * Ausgeliefert in 0.5.1; mit Vite 7 (0.5.0) war es in Ordnung.
 *
 * Weder `commonjsOptions.defaultIsModuleExports` (eine Rollup-Option, die
 * Rolldown nicht auswertet) noch eine Interop von Hand haben das behoben.
 *
 * Fuer die eine Aufgabe — ein ISO-Datum als TT.MM.JJJJ ausgeben — braucht es die
 * Bibliothek ohnehin nicht. Ohne sie gibt es keine Interop, von der etwas
 * abhaengt, und das Bundle verliert die komplette moment-Sprachdatenbank.
 *
 * Die Unit-Tests haben den Fehler NICHT gefunden: vitest transformiert anders als
 * der Bibliotheks-Build, der Defekt existierte nur im gebauten Bundle. Ein
 * Frontend-Fix muss deshalb am gebauten Artefakt bedient werden.
 */

/**
 * ISO-Datum oder -Zeitstempel als TT.MM.JJJJ. Nicht lesbare Eingaben werden
 * unveraendert durchgelassen (die Aktivitaetsliste mischt drei Quellen: reine
 * Datumsangaben und Zeitstempel).
 */
export function formatDate(iso: string): string {
	const teile = /^(\d{4})-(\d{2})-(\d{2})(?:[T ]|$)/.exec(iso)
	if (!teile) return iso

	const [, jahr, monat, tag] = teile
	// Kalendarisch pruefen: "2026-02-31" passt auf das Muster, ist aber kein Datum.
	// Ueber UTC, damit keine Zeitzone das Ergebnis um einen Tag verschiebt.
	const probe = new Date(`${jahr}-${monat}-${tag}T00:00:00Z`)
	if (
		Number.isNaN(probe.getTime())
		|| probe.getUTCFullYear() !== Number(jahr)
		|| probe.getUTCMonth() + 1 !== Number(monat)
		|| probe.getUTCDate() !== Number(tag)
	) {
		return iso
	}

	return `${tag}.${monat}.${jahr}`
}

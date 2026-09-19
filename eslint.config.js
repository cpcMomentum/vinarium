/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * ESLint war als Abhaengigkeit deklariert, lief aber nirgends: keine
 * Konfiguration, kein Skript, kein CI-Schritt. Dieselbe Luecke wie in
 * rechnungswerk (dort #210); beide sind die neueren Vue-3/Vite-Apps, bei denen
 * das Anschliessen beim Aufsetzen nie nachgezogen wurde.
 *
 * Grundlage ist die Nextcloud-Empfehlung fuer Vue 3. Die reinen
 * FORMATIERUNGSREGELN sind abgeschaltet: sie machen den weit ueberwiegenden Teil
 * der Befunde aus (Einrueckung, Umbrueche in Vorlagen, Import-Sortierung,
 * JSDoc-Pflicht), und alle auf einmal umzuschreiben waere ein Diff ueber jede
 * Frontend-Datei ohne fachlichen Gewinn. Die uebrigen Apps der Flotte
 * (worktime, contractmanager, brandmail) erzwingen sie ebenfalls nicht.
 *
 * Wieder einschalten ist eine Zeile: den entsprechenden Block unten entfernen.
 */

import { recommended } from '@nextcloud/eslint-config'
// Das TS-Plugin muss im selben Config-Objekt deklariert sein, in dem seine Regeln
// ueberschrieben werden (Flat-Config-Regel). @nextcloud/eslint-config bringt es in
// genau einem Objekt mit; hier wird es fuer den Ueberschreib-Block wiederverwendet.
const tsPlugin = recommended.find((c) => c.plugins?.['@typescript-eslint'])
	?.plugins['@typescript-eslint']
// Dasselbe fuer das Vue-Plugin: eine Regel ABSCHALTEN geht ohne, sie
// EINSCHALTEN nicht — dafuer muss das Plugin im selben Objekt stehen.
const vuePlugin = recommended.find((c) => c.plugins?.vue)?.plugins.vue

export default [
	...recommended,
	{
		name: 'vinarium/formatierung-aus',
		rules: {
			// Vue-Vorlagen: Umbrueche, Einrueckung, Attributverteilung
			'vue/singleline-html-element-content-newline': 'off',
			'vue/multiline-html-element-content-newline': 'off',
			'vue/html-indent': 'off',
			'vue/max-attributes-per-line': 'off',
			'vue/html-self-closing': 'off',
			'vue/first-attribute-linebreak': 'off',
			'vue/html-closing-bracket-newline': 'off',
			'vue/attributes-order': 'off',
			// Import-Reihenfolge und Sortierung von Schluesseln
			'perfectionist/sort-imports': 'off',
			'perfectionist/sort-named-imports': 'off',
			'perfectionist/sort-exports': 'off',
			'perfectionist/sort-objects': 'off',
			// JSDoc-Pflicht: der Code kommentiert das Warum in Prosa, nicht in Tags
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-description': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/require-returns': 'off',
			'jsdoc/require-returns-description': 'off',
			// Reine Schreibweise
			'antfu/top-level-function': 'off',
			'import-extensions/extensions': 'off',
			'import-extensions/ban-inline-type-imports': 'off',
			'vue/prefer-separate-static-class': 'off',
			'vue/define-macros-order': 'off',
			'jsdoc/escape-inline-tags': 'off',
			'vue/html-closing-bracket-spacing': 'off',
			/*
			 * Ereignisnamen bleiben in kebab-Schreibweise. Vues eigener Stilleitfaden
			 * empfiehlt sie in Vorlagen, der Code ist durchgaengig so, und ein
			 * Umbenennen waere heikel: Vue normalisiert Ereignisnamen NICHT wie
			 * Eigenschaften — ein uebersehener Empfaenger hoert einfach still auf zu
			 * reagieren. Das gehoert nicht in einen PR, der nur den Linter anschliesst.
			 */
			'vue/custom-event-name-casing': 'off',
		},
	},
	{
		name: 'vinarium/stylistic-aus',
		rules: Object.fromEntries([
			'implicit-arrow-linebreak', 'exp-list-style', 'indent', 'quotes', 'semi',
			'comma-dangle', 'operator-linebreak', 'space-before-function-paren',
			'arrow-parens', 'brace-style', 'max-len', 'no-tabs', 'member-delimiter-style',
			'max-statements-per-line', 'function-paren-newline',
			'no-multiple-empty-lines', 'multiline-ternary',
		].map((r) => ['@stylistic/' + r, 'off'])),
	},
	{
		name: 'vinarium/typescript-regeln',
		...(tsPlugin ? { plugins: { '@typescript-eslint': tsPlugin } } : {}),
		rules: {
			/*
			 * 26 Stellen, ueberwiegend `Record<string, any>` in Formular-Objekten.
			 * Sie ordentlich zu typisieren ist ein Umbau in den Komponenten, kein
			 * Aufraeumen — und `any` hebelt die Typpruefung dort tatsaechlich aus.
			 * Deshalb sichtbar als Warnung statt abgeschaltet oder vorgetaeuscht
			 * behoben, siehe #232.
			 */
			'@typescript-eslint/no-explicit-any': 'warn',
			/*
			 * Absichtlich unbenutzte Parameter mit Unterstrich-Praefix zulassen. Das
			 * ist die uebliche Kennzeichnung und steht so auch im generischen
			 * Standard (`argsIgnorePattern: '^_'`); betroffen ist hier ein
			 * Rueckruf, der seine Nutzlast nicht braucht.
			 */
			'@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
		},
	},
	{
		name: 'vinarium/angepasste-regeln',
		rules: {
			/*
			 * console.error bleibt erlaubt. Die Regel soll vergessenes
			 * console.log-Debugging verhindern; hier steht in jedem
			 * Fehlerbehandler ein console.error mit einheitlichem
			 * "[vinarium]"-Praefix neben der sichtbaren Fehlermeldung. Das
			 * ist die einzige Spur, die bleibt, wenn jemand ein Problem meldet.
			 * Nur error, nicht warn: der Code benutzt ausschliesslich console.error,
			 * und eine Erlaubnis fuer etwas Unbenutztes waere eine offene Tuer.
			 */
			'no-console': ['error', { allow: ['error'] }],
			/*
			 * `x != null` ist absichtlich lose: es prueft null UND undefined in
			 * einem Ausdruck. Ein `!==` daraus zu machen waere kein Aufraeumen,
			 * sondern ein eingebauter Fehler.
			 */
			eqeqeq: ['error', 'always', { null: 'ignore' }],
			/*
			 * 'multi-line' statt der Vollforderung: einzeilige Bedingungen wie
			 * `if (filter.from) params.set(...)` sind hier durchgaengiger Stil (156
			 * Stellen). Was die Regel schuetzt — ein spaeter angehaengter zweiter
			 * Befehl, der still ausserhalb des if landet — bleibt abgedeckt, weil
			 * mehrzeilige Koerper weiter Klammern brauchen.
			 */
			curly: ['error', 'multi-line'],
		},
	},
	{
		name: 'vinarium/ereignisnamen',
		...(vuePlugin ? { plugins: { vue: vuePlugin } } : {}),
		rules: {
			/*
			 * Das Nextcloud-Preset stellt diese Regel auf 'never', verlangt also
			 * `@dataChanged`. Das passt hier nicht: die Komponenten emittieren
			 * `data-changed` und `photo-changed`, und Vue 3 uebersetzt
			 * Ereignisnamen NICHT zwischen kebab-case und camelCase, wie es das
			 * bei Eigenschaften tut. Ein `@dataChanged` faengt ein
			 * `emit('data-changed')` also schlicht nicht ab.
			 *
			 * Der Autofix hat genau das angerichtet: aus `@data-changed` wurde
			 * `@dataChanged`, und `loadStats` sowie `onPhotoChanged` in
			 * InventoryView liefen danach still ins Leere — kein Fehler, keine
			 * Warnung, die Oberflaeche aktualisierte sich einfach nicht mehr.
			 * Gefunden im Bot-Review zu #239.
			 *
			 * Der Block weiter oben schaltet `vue/custom-event-name-casing` ab,
			 * also die EMIT-Seite. Die Empfaenger-Seite blieb offen — genau durch
			 * diese Luecke kam der Fehler herein.
			 *
			 * Deshalb 'always', passend zu dem, was die App tatsaechlich
			 * emittiert. Und `autofix: false`, damit dieselbe Automatik den
			 * Fehler nicht beim naechsten `lint:fix` erneut einbaut.
			 */
			'vue/v-on-event-hyphenation': ['error', 'always', { autofix: false }],
		},
	},
]

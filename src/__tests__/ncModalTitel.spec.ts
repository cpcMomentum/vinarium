/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

const SRC = join(__dirname, '..')

function vueDateien(dir: string): string[] {
	return readdirSync(dir).flatMap(name => {
		const pfad = join(dir, name)
		if (statSync(pfad).isDirectory()) {
			return name === '__tests__' ? [] : vueDateien(pfad)
		}
		return name.endsWith('.vue') ? [pfad] : []
	})
}

// NcModal baut aus `name` eine Kopfzeile, die ueber der Nextcloud-Leiste schwebt.
describe('NcModal-Beschriftung', () => {
	const dateien = vueDateien(SRC)

	it('findet die Dialoge ueberhaupt', () => {
		const mitModal = dateien.filter(f => readFileSync(f, 'utf8').includes('<NcModal'))
		expect(mitModal.length).toBeGreaterThanOrEqual(9)
	})

	it('setzt name an keinem NcModal, sondern labelId', () => {
		const verstoesse: string[] = []
		for (const datei of dateien) {
			const quelle = readFileSync(datei, 'utf8')
			for (const tag of quelle.match(/<NcModal\b(?:[^>=]|=(?!>)|=>)*>/g) ?? []) {
				if (/\s:?name=/.test(tag)) {
					verstoesse.push(relative(SRC, datei))
				}
				if (!/\s:?labelId=/.test(tag)) {
					verstoesse.push(relative(SRC, datei) + ' (ohne labelId)')
				}
			}
		}
		expect(verstoesse).toEqual([])
	})
})

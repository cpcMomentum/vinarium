/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

process.env.TZ = 'Europe/Berlin'

import { describe, expect, it } from 'vitest'
import { localDateIso } from '@/utils/date'

describe('localDateIso', () => {
	it('runs in a timezone ahead of UTC', () => {
		expect(new Date('2026-11-11T23:30:00Z').getTimezoneOffset()).toBe(-60)
	})

	it('returns the local day shortly after midnight in winter', () => {
		expect(localDateIso(new Date('2026-11-11T23:30:00Z'))).toBe('2026-11-12')
	})

	it('returns the local day shortly after midnight in summer', () => {
		expect(localDateIso(new Date('2026-07-01T22:30:00Z'))).toBe('2026-07-02')
	})

	it('pads month and day', () => {
		expect(localDateIso(new Date('2026-01-05T12:00:00Z'))).toBe('2026-01-05')
	})
})

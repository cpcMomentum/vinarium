/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { formatDate } from '@/utils/date'

describe('formatDate', () => {
	it('carries the year', () => {
		// #205: the activity widget formatted dates locally without the year, so
		// entries from different years were indistinguishable and a date in the
		// future read as a sorting error. Every caller goes through here now.
		expect(formatDate('2025-10-20')).toBe('20.10.2025')
		expect(formatDate('2026-10-20')).toBe('20.10.2026')
	})

	it('distinguishes the same day in different years', () => {
		expect(formatDate('2025-10-20')).not.toBe(formatDate('2026-10-20'))
	})

	it('accepts both plain dates and timestamps', () => {
		// The activity log merges three sources: purchased_at and tasted_at are
		// timestamps, event_date is a plain date.
		expect(formatDate('2026-08-03')).toBe('03.08.2026')
		expect(formatDate('2026-08-03 14:32:00')).toBe('03.08.2026')
	})

	it('passes unparseable input through untouched', () => {
		expect(formatDate('not a date')).toBe('not a date')
		expect(formatDate('')).toBe('')
	})
})

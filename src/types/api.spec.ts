/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect } from 'vitest'
import {
	WINE_COLORS,
	type Bottle,
	type Producer,
	type Tasting,
	type Vintage,
	type Wine,
	type WineColor,
} from './api'

describe('api types', () => {
	it('accepts a mocked producer payload', () => {
		const producer: Producer = {
			id: 1,
			ownerUserId: 'alice',
			name: 'Weingut Müller',
			country: 'DE',
			region: 'Mosel',
			website: null,
			notes: null,
		}
		expect(producer.name).toBe('Weingut Müller')
		expect(producer.region).toBe('Mosel')
	})

	it('enforces WineColor union', () => {
		const wine: Wine = {
			id: 1,
			producerId: 1,
			name: 'Riesling',
			color: 'white',
			appellation: null,
			notes: null,
			barcode: null,
		}
		expect(WINE_COLORS).toContain(wine.color)
	})

	it('lists exactly six wine colors', () => {
		expect(WINE_COLORS).toHaveLength(6)
		const expected: WineColor[] = ['red', 'white', 'rose', 'sparkling', 'dessert', 'fortified']
		expect([...WINE_COLORS]).toEqual(expected)
	})

	it('accepts vintage with nullable fields', () => {
		const vintage: Vintage = {
			id: 1,
			wineId: 1,
			year: 2022,
			alcoholPercent: 12.5,
			grapeVarieties: 'Merlot 70%, Cabernet Franc 30%',
			drinkFromYear: 2025,
			drinkUntilYear: 2032,
			externalRating: null,
			externalRatingSource: null,
			description: 'Frisch, mineralisch',
			referenceUrl: null,
		}
		expect(vintage.year).toBeGreaterThan(2000)
		expect(vintage.grapeVarieties).toContain('Merlot')
		expect(vintage.drinkUntilYear).toBe(2032)
	})

	it('bottle slotId is nullable for parkzone', () => {
		const bottle: Bottle = {
			id: 1,
			purchaseId: 1,
			slotId: null,
			status: 'in_storage',
			photoFileId: null,
			notes: null,
		}
		expect(bottle.slotId).toBeNull()
	})

	it('tasting photoFileIds is a number array or null', () => {
		const tasting: Tasting = {
			id: 1,
			bottleId: 1,
			tastedAt: '2026-04-15T18:00:00+00:00',
			rating: 8.5,
			notes: null,
			occasion: null,
			companions: null,
			photoFileIds: [123, 456],
		}
		expect(tasting.photoFileIds).toEqual([123, 456])
	})
})

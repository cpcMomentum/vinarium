/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Producer, Vintage, Wine } from '@/types/api'

vi.mock('@/api/producers')
vi.mock('@/api/wines')
vi.mock('@/api/vintages')

import * as producersApi from '@/api/producers'
import * as winesApi from '@/api/wines'
import * as vintagesApi from '@/api/vintages'
import { useWineStore } from '@/stores/wineStore'

const fakeProducer = (id: number, name = 'P'): Producer => ({
	id, ownerUserId: 'alice', name, country: null, region: null, website: null, notes: null,
})
const fakeWine = (id: number, producerId: number): Wine => ({
	id, producerId, name: 'W', color: 'red', appellation: null, notes: null, barcode: null,
})
const fakeVintage = (id: number, wineId: number): Vintage => ({
	id, wineId, year: 2020, alcoholPercent: null, grapeVarieties: null,
	drinkFromYear: null, drinkUntilYear: null,
	externalRating: null, externalRatingSource: null, description: null, referenceUrl: null,
})

describe('wineStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('fetchProducers populates state', async () => {
		vi.mocked(producersApi.listProducers).mockResolvedValue([fakeProducer(1), fakeProducer(2)])
		const store = useWineStore()
		await store.fetchProducers()
		expect(store.producers).toHaveLength(2)
		expect(store.loading).toBe(false)
	})

	it('createProducer appends to state', async () => {
		const p = fakeProducer(3, 'Neu')
		vi.mocked(producersApi.createProducer).mockResolvedValue(p)
		const store = useWineStore()
		const result = await store.createProducer({ name: 'Neu' })
		expect(result.id).toBe(3)
		expect(store.producers).toContainEqual(p)
	})

	it('updateProducer rolls back on error', async () => {
		const original = fakeProducer(1, 'Alt')
		const store = useWineStore()
		store.producers = [original]
		vi.mocked(producersApi.updateProducer).mockRejectedValue({ status: 500, message: 'oops' })

		await expect(store.updateProducer(1, { name: 'Neu' })).rejects.toMatchObject({ status: 500 })
		expect(store.producers[0].name).toBe('Alt')
	})

	it('deleteProducer removes optimistically and rolls back on error', async () => {
		const p = fakeProducer(1)
		const store = useWineStore()
		store.producers = [p]
		vi.mocked(producersApi.deleteProducer).mockRejectedValue({ status: 500, message: 'oops' })

		await expect(store.deleteProducer(1)).rejects.toBeTruthy()
		expect(store.producers).toHaveLength(1)
	})

	it('winesByProducer getter filters correctly', () => {
		const store = useWineStore()
		store.wines = [fakeWine(1, 10), fakeWine(2, 20), fakeWine(3, 10)]
		expect(store.winesByProducer(10)).toHaveLength(2)
		expect(store.winesByProducer(99)).toHaveLength(0)
	})

	it('fetchWinesByProducer replaces wines for that producer', async () => {
		vi.mocked(winesApi.listWinesByProducer).mockResolvedValue([fakeWine(5, 42)])
		const store = useWineStore()
		store.wines = [fakeWine(1, 42), fakeWine(2, 99)]
		await store.fetchWinesByProducer(42)
		expect(store.wines.filter(w => w.producerId === 42)).toHaveLength(1)
		expect(store.wines.find(w => w.producerId === 99)).toBeDefined()
	})

	it('createVintage appends to state', async () => {
		const v = fakeVintage(1, 5)
		vi.mocked(vintagesApi.createVintage).mockResolvedValue(v)
		const store = useWineStore()
		await store.createVintage({ wineId: 5, year: 2020 })
		expect(store.vintages).toHaveLength(1)
	})

	it('vintagesByWine getter filters correctly', () => {
		const store = useWineStore()
		store.vintages = [fakeVintage(1, 10), fakeVintage(2, 20)]
		expect(store.vintagesByWine(10)).toHaveLength(1)
	})
})

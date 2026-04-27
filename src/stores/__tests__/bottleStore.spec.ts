/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Bottle, BottleListItem } from '@/types/api'

vi.mock('@/api/bottles')

import * as bottlesApi from '@/api/bottles'
import { useBottleStore } from '@/stores/bottleStore'

const fakeListItem = (id: number, slotId: number | null = null): BottleListItem => ({
	id, purchase_id: 1, slot_id: slotId, status: 'in_storage', photo_file_id: null, notes: null,
	year: 2020, wine_name: 'W', wine_color: 'red', producer_name: 'P', drink_until_year: null,
})
const fakeBottle = (id: number, slotId: number | null = null): Bottle => ({
	id, purchaseId: 1, slotId, status: 'in_storage', photoFileId: null, notes: null,
})

describe('bottleStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('fetchBottles populates and stores filter', async () => {
		vi.mocked(bottlesApi.listBottles).mockResolvedValue([fakeListItem(1)])
		const store = useBottleStore()
		await store.fetchBottles({ color: 'red' })
		expect(store.bottles).toHaveLength(1)
		expect(store.filter.color).toBe('red')
		expect(store.loading).toBe(false)
	})

	it('parkedCount reflects parked bottles', async () => {
		vi.mocked(bottlesApi.listParkedBottles).mockResolvedValue([fakeBottle(1), fakeBottle(2)])
		const store = useBottleStore()
		await store.fetchParked()
		expect(store.parkedCount).toBe(2)
	})

	it('moveBottle optimistically removes from parked and updates bottle slot', async () => {
		vi.mocked(bottlesApi.moveBottle).mockResolvedValue(fakeBottle(1, 99))
		vi.mocked(bottlesApi.listParkedBottles).mockResolvedValue([])
		const store = useBottleStore()
		store.parked = [fakeBottle(1), fakeBottle(2)]
		store.bottles = [fakeListItem(1)]

		await store.moveBottle(1, 99)

		expect(store.parked).toHaveLength(0)
		expect(store.bottles[0].slot_id).toBe(99)
	})

	it('moveBottle rolls back on error', async () => {
		vi.mocked(bottlesApi.moveBottle).mockRejectedValue({ status: 409, message: 'occupied' })
		const store = useBottleStore()
		store.parked = [fakeBottle(1)]
		store.bottles = [fakeListItem(1)]

		await expect(store.moveBottle(1, 99)).rejects.toMatchObject({ status: 409 })
		expect(store.parked).toHaveLength(1)
		expect(store.bottles[0].slot_id).toBeNull()
	})

	it('consumeBottle marks consumed and removes from parked', async () => {
		vi.mocked(bottlesApi.consumeBottle).mockResolvedValue(fakeBottle(1))
		const store = useBottleStore()
		store.bottles = [fakeListItem(1)]
		store.parked = [fakeBottle(1)]

		await store.consumeBottle(1)

		expect(store.bottles[0].status).toBe('consumed')
		expect(store.parked).toHaveLength(0)
	})
})

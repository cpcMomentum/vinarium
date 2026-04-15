/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { Bottle, BottleFilter, BottleListItem } from '@/types/api'
import {
	consumeBottle as apiConsume,
	listBottles as apiList,
	listParkedBottles as apiListParked,
	moveBottle as apiMove,
} from '@/api/bottles'

export const useBottleStore = defineStore('bottle', () => {
	const bottles = ref<BottleListItem[]>([])
	const parked = ref<Bottle[]>([])
	const filter = ref<BottleFilter>({})
	const loading = ref(false)

	const parkedCount = computed(() => parked.value.length)
	const totalCount = computed(() => bottles.value.length)

	async function fetchBottles(newFilter: BottleFilter = {}): Promise<void> {
		loading.value = true
		filter.value = newFilter
		try {
			bottles.value = await apiList(newFilter)
		} finally {
			loading.value = false
		}
	}

	async function fetchParked(): Promise<void> {
		parked.value = await apiListParked()
	}

	async function moveBottle(bottleId: number, slotId: number | null): Promise<void> {
		const previousParked = [...parked.value]
		const previousBottles = [...bottles.value]

		// optimistic: remove from parked if moving to a slot
		if (slotId !== null) {
			parked.value = parked.value.filter(b => b.id !== bottleId)
		}
		// optimistic: update slot_id in bottles list
		const idx = bottles.value.findIndex(b => b.id === bottleId)
		if (idx >= 0) {
			bottles.value[idx] = { ...bottles.value[idx], slot_id: slotId }
		}

		try {
			await apiMove(bottleId, slotId)
			// refresh authoritative state
			await fetchParked()
		} catch (e) {
			parked.value = previousParked
			bottles.value = previousBottles
			throw e
		}
	}

	async function consumeBottle(bottleId: number): Promise<void> {
		await apiConsume(bottleId)
		bottles.value = bottles.value.map(b =>
			b.id === bottleId ? { ...b, status: 'consumed', slot_id: null } : b,
		)
		parked.value = parked.value.filter(b => b.id !== bottleId)
	}

	function $reset() {
		bottles.value = []
		parked.value = []
		filter.value = {}
		loading.value = false
	}

	return {
		bottles, parked, filter, loading,
		parkedCount, totalCount,
		fetchBottles, fetchParked, moveBottle, consumeBottle,
		$reset,
	}
})

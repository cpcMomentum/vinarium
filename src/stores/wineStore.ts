/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { Producer, PurchaseListItem, Vintage, Wine } from '@/types/api'
import {
	createProducer as apiCreateProducer,
	deleteProducer as apiDeleteProducer,
	listProducers as apiListProducers,
	updateProducer as apiUpdateProducer,
	type ProducerCreate,
	type ProducerUpdate,
} from '@/api/producers'
import {
	createWine as apiCreateWine,
	deleteWine as apiDeleteWine,
	listWinesByProducer as apiListWinesByProducer,
	updateWine as apiUpdateWine,
	type WineCreate,
	type WineUpdate,
} from '@/api/wines'
import { listAllPurchases as apiListPurchases } from '@/api/purchases'
import {
	createVintage as apiCreateVintage,
	deleteVintage as apiDeleteVintage,
	listVintagesByWine as apiListVintagesByWine,
	updateVintage as apiUpdateVintage,
	type VintageCreate,
	type VintageUpdate,
} from '@/api/vintages'

export const useWineStore = defineStore('wine', () => {
	const producers = ref<Producer[]>([])
	const wines = ref<Wine[]>([])
	const vintages = ref<Vintage[]>([])
	const purchases = ref<PurchaseListItem[]>([])
	const loading = ref(false)

	const producerById = computed(() => (id: number) => producers.value.find(p => p.id === id))
	const winesByProducer = computed(() => (producerId: number) => wines.value.filter(w => w.producerId === producerId))
	const vintagesByWine = computed(() => (wineId: number) => vintages.value.filter(v => v.wineId === wineId))

	async function fetchProducers(): Promise<void> {
		loading.value = true
		try {
			producers.value = await apiListProducers()
		} finally {
			loading.value = false
		}
	}

	async function createProducer(data: ProducerCreate): Promise<Producer> {
		const producer = await apiCreateProducer(data)
		producers.value.push(producer)
		return producer
	}

	async function updateProducer(id: number, data: ProducerUpdate): Promise<Producer> {
		const index = producers.value.findIndex(p => p.id === id)
		const previous = index >= 0 ? { ...producers.value[index] } : null
		if (index >= 0 && previous) {
			producers.value[index] = { ...previous, ...data } as Producer
		}
		try {
			const updated = await apiUpdateProducer(id, data)
			if (index >= 0) producers.value[index] = updated
			return updated
		} catch (e) {
			if (index >= 0 && previous) producers.value[index] = previous
			throw e
		}
	}

	async function deleteProducer(id: number): Promise<void> {
		const index = producers.value.findIndex(p => p.id === id)
		const previous = index >= 0 ? producers.value[index] : null
		if (index >= 0) producers.value.splice(index, 1)
		try {
			await apiDeleteProducer(id)
		} catch (e) {
			if (previous && index >= 0) producers.value.splice(index, 0, previous)
			throw e
		}
	}

	async function fetchWinesByProducer(producerId: number): Promise<void> {
		loading.value = true
		try {
			const fetched = await apiListWinesByProducer(producerId)
			wines.value = [...wines.value.filter(w => w.producerId !== producerId), ...fetched]
		} finally {
			loading.value = false
		}
	}

	async function createWine(payload: WineCreate): Promise<Wine> {
		const wine = await apiCreateWine(payload)
		wines.value.push(wine)
		return wine
	}

	async function updateWine(id: number, data: WineUpdate): Promise<Wine> {
		const updated = await apiUpdateWine(id, data)
		const index = wines.value.findIndex(w => w.id === id)
		if (index >= 0) wines.value[index] = updated
		return updated
	}

	async function deleteWine(id: number): Promise<void> {
		await apiDeleteWine(id)
		wines.value = wines.value.filter(w => w.id !== id)
	}

	async function fetchPurchases(): Promise<void> {
		loading.value = true
		try {
			purchases.value = await apiListPurchases()
		} finally {
			loading.value = false
		}
	}

	async function fetchVintagesByWine(wineId: number): Promise<void> {
		loading.value = true
		try {
			const fetched = await apiListVintagesByWine(wineId)
			vintages.value = [...vintages.value.filter(v => v.wineId !== wineId), ...fetched]
		} finally {
			loading.value = false
		}
	}

	async function createVintage(payload: VintageCreate): Promise<Vintage> {
		const vintage = await apiCreateVintage(payload)
		vintages.value.push(vintage)
		return vintage
	}

	async function updateVintage(id: number, data: VintageUpdate): Promise<Vintage> {
		const updated = await apiUpdateVintage(id, data)
		const index = vintages.value.findIndex(v => v.id === id)
		if (index >= 0) vintages.value[index] = updated
		return updated
	}

	async function deleteVintage(id: number): Promise<void> {
		await apiDeleteVintage(id)
		vintages.value = vintages.value.filter(v => v.id !== id)
	}

	function $reset() {
		producers.value = []
		wines.value = []
		vintages.value = []
		purchases.value = []
		loading.value = false
	}

	return {
		producers, wines, vintages, purchases, loading,
		producerById, winesByProducer, vintagesByWine,
		fetchProducers, createProducer, updateProducer, deleteProducer,
		fetchWinesByProducer, createWine, updateWine, deleteWine,
		fetchVintagesByWine, createVintage, updateVintage, deleteVintage,
		fetchPurchases,
		$reset,
	}
})

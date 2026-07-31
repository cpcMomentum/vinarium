/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Vintage } from '@/types/api'
import { apiDelete, apiGet, apiPatch, apiPost } from './client'

export interface VintageCreate {
	wineId: number
	year: number
	data?: {
		alcoholPercent?: number | null
		grapeVarieties?: string | null
		drinkFromYear?: number | null
		drinkUntilYear?: number | null
		externalRating?: number | null
		externalRatingSource?: string | null
		description?: string | null
		referenceUrl?: string | null
	}
}

export type VintageUpdate = Partial<Omit<VintageCreate, 'wineId'>> & { year?: number }

export const listVintagesByWine = (wineId: number): Promise<Vintage[]> =>
	apiGet<Vintage[]>(`/vintages?${new URLSearchParams({ wineId: String(wineId) })}`)

export const getVintage = (id: number): Promise<Vintage> =>
	apiGet<Vintage>(`/vintages/${id}`)

/**
 * Bottles still in storage, keyed by vintage id. Vintages without stock are
 * omitted, so callers must treat a missing key as 0.
 */
export const fetchVintageStock = (): Promise<Record<number, number>> =>
	apiGet<Record<number, number>>('/vintages/stock')

export const createVintage = (payload: VintageCreate): Promise<Vintage> =>
	apiPost<Vintage, VintageCreate>('/vintages', payload)

export const updateVintage = (id: number, data: VintageUpdate): Promise<Vintage> =>
	apiPatch<Vintage, { data: VintageUpdate }>(`/vintages/${id}`, { data })

export const deleteVintage = (id: number): Promise<void> =>
	apiDelete(`/vintages/${id}`)

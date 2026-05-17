/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Tasting, WineColor } from '@/types/api'
import { apiDelete, apiGet, apiPatch, apiPost } from './client'

export interface TastingListItem {
	id: number
	bottle_id: number
	tasted_at: string
	rating: number | null
	notes: string | null
	occasion: string | null
	companions: string | null
	wine_name: string
	wine_color: WineColor
	year: number
	producer_name: string
}

export interface TastingCreate {
	tastedAt?: string
	rating?: number | null
	notes?: string | null
	occasion?: string | null
	companions?: string | null
}

export interface ConsumeResult {
	bottle: { id: number; status: string; slotId: number | null }
	tasting: Tasting
}

export const listAllTastings = (): Promise<TastingListItem[]> =>
	apiGet<TastingListItem[]>('/tastings')

export const listTastingsByBottle = (bottleId: number): Promise<Tasting[]> =>
	apiGet<Tasting[]>(`/bottles/${bottleId}/tastings`)

export const createTasting = (bottleId: number, data: TastingCreate): Promise<Tasting> =>
	apiPost<Tasting, { bottleId: number; data: TastingCreate }>(`/bottles/${bottleId}/tastings`, { bottleId, data })

export const consumeWithTasting = (bottleId: number, data: TastingCreate): Promise<ConsumeResult> =>
	apiPost<ConsumeResult, { bottleId: number; data: TastingCreate }>(`/bottles/${bottleId}/consume`, { bottleId, data })

export const updateTasting = (id: number, data: TastingCreate): Promise<Tasting> =>
	apiPatch<Tasting, TastingCreate>(`/tastings/${id}`, data)

export const deleteTasting = (id: number): Promise<void> =>
	apiDelete(`/tastings/${id}`)

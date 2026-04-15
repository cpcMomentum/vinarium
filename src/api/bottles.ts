/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Bottle, BottleFilter, BottleListItem } from '@/types/api'
import { apiDelete, apiGet, apiPatch, apiPost } from './client'

function buildQuery(filter: BottleFilter): string {
	const params = new URLSearchParams()
	if (filter.status) params.set('status', filter.status)
	if (filter.color) params.set('color', filter.color)
	if (filter.year !== undefined) params.set('year', String(filter.year))
	if (filter.drinkUntilBefore) params.set('drinkUntilBefore', filter.drinkUntilBefore)
	const q = params.toString()
	return q ? `?${q}` : ''
}

export const listBottles = (filter: BottleFilter = {}): Promise<BottleListItem[]> =>
	apiGet<BottleListItem[]>(`/bottles${buildQuery(filter)}`)

export const listParkedBottles = (): Promise<Bottle[]> =>
	apiGet<Bottle[]>('/bottles/parked')

export const getBottle = (id: number): Promise<Bottle> =>
	apiGet<Bottle>(`/bottles/${id}`)

export const moveBottle = (id: number, slotId: number | null): Promise<Bottle> =>
	apiPatch<Bottle, { slotId: number | null }>(`/bottles/${id}/move`, { slotId })

export const consumeBottle = (id: number): Promise<Bottle> =>
	apiPost<Bottle, Record<string, never>>(`/bottles/${id}/consume`, {})

export const deleteBottle = (id: number): Promise<void> =>
	apiDelete(`/bottles/${id}`)

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Bottle, BottleFilter, BottleListItem } from '@/types/api'
import { apiDelete, apiGet, apiPatch, apiUrl } from './client'

function buildQuery(filter: BottleFilter): string {
	const params = new URLSearchParams()
	if (filter.status) params.set('status', filter.status)
	if (filter.color) params.set('color', filter.color)
	if (filter.year !== undefined) params.set('year', String(filter.year))
	if (filter.drinkUntilYearBefore !== undefined) params.set('drinkUntilYearBefore', String(filter.drinkUntilYearBefore))
	const q = params.toString()
	return q ? `?${q}` : ''
}

export interface BottleDetail {
	id: number
	purchase_id: number
	slot_id: number | null
	status: string
	photo_file_id: number | null
	notes: string | null
	wine_name: string
	wine_color: string
	appellation: string | null
	producer_name: string
	year: number
	grape_varieties: string | null
	drink_from_year: number | null
	drink_until_year: number | null
	alcohol_percent: number | null
	external_rating: number | null
	external_rating_source: string | null
	purchased_at: string
	vendor: string | null
	unit_price: number | null
	currency: string | null
	bottle_size_ml: number
	slot_level: number | null
	slot_row: string | null
	slot_column: number | null
	compartment_label: string | null
}

export const getBottleDetails = (id: number): Promise<BottleDetail> =>
	apiGet<BottleDetail>(`/bottles/${id}/details`)

export async function uploadBottlePhoto(id: number, file: File): Promise<{ photo_file_id: number }> {
	const axios = (await import('@nextcloud/axios')).default
	const { generateUrl } = await import('@nextcloud/router')
	const url = generateUrl(`/apps/vinarium/api/v1/bottles/${id}/photo`)
	const form = new FormData()
	form.append('photo', file)
	const { data } = await axios.post<{ photo_file_id: number }>(url, form, {
		headers: { 'Content-Type': 'multipart/form-data' },
	})
	return data
}

export const deleteBottlePhoto = (id: number): Promise<void> =>
	apiDelete(`/bottles/${id}/photo`)

export const getBottlePhotoUrl = (id: number): string =>
	apiUrl(`/bottles/${id}/photo`)

export const listBottles = (filter: BottleFilter = {}): Promise<BottleListItem[]> =>
	apiGet<BottleListItem[]>(`/bottles${buildQuery(filter)}`)

export const listParkedBottles = (): Promise<Bottle[]> =>
	apiGet<Bottle[]>('/bottles/parked')

export const getBottle = (id: number): Promise<Bottle> =>
	apiGet<Bottle>(`/bottles/${id}`)

export const moveBottle = (id: number, slotId: number | null): Promise<Bottle> =>
	apiPatch<Bottle, { slotId: number | null }>(`/bottles/${id}/move`, { slotId })

export const swapBottles = (id: number, targetBottleId: number): Promise<Bottle[]> =>
	apiPatch<Bottle[], { targetBottleId: number }>(`/bottles/${id}/swap`, { targetBottleId })

export const restoreBottle = (id: number): Promise<Bottle> =>
	apiPatch<Bottle, Record<string, never>>(`/bottles/${id}/restore`, {})

export const deleteBottle = (id: number): Promise<void> =>
	apiDelete(`/bottles/${id}`)

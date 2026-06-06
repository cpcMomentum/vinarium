/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Tasting } from '@/types/api'
import { apiDelete, apiGet, apiPatch, apiPost } from './client'
import { generateUrl } from '@nextcloud/router'

export interface TastingListItem {
	id: number
	bottle_id: number
	tasted_at: string
	rating: number | null
	notes: string | null
	occasion: string | null
	companions: string | null
	photo_file_ids: number[]
	wine_name: string
	wine_color: string
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

export interface TastingRelated {
	id: number
	tasted_at: string
	rating: number | null
	notes: string | null
	year: number
	wine_name?: string
}

export interface TastingDetail {
	id: number
	bottle_id: number
	tasted_at: string
	rating: number | null
	notes: string | null
	occasion: string | null
	companions: string | null
	photo_file_ids: number[]
	wine_id: number
	wine_name: string
	wine_color: string
	vintage_id: number
	year: number
	producer_id: number
	producer_name: string
	purchase: {
		purchased_at: string
		vendor: string | null
		unit_price: number | null
		currency: string | null
		bottle_size_ml: number
	}
	related_same_wine: TastingRelated[]
	related_same_producer: TastingRelated[]
}

export interface TastingStats {
	year: number
	month: number
	count_year: number
	count_current_month: number
	total_count: number
	avg_rating: number | null
	best_wine: {
		wine_name: string
		producer_name: string
		year: number
		rating: number
	} | null
	with_photos_count: number
}

export const listAllTastings = (): Promise<TastingListItem[]> =>
	apiGet<TastingListItem[]>('/tastings')

export const fetchTastingStats = (): Promise<TastingStats> =>
	apiGet<TastingStats>('/tastings/stats')

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

export const getTastingDetails = (id: number): Promise<TastingDetail> =>
	apiGet<TastingDetail>(`/tastings/${id}/details`)

export async function uploadTastingPhoto(id: number, file: File): Promise<{ photo_file_ids: number[] }> {
	const axios = (await import('@nextcloud/axios')).default
	const url = generateUrl(`/apps/vinarium/api/v1/tastings/${id}/photo`)
	const form = new FormData()
	form.append('photo', file)
	const { data } = await axios.post<{ photo_file_ids: number[] }>(url, form, {
		headers: { 'Content-Type': 'multipart/form-data' },
	})
	return data
}

export const deleteTastingPhoto = (id: number, fileId: number): Promise<{ photo_file_ids: number[] }> =>
	apiDelete<{ photo_file_ids: number[] }>(`/tastings/${id}/photo/${fileId}`)

export function tastingPhotoThumbnailUrl(fileId: number): string {
	return generateUrl('/core/preview') + `?fileId=${fileId}&x=128&y=128&forceIcon=0`
}

export function tastingPhotoFullUrl(fileId: number): string {
	return generateUrl('/core/preview') + `?fileId=${fileId}&x=1920&y=1920&forceIcon=0&a=1`
}

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Wine, WineColor } from '@/types/api'
import { apiDelete, apiGet, apiPatch, apiPost } from './client'

export interface WineCreate {
	producerId: number
	name: string
	color: WineColor
	data?: {
		appellation?: string | null
		notes?: string | null
		barcode?: string | null
	}
}

export type WineUpdate = Partial<Omit<WineCreate, 'producerId'>> & {
	name?: string
	color?: WineColor
}

export const listWinesByProducer = (producerId: number): Promise<Wine[]> =>
	apiGet<Wine[]>(`/wines?producerId=${producerId}`)

export const getWine = (id: number): Promise<Wine> =>
	apiGet<Wine>(`/wines/${id}`)

export const createWine = (payload: WineCreate): Promise<Wine> =>
	apiPost<Wine, WineCreate>('/wines', payload)

export const updateWine = (id: number, data: WineUpdate): Promise<Wine> =>
	apiPatch<Wine, { data: WineUpdate }>(`/wines/${id}`, { data })

export const deleteWine = (id: number): Promise<void> =>
	apiDelete(`/wines/${id}`)

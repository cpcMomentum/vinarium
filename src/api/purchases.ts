/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Bottle, BottleSizeMl, Purchase } from '@/types/api'
import { apiDelete, apiGet, apiPatch, apiPost } from './client'

export interface PurchaseCreate {
	vintageId: number
	purchasedAt?: string
	vendor?: string | null
	unitPrice?: number | null
	currency?: string
	quantity: number
	bottleSizeMl: BottleSizeMl
	notes?: string | null
}

export type PurchaseUpdate = Partial<Omit<PurchaseCreate, 'vintageId'>>

export interface PurchaseWithBottlesResult {
	purchase: Purchase
	bottles: Bottle[]
}

export const listPurchasesByVintage = (vintageId: number): Promise<Purchase[]> =>
	apiGet<Purchase[]>(`/purchases?vintageId=${vintageId}`)

export const getPurchase = (id: number): Promise<Purchase> =>
	apiGet<Purchase>(`/purchases/${id}`)

export const createPurchaseWithBottles = (payload: PurchaseCreate): Promise<PurchaseWithBottlesResult> => {
	const { vintageId, ...data } = payload
	return apiPost<PurchaseWithBottlesResult, { vintageId: number; data: typeof data }>('/purchases', { vintageId, data })
}

export const updatePurchase = (id: number, data: PurchaseUpdate): Promise<Purchase> =>
	apiPatch<Purchase, { data: PurchaseUpdate }>(`/purchases/${id}`, { data })

export const deletePurchase = (id: number): Promise<void> =>
	apiDelete(`/purchases/${id}`)

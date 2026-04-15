/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Producer } from '@/types/api'
import { apiDelete, apiGet, apiPatch, apiPost } from './client'

export interface ProducerCreate {
	name: string
	country?: string | null
	region?: string | null
	website?: string | null
	notes?: string | null
}

export type ProducerUpdate = Partial<ProducerCreate>

export const listProducers = (): Promise<Producer[]> =>
	apiGet<Producer[]>('/producers')

export const getProducer = (id: number): Promise<Producer> =>
	apiGet<Producer>(`/producers/${id}`)

export const createProducer = (data: ProducerCreate): Promise<Producer> =>
	apiPost<Producer, ProducerCreate>('/producers', data)

export const updateProducer = (id: number, data: ProducerUpdate): Promise<Producer> =>
	apiPatch<Producer, { data: ProducerUpdate }>(`/producers/${id}`, { data })

export const deleteProducer = (id: number): Promise<void> =>
	apiDelete(`/producers/${id}`)

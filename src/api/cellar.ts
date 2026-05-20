/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { apiDelete, apiGet, apiPatch, apiPost } from './client'
import type { Cellar, Compartment, CompartmentWithLevels, Level, Shelf, Slot } from '@/types/api'

export interface CellarResponse {
	cellar: Cellar
	shelves: Array<{
		shelf: Shelf
		compartments: CompartmentWithLevels[]
	}>
}

export interface LevelConfig {
	columnsFront: number
	columnsBack: number | null
}

export function fetchCellar(): Promise<CellarResponse> {
	return apiGet<CellarResponse>('/cellar')
}

export function createShelf(
	name: string,
	compartmentCount: number,
	levelsConfig: LevelConfig[],
): Promise<Shelf> {
	return apiPost<Shelf>('/cellar/shelves', { name, compartmentCount, levelsConfig })
}

export function updateShelf(shelfId: number, name: string): Promise<Shelf> {
	return apiPatch<Shelf>(`/cellar/shelves/${shelfId}`, { name })
}

export function destroyShelf(shelfId: number): Promise<{ movedToParkzone: number }> {
	return apiDelete<{ movedToParkzone: number }>(`/cellar/shelves/${shelfId}`)
}

export function fetchSlots(compartmentId: number): Promise<Slot[]> {
	return apiGet<Slot[]>(`/compartments/${compartmentId}/slots`)
}

export function reconfigureCompartment(
	compartmentId: number,
	levelsConfig: LevelConfig[],
): Promise<{ movedToParkzone: number }> {
	return apiPatch<{ movedToParkzone: number }>(`/compartments/${compartmentId}/reconfigure`, { levelsConfig })
}

export function addCompartment(
	shelfId: number,
	levelsConfig: LevelConfig[],
	label?: string,
): Promise<Compartment> {
	return apiPost<Compartment>(`/cellar/shelves/${shelfId}/compartments`, { levelsConfig, label })
}

export function destroyCompartment(compartmentId: number): Promise<{ movedToParkzone: number }> {
	return apiDelete<{ movedToParkzone: number }>(`/compartments/${compartmentId}`)
}

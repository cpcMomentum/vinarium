/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './client'
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

/**
 * Schreibt die Regal-Reihenfolge eines Kellers neu.
 *
 * Erwartet die vollständige Zielreihenfolge, nicht das verschobene Regal:
 * Der Server nummeriert daraus in einer Transaktion durch. Einzelne PATCHes
 * würden Zwischenzustände mit doppelten sort_order-Werten hinterlassen.
 */
export function reorderShelves(cellarId: number, shelfIds: number[]): Promise<Shelf[]> {
	return apiPut<Shelf[]>(`/cellar/${cellarId}/shelves/order`, { shelfIds })
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

export function updateCompartment(compartmentId: number, label: string): Promise<Compartment> {
	return apiPatch<Compartment>(`/compartments/${compartmentId}`, { label })
}

export function destroyCompartment(compartmentId: number): Promise<{ movedToParkzone: number }> {
	return apiDelete<{ movedToParkzone: number }>(`/compartments/${compartmentId}`)
}

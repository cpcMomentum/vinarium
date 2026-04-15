/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * API types — mirror OCA\Vinarium\Db\* entities 1:1.
 */

export type WineColor = 'red' | 'white' | 'rose' | 'sparkling' | 'dessert' | 'fortified'

export const WINE_COLORS: readonly WineColor[] = [
	'red', 'white', 'rose', 'sparkling', 'dessert', 'fortified',
] as const

export const WINE_COLOR_LABELS: Record<WineColor, string> = {
	red: 'Rot',
	white: 'Weiß',
	rose: 'Rosé',
	sparkling: 'Schaumwein',
	dessert: 'Dessertwein',
	fortified: 'Likörwein',
}

export type BottleStatus = 'in_storage' | 'consumed' | 'gifted' | 'lost'

export type SlotRow = 'front' | 'back'

export interface Producer {
	id: number
	ownerUserId: string
	name: string
	country: string | null
	region: string | null
	website: string | null
	notes: string | null
}

export interface Wine {
	id: number
	producerId: number
	name: string
	color: WineColor
	appellation: string | null
	notes: string | null
	barcode: string | null
}

export interface Vintage {
	id: number
	wineId: number
	year: number
	alcoholPercent: number | null
	grapeVarieties: string | null
	drinkFrom: string | null
	drinkUntil: string | null
	externalRating: number | null
	externalRatingSource: string | null
	description: string | null
	referenceUrl: string | null
}

export interface Purchase {
	id: number
	vintageId: number
	purchasedAt: string
	vendor: string | null
	unitPrice: number | null
	currency: string | null
	quantity: number
	bottleSizeMl: number
	notes: string | null
}

export interface Bottle {
	id: number
	purchaseId: number
	slotId: number | null
	status: BottleStatus
	photoFileId: number | null
	notes: string | null
}

export interface Tasting {
	id: number
	bottleId: number
	tastedAt: string
	rating: number | null
	notes: string | null
	occasion: string | null
	companions: string | null
	photoFileIds: number[] | null
}

export interface Cellar {
	id: number
	ownerUserId: string
	name: string
	createdAt: string
}

export interface Shelf {
	id: number
	cellarId: number
	name: string
	sortOrder: number
}

export interface Compartment {
	id: number
	shelfId: number
	label: string
	sortOrder: number
	levels: number
	columnsFront: number
	columnsBack: number
}

export interface Slot {
	id: number
	compartmentId: number
	level: number
	row: SlotRow
	column: number
}

export interface ApiError {
	status: number
	message: string
}

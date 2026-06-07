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
	drinkFromYear: number | null
	drinkUntilYear: number | null
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
	eventDate: string | null
	eventRecipient: string | null
	eventNote: string | null
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

export interface Level {
	id: number
	compartmentId: number
	levelNumber: number
	columnsFront: number
	columnsBack: number | null
	sortOrder: number
}

export interface Compartment {
	id: number
	shelfId: number
	label: string
	sortOrder: number
}

export interface CompartmentWithLevels {
	compartment: Compartment
	levels: Level[]
}

export interface Slot {
	id: number
	compartmentId: number
	level: number
	row: SlotRow
	column: number
}

export interface PurchaseListItem {
	id: number
	vintage_id: number
	purchased_at: string
	vendor: string | null
	unit_price: number | null
	currency: string | null
	quantity: number
	bottle_size_ml: number
	notes: string | null
	year: number
	wine_name: string
	wine_color: WineColor
	producer_name: string
}

export interface ApiError {
	status: number
	message: string
}

export type BottleSizeMl = 375 | 500 | 750 | 1000 | 1500 | 3000

export const BOTTLE_SIZES: readonly BottleSizeMl[] = [375, 500, 750, 1000, 1500, 3000] as const

export const BOTTLE_SIZE_LABELS: Record<BottleSizeMl, string> = {
	375: '0,375 l (Halb)',
	500: '0,5 l',
	750: '0,75 l (Standard)',
	1000: '1,0 l',
	1500: '1,5 l (Magnum)',
	3000: '3,0 l (Doppelmagnum)',
}

export const BOTTLE_STATUS_LABELS: Record<BottleStatus, string> = {
	in_storage: 'Im Bestand',
	consumed: 'Getrunken',
	gifted: 'Verschenkt',
	lost: 'Verloren',
}

export interface BottleListItem {
	id: number
	purchase_id: number
	slot_id: number | null
	status: BottleStatus
	photo_file_id: number | null
	notes: string | null
	wine_id: number
	vintage_id: number
	producer_id: number
	year: number
	wine_name: string
	wine_color: WineColor
	producer_name: string
	drink_until_year: number | null
	slot_level: number | null
	slot_row: string | null
	slot_column: number | null
	compartment_label: string | null
	event_date: string | null
	event_recipient: string | null
	event_note: string | null
	avg_rating: number | null
}

export interface BottleFilter {
	status?: BottleStatus
	color?: WineColor
	year?: number
	producerId?: number
	drinkUntilYearBefore?: number
}

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { apiGet, apiUrl } from './client'
import type { WineColor } from '@/types/api'

export type ActivityType = 'purchase' | 'tasting' | 'gifted' | 'lost'

export interface ActivityEvent {
	type: ActivityType
	date: string
	label: string
	refs: {
		wine_id?: number
		wine_color?: WineColor
		producer_name?: string
		tasting_id?: number
		bottle_id?: number
	}
}

export interface DrinkSoonEntry {
	wine_id: number
	wine_name: string
	wine_color: WineColor
	year: number
	drink_until_year: number
	producer_name: string
	bottle_count: number
	slot_label: string | null
}

export interface RatedWineEntry {
	wine_id: number
	wine_name: string
	wine_color: WineColor
	vintage_id: number
	year: number
	producer_name: string
	avg_rating: number
	tasting_count: number
}

export interface DashboardStats {
	totalBottles: number
	inStorage: number
	consumed: number
	gifted: number
	lost: number
	parked: number
	shelfCount: number
	colorDistribution: Record<string, number>
	drinkSoon: DrinkSoonEntry[]
	recentTastings: Array<{
		tasted_at: string
		rating: number | null
		notes: string | null
		wine_name: string
		year: number
		producer_name: string
		wine_color?: WineColor
	}>
	recentActivity: ActivityEvent[]
	topRated: RatedWineEntry[]
	flopRated: RatedWineEntry[]
}

export const fetchStats = (): Promise<DashboardStats> =>
	apiGet<DashboardStats>('/dashboard/stats')

export function exportCsvUrl(): string {
	return apiUrl('/export/csv')
}

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { apiGet, apiUrl } from './client'

export interface DashboardStats {
	totalBottles: number
	inStorage: number
	consumed: number
	parked: number
	colorDistribution: Record<string, number>
	drinkSoon: Array<{
		wine_name: string
		year: number
		drink_until_year: number
		producer_name: string
		bottle_count: number
	}>
	recentTastings: Array<{
		tasted_at: string
		rating: number | null
		notes: string | null
		wine_name: string
		year: number
		producer_name: string
	}>
}

export const fetchStats = (): Promise<DashboardStats> =>
	apiGet<DashboardStats>('/dashboard/stats')

export function exportCsvUrl(): string {
	return apiUrl('/export/csv')
}

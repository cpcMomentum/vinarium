/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { apiGet } from './client'
// ActivityType/ActivityEvent stammen aus der Dashboard-Card, die dieselben
// Events in Kurzform zeigt. Bewusst wiederverwendet statt dupliziert — der
// Stream hier ist dieselbe Aggregation, nur vollständig und filterbar (#92).
import type { ActivityEvent, ActivityType } from './dashboard'

export type { ActivityEvent, ActivityType }

export const ACTIVITY_TYPES: readonly ActivityType[] = ['purchase', 'tasting', 'gifted', 'lost'] as const

export interface ActivityStream {
	events: ActivityEvent[]
	hasMore: boolean
}

export interface ActivityFilter {
	type?: ActivityType | 'all'
	from?: string
	to?: string
	limit?: number
	offset?: number
}

export function fetchActivity(filter: ActivityFilter = {}): Promise<ActivityStream> {
	const params = new URLSearchParams()
	if (filter.type && filter.type !== 'all') params.set('type', filter.type)
	if (filter.from) params.set('from', filter.from)
	if (filter.to) params.set('to', filter.to)
	if (filter.limit !== undefined) params.set('limit', String(filter.limit))
	if (filter.offset !== undefined) params.set('offset', String(filter.offset))
	const q = params.toString()
	return apiGet<ActivityStream>(`/activity${q ? `?${q}` : ''}`)
}

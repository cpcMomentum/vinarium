/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { apiGet } from './client'
import type { SearchResult } from '@/types/api'

/** Full-text search over producers / wines / vintages (owner-scoped backend). */
export const search = (query: string): Promise<SearchResult[]> =>
	apiGet<SearchResult[]>(`/search?q=${encodeURIComponent(query)}`)

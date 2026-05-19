/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { WineColor } from '@/types/api'

const PALETTE: Record<WineColor, string> = {
	red: '#7a1c1c',
	white: '#e8d57a',
	rose: '#e8a3b8',
	sparkling: '#fff7c0',
	dessert: '#c2934e',
	fortified: '#4a1010',
}

export function cssColorFor(color: WineColor | string): string {
	return PALETTE[color as WineColor] ?? '#999'
}

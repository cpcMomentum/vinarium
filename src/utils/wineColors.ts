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

/** Dezenter linearer Gradient pro Wein-Kategorie für gefüllte Regal-Slots */
const SLOT_GRADIENTS: Record<WineColor, string> = {
	red: 'linear-gradient(160deg, #9a3b39, #6e2624)',
	white: 'linear-gradient(160deg, #d6c468, #a4943a)',
	rose: 'linear-gradient(160deg, #e0a3a4, #b56e6f)',
	sparkling: 'linear-gradient(160deg, #d4be58, #9a8b3a)',
	dessert: 'linear-gradient(160deg, #c89352, #8e6128)',
	fortified: 'linear-gradient(160deg, #86462f, #532b1f)',
}

export function cssSlotGradient(color: WineColor | string): string {
	return SLOT_GRADIENTS[color as WineColor] ?? PALETTE[color as WineColor] ?? '#999'
}

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'

/**
 * Formats an ISO date string as DD.MM.YYYY (German format).
 * Returns the original input for invalid dates (moment yields "Invalid date"
 * instead of throwing, so an explicit isValid() check is required).
 */
export function formatDate(iso: string): string {
	const m = moment(iso)
	return m.isValid() ? m.format('DD.MM.YYYY') : iso
}

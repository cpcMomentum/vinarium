/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'

// isValid() required: moment returns "Invalid date" string instead of throwing
export function formatDate(iso: string): string {
	const m = moment(iso)
	return m.isValid() ? m.format('DD.MM.YYYY') : iso
}

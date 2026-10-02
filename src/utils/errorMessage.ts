/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Servermeldung vor `message`, weil die Foto-Uploads rohe AxiosErrors werfen.
export function errorMessage(e: unknown, fallback: string): string {
	if (typeof e !== 'object' || e === null) {
		return fallback
	}
	const serverText = (e as { response?: { data?: { error?: unknown } } }).response?.data?.error
	if (typeof serverText === 'string' && serverText !== '') {
		return serverText
	}
	const message = (e as { message?: unknown }).message
	return typeof message === 'string' && message !== '' ? message : fallback
}

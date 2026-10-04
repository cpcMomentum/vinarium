/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { errorMessage } from '@/utils/errorMessage'

describe('errorMessage', () => {
	it('nimmt die Meldung eines ApiError', () => {
		expect(errorMessage({ status: 404, message: 'Flasche nicht gefunden' }, 'Fallback')).toBe('Flasche nicht gefunden')
	})

	it('nimmt die Meldung eines Error', () => {
		expect(errorMessage(new Error('kaputt'), 'Fallback')).toBe('kaputt')
	})

	it('bevorzugt die Servermeldung eines rohen AxiosError', () => {
		const axiosFehler = { message: 'Request failed with status code 400', response: { data: { error: 'Datei zu gross' } } }
		expect(errorMessage(axiosFehler, 'Fallback')).toBe('Datei zu gross')
	})

	it('faellt bei leerer oder fehlender Meldung auf den Fallback zurueck', () => {
		expect(errorMessage({ message: '' }, 'Fallback')).toBe('Fallback')
		expect(errorMessage({ status: 500 }, 'Fallback')).toBe('Fallback')
		expect(errorMessage({ message: 42 }, 'Fallback')).toBe('Fallback')
	})

	it('faellt bei Nicht-Objekten auf den Fallback zurueck', () => {
		expect(errorMessage(null, 'Fallback')).toBe('Fallback')
		expect(errorMessage(undefined, 'Fallback')).toBe('Fallback')
		expect(errorMessage('Text', 'Fallback')).toBe('Fallback')
	})
})

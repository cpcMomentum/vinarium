/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { WhatsNewArchive, WhatsNewPayload } from '@/types/api'
import { apiGet, apiPost } from './client'

/** Noch nicht gesehene Neuerungen der laufenden Version (#284). */
export const getWhatsNew = (): Promise<WhatsNewPayload> =>
	apiGet<WhatsNewPayload>('/whatsnew')

/** Alle bisherigen Neuerungen, nach Version gruppiert (#298), fuers Menue „Neuerungen". */
export const getWhatsNewArchive = (): Promise<WhatsNewArchive> =>
	apiGet<WhatsNewArchive>('/whatsnew/all')

/** Quittiert das Fenster; es kommt fuer diese Version nicht wieder. */
export const markWhatsNewSeen = (): Promise<void> =>
	apiPost<void, Record<string, never>>('/whatsnew/seen', {})

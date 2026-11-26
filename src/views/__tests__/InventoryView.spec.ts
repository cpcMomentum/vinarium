/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('vue-router', () => ({
	useRoute: () => ({ query: {} }),
	useRouter: () => ({ replace: vi.fn() }),
}))
vi.mock('@/api/bottles', () => ({ listBottles: vi.fn().mockResolvedValue([]) }))
vi.mock('@/api/producers', () => ({ listProducers: vi.fn().mockResolvedValue([]) }))
vi.mock('@/api/wines', () => ({ listWines: vi.fn().mockResolvedValue([]) }))
vi.mock('@/api/vintages', () => ({ listVintages: vi.fn().mockResolvedValue([]) }))
vi.mock('@/api/purchases', () => ({ listAllPurchases: vi.fn().mockResolvedValue([]) }))
const { stub } = vi.hoisted(() => ({
	stub: (name: string, template = '<div />') => ({ default: { name, template } }),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => stub('NcButton', '<button><slot /></button>'))
vi.mock('@nextcloud/vue/components/NcActions', () => stub('NcActions', '<div class="nc-actions"><slot /></div>'))
vi.mock('@nextcloud/vue/components/NcActionButton', () => stub('NcActionButton'))
vi.mock('@nextcloud/vue/components/NcActionLink', () => ({
	default: { name: 'NcActionLink', props: ['href'], template: '<a :href="href"><slot /></a>' },
}))
vi.mock('@/components/TastingDialog.vue', () => stub('TastingDialog'))
vi.mock('@/components/BottleEventDialog.vue', () => stub('BottleEventDialog'))
vi.mock('@/components/PurchaseWizardModal.vue', () => stub('PurchaseWizardModal'))
vi.mock('@/components/MasterDataPanel.vue', () => stub('MasterDataPanel'))
vi.mock('@/components/BottleDetailPanel.vue', () => stub('BottleDetailPanel'))
vi.mock('@/api/dashboard', () => ({
	fetchStats: vi.fn().mockResolvedValue(null),
	exportCsvUrl: () => '/apps/vinarium/api/v1/export/csv',
}))

import InventoryView from '@/views/InventoryView.vue'

describe('InventoryView', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('offers the CSV export in the header', async () => {
		const wrapper = mount(InventoryView)
		await flushPromises()

		const link = wrapper.find('.inventory-view__header a')
		expect(link.exists()).toBe(true)
		expect(link.attributes('href')).toBe('/apps/vinarium/api/v1/export/csv')
		expect(link.text()).toBe('CSV-Export herunterladen')
	})
})

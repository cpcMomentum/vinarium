/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

vi.mock('@/api/producers')
vi.mock('@/api/wines')
vi.mock('@/api/vintages')
vi.mock('@nextcloud/vue/components/NcModal', () => ({
	default: { name: 'NcModal', template: '<div class="nc-modal"><slot /></div>' },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: { name: 'NcButton', template: '<button @click="$emit(\'click\')"><slot /></button>' },
}))

import * as producersApi from '@/api/producers'
import PurchaseWizardModal from '@/components/PurchaseWizardModal.vue'
import { useWineStore } from '@/stores/wineStore'

const makeProducer = (id: number, name: string) => ({
	id, ownerUserId: 'alice', name, country: null, region: null, website: null, notes: null,
})

describe('PurchaseWizardModal', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		vi.mocked(producersApi.listProducers).mockResolvedValue([makeProducer(1, 'Weingut A')])
	})

	it('renders step 1 title when open=true', async () => {
		const wrapper = mount(PurchaseWizardModal, { props: { open: true } })
		await nextTick()
		expect(wrapper.html()).toContain('Schritt 1')
	})

	it('does not render when open=false', () => {
		const wrapper = mount(PurchaseWizardModal, { props: { open: false } })
		expect(wrapper.find('.wizard').exists()).toBe(false)
	})

	it('emits close when cancel clicked', async () => {
		const wrapper = mount(PurchaseWizardModal, { props: { open: true } })
		await nextTick()
		const cancelBtn = wrapper.findAll('button').find(b => b.text() === 'Abbrechen')
		await cancelBtn?.trigger('click')
		expect(wrapper.emitted('close')).toBeTruthy()
	})

	it('fetchProducers is triggered on open', async () => {
		mount(PurchaseWizardModal, { props: { open: true } })
		await nextTick()
		await nextTick()
		expect(producersApi.listProducers).toHaveBeenCalled()
	})

	it('displays stepper with 4 steps', async () => {
		const wrapper = mount(PurchaseWizardModal, { props: { open: true } })
		await nextTick()
		expect(wrapper.findAll('.step')).toHaveLength(4)
	})
})

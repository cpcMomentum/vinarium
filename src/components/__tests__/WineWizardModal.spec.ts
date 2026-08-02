/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/api/producers')
vi.mock('@/api/wines')
vi.mock('@/api/vintages')
vi.mock('@nextcloud/vue/components/NcModal', () => ({
	default: { name: 'NcModal', template: '<div class="nc-modal"><slot /></div>' },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	// emits MUSS deklariert sein: sonst bindet Vue @click zusätzlich als
	// nativen Listener auf das Root-Element und jeder Klick zählt doppelt —
	// der Wizard spränge dann zwei Schritte weiter.
	default: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
}))

import * as producersApi from '@/api/producers'
import * as vintagesApi from '@/api/vintages'
import * as winesApi from '@/api/wines'
import WineWizardModal from '@/components/WineWizardModal.vue'
import { useWineStore } from '@/stores/wineStore'

const makeProducer = (id: number, name: string) => ({
	id, ownerUserId: 'alice', name, country: null, region: null, website: null, notes: null,
})
const makeWine = (id: number, producerId: number, name: string) => ({
	id, producerId, name, color: 'red' as const, appellation: null, notes: null, barcode: null,
})
const makeVintage = (id: number, wineId: number, year: number) => ({
	id, wineId, year, alcoholPercent: null, grapeVarieties: null,
	drinkFromYear: null, drinkUntilYear: null, externalRating: null,
	externalRatingSource: null, description: null, referenceUrl: null,
	sweetness: null,
})

/** Führt den Wizard bis Schritt 3 mit einem bestehenden Weingut. */
async function mountAtVintageStep() {
	const store = useWineStore()
	store.producers = [makeProducer(1, 'Weingut A')]

	const wrapper = mount(WineWizardModal, { props: { open: true } })
	await flushPromises()

	await wrapper.find('select').setValue('1')
	await wrapper.vm.$nextTick()
	await wrapper.findAll('button').find(b => b.text() === 'Weiter')!.trigger('click')

	await wrapper.find('input').setValue('Clos Louie')
	await wrapper.findAll('button').find(b => b.text() === 'Weiter')!.trigger('click')

	return wrapper
}

describe('WineWizardModal', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		vi.mocked(winesApi.createWine).mockResolvedValue(makeWine(10, 1, 'Clos Louie'))
		vi.mocked(vintagesApi.createVintage).mockResolvedValue(makeVintage(20, 10, 2019))
		vi.mocked(producersApi.createProducer).mockResolvedValue(makeProducer(2, 'Neues Weingut'))
	})

	it('legt den Wein ohne Jahrgang an, wenn der optionale Schritt übersprungen wird', async () => {
		const wrapper = await mountAtVintageStep()

		await wrapper.findAll('button').find(b => b.text().includes('Fertig'))!.trigger('click')
		await flushPromises()

		expect(winesApi.createWine).toHaveBeenCalledTimes(1)
		expect(vintagesApi.createVintage).not.toHaveBeenCalled()
		expect(wrapper.emitted('complete')?.[0]).toEqual([{ wineId: 10, vintageId: null }])
	})

	it('reicht die gewaehlte Suesse an den Jahrgang durch', async () => {
		// Der Wein-Wizard entstand parallel zum Suesse-Feld und hatte es
		// zunaechst nicht — hier festgehalten, damit es nicht wieder wegfaellt.
		const wrapper = await mountAtVintageStep()

		await wrapper.find('input[type="checkbox"]').setValue(true)
		await wrapper.find('select').setValue('medium_sweet')
		await wrapper.findAll('button').find(b => b.text().includes('Fertig'))!.trigger('click')
		await flushPromises()

		expect(vintagesApi.createVintage).toHaveBeenCalledWith(
			expect.objectContaining({
				data: expect.objectContaining({ sweetness: 'medium_sweet' }),
			}),
		)
	})

	it('legt Wein und Jahrgang an, wenn der Jahrgang aktiviert wurde', async () => {
		const wrapper = await mountAtVintageStep()

		await wrapper.find('input[type="checkbox"]').setValue(true)
		await wrapper.findAll('button').find(b => b.text().includes('Fertig'))!.trigger('click')
		await flushPromises()

		expect(winesApi.createWine).toHaveBeenCalledTimes(1)
		expect(vintagesApi.createVintage).toHaveBeenCalledTimes(1)
		expect(vintagesApi.createVintage).toHaveBeenCalledWith(
			expect.objectContaining({ wineId: 10 }),
		)
		expect(wrapper.emitted('complete')?.[0]).toEqual([{ wineId: 10, vintageId: 20 }])
	})

	it('legt bei einem neuen Weingut zuerst den Producer an und hängt den Wein daran', async () => {
		const store = useWineStore()
		store.producers = []

		const wrapper = mount(WineWizardModal, { props: { open: true } })
		await flushPromises()

		await wrapper.find('input').setValue('Neues Weingut')
		await wrapper.findAll('button').find(b => b.text() === 'Weiter')!.trigger('click')

		await wrapper.find('input').setValue('Erster Wein')
		await wrapper.findAll('button').find(b => b.text() === 'Weiter')!.trigger('click')

		await wrapper.findAll('button').find(b => b.text().includes('Fertig'))!.trigger('click')
		await flushPromises()

		expect(producersApi.createProducer).toHaveBeenCalledTimes(1)
		expect(winesApi.createWine).toHaveBeenCalledWith(
			expect.objectContaining({ producerId: 2, name: 'Erster Wein' }),
		)
	})

	it('bricht nicht stumm ab, wenn das Anlegen fehlschlägt', async () => {
		vi.mocked(winesApi.createWine).mockRejectedValue(new Error('Serverfehler'))
		const wrapper = await mountAtVintageStep()

		await wrapper.findAll('button').find(b => b.text().includes('Fertig'))!.trigger('click')
		await flushPromises()

		expect(wrapper.text()).toContain('Serverfehler')
		expect(wrapper.emitted('complete')).toBeUndefined()
	})
})

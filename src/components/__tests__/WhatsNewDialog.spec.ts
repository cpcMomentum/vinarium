/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * „Was ist neu?"-Fenster (#284): Das Fenster darf nur erscheinen, wenn es
 * wirklich etwas zu berichten gibt, und es darf die App nie blockieren.
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { WhatsNewArchive, WhatsNewEntry, WhatsNewPayload } from '@/types/api'

const getWhatsNew = vi.fn<() => Promise<WhatsNewPayload>>()
const markWhatsNewSeen = vi.fn<() => Promise<void>>()
const getWhatsNewArchive = vi.fn<() => Promise<WhatsNewArchive>>()

vi.mock('@/api/whatsnew', () => ({
	getWhatsNew: () => getWhatsNew(),
	markWhatsNewSeen: () => markWhatsNewSeen(),
	getWhatsNewArchive: () => getWhatsNewArchive(),
}))

// Die echten Komponenten ziehen ihr CSS mit, das der Test-Runner nicht laedt.
vi.mock('@nextcloud/vue/components/NcModal', () => ({
	default: {
		name: 'NcModal',
		// Bewusst `labelId` statt `name`: NcModal baut aus `name` eine eigene
		// Kopfzeile, die am oberen Bildschirmrand schwebt und dort die
		// Nextcloud-Leiste ueberdeckt (#284).
		props: ['labelId', 'name'],
		emits: ['close'],
		template: '<div class="stub-modal"><slot /></div>',
	},
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		props: ['variant'],
		emits: ['click'],
		template: '<button class="stub-button" @click="$emit(\'click\')"><slot /></button>',
	},
}))

import WhatsNewDialog from '@/components/WhatsNewDialog.vue'

const eintrag = (teil: Partial<WhatsNewEntry>): WhatsNewEntry => ({
	title: 'Etikett mit zwei Seiten',
	text: 'Vorder- und Rückseite.',
	icon: 'camera',
	where: '',
	adminOnly: false,
	...teil,
})

const payload = (entries: WhatsNewPayload['entries']): WhatsNewPayload => ({
	version: '0.5.4',
	entries,
})

describe('WhatsNewDialog', () => {
	beforeEach(() => {
		getWhatsNew.mockReset()
		markWhatsNewSeen.mockReset()
		markWhatsNewSeen.mockResolvedValue(undefined)
		getWhatsNewArchive.mockReset()
	})

	it('zeigt kein Fenster, wenn es nichts zu berichten gibt', async () => {
		getWhatsNew.mockResolvedValue(payload([]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		expect(wrapper.find('.stub-modal').exists()).toBe(false)
	})

	it('zeigt Titel und Text der Eintraege', async () => {
		getWhatsNew.mockResolvedValue(payload([
			eintrag({ title: 'Etikett mit zwei Seiten', text: 'Vorder- und Rückseite.' }),
			eintrag({ title: 'Regal-Reihenfolge', text: 'Per Tastatur verschiebbar.' }),
		]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		expect(wrapper.find('.stub-modal').exists()).toBe(true)
		expect(wrapper.text()).toContain('Etikett mit zwei Seiten')
		expect(wrapper.text()).toContain('Per Tastatur verschiebbar.')
		expect(wrapper.findAll('.whatsnew__entry')).toHaveLength(2)
	})

	it('quittiert beim Schliessen und schliesst das Fenster', async () => {
		getWhatsNew.mockResolvedValue(payload([eintrag({})]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		await wrapper.find('.stub-button').trigger('click')
		await flushPromises()

		expect(markWhatsNewSeen).toHaveBeenCalledTimes(1)
		expect(wrapper.find('.stub-modal').exists()).toBe(false)
	})

	it('zeigt die Fundort-Zeile nur, wenn ein Ort angegeben ist', async () => {
		getWhatsNew.mockResolvedValue(payload([
			eintrag({ title: 'Mit Ort', where: 'Bestand' }),
			eintrag({ title: 'Ohne Ort', where: '' }),
		]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		const zeilen = wrapper.findAll('.whatsnew__where')
		expect(zeilen).toHaveLength(1)
		expect(zeilen[0].text()).toContain('Bestand')
	})

	it('schreibt bei adminpflichtigen Stellen den Hinweis dazu', async () => {
		getWhatsNew.mockResolvedValue(payload([
			eintrag({ where: 'Verwaltung', adminOnly: true }),
		]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		expect(wrapper.find('.whatsnew__where').text()).toContain('Administratoren')
	})

	it('zeigt zu jedem Eintrag ein Symbol, auch bei unbekanntem Namen', async () => {
		getWhatsNew.mockResolvedValue(payload([
			eintrag({ title: 'Bekannt', icon: 'bottle-wine' }),
			eintrag({ title: 'Unbekannt', icon: 'gibt-es-nicht' }),
			eintrag({ title: 'Leer', icon: '' }),
		]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		const symbole = wrapper.findAll('.whatsnew__icon')
		expect(symbole).toHaveLength(3)
		for (const symbol of symbole) {
			expect(symbol.find('svg').exists()).toBe(true)
		}
	})

	it('beschriftet NcModal ueber die eigene Ueberschrift, nicht ueber name', async () => {
		getWhatsNew.mockResolvedValue(payload([eintrag({})]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		const modal = wrapper.findComponent({ name: 'NcModal' })
		expect(modal.props('name')).toBeUndefined()
		expect(modal.props('labelId')).toBe('whatsnew-title')
		expect(wrapper.find('h2').attributes('id')).toBe('whatsnew-title')
	})

	it('quittiert auch beim Schliessen ueber X oder Escape', async () => {
		getWhatsNew.mockResolvedValue(payload([eintrag({})]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		// NcModal meldet X, Escape und den Klick daneben ueber dasselbe Ereignis.
		wrapper.findComponent({ name: 'NcModal' }).vm.$emit('close')
		await flushPromises()

		expect(markWhatsNewSeen).toHaveBeenCalledTimes(1)
		expect(wrapper.find('.stub-modal').exists()).toBe(false)
	})

	it('bleibt still, wenn der Abruf scheitert', async () => {
		getWhatsNew.mockRejectedValue(new Error('offline'))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		expect(wrapper.find('.stub-modal').exists()).toBe(false)
	})

	it('schliesst auch dann, wenn die Quittung scheitert', async () => {
		getWhatsNew.mockResolvedValue(payload([eintrag({})]))
		markWhatsNewSeen.mockRejectedValue(new Error('offline'))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		await wrapper.find('.stub-button').trigger('click')
		await flushPromises()

		expect(wrapper.find('.stub-modal').exists()).toBe(false)
	})

	it('traegt kein WerkPlus-Abzeichen', async () => {
		// Vinarium ist eine private Weinkeller-App. Das Abzeichen der Vorlage
		// wurde bewusst herausgenommen, nicht nur unsichtbar geschaltet.
		getWhatsNew.mockResolvedValue(payload([eintrag({})]))

		const wrapper = mount(WhatsNewDialog)
		await flushPromises()

		expect(wrapper.find('.whatsnew__badge').exists()).toBe(false)
		expect(wrapper.html()).not.toContain('werkwolke')
	})

	describe('Archiv ueber das Menue', () => {
		// Kein Auto-Popup, damit nur der Archiv-Pfad das Fenster oeffnet.
		beforeEach(() => {
			getWhatsNew.mockResolvedValue(payload([]))
		})

		async function oeffneArchiv(archiv: WhatsNewArchive) {
			getWhatsNewArchive.mockResolvedValue(archiv)
			const wrapper = mount(WhatsNewDialog)
			await flushPromises()
			await (wrapper.vm as unknown as { openArchive: () => Promise<void> }).openArchive()
			await flushPromises()
			return wrapper
		}

		it('zeigt alle Versionen in der gelieferten Reihenfolge', async () => {
			const wrapper = await oeffneArchiv({ versions: [
				{ version: '0.5.5', entries: [eintrag({ title: 'Neu' })] },
				{ version: '0.5.4', entries: [eintrag({ title: 'Alt A' }), eintrag({ title: 'Alt B' })] },
			] })

			expect(wrapper.find('.stub-modal').exists()).toBe(true)
			expect(wrapper.findAll('.whatsnew__group')).toHaveLength(2)
			const titel = wrapper.findAll('.whatsnew__entry-title').map(h => h.text())
			expect(titel).toEqual(['Neu', 'Alt A', 'Alt B'])
			expect(wrapper.findAll('.whatsnew__entry')).toHaveLength(3)
			expect(wrapper.find('.stub-button').text()).toBe('Schließen')
		})

		it('quittiert beim Schliessen nicht', async () => {
			const wrapper = await oeffneArchiv({ versions: [{ version: '0.5.4', entries: [eintrag({})] }] })

			await wrapper.find('.stub-button').trigger('click')
			await flushPromises()

			expect(markWhatsNewSeen).not.toHaveBeenCalled()
			expect(wrapper.find('.stub-modal').exists()).toBe(false)
		})

		it('quittiert auch ueber X oder Escape nicht', async () => {
			const wrapper = await oeffneArchiv({ versions: [{ version: '0.5.4', entries: [eintrag({})] }] })

			wrapper.findComponent({ name: 'NcModal' }).vm.$emit('close')
			await flushPromises()

			expect(markWhatsNewSeen).not.toHaveBeenCalled()
		})

		it('zeigt einen Leerzustand, wenn es noch nichts gibt', async () => {
			const wrapper = await oeffneArchiv({ versions: [] })

			expect(wrapper.find('.stub-modal').exists()).toBe(true)
			expect(wrapper.find('.whatsnew__empty').text()).toBe('Noch keine Neuerungen.')
			expect(wrapper.findAll('.whatsnew__entry')).toHaveLength(0)
		})
	})
})

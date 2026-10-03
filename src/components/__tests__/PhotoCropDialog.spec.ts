/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

// Die echten Komponenten ziehen ihr CSS mit, das der Test-Runner nicht laedt.
vi.mock('@nextcloud/vue/components/NcModal', () => ({
	default: {
		name: 'NcModal',
		props: ['name', 'labelId'],
		emits: ['close'],
		template: '<div class="stub-modal"><slot /></div>',
	},
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		props: ['variant', 'disabled'],
		emits: ['click'],
		template: '<button class="stub-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}))
vi.mock('vue-cropperjs', () => ({
	default: {
		name: 'VueCropper',
		props: ['src'],
		template: '<div class="stub-cropper" :data-src="src" />',
	},
}))
vi.mock('cropperjs/dist/cropper.css', () => ({}))

import PhotoCropDialog from '@/components/PhotoCropDialog.vue'

// Der Test entscheidet je Bild, ob es dekodiert, leer dekodiert oder scheitert.
type Ausgang = 'ok' | 'leer' | 'fehler'
const offen: Array<{ img: FakeImage, src: string }> = []

class FakeImage {
	onload: (() => void) | null = null
	onerror: (() => void) | null = null
	naturalWidth = 0
	set src(value: string) {
		offen.push({ img: this, src: value })
	}
}

function aufloesen(ausgang: Ausgang, index = 0) {
	const { img } = offen[index]
	if (ausgang === 'fehler') {
		img.onerror?.()
		return
	}
	img.naturalWidth = ausgang === 'ok' ? 800 : 0
	img.onload?.()
}

const datei = (name: string, type: string) => new File(['x'], name, { type })

async function warteAufLesen() {
	// FileReader meldet asynchron, erst danach entsteht das Image.
	await vi.waitFor(() => expect(offen.length).toBeGreaterThan(0))
}

describe('PhotoCropDialog', () => {
	beforeEach(() => {
		offen.length = 0
		vi.stubGlobal('Image', FakeImage)
	})
	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('zeigt den Zuschnitt, wenn der Browser das Bild dekodiert', async () => {
		const wrapper = mount(PhotoCropDialog, { props: { open: true, file: datei('etikett.jpg', 'image/jpeg') } })
		await warteAufLesen()
		aufloesen('ok')
		await flushPromises()

		expect(wrapper.find('.stub-cropper').exists()).toBe(true)
		expect(wrapper.find('.crop-dialog__error').exists()).toBe(false)
		expect(wrapper.find('.crop-dialog__hint').exists()).toBe(true)
		const uebernehmen = wrapper.findAll('.stub-button')[1]
		expect(uebernehmen.attributes('disabled')).toBeUndefined()
	})

	it('meldet ein nicht dekodierbares Format und sperrt „Übernehmen"', async () => {
		const wrapper = mount(PhotoCropDialog, { props: { open: true, file: datei('IMG_0001.HEIC', 'image/heic') } })
		await warteAufLesen()
		aufloesen('fehler')
		await flushPromises()

		expect(wrapper.find('.stub-cropper').exists()).toBe(false)
		expect(wrapper.find('.crop-dialog__error').text()).toContain('HEIC')
		expect(wrapper.find('.crop-dialog__hint').exists()).toBe(false)
		const uebernehmen = wrapper.findAll('.stub-button')[1]
		expect(uebernehmen.attributes('disabled')).toBeDefined()
	})

	it('behandelt ein leer dekodiertes Bild (Breite 0) wie ein undekodierbares', async () => {
		const wrapper = mount(PhotoCropDialog, { props: { open: true, file: datei('kaputt.png', 'image/png') } })
		await warteAufLesen()
		aufloesen('leer')
		await flushPromises()

		expect(wrapper.find('.stub-cropper').exists()).toBe(false)
		expect(wrapper.find('.crop-dialog__error').exists()).toBe(true)
	})

	it('verwirft ein spaetes Ergebnis, wenn inzwischen eine andere Datei gewaehlt wurde', async () => {
		const wrapper = mount(PhotoCropDialog, { props: { open: true, file: datei('IMG_0001.HEIC', 'image/heic') } })
		await warteAufLesen()

		await wrapper.setProps({ file: datei('etikett.jpg', 'image/jpeg') })
		await vi.waitFor(() => expect(offen.length).toBe(2))

		// Die zweite (gueltige) Datei ist zuerst fertig, danach scheitert die erste.
		aufloesen('ok', 1)
		await flushPromises()
		aufloesen('fehler', 0)
		await flushPromises()

		expect(wrapper.find('.stub-cropper').exists()).toBe(true)
		expect(wrapper.find('.crop-dialog__error').exists()).toBe(false)
	})

	it('beschriftet NcModal ueber die eigene sichtbare Ueberschrift', async () => {
		const wrapper = mount(PhotoCropDialog, { props: { open: true, file: datei('etikett.jpg', 'image/jpeg') } })
		await warteAufLesen()
		aufloesen('ok')
		await flushPromises()

		const modal = wrapper.findComponent({ name: 'NcModal' })
		expect(modal.props('name')).toBeUndefined()
		const titel = wrapper.find('h2')
		expect(titel.text()).toBe('Foto zuschneiden')
		expect(modal.props('labelId')).toBe(titel.attributes('id'))
		expect(titel.attributes('id')).toBeTruthy()
	})
})

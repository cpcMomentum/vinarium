<template>
	<NcModal v-if="open" :name="t('vinarium', 'Foto zuschneiden')" @keydown.esc="e => escCloses(e, cancel)" @close="cancel">
		<div class="crop-dialog">
			<p v-if="imageSrc || !errorMsg" class="crop-dialog__hint">
				{{ aspectRatio === null
					? t('vinarium', 'Wähle den Etiketten-Ausschnitt — frei wählbares Verhältnis.')
					: t('vinarium', 'Wähle den Etiketten-Ausschnitt — fixes Hochformat-Verhältnis.') }}
			</p>
			<div v-if="imageSrc" class="crop-dialog__container">
				<VueCropper
					ref="cropper"
					:src="imageSrc"
					:aspectRatio="aspectRatio ?? NaN"
					:viewMode="1"
					:autoCropArea="0.85"
					:background="true"
					:rotatable="true"
					:scalable="true"
					:zoomable="true"
					:movable="true"
					dragMode="move"
					class="crop-dialog__cropper"
				/>
			</div>
			<div class="crop-dialog__actions">
				<NcButton @click="cancel">{{ t('vinarium', 'Abbrechen') }}</NcButton>
				<NcButton variant="primary" :disabled="!imageSrc || saving" @click="confirm">
					{{ t('vinarium', 'Übernehmen') }}
				</NcButton>
			</div>
			<p v-if="errorMsg" class="crop-dialog__error">{{ errorMsg }}</p>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { escCloses } from '@/utils/modalEsc'
import { ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import VueCropperModule from 'vue-cropperjs'
import 'cropperjs/dist/cropper.css'

// vue-cropperjs ships only CommonJS (exports.default = component), it provides no
// ESM entry. Under Vite 8's default-interop the namespace can arrive as
// { default: component } instead of the component itself, so `<VueCropper>` binds
// to a non-component object: it renders nothing and its methods (getCroppedCanvas)
// are missing — the crop dialog stays blank and "Übernehmen" fails. Resolve the
// real component defensively so it works whichever shape the interop yields.
const VueCropper = ((VueCropperModule as unknown as { default?: unknown }).default
	?? VueCropperModule) as typeof VueCropperModule

const props = withDefaults(defineProps<{
	open: boolean
	file: File | null
	/**
	 * Fixed crop ratio, or null to let the user choose freely. Label photos pass
	 * null because bottles differ in shape — a forced ratio cuts text off the back
	 * label, where the grape varieties and bottler are printed. Defaults to the
	 * portrait ratio so existing callers keep their behaviour.
	 */
	aspectRatio?: number | null
}>(), { aspectRatio: 3 / 4 })
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'confirm', file: File): void
}>()

const cropper = ref<InstanceType<typeof VueCropper> | null>(null)
const imageSrc = ref<string | null>(null)
const saving = ref(false)
const errorMsg = ref<string | null>(null)

// Sequence number of the current file: reading and decoding are async. If the
// file changes or the dialog closes in between, a late result must not overwrite
// the newer state.
let loadSeq = 0

/**
 * Checks whether the browser can actually render the image. FileReader reads
 * HEIC/HEIF from an iPhone without complaint, but Chrome and Firefox cannot decode
 * it: `<img>` stays empty (naturalWidth 0) and getCroppedCanvas fails (#276).
 * Without this check the dialog showed a blank area and "Übernehmen" failed
 * silently. Catches every undecodable format, not only HEIC.
 */
function isDecodable(src: string): Promise<boolean> {
	return new Promise(resolve => {
		const img = new Image()
		img.onload = () => resolve(img.naturalWidth > 0)
		img.onerror = () => resolve(false)
		img.src = src
	})
}

watch(() => [props.open, props.file], ([isOpen, file]) => {
	const seq = ++loadSeq
	if (isOpen && file instanceof File) {
		errorMsg.value = null
		imageSrc.value = null
		const reader = new FileReader()
		reader.onload = async () => {
			const src = reader.result as string
			const decodable = await isDecodable(src)
			if (seq !== loadSeq) return
			if (decodable) {
				imageSrc.value = src
			} else {
				errorMsg.value = t('vinarium', 'Dieses Bildformat kann dein Browser nicht anzeigen (z. B. HEIC vom iPhone). Bitte lade das Foto als JPG oder PNG hoch.')
			}
		}
		reader.onerror = () => {
			if (seq !== loadSeq) return
			errorMsg.value = t('vinarium', 'Datei konnte nicht gelesen werden')
		}
		reader.readAsDataURL(file)
	} else if (!isOpen) {
		imageSrc.value = null
		saving.value = false
	}
}, { immediate: true })

function cancel() {
	emit('close')
}

function confirm() {
	if (!cropper.value) return
	saving.value = true
	errorMsg.value = null
	try {
		const canvas: HTMLCanvasElement = cropper.value.getCroppedCanvas({
			maxWidth: 1600,
			maxHeight: 2133,
			imageSmoothingQuality: 'high',
		})
		canvas.toBlob(blob => {
			if (!blob) {
				saving.value = false
				errorMsg.value = t('vinarium', 'Zuschneiden fehlgeschlagen')
				return
			}
			const sourceName = props.file?.name?.replace(/\.[^.]+$/, '') || 'photo'
			const out = new File([blob], sourceName + '.jpg', { type: 'image/jpeg' })
			emit('confirm', out)
			saving.value = false
		}, 'image/jpeg', 0.92)
	} catch (e) {
		saving.value = false
		errorMsg.value = t('vinarium', 'Zuschneiden fehlgeschlagen')
		console.error('Crop error:', e)
	}
}
</script>

<style scoped>
.crop-dialog {
	padding: 1.5rem;
	min-width: min(540px, 92vw);
	display: flex;
	flex-direction: column;
	gap: 14px;
}
.crop-dialog__hint {
	font-size: 13.5px;
	color: var(--color-text-maxcontrast);
	margin: 0;
}
.crop-dialog__container {
	background: var(--color-background-dark);
	border-radius: var(--border-radius, 8px);
	overflow: hidden;
}
.crop-dialog__cropper {
	width: 100%;
	max-height: min(60vh, 480px);
}
.crop-dialog__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
.crop-dialog__error {
	color: #c62828;
	font-size: 13px;
	margin: 0;
}
</style>

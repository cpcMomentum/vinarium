<template>
	<NcModal v-if="open" :name="t('vinarium', 'Foto zuschneiden')" @keydown.esc="e => escCloses(e, cancel)" @close="cancel">
		<div class="crop-dialog">
			<p class="crop-dialog__hint">
				{{ t('vinarium', 'Wähle den Etiketten-Ausschnitt — fixes Hochformat-Verhältnis.') }}
			</p>
			<div v-if="imageSrc" class="crop-dialog__container">
				<VueCropper
					ref="cropper"
					:src="imageSrc"
					:aspect-ratio="3 / 4"
					:view-mode="1"
					:auto-crop-area="0.85"
					:background="true"
					:rotatable="true"
					:scalable="true"
					:zoomable="true"
					:movable="true"
					drag-mode="move"
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
import VueCropper from 'vue-cropperjs'
import 'cropperjs/dist/cropper.css'

const props = defineProps<{ open: boolean; file: File | null }>()
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'confirm', file: File): void
}>()

const cropper = ref<InstanceType<typeof VueCropper> | null>(null)
const imageSrc = ref<string | null>(null)
const saving = ref(false)
const errorMsg = ref<string | null>(null)

watch(() => [props.open, props.file], ([isOpen, file]) => {
	if (isOpen && file instanceof File) {
		errorMsg.value = null
		const reader = new FileReader()
		reader.onload = () => { imageSrc.value = reader.result as string }
		reader.onerror = () => { errorMsg.value = t('vinarium', 'Datei konnte nicht gelesen werden') }
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

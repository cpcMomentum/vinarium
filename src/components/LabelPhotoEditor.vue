<!--
  - SPDX-FileCopyrightText: 2026 cpcMomentum
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="label-photos">
		<p v-if="showScopeHint" class="label-photos__scope muted">
			{{ t('vinarium', 'Etikett des Jahrgangs — gilt für alle Flaschen daraus.') }}
		</p>
		<div v-for="side in SIDES" :key="side.key" class="label-photos__side">
			<span class="label-photos__label">{{ side.label() }}</span>
			<div class="label-photos__frame">
				<img
					v-if="fileIdFor(side.key) !== null"
					:key="urlFor(side.key)!"
					:src="urlFor(side.key)!"
					class="label-photos__img"
					:alt="`${alt} — ${side.label()}`"
				/>
				<div v-else class="label-photos__empty muted">
					{{ t('vinarium', 'Kein Foto') }}
				</div>
			</div>
			<div class="label-photos__actions">
				<label class="label-photos__upload" :title="t('vinarium', 'Foto hochladen')">
					<input
						type="file"
						accept="image/*"
						class="label-photos__input"
						:disabled="busy"
						@change="e => onSelected(e, side.key)"
					/>
					{{ fileIdFor(side.key) !== null ? t('vinarium', 'Ersetzen') : t('vinarium', 'Foto hinzufügen') }}
				</label>
				<button
					v-if="fileIdFor(side.key) !== null"
					class="label-photos__remove"
					:disabled="busy"
					:title="t('vinarium', 'Foto entfernen')"
					@click.prevent="onRemove(side.key)"
				>✕</button>
			</div>
		</div>
		<p v-if="errorMsg" class="label-photos__error">{{ errorMsg }}</p>

		<PhotoCropDialog
			:open="cropOpen"
			:file="cropFile"
			:aspectRatio="null"
			@close="onCropCancel"
			@confirm="onCropConfirm"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import PhotoCropDialog from '@/components/PhotoCropDialog.vue'
import {
	deleteVintagePhoto,
	getVintagePhotoUrl,
	uploadVintagePhoto,
	type LabelSide,
} from '@/api/vintages'

const props = withDefaults(defineProps<{
	vintageId: number
	frontFileId: number | null
	backFileId: number | null
	/** Label for the img alt text — usually the wine name. */
	alt?: string
	/** Hidden where the surrounding form already makes the vintage scope obvious. */
	showScopeHint?: boolean
}>(), { alt: '', showScopeHint: true })

const emit = defineEmits<{
	(e: 'changed', side: LabelSide, fileId: number | null): void
}>()

const SIDES: { key: LabelSide, label: () => string }[] = [
	{ key: 'front', label: () => t('vinarium', 'Vorderseite') },
	{ key: 'back', label: () => t('vinarium', 'Rückseite') },
]

const cropOpen = ref(false)
const cropFile = ref<File | null>(null)
const cropSide = ref<LabelSide>('front')
const errorMsg = ref<string | null>(null)
const busy = ref(false)

const fileIdFor = (side: LabelSide): number | null =>
	side === 'front' ? props.frontFileId : props.backFileId

/**
 * The file id rides along as a cache buster: the URL names only the vintage and
 * the side, so after a replacement the endpoint's one hour cache would keep
 * serving the previous image.
 */
const urlFor = (side: LabelSide): string | null => {
	const fileId = fileIdFor(side)
	return fileId === null ? null : `${getVintagePhotoUrl(props.vintageId, side)}?v=${fileId}`
}

function onSelected(event: Event, side: LabelSide) {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	if (!file) return
	errorMsg.value = null
	cropFile.value = file
	cropSide.value = side
	cropOpen.value = true
	input.value = ''
}

function onCropCancel() {
	cropOpen.value = false
	cropFile.value = null
}

async function onCropConfirm(file: File) {
	cropOpen.value = false
	cropFile.value = null
	const side = cropSide.value
	busy.value = true
	try {
		const result = await uploadVintagePhoto(props.vintageId, side, file)
		emit('changed', side, result.fileId)
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Upload fehlgeschlagen')
	} finally {
		busy.value = false
	}
}

async function onRemove(side: LabelSide) {
	errorMsg.value = null
	busy.value = true
	try {
		await deleteVintagePhoto(props.vintageId, side)
		emit('changed', side, null)
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Entfernen fehlgeschlagen')
	} finally {
		busy.value = false
	}
}
</script>

<style scoped>
.label-photos__scope {
	font-size: 0.85em;
	max-width: 180px;
	margin: 0 0 4px;
}
.label-photos__side {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin-bottom: 12px;
}
.label-photos__label {
	font-size: 0.85em;
	font-weight: 600;
}
/*
 * The ratio shapes the frame only. The image is contained, so freely cropped
 * labels of any proportion stay complete instead of being cut to fit.
 */
.label-photos__frame {
	width: 180px; aspect-ratio: 3 / 4;
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-background-dark);
}
.label-photos__img {
	width: 100%; height: 100%;
	object-fit: contain;
}
.label-photos__empty {
	width: 100%; height: 100%;
	display: flex; align-items: center; justify-content: center;
	font-size: 0.85em;
}
.label-photos__actions {
	display: flex; align-items: center; gap: 8px;
}
.label-photos__upload {
	cursor: pointer;
	font-size: 0.85em;
	color: var(--color-primary-element);
}
.label-photos__upload:has(input:disabled) {
	cursor: default;
	opacity: 0.5;
}
.label-photos__input {
	display: none;
}
.label-photos__remove {
	background: none; border: none; cursor: pointer;
	color: var(--color-error);
	padding: 0 4px;
}
.label-photos__error {
	color: var(--color-error);
	font-size: 0.85em;
}
</style>

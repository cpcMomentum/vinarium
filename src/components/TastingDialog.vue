<template>
	<NcModal v-if="open" @close="$emit('close')">
		<div class="tasting-dialog">
			<h2>{{ editMode ? t('vinarium', 'Verkostung bearbeiten') : t('vinarium', 'Flasche öffnen + Verkostung') }}</h2>
			<p v-if="!editMode" class="muted">{{ t('vinarium', 'Die Flasche wird als „getrunken" markiert und der Slot freigegeben.') }}</p>

			<fieldset class="fieldset">
				<label class="field"><span>{{ t('vinarium', 'Datum') }}</span><input v-model="form.tastedAt" type="date" class="input" /></label>
				<label class="field">
					<span>{{ t('vinarium', 'Bewertung (0.5 – 10)') }}</span>
					<div class="rating-row">
						<input v-model.number="form.rating" type="range" min="0.5" max="10" step="0.5" class="rating-slider" />
						<span class="rating-value">{{ form.rating !== null ? form.rating.toFixed(1) : '—' }}</span>
					</div>
				</label>
				<label class="field"><span>{{ t('vinarium', 'Notizen') }}</span><textarea v-model="form.notes" class="input" rows="3" :placeholder="t('vinarium', 'Wie schmeckt der Wein?')" /></label>
				<label class="field"><span>{{ t('vinarium', 'Anlass') }}</span><input v-model="form.occasion" class="input" :placeholder="t('vinarium', 'z. B. Geburtstagsessen')" /></label>
				<label class="field"><span>{{ t('vinarium', 'Begleitung') }}</span><input v-model="form.companions" class="input" :placeholder="t('vinarium', 'z. B. Anna, Max')" /></label>
			</fieldset>

			<!-- Photo section -->
			<div class="photo-section">
				<span class="photo-section__label">{{ t('vinarium', 'Fotos') }}</span>
				<div class="photo-grid">
					<!-- Existing uploaded photos (edit mode) -->
					<div
						v-for="fileId in existingFileIds"
						:key="fileId"
						class="photo-thumb-wrap"
					>
						<img :src="thumbUrl(fileId)" class="photo-thumb" :alt="t('vinarium', 'Foto')" />
						<button class="photo-thumb__remove" :title="t('vinarium', 'Foto entfernen')" @click="removeExisting(fileId)">✕</button>
					</div>
					<!-- Pending photos (not yet uploaded) -->
					<div
						v-for="(f, idx) in pendingPhotos"
						:key="'p' + idx"
						class="photo-thumb-wrap photo-thumb-wrap--pending"
					>
						<img :src="pendingUrls[idx]" class="photo-thumb" :alt="t('vinarium', 'Foto')" />
						<button class="photo-thumb__remove" :title="t('vinarium', 'Foto entfernen')" @click="removePending(idx)">✕</button>
					</div>
					<!-- Add button -->
					<label class="photo-add-btn" :title="t('vinarium', 'Fotos hinzufügen')">
						<input type="file" accept="image/*" multiple class="photo-file-input" :disabled="saving" @change="onFilesSelected" />
						<span>+</span>
					</label>
				</div>
				<p v-if="photoError" class="photo-error">{{ photoError }}</p>
			</div>

			<div class="actions">
				<NcButton @click="$emit('close')">{{ t('vinarium', 'Abbrechen') }}</NcButton>
				<NcButton type="primary" :disabled="saving" @click="submit">
					{{ editMode ? t('vinarium', 'Speichern') : t('vinarium', 'Flasche öffnen') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import { consumeWithTasting, updateTasting, uploadTastingPhoto, deleteTastingPhoto, tastingPhotoThumbnailUrl } from '@/api/tastings'
import type { TastingListItem } from '@/api/tastings'

const props = defineProps<{
	open: boolean
	bottleId?: number | null
	tasting?: TastingListItem | null
}>()

const emit = defineEmits<{
	(e: 'close'): void
	(e: 'consumed'): void
	(e: 'updated', tasting: TastingListItem): void
}>()

const editMode = computed(() => !!props.tasting)
const saving = ref(false)
const pendingPhotos = ref<File[]>([])
const pendingUrls = ref<string[]>([])
const existingFileIds = ref<number[]>([])
const photoError = ref<string | null>(null)

const form = ref({
	tastedAt: new Date().toISOString().substring(0, 10),
	rating: 7.0 as number | null,
	notes: '',
	occasion: '',
	companions: '',
})

watch(() => props.open, (isOpen) => {
	if (!isOpen) return
	photoError.value = null
	pendingPhotos.value = []
	pendingUrls.value.forEach(u => URL.revokeObjectURL(u))
	pendingUrls.value = []
	if (props.tasting) {
		form.value = {
			tastedAt: props.tasting.tasted_at.substring(0, 10),
			rating: props.tasting.rating ?? 7.0,
			notes: props.tasting.notes ?? '',
			occasion: props.tasting.occasion ?? '',
			companions: props.tasting.companions ?? '',
		}
		existingFileIds.value = [...(props.tasting.photo_file_ids ?? [])]
	} else {
		form.value = {
			tastedAt: new Date().toISOString().substring(0, 10),
			rating: 7.0,
			notes: '',
			occasion: '',
			companions: '',
		}
		existingFileIds.value = []
	}
})

function thumbUrl(fileId: number): string {
	return tastingPhotoThumbnailUrl(fileId)
}

function onFilesSelected(event: Event) {
	const input = event.target as HTMLInputElement
	const files = Array.from(input.files ?? [])
	for (const file of files) {
		pendingPhotos.value.push(file)
		pendingUrls.value.push(URL.createObjectURL(file))
	}
	input.value = ''
}

function removePending(idx: number) {
	URL.revokeObjectURL(pendingUrls.value[idx])
	pendingPhotos.value.splice(idx, 1)
	pendingUrls.value.splice(idx, 1)
}

async function removeExisting(fileId: number) {
	if (!props.tasting) return
	photoError.value = null
	try {
		const result = await deleteTastingPhoto(props.tasting.id, fileId)
		existingFileIds.value = result.photo_file_ids
	} catch {
		photoError.value = t('vinarium', 'Entfernen fehlgeschlagen')
	}
}

async function uploadPendingPhotos(tastingId: number): Promise<boolean> {
	let allOk = true
	const remaining: File[] = []
	const remainingUrls: string[] = []
	for (let i = 0; i < pendingPhotos.value.length; i++) {
		const file = pendingPhotos.value[i]
		try {
			await uploadTastingPhoto(tastingId, file)
			URL.revokeObjectURL(pendingUrls.value[i])
		} catch {
			allOk = false
			photoError.value = t('vinarium', 'Upload fehlgeschlagen')
			remaining.push(file)
			remainingUrls.push(pendingUrls.value[i])
		}
	}
	pendingPhotos.value = remaining
	pendingUrls.value = remainingUrls
	return allOk
}

async function submit() {
	saving.value = true
	try {
		if (editMode.value && props.tasting) {
			await updateTasting(props.tasting.id, {
				tastedAt: form.value.tastedAt,
				rating: form.value.rating,
				notes: form.value.notes || null,
				occasion: form.value.occasion || null,
				companions: form.value.companions || null,
			})
			const photosOk = await uploadPendingPhotos(props.tasting.id)
			emit('updated', {
				...props.tasting,
				tasted_at: form.value.tastedAt,
				rating: form.value.rating,
				notes: form.value.notes || null,
				occasion: form.value.occasion || null,
				companions: form.value.companions || null,
				photo_file_ids: existingFileIds.value,
			})
			if (photosOk) emit('close')
		} else if (props.bottleId) {
			const result = await consumeWithTasting(props.bottleId, {
				tastedAt: form.value.tastedAt,
				rating: form.value.rating,
				notes: form.value.notes || null,
				occasion: form.value.occasion || null,
				companions: form.value.companions || null,
			})
			emit('consumed')
			const photosOk = await uploadPendingPhotos(result.tasting.id)
			if (photosOk) emit('close')
		}
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.tasting-dialog {
	padding: 2rem;
	min-width: 460px;
}
.tasting-dialog h2 { margin-bottom: 0.5rem; }
.muted { color: var(--color-text-maxcontrast); font-size: 0.9rem; margin-bottom: 1rem; }
.fieldset { border: 1px solid var(--color-border); border-radius: var(--border-radius); padding: 1rem; }
.field { display: block; margin-bottom: 0.75rem; }
.field span { display: block; font-size: 0.85rem; color: var(--color-text-maxcontrast); margin-bottom: 0.25rem; }
.input { width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); font-family: inherit; }
.rating-row { display: flex; align-items: center; gap: 1rem; }
.rating-slider { flex: 1; }
.rating-value { font-size: 1.2rem; font-weight: 600; min-width: 40px; text-align: center; }
.actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; }

.photo-section {
	margin-top: 1rem;
}
.photo-section__label {
	display: block;
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
	margin-bottom: 0.5rem;
}
.photo-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	align-items: flex-start;
}
.photo-thumb-wrap {
	position: relative;
	width: 72px;
	height: 72px;
	border-radius: var(--border-radius);
	overflow: hidden;
	background: var(--color-background-dark);
}
.photo-thumb-wrap--pending {
	opacity: 0.8;
}
.photo-thumb {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}
.photo-thumb__remove {
	position: absolute;
	top: 2px;
	right: 2px;
	background: rgba(0,0,0,0.55);
	border: none;
	border-radius: 50%;
	width: 20px;
	height: 20px;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	color: #fff;
	font-size: 0.7rem;
	padding: 0;
	line-height: 1;
}
.photo-thumb__remove:hover { background: rgba(180,0,0,0.8); }
.photo-add-btn {
	width: 72px;
	height: 72px;
	border: 2px dashed var(--color-border);
	border-radius: var(--border-radius);
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	font-size: 1.5rem;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-hover);
	flex-shrink: 0;
}
.photo-add-btn:hover { border-color: var(--color-primary-element); color: var(--color-primary-element); }
.photo-file-input { display: none; }
.photo-error {
	font-size: 0.8rem;
	color: var(--color-error, #c62828);
	margin: 0.25rem 0 0;
}
</style>

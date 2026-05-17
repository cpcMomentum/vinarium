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
import { consumeWithTasting, updateTasting } from '@/api/tastings'
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

const form = ref({
	tastedAt: new Date().toISOString().substring(0, 10),
	rating: 7.0 as number | null,
	notes: '',
	occasion: '',
	companions: '',
})

watch(() => props.open, (isOpen) => {
	if (!isOpen) return
	if (props.tasting) {
		form.value = {
			tastedAt: props.tasting.tasted_at.substring(0, 10),
			rating: props.tasting.rating ?? 7.0,
			notes: props.tasting.notes ?? '',
			occasion: props.tasting.occasion ?? '',
			companions: props.tasting.companions ?? '',
		}
	} else {
		form.value = {
			tastedAt: new Date().toISOString().substring(0, 10),
			rating: 7.0,
			notes: '',
			occasion: '',
			companions: '',
		}
	}
})

async function submit() {
	saving.value = true
	try {
		if (editMode.value && props.tasting) {
			const updated = await updateTasting(props.tasting.id, {
				tastedAt: form.value.tastedAt,
				rating: form.value.rating,
				notes: form.value.notes || null,
				occasion: form.value.occasion || null,
				companions: form.value.companions || null,
			})
			emit('updated', { ...props.tasting, ...updated })
			emit('close')
		} else if (props.bottleId) {
			await consumeWithTasting(props.bottleId, {
				tastedAt: form.value.tastedAt,
				rating: form.value.rating,
				notes: form.value.notes || null,
				occasion: form.value.occasion || null,
				companions: form.value.companions || null,
			})
			emit('consumed')
			emit('close')
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
</style>

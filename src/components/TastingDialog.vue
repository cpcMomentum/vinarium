<template>
	<NcModal v-if="open" @close="$emit('close')">
		<div class="tasting-dialog">
			<h2>Flasche öffnen + Verkostung</h2>
			<p class="muted">Die Flasche wird als „getrunken" markiert und der Slot freigegeben.</p>

			<fieldset class="fieldset">
				<label class="field"><span>Datum</span><input v-model="form.tastedAt" type="date" class="input" /></label>
				<label class="field">
					<span>Bewertung (0.5 – 10)</span>
					<div class="rating-row">
						<input v-model.number="form.rating" type="range" min="0.5" max="10" step="0.5" class="rating-slider" />
						<span class="rating-value">{{ form.rating !== null ? form.rating.toFixed(1) : '—' }}</span>
					</div>
				</label>
				<label class="field"><span>Notizen</span><textarea v-model="form.notes" class="input" rows="3" placeholder="Wie schmeckt der Wein?" /></label>
				<label class="field"><span>Anlass</span><input v-model="form.occasion" class="input" placeholder="z. B. Geburtstagsessen" /></label>
				<label class="field"><span>Begleitung</span><input v-model="form.companions" class="input" placeholder="z. B. Anna, Max" /></label>
			</fieldset>

			<div class="actions">
				<NcButton @click="$emit('close')">Abbrechen</NcButton>
				<NcButton type="primary" :disabled="saving" @click="submit">Flasche öffnen</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import { consumeWithTasting } from '@/api/tastings'

const props = defineProps<{ open: boolean; bottleId: number | null }>()
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'consumed'): void
}>()

const saving = ref(false)
const form = ref({
	tastedAt: new Date().toISOString().substring(0, 10),
	rating: 7.0 as number | null,
	notes: '',
	occasion: '',
	companions: '',
})

watch(() => props.open, (isOpen) => {
	if (isOpen) {
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
	if (!props.bottleId) return
	saving.value = true
	try {
		await consumeWithTasting(props.bottleId, {
			tastedAt: form.value.tastedAt,
			rating: form.value.rating,
			notes: form.value.notes || null,
			occasion: form.value.occasion || null,
			companions: form.value.companions || null,
		})
		emit('consumed')
		emit('close')
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

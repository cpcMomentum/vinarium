<template>
	<NcModal v-if="open" :name="title" @close="$emit('close')">
		<div class="edit-modal">
			<h2>{{ title }}</h2>

			<template v-if="type === 'producer' && producer">
				<label class="field"><span>{{ t('vinarium', 'Name *') }}</span><input v-model="producer.name" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Land') }}</span><input v-model.lazy="producerCountry" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Region') }}</span><input v-model.lazy="producerRegion" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Website') }}</span><input v-model.lazy="producerWebsite" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Notizen') }}</span><textarea v-model.lazy="producerNotes" class="input" rows="3" /></label>
			</template>

			<template v-else-if="type === 'wine' && wine">
				<label class="field"><span>{{ t('vinarium', 'Name *') }}</span><input v-model="wine.name" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Farbe *') }}</span>
					<select v-model="wine.color" class="input">
						<option v-for="c in WINE_COLORS" :key="c" :value="c">{{ t('vinarium', WINE_COLOR_LABELS[c]) }}</option>
					</select>
				</label>
				<label class="field"><span>{{ t('vinarium', 'Appellation') }}</span><input v-model.lazy="wineAppellation" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Barcode') }}</span><input v-model.lazy="wineBarcode" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Notizen zur Cuvée') }}</span><textarea v-model.lazy="wineNotes" class="input" rows="3" /></label>
			</template>

			<template v-else-if="type === 'vintage' && vintage">
				<label class="field"><span>{{ t('vinarium', 'Jahr *') }}</span><input v-model.number="vintage.year" type="number" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Alkohol %') }}</span><input v-model.number="vintageAlcohol" type="number" step="0.1" class="input" /></label>
				<label class="field">
					<span>{{ t('vinarium', 'Rebsorten (jahrgangsspezifisch)') }}</span>
					<input v-model.lazy="vintageGrapeVarieties" class="input" :placeholder="t('vinarium', 'z. B. Merlot 70%, Cabernet Franc 30%')" />
				</label>
				<div class="field-row">
					<label class="field"><span>{{ t('vinarium', 'Trinken ab (Jahr)') }}</span><input v-model.number="vintageDrinkFromYear" type="number" min="1900" class="input" :placeholder="t('vinarium', 'z. B. 2025')" /></label>
					<label class="field"><span>{{ t('vinarium', 'Trinken bis (Jahr)') }}</span><input v-model.number="vintageDrinkUntilYear" type="number" min="1900" class="input" :placeholder="t('vinarium', 'z. B. 2032')" /></label>
				</div>
				<div class="field-row">
					<label class="field"><span>{{ t('vinarium', 'Externe Bewertung') }}</span><input v-model.number="vintageExternalRating" type="number" step="0.1" class="input" /></label>
					<label class="field"><span>{{ t('vinarium', 'Bewertungsquelle') }}</span><input v-model.lazy="vintageRatingSource" class="input" :placeholder="t('vinarium', 'z. B. Parker, Vinous')" /></label>
				</div>
				<label class="field"><span>{{ t('vinarium', 'Referenz-URL') }}</span><input v-model.lazy="vintageReferenceUrl" class="input" /></label>
				<label class="field"><span>{{ t('vinarium', 'Beschreibung') }}</span><textarea v-model.lazy="vintageDescription" class="input" rows="3" /></label>
			</template>

			<div class="actions">
				<NcButton @click="$emit('close')">{{ t('vinarium', 'Abbrechen') }}</NcButton>
				<NcButton type="primary" :disabled="saving || !isValid" @click="save">{{ t('vinarium', 'Speichern') }}</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import { WINE_COLORS, WINE_COLOR_LABELS, type Producer, type Vintage, type Wine } from '@/types/api'
import { useWineStore } from '@/stores/wineStore'

type EntityType = 'producer' | 'wine' | 'vintage'

const props = defineProps<{
	open: boolean
	type: EntityType
	entityId: number | null
}>()
const emit = defineEmits<(e: 'close') => void>()

const store = useWineStore()
const saving = ref(false)
const producer = ref<Producer | null>(null)
const wine = ref<Wine | null>(null)
const vintage = ref<Vintage | null>(null)

const producerCountry = computed({
	get: () => producer.value?.country ?? '',
	set: (v: string) => { if (producer.value) producer.value.country = v || null },
})
const producerRegion = computed({
	get: () => producer.value?.region ?? '',
	set: (v: string) => { if (producer.value) producer.value.region = v || null },
})
const producerWebsite = computed({
	get: () => producer.value?.website ?? '',
	set: (v: string) => { if (producer.value) producer.value.website = v || null },
})
const producerNotes = computed({
	get: () => producer.value?.notes ?? '',
	set: (v: string) => { if (producer.value) producer.value.notes = v || null },
})
const wineAppellation = computed({
	get: () => wine.value?.appellation ?? '',
	set: (v: string) => { if (wine.value) wine.value.appellation = v || null },
})
const wineBarcode = computed({
	get: () => wine.value?.barcode ?? '',
	set: (v: string) => { if (wine.value) wine.value.barcode = v || null },
})
const wineNotes = computed({
	get: () => wine.value?.notes ?? '',
	set: (v: string) => { if (wine.value) wine.value.notes = v || null },
})
const vintageAlcohol = computed({
	get: () => vintage.value?.alcoholPercent ?? null,
	set: (v: number | null) => { if (vintage.value) vintage.value.alcoholPercent = v },
})
const vintageGrapeVarieties = computed({
	get: () => vintage.value?.grapeVarieties ?? '',
	set: (v: string) => { if (vintage.value) vintage.value.grapeVarieties = v || null },
})
const vintageDrinkFromYear = computed({
	get: () => vintage.value?.drinkFromYear ?? null,
	set: (v: number | null) => { if (vintage.value) vintage.value.drinkFromYear = v },
})
const vintageDrinkUntilYear = computed({
	get: () => vintage.value?.drinkUntilYear ?? null,
	set: (v: number | null) => { if (vintage.value) vintage.value.drinkUntilYear = v },
})
const vintageExternalRating = computed({
	get: () => vintage.value?.externalRating ?? null,
	set: (v: number | null) => { if (vintage.value) vintage.value.externalRating = v },
})
const vintageRatingSource = computed({
	get: () => vintage.value?.externalRatingSource ?? '',
	set: (v: string) => { if (vintage.value) vintage.value.externalRatingSource = v || null },
})
const vintageReferenceUrl = computed({
	get: () => vintage.value?.referenceUrl ?? '',
	set: (v: string) => { if (vintage.value) vintage.value.referenceUrl = v || null },
})
const vintageDescription = computed({
	get: () => vintage.value?.description ?? '',
	set: (v: string) => { if (vintage.value) vintage.value.description = v || null },
})

const title = computed(() => {
	const isCreate = props.entityId === null
	if (props.type === 'producer') return isCreate ? t('vinarium', 'Weingut erfassen') : t('vinarium', 'Weingut bearbeiten')
	if (props.type === 'wine') return isCreate ? t('vinarium', 'Wein erfassen') : t('vinarium', 'Wein bearbeiten')
	return isCreate ? t('vinarium', 'Jahrgang erfassen') : t('vinarium', 'Jahrgang bearbeiten')
})

const isValid = computed(() => {
	if (props.type === 'producer') return !!producer.value?.name?.trim()
	if (props.type === 'wine') return !!wine.value?.name?.trim()
	if (props.type === 'vintage') {
		const y = vintage.value?.year ?? 0
		return y >= 1900 && y <= new Date().getFullYear() + 2
	}
	return false
})

watch([() => props.open, () => props.entityId, () => props.type], () => {
	if (!props.open) return
	if (props.entityId === null) {
		if (props.type === 'producer') {
			producer.value = { id: 0, ownerUserId: '', name: '', country: null, region: null, website: null, notes: null }
		}
		return
	}
	if (props.type === 'producer') {
		const found = store.producers.find(p => p.id === props.entityId)
		producer.value = found ? { ...found } : null
	} else if (props.type === 'wine') {
		const found = store.wines.find(w => w.id === props.entityId)
		wine.value = found ? { ...found } : null
	} else {
		const found = store.vintages.find(v => v.id === props.entityId)
		vintage.value = found ? { ...found } : null
	}
}, { immediate: true })

async function save() {
	saving.value = true
	try {
		if (props.entityId === null) {
			if (props.type === 'producer' && producer.value) {
				await store.createProducer({
					name: producer.value.name,
					country: producer.value.country,
					region: producer.value.region,
					website: producer.value.website,
					notes: producer.value.notes,
				})
			}
		} else if (props.type === 'producer' && producer.value) {
			await store.updateProducer(producer.value.id, {
				name: producer.value.name,
				country: producer.value.country,
				region: producer.value.region,
				website: producer.value.website,
				notes: producer.value.notes,
			})
		} else if (props.type === 'wine' && wine.value) {
			await store.updateWine(wine.value.id, {
				name: wine.value.name,
				color: wine.value.color,
				data: {
					appellation: wine.value.appellation,
					barcode: wine.value.barcode,
					notes: wine.value.notes,
				},
			})
		} else if (props.type === 'vintage' && vintage.value) {
			await store.updateVintage(vintage.value.id, {
				year: vintage.value.year,
				data: {
					alcoholPercent: vintage.value.alcoholPercent,
					grapeVarieties: vintage.value.grapeVarieties,
					drinkFromYear: vintage.value.drinkFromYear,
					drinkUntilYear: vintage.value.drinkUntilYear,
					externalRating: vintage.value.externalRating,
					externalRatingSource: vintage.value.externalRatingSource,
					description: vintage.value.description,
					referenceUrl: vintage.value.referenceUrl,
				},
			})
		}
		emit('close')
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.edit-modal {
	padding: 2rem;
	min-width: 420px;
}
.edit-modal h2 {
	margin-bottom: 1.5rem;
}
.field {
	display: block;
	margin-bottom: 1rem;
}
.field span {
	display: block;
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
	margin-bottom: 0.25rem;
}
.input {
	width: 100%;
	padding: 0.5rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-family: inherit;
}
.field-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 1rem;
}
.actions {
	display: flex;
	justify-content: flex-end;
	gap: 0.5rem;
	margin-top: 1.5rem;
}
</style>

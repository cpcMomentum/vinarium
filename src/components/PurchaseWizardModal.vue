<template>
	<NcModal v-if="open" @close="cancel">
		<div class="wizard">
			<h2 class="wizard__title">{{ titles[step] }}</h2>
			<div class="wizard__stepper">
				<span v-for="s in 3" :key="s" :class="['step', { active: step === s, done: step > s }]">{{ s }}</span>
			</div>

			<!-- Step 1: Producer -->
			<section v-if="step === 1" class="wizard__section">
				<div class="pick-or-create">
					<label class="field">
						<span>Bestehendes Weingut wählen</span>
						<select v-model.number="producerId" class="input" @change="mode1 = producerId ? 'pick' : 'create'">
							<option :value="null">-- neues Weingut anlegen --</option>
							<option v-for="p in store.producers" :key="p.id" :value="p.id">{{ p.name }}</option>
						</select>
					</label>
				</div>
				<fieldset v-if="!producerId" class="fieldset">
					<legend>Neues Weingut</legend>
					<label class="field"><span>Name *</span><input v-model="newProducer.name" class="input" /></label>
					<div class="field-row">
						<label class="field"><span>Land</span><input v-model="newProducer.country" class="input" placeholder="z. B. Frankreich" /></label>
						<label class="field"><span>Region</span><input v-model="newProducer.region" class="input" placeholder="z. B. Bordeaux" /></label>
					</div>
					<label class="field"><span>Website</span><input v-model="newProducer.website" class="input" placeholder="https://..." /></label>
					<label class="field"><span>Notizen</span><textarea v-model="newProducer.notes" class="input" rows="2" /></label>
					<NcButton :disabled="!newProducer.name.trim() || saving" @click="saveNewProducer">Weingut speichern</NcButton>
				</fieldset>
			</section>

			<!-- Step 2: Wine -->
			<section v-else-if="step === 2" class="wizard__section">
				<p class="hint">
					Der Wein entspricht einer <em>Cuvée (Name + Farbe)</em>, ohne Jahrgang oder Rebsortenanteile —
					die variieren pro Jahrgang und gehören in Schritt 3.
				</p>
				<div class="pick-or-create">
					<label class="field">
						<span>Bestehenden Wein wählen</span>
						<select v-model.number="wineId" class="input">
							<option :value="null">-- neuen Wein anlegen --</option>
							<option v-for="w in winesForProducer" :key="w.id" :value="w.id">{{ w.name }} ({{ WINE_COLOR_LABELS[w.color] }})</option>
						</select>
					</label>
				</div>
				<fieldset v-if="!wineId" class="fieldset">
					<legend>Neuer Wein</legend>
					<label class="field"><span>Name *</span><input v-model="newWine.name" class="input" placeholder="z. B. Chateau Clos Louie (ohne Jahrgang)" /></label>
					<div class="field-row">
						<label class="field"><span>Farbe *</span>
							<select v-model="newWine.color" class="input">
								<option v-for="c in WINE_COLORS" :key="c" :value="c">{{ WINE_COLOR_LABELS[c] }}</option>
							</select>
						</label>
						<label class="field"><span>Appellation</span><input v-model="newWine.appellation" class="input" placeholder="z. B. Saint-Émilion GC" /></label>
					</div>
					<label class="field"><span>Barcode</span><input v-model="newWine.barcode" class="input" /></label>
					<label class="field"><span>Notizen zur Cuvée</span><textarea v-model="newWine.notes" class="input" rows="2" /></label>
					<NcButton :disabled="!newWine.name.trim() || saving" @click="saveNewWine">Wein speichern</NcButton>
				</fieldset>
			</section>

			<!-- Step 3: Vintage -->
			<section v-else-if="step === 3" class="wizard__section">
				<div class="pick-or-create">
					<label class="field">
						<span>Bestehenden Jahrgang wählen</span>
						<select v-model.number="vintageId" class="input">
							<option :value="null">-- neuen Jahrgang anlegen --</option>
							<option v-for="v in vintagesForWine" :key="v.id" :value="v.id">{{ v.year }}</option>
						</select>
					</label>
				</div>
				<fieldset v-if="!vintageId" class="fieldset">
					<legend>Neuer Jahrgang</legend>
					<div class="field-row">
						<label class="field"><span>Jahr *</span><input v-model.number="newVintage.year" type="number" class="input" /></label>
						<label class="field"><span>Alkohol %</span><input v-model.number="newVintage.alcoholPercent" type="number" step="0.1" class="input" placeholder="z. B. 13.5" /></label>
					</div>
					<label class="field">
						<span>Rebsorten (jahrgangsspezifisch)</span>
						<input v-model="newVintage.grapeVarieties" class="input" placeholder="z. B. Merlot 70%, Cabernet Franc 30%" />
					</label>
					<div class="field-row">
						<label class="field"><span>Trinken ab</span><input v-model="newVintage.drinkFrom" type="date" class="input" /></label>
						<label class="field"><span>Trinken bis</span><input v-model="newVintage.drinkUntil" type="date" class="input" /></label>
					</div>
					<div class="field-row">
						<label class="field"><span>Externe Bewertung</span><input v-model.number="newVintage.externalRating" type="number" step="0.1" class="input" placeholder="z. B. 92" /></label>
						<label class="field"><span>Quelle</span><input v-model="newVintage.externalRatingSource" class="input" placeholder="z. B. Parker" /></label>
					</div>
					<label class="field"><span>Referenz-URL</span><input v-model="newVintage.referenceUrl" class="input" /></label>
					<label class="field"><span>Beschreibung</span><textarea v-model="newVintage.description" class="input" rows="2" /></label>
					<NcButton :disabled="!isValidYear || saving" @click="saveNewVintage">Jahrgang speichern</NcButton>
				</fieldset>
			</section>

			<div class="wizard__actions">
				<NcButton @click="cancel">Abbrechen</NcButton>
				<NcButton v-if="step > 1" @click="step--">Zurück</NcButton>
				<NcButton v-if="step < 3" type="primary" :disabled="!canAdvance" @click="step++">Weiter</NcButton>
				<NcButton v-if="step === 3" type="primary" :disabled="!vintageId" @click="complete">Fertig</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import { WINE_COLORS, WINE_COLOR_LABELS, type WineColor } from '@/types/api'
import { useWineStore } from '@/stores/wineStore'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'complete', payload: { vintageId: number }): void
}>()

const store = useWineStore()
const step = ref(1)
const saving = ref(false)
const mode1 = ref<'pick' | 'create'>('create')

const producerId = ref<number | null>(null)
const wineId = ref<number | null>(null)
const vintageId = ref<number | null>(null)

const newProducer = ref({ name: '', country: '', region: '', website: '', notes: '' })
const newWine = ref<{ name: string; color: WineColor; appellation: string; barcode: string; notes: string }>({
	name: '', color: 'red', appellation: '', barcode: '', notes: '',
})
const newVintage = ref({
	year: new Date().getFullYear() as number | null,
	alcoholPercent: null as number | null,
	grapeVarieties: '',
	drinkFrom: '',
	drinkUntil: '',
	externalRating: null as number | null,
	externalRatingSource: '',
	referenceUrl: '',
	description: '',
})

const titles = { 1: 'Schritt 1: Weingut', 2: 'Schritt 2: Wein', 3: 'Schritt 3: Jahrgang' } as const

const winesForProducer = computed(() => (producerId.value ? store.winesByProducer(producerId.value) : []))
const vintagesForWine = computed(() => (wineId.value ? store.vintagesByWine(wineId.value) : []))

const canAdvance = computed(() => {
	if (step.value === 1) return producerId.value !== null
	if (step.value === 2) return wineId.value !== null
	return true
})

const isValidYear = computed(() => {
	const y = newVintage.value.year
	return typeof y === 'number' && y >= 1900 && y <= new Date().getFullYear() + 2
})

watch(() => props.open, async (isOpen) => {
	if (isOpen) {
		step.value = 1
		producerId.value = null
		wineId.value = null
		vintageId.value = null
		await store.fetchProducers()
	}
}, { immediate: true })

watch(producerId, async (id) => {
	if (id !== null) await store.fetchWinesByProducer(id)
	wineId.value = null
})

watch(wineId, async (id) => {
	if (id !== null) await store.fetchVintagesByWine(id)
	vintageId.value = null
})

async function saveNewProducer() {
	saving.value = true
	try {
		const p = await store.createProducer({
			name: newProducer.value.name,
			country: newProducer.value.country || null,
			region: newProducer.value.region || null,
			website: newProducer.value.website || null,
			notes: newProducer.value.notes || null,
		})
		producerId.value = p.id
		newProducer.value = { name: '', country: '', region: '', website: '', notes: '' }
	} finally {
		saving.value = false
	}
}

async function saveNewWine() {
	if (!producerId.value) return
	saving.value = true
	try {
		const w = await store.createWine({
			producerId: producerId.value,
			name: newWine.value.name,
			color: newWine.value.color,
			data: {
				appellation: newWine.value.appellation || null,
				barcode: newWine.value.barcode || null,
				notes: newWine.value.notes || null,
			},
		})
		wineId.value = w.id
		newWine.value = { name: '', color: 'red', appellation: '', barcode: '', notes: '' }
	} finally {
		saving.value = false
	}
}

async function saveNewVintage() {
	if (!wineId.value || !newVintage.value.year) return
	saving.value = true
	try {
		const v = await store.createVintage({
			wineId: wineId.value,
			year: newVintage.value.year,
			data: {
				alcoholPercent: newVintage.value.alcoholPercent,
				grapeVarieties: newVintage.value.grapeVarieties || null,
				drinkFrom: newVintage.value.drinkFrom || null,
				drinkUntil: newVintage.value.drinkUntil || null,
				externalRating: newVintage.value.externalRating,
				externalRatingSource: newVintage.value.externalRatingSource || null,
				referenceUrl: newVintage.value.referenceUrl || null,
				description: newVintage.value.description || null,
			},
		})
		vintageId.value = v.id
		newVintage.value = {
			year: new Date().getFullYear(),
			alcoholPercent: null,
			grapeVarieties: '',
			drinkFrom: '',
			drinkUntil: '',
			externalRating: null,
			externalRatingSource: '',
			referenceUrl: '',
			description: '',
		}
	} finally {
		saving.value = false
	}
}

function cancel() {
	emit('close')
}

function complete() {
	if (vintageId.value) {
		emit('complete', { vintageId: vintageId.value })
		emit('close')
	}
}
</script>

<style scoped>
.wizard {
	padding: 2rem;
	min-width: 520px;
	max-width: 680px;
}
.wizard__title {
	margin-bottom: 1rem;
}
.wizard__stepper {
	display: flex;
	gap: 0.5rem;
	margin-bottom: 1.5rem;
}
.wizard__stepper .step {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-weight: bold;
}
.wizard__stepper .step.active {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}
.wizard__stepper .step.done {
	background: var(--color-success);
	color: white;
}
.wizard__section {
	min-height: 200px;
}
.pick-or-create {
	margin-bottom: 1rem;
}
.fieldset {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 1rem;
	margin-top: 0.5rem;
}
.fieldset legend {
	padding: 0 0.5rem;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}
.field {
	display: block;
	margin-bottom: 0.75rem;
}
.field span {
	display: block;
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
	margin-bottom: 0.25rem;
}
.field-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 0.75rem;
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
.hint {
	padding: 0.75rem;
	background: var(--color-background-dark);
	border-left: 3px solid var(--color-primary-element);
	border-radius: var(--border-radius);
	margin-bottom: 1rem;
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast);
}
.hint em {
	color: var(--color-main-text);
	font-style: normal;
	font-weight: 500;
}
.wizard__actions {
	display: flex;
	justify-content: flex-end;
	gap: 0.5rem;
	margin-top: 2rem;
}
</style>

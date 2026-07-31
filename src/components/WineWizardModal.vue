<!--
  - SPDX-FileCopyrightText: 2026 cpcMomentum
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->
<template>
	<NcModal v-if="open" :name="titles[step]" @keydown.esc="e => escCloses(e, cancel)" @close="cancel">
		<div class="wizard">
			<h2 class="wizard__title">{{ titles[step] }}</h2>
			<div class="wizard__stepper">
				<span v-for="s in 3" :key="s" :class="['step', { active: step === s, done: step > s }]">{{ s }}</span>
			</div>

			<!-- Step 1: Producer -->
			<section v-if="step === 1" class="wizard__section">
				<p class="hint">{{ t('vinarium', 'Hier entsteht nur der Wein selbst — ohne Kauf und ohne Flaschen. Nützlich für Geschenke, Altbestand oder eine Wunschliste.') }}</p>
				<label v-if="store.producers.length > 0" class="field">
					<span>{{ t('vinarium', 'Bestehendes Weingut') }}</span>
					<select v-model.number="producerId" class="input">
						<option :value="null">{{ t('vinarium', '-- bitte wählen --') }}</option>
						<option v-for="p in store.producers" :key="p.id" :value="p.id">{{ p.name }}</option>
					</select>
				</label>

				<fieldset class="fieldset">
					<label class="field"><span>{{ t('vinarium', 'Name *') }}</span><input v-model="form1.name" :disabled="isPicked1" class="input" /></label>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Land') }}</span><input v-model="form1.country" :disabled="isPicked1" class="input" :placeholder="t('vinarium', 'z. B. Frankreich')" /></label>
						<label class="field"><span>{{ t('vinarium', 'Region') }}</span><input v-model="form1.region" :disabled="isPicked1" class="input" :placeholder="t('vinarium', 'z. B. Bordeaux')" /></label>
					</div>
					<label class="field"><span>{{ t('vinarium', 'Website') }}</span><input v-model="form1.website" :disabled="isPicked1" class="input" :placeholder="t('vinarium', 'https://...')" /></label>
					<label class="field"><span>{{ t('vinarium', 'Notizen') }}</span><textarea v-model="form1.notes" :disabled="isPicked1" class="input" rows="2" /></label>
				</fieldset>
			</section>

			<!-- Step 2: Wine -->
			<section v-else-if="step === 2" class="wizard__section">
				<p class="hint">{{ t('vinarium', 'Der Wein entspricht einer Cuvée (Name + Farbe), ohne Jahrgang oder Rebsortenanteile — die variieren pro Jahrgang und gehören in Schritt 3.') }}</p>
				<fieldset class="fieldset">
					<label class="field"><span>{{ t('vinarium', 'Name *') }}</span><input v-model="form2.name" class="input" :placeholder="t('vinarium', 'z. B. Chateau Clos Louie (ohne Jahrgang)')" /></label>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Farbe *') }}</span>
							<select v-model="form2.color" class="input">
								<option v-for="c in WINE_COLORS" :key="c" :value="c">{{ t('vinarium', WINE_COLOR_LABELS[c]) }}</option>
							</select>
						</label>
						<label class="field"><span>{{ t('vinarium', 'Appellation') }}</span><input v-model="form2.appellation" class="input" :placeholder="t('vinarium', 'z. B. Saint-Émilion GC')" /></label>
					</div>
					<label class="field"><span>{{ t('vinarium', 'Barcode') }}</span><input v-model="form2.barcode" class="input" /></label>
					<label class="field"><span>{{ t('vinarium', 'Notizen zur Cuvée') }}</span><textarea v-model="form2.notes" class="input" rows="2" :placeholder="t('vinarium', 'z. B. tanninbetonter Saint-Émilion, klassischer Bordeaux-Stil')" /></label>
					<p v-if="duplicateWineName" class="warn">
						{{ t('vinarium', 'Dieses Weingut hat bereits einen Wein mit diesem Namen. Anlegen ist möglich, prüfe aber kurz, ob du ihn doppelt erfasst.') }}
					</p>
				</fieldset>
			</section>

			<!-- Step 3: Vintage (optional) -->
			<section v-else-if="step === 3" class="wizard__section">
				<p class="hint">{{ t('vinarium', 'Der Jahrgang ist optional. Ohne Jahrgang erscheint der Wein in der Liste, bekommt aber erst beim ersten Kauf eine Jahrgangszeile.') }}</p>

				<label class="field field--check">
					<input v-model="withVintage" type="checkbox" />
					<span class="field__inline-label">{{ t('vinarium', 'Jahrgang gleich mit anlegen') }}</span>
				</label>

				<fieldset v-if="withVintage" class="fieldset">
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Jahr *') }}</span><input v-model.number="form3.year" type="number" class="input" /></label>
						<label class="field"><span>{{ t('vinarium', 'Alkohol %') }}</span><input v-model.number="form3.alcoholPercent" type="number" step="0.1" class="input" :placeholder="t('vinarium', 'z. B. 13,5')" /></label>
					</div>
					<label class="field">
						<span>{{ t('vinarium', 'Rebsorten (jahrgangsspezifisch)') }}</span>
						<input v-model="form3.grapeVarieties" class="input" :placeholder="t('vinarium', 'z. B. Merlot 70%, Cabernet Franc 30%')" />
					</label>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Trinken ab (Jahr)') }}</span><input v-model.number="form3.drinkFromYear" type="number" min="1900" class="input" :placeholder="t('vinarium', 'z. B. 2025')" /></label>
						<label class="field"><span>{{ t('vinarium', 'Trinken bis (Jahr)') }}</span><input v-model.number="form3.drinkUntilYear" type="number" min="1900" class="input" :placeholder="t('vinarium', 'z. B. 2032')" /></label>
					</div>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Externe Bewertung') }}</span><input v-model.number="form3.externalRating" type="number" step="0.1" class="input" :placeholder="t('vinarium', 'z. B. 92')" /></label>
						<label class="field"><span>{{ t('vinarium', 'Quelle') }}</span><input v-model="form3.externalRatingSource" class="input" :placeholder="t('vinarium', 'z. B. Parker')" /></label>
					</div>
					<label class="field"><span>{{ t('vinarium', 'Referenz-URL') }}</span><input v-model="form3.referenceUrl" class="input" /></label>
					<label class="field"><span>{{ t('vinarium', 'Jahrgangsnotizen') }}</span><textarea v-model="form3.description" class="input" rows="2" :placeholder="t('vinarium', 'z. B. trockener Sommer 2019, sehr konzentrierte Lese')" /></label>
					<p v-if="vintageYearInvalid" class="warn">
						{{ t('vinarium', 'Jahr muss zwischen 1900 und {max} liegen.', { max: maxYear }) }}
					</p>
				</fieldset>
			</section>

			<p v-if="errorMsg" class="error">{{ errorMsg }}</p>

			<div class="wizard__actions">
				<NcButton @click="cancel">{{ t('vinarium', 'Abbrechen') }}</NcButton>
				<NcButton v-if="step > 1" :disabled="saving" @click="step--">{{ t('vinarium', 'Zurück') }}</NcButton>
				<NcButton v-if="step < 3" variant="primary" :disabled="!canAdvance || saving" @click="step++">
					{{ t('vinarium', 'Weiter') }}
				</NcButton>
				<NcButton v-if="step === 3" variant="primary" :disabled="!canComplete || saving" @click="complete">
					{{ t('vinarium', 'Fertig (Wein anlegen)') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { escCloses } from '@/utils/modalEsc'
import { computed, ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import { WINE_COLORS, WINE_COLOR_LABELS, type WineColor } from '@/types/api'
import { useWineStore } from '@/stores/wineStore'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'complete', payload: { wineId: number; vintageId: number | null }): void
}>()

const store = useWineStore()
const step = ref(1)
const saving = ref(false)
const errorMsg = ref('')
const withVintage = ref(false)

const maxYear = new Date().getFullYear() + 2

const titles: Record<number, string> = {
	1: t('vinarium', 'Schritt 1: Weingut'),
	2: t('vinarium', 'Schritt 2: Wein'),
	3: t('vinarium', 'Schritt 3: Jahrgang (optional)'),
}

const producerId = ref<number | null>(null)
const isPicked1 = computed(() => producerId.value !== null)

const form1 = ref({ name: '', country: '', region: '', website: '', notes: '' })
const form2 = ref<{ name: string; color: WineColor; appellation: string; barcode: string; notes: string }>({
	name: '', color: 'red', appellation: '', barcode: '', notes: '',
})
const form3 = ref({
	year: new Date().getFullYear() as number | null,
	alcoholPercent: null as number | null,
	grapeVarieties: '',
	drinkFromYear: null as number | null,
	drinkUntilYear: null as number | null,
	externalRating: null as number | null,
	externalRatingSource: '',
	referenceUrl: '',
	description: '',
})

// Beim Wechsel auf ein bestehendes Weingut dessen Stammdaten anzeigen (read-only),
// beim Zurücksetzen auf "neu anlegen" die Felder wieder leeren.
watch(producerId, (id) => {
	if (id !== null) {
		const p = store.producerById(id)
		if (p) {
			form1.value = {
				name: p.name,
				country: p.country ?? '',
				region: p.region ?? '',
				website: p.website ?? '',
				notes: p.notes ?? '',
			}
		}
	} else {
		form1.value = { name: '', country: '', region: '', website: '', notes: '' }
	}
})

/**
 * Warnung statt Sperre: derselbe Name unter demselben Weingut ist zulässig
 * (zweite Abfüllung, Namensdopplung), aber meistens ein Versehen.
 */
const duplicateWineName = computed(() => {
	if (producerId.value === null) return false
	const name = form2.value.name.trim().toLocaleLowerCase()
	if (name === '') return false
	return store.winesByProducer(producerId.value)
		.some(w => w.name.trim().toLocaleLowerCase() === name)
})

const vintageYearInvalid = computed(() => {
	if (!withVintage.value) return false
	const y = form3.value.year
	return y === null || y < 1900 || y > maxYear
})

const canAdvance = computed(() => {
	if (step.value === 1) return isPicked1.value || form1.value.name.trim() !== ''
	if (step.value === 2) return form2.value.name.trim() !== ''
	return true
})

const canComplete = computed(() => form2.value.name.trim() !== '' && !vintageYearInvalid.value)

function reset() {
	step.value = 1
	saving.value = false
	errorMsg.value = ''
	withVintage.value = false
	producerId.value = null
	form1.value = { name: '', country: '', region: '', website: '', notes: '' }
	form2.value = { name: '', color: 'red', appellation: '', barcode: '', notes: '' }
	form3.value = {
		year: new Date().getFullYear(), alcoholPercent: null, grapeVarieties: '',
		drinkFromYear: null, drinkUntilYear: null,
		externalRating: null, externalRatingSource: '', referenceUrl: '', description: '',
	}
}

watch(() => props.open, (isOpen) => {
	if (isOpen) reset()
})

function cancel() {
	emit('close')
}

async function complete() {
	saving.value = true
	errorMsg.value = ''
	// Anders als der Kauf-Wizard gibt es hier keinen Transaktions-Endpoint.
	// Producer und Wein laufen daher als getrennte Requests: schlägt der Wein
	// fehl, bleibt ein neu angelegtes Weingut bestehen — sichtbar im
	// Weingüter-Tab und wiederverwendbar, statt still verloren zu gehen.
	try {
		let resolvedProducerId = producerId.value
		if (resolvedProducerId === null) {
			const producer = await store.createProducer({
				name: form1.value.name.trim(),
				country: form1.value.country.trim() || null,
				region: form1.value.region.trim() || null,
				website: form1.value.website.trim() || null,
				notes: form1.value.notes.trim() || null,
			})
			resolvedProducerId = producer.id
		}

		const wine = await store.createWine({
			producerId: resolvedProducerId,
			name: form2.value.name.trim(),
			color: form2.value.color,
			data: {
				appellation: form2.value.appellation.trim() || null,
				barcode: form2.value.barcode.trim() || null,
				notes: form2.value.notes.trim() || null,
			},
		})

		let vintageId: number | null = null
		if (withVintage.value && form3.value.year !== null) {
			const vintage = await store.createVintage({
				wineId: wine.id,
				year: form3.value.year,
				data: {
					alcoholPercent: form3.value.alcoholPercent,
					grapeVarieties: form3.value.grapeVarieties.trim() || null,
					drinkFromYear: form3.value.drinkFromYear,
					drinkUntilYear: form3.value.drinkUntilYear,
					externalRating: form3.value.externalRating,
					externalRatingSource: form3.value.externalRatingSource.trim() || null,
					referenceUrl: form3.value.referenceUrl.trim() || null,
					description: form3.value.description.trim() || null,
				},
			})
			vintageId = vintage.id
		}

		emit('complete', { wineId: wine.id, vintageId })
		emit('close')
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Anlegen fehlgeschlagen')
	} finally {
		saving.value = false
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
	background: #2e7d32;
	color: white;
}
.wizard__section {
	min-height: 200px;
}
.fieldset {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 1rem;
	margin-top: 0.75rem;
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
.field--check {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}
.field--check .field__inline-label {
	display: inline;
	margin-bottom: 0;
	font-size: 0.95rem;
	color: var(--color-main-text);
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
.input:disabled {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	cursor: default;
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
.warn {
	margin-top: 0.5rem;
	font-size: 0.85rem;
	color: var(--color-warning-text, #8a6d00);
}
.error {
	padding: 0.75rem;
	background: var(--color-error, #c62828);
	color: white;
	border-radius: var(--border-radius);
	margin-top: 1rem;
}
.wizard__actions {
	display: flex;
	justify-content: flex-end;
	gap: 0.5rem;
	margin-top: 1.5rem;
}
</style>

<template>
	<NcModal v-if="open" :name="titles[step]" @close="cancel">
		<div class="wizard">
			<h2 class="wizard__title">{{ titles[step] }}</h2>
			<div class="wizard__stepper">
				<span v-for="s in 4" :key="s" :class="['step', { active: step === s, done: step > s }]">{{ s }}</span>
			</div>

			<!-- Step 1: Producer -->
			<section v-if="step === 1" class="wizard__section">
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
				<label v-if="winesForProducer.length > 0" class="field">
					<span>{{ t('vinarium', 'Bestehender Wein') }}</span>
					<select v-model.number="wineId" class="input">
						<option :value="null">{{ t('vinarium', '-- bitte wählen --') }}</option>
						<option v-for="w in winesForProducer" :key="w.id" :value="w.id">{{ w.name }} ({{ t('vinarium', WINE_COLOR_LABELS[w.color]) }})</option>
					</select>
				</label>

				<fieldset class="fieldset">
					<label class="field"><span>{{ t('vinarium', 'Name *') }}</span><input v-model="form2.name" :disabled="isPicked2" class="input" :placeholder="t('vinarium', 'z. B. Chateau Clos Louie (ohne Jahrgang)')" /></label>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Farbe *') }}</span>
							<select v-model="form2.color" :disabled="isPicked2" class="input">
								<option v-for="c in WINE_COLORS" :key="c" :value="c">{{ t('vinarium', WINE_COLOR_LABELS[c]) }}</option>
							</select>
						</label>
						<label class="field"><span>{{ t('vinarium', 'Appellation') }}</span><input v-model="form2.appellation" :disabled="isPicked2" class="input" :placeholder="t('vinarium', 'z. B. Saint-Émilion GC')" /></label>
					</div>
					<label class="field"><span>{{ t('vinarium', 'Barcode') }}</span><input v-model="form2.barcode" :disabled="isPicked2" class="input" /></label>
					<label class="field"><span>{{ t('vinarium', 'Notizen zur Cuvée') }}</span><textarea v-model="form2.notes" :disabled="isPicked2" class="input" rows="2" :placeholder="t('vinarium', 'z. B. tanninbetonter Saint-Émilion, klassischer Bordeaux-Stil')" /></label>
				</fieldset>
			</section>

			<!-- Step 3: Vintage -->
			<section v-else-if="step === 3" class="wizard__section">
				<p class="hint">{{ t('vinarium', 'Hier landen nur Angaben, die diesen Jahrgang betreffen — Wetter/Lese, jahrgangsspezifische Bewertungen, Trinkfenster. Allgemeines zur Cuvée gehört in Schritt 2.') }}</p>
				<label v-if="vintagesForWine.length > 0" class="field">
					<span>{{ t('vinarium', 'Bestehender Jahrgang') }}</span>
					<select v-model.number="vintageId" class="input">
						<option :value="null">{{ t('vinarium', '-- bitte wählen --') }}</option>
						<option v-for="v in vintagesForWine" :key="v.id" :value="v.id">{{ v.year }}</option>
					</select>
				</label>

				<fieldset class="fieldset">
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Jahr *') }}</span><input v-model.number="form3.year" :disabled="isPicked3" type="number" class="input" /></label>
						<label class="field"><span>{{ t('vinarium', 'Alkohol %') }}</span><input v-model.number="form3.alcoholPercent" :disabled="isPicked3" type="number" step="0.1" class="input" :placeholder="t('vinarium', 'z. B. 13,5')" /></label>
					</div>
					<label class="field">
						<span>{{ t('vinarium', 'Rebsorten (jahrgangsspezifisch)') }}</span>
						<input v-model="form3.grapeVarieties" :disabled="isPicked3" class="input" :placeholder="t('vinarium', 'z. B. Merlot 70%, Cabernet Franc 30%')" />
					</label>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Trinken ab (Jahr)') }}</span><input v-model.number="form3.drinkFromYear" :disabled="isPicked3" type="number" min="1900" class="input" :placeholder="t('vinarium', 'z. B. 2025')" /></label>
						<label class="field"><span>{{ t('vinarium', 'Trinken bis (Jahr)') }}</span><input v-model.number="form3.drinkUntilYear" :disabled="isPicked3" type="number" min="1900" class="input" :placeholder="t('vinarium', 'z. B. 2032')" /></label>
					</div>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Externe Bewertung') }}</span><input v-model.number="form3.externalRating" :disabled="isPicked3" type="number" step="0.1" class="input" :placeholder="t('vinarium', 'z. B. 92')" /></label>
						<label class="field"><span>{{ t('vinarium', 'Quelle') }}</span><input v-model="form3.externalRatingSource" :disabled="isPicked3" class="input" :placeholder="t('vinarium', 'z. B. Parker')" /></label>
					</div>
					<label class="field"><span>{{ t('vinarium', 'Referenz-URL') }}</span><input v-model="form3.referenceUrl" :disabled="isPicked3" class="input" /></label>
					<label class="field"><span>{{ t('vinarium', 'Jahrgangsnotizen') }}</span><textarea v-model="form3.description" :disabled="isPicked3" class="input" rows="2" :placeholder="t('vinarium', 'z. B. trockener Sommer 2019, sehr konzentrierte Lese')" /></label>
				</fieldset>
			</section>

			<!-- Step 4: Purchase -->
			<section v-else-if="step === 4" class="wizard__section">
				<p class="hint">{{ t('vinarium', 'Hier landet der eigentliche Kauf: Anzahl Flaschen, Flaschengröße, optional Händler/Preis. Die Flaschen kommen automatisch in die Parkzone.') }}</p>
				<fieldset class="fieldset">
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Kaufdatum *') }}</span><input v-model="form4.purchasedAt" type="date" class="input" /></label>
						<label class="field"><span>{{ t('vinarium', 'Anzahl Flaschen *') }}</span><input v-model.number="form4.quantity" type="number" min="1" class="input" /></label>
					</div>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Flaschengröße *') }}</span>
							<select v-model.number="form4.bottleSizeMl" class="input">
								<option v-for="size in BOTTLE_SIZES" :key="size" :value="size">{{ t('vinarium', BOTTLE_SIZE_LABELS[size]) }}</option>
							</select>
						</label>
						<label class="field"><span>{{ t('vinarium', 'Händler') }}</span>
							<input v-model="form4.vendor" class="input" list="vinarium-vendor-list" :placeholder="t('vinarium', 'z. B. Weinhandlung Müller')" />
							<datalist id="vinarium-vendor-list">
								<option v-for="v in knownVendors" :key="v" :value="v" />
							</datalist>
						</label>
					</div>
					<div class="field-row">
						<label class="field"><span>{{ t('vinarium', 'Stückpreis') }}</span><input v-model.number="form4.unitPrice" type="number" step="0.01" class="input" /></label>
						<label class="field"><span>{{ t('vinarium', 'Währung') }}</span>
							<select v-model="form4.currency" class="input">
								<option value="EUR">EUR</option>
								<option value="USD">USD</option>
								<option value="CHF">CHF</option>
								<option value="GBP">GBP</option>
							</select>
						</label>
					</div>
					<label class="field"><span>{{ t('vinarium', 'Notizen') }}</span><textarea v-model="form4.notes" class="input" rows="2" /></label>

					<!-- Optionales Etiketten-Foto für alle Flaschen dieses Kaufs -->
					<div class="photo-capture">
						<div class="photo-capture__head">
							<span class="photo-capture__label">{{ t('vinarium', 'Etiketten-Foto') }}</span>
							<span class="photo-capture__hint">{{ t('vinarium', 'optional — wird allen Flaschen dieses Kaufs zugeordnet') }}</span>
						</div>
						<div class="photo-capture__body">
							<img v-if="photoPreviewUrl" :src="photoPreviewUrl" class="photo-capture__preview" alt="" />
							<label class="photo-capture__btn">
								<input
									type="file"
									accept="image/*"
									capture="environment"
									class="photo-capture__input"
									@change="onPhotoSelected"
								/>
								<CameraIcon :size="18" />
								<span>{{ photoFile ? t('vinarium', 'Foto ersetzen') : t('vinarium', '📷 Foto aufnehmen') }}</span>
							</label>
							<NcButton v-if="photoFile" variant="tertiary" @click="clearPhoto">
								{{ t('vinarium', 'Entfernen') }}
							</NcButton>
						</div>
					</div>
				</fieldset>
			</section>

			<p v-if="errorMsg" class="error">{{ errorMsg }}</p>

			<div class="wizard__actions">
				<NcButton @click="cancel">{{ t('vinarium', 'Abbrechen') }}</NcButton>
				<NcButton v-if="step > 1" @click="step--">{{ t('vinarium', 'Zurück') }}</NcButton>
				<NcButton v-if="step < 4" variant="primary" :disabled="!canAdvance || saving" @click="advance">
					{{ t('vinarium', 'Weiter') }}
				</NcButton>
				<NcButton v-if="step === 4" variant="primary" :disabled="!isValidPurchase || saving" @click="complete">
					{{ t('vinarium', 'Fertig (Kauf erfassen)') }}
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
import { BOTTLE_SIZES, BOTTLE_SIZE_LABELS, WINE_COLORS, WINE_COLOR_LABELS, type BottleSizeMl, type WineColor } from '@/types/api'
import { useWineStore } from '@/stores/wineStore'
import { createPurchaseViaWizard, listVendors } from '@/api/purchases'
import { uploadBottlePhoto } from '@/api/bottles'
import CameraIcon from 'vue-material-design-icons/Camera.vue'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'complete', payload: { purchaseId: number; bottleCount: number }): void
}>()

const store = useWineStore()
const step = ref(1)
const saving = ref(false)
const errorMsg = ref('')

const photoFile = ref<File | null>(null)
const photoPreviewUrl = ref<string | null>(null)

function onPhotoSelected(event: Event) {
	const target = event.target as HTMLInputElement
	const file = target.files?.[0]
	if (!file) return
	photoFile.value = file
	if (photoPreviewUrl.value) URL.revokeObjectURL(photoPreviewUrl.value)
	photoPreviewUrl.value = URL.createObjectURL(file)
}

function clearPhoto() {
	if (photoPreviewUrl.value) URL.revokeObjectURL(photoPreviewUrl.value)
	photoPreviewUrl.value = null
	photoFile.value = null
}

const producerId = ref<number | null>(null)
const wineId = ref<number | null>(null)
const vintageId = ref<number | null>(null)

const isPicked1 = computed(() => producerId.value !== null)
const isPicked2 = computed(() => wineId.value !== null)
const isPicked3 = computed(() => vintageId.value !== null)

const form1 = ref({ name: '', country: '', region: '', website: '', notes: '' })
const form2 = ref<{ name: string; color: WineColor; appellation: string; barcode: string; notes: string }>({
	name: '', color: 'red', appellation: '', barcode: '', notes: '',
})
const form3 = ref<{
	year: number | null; alcoholPercent: number | null; grapeVarieties: string
	drinkFromYear: number | null; drinkUntilYear: number | null
	externalRating: number | null; externalRatingSource: string; referenceUrl: string; description: string
}>({
	year: new Date().getFullYear(), alcoholPercent: null, grapeVarieties: '',
	drinkFromYear: null, drinkUntilYear: null,
	externalRating: null, externalRatingSource: '', referenceUrl: '', description: '',
})
const form4 = ref<{
	purchasedAt: string; vendor: string; unitPrice: number | null; currency: string
	quantity: number; bottleSizeMl: BottleSizeMl; notes: string
}>({
	purchasedAt: new Date().toISOString().substring(0, 10),
	vendor: '', unitPrice: null, currency: 'EUR', quantity: 6, bottleSizeMl: 750, notes: '',
})

const titles = computed(() => ({
	1: t('vinarium', 'Schritt 1: Weingut'),
	2: t('vinarium', 'Schritt 2: Wein'),
	3: t('vinarium', 'Schritt 3: Jahrgang'),
	4: t('vinarium', 'Schritt 4: Kauf'),
} as const))

const winesForProducer = computed(() => (producerId.value ? store.winesByProducer(producerId.value) : []))
const vintagesForWine = computed(() => (wineId.value ? store.vintagesByWine(wineId.value) : []))

const isValidYear = computed(() => {
	const y = form3.value.year
	return typeof y === 'number' && y >= 1900 && y <= new Date().getFullYear() + 2
})
const canAdvance = computed(() => {
	if (step.value === 1) return producerId.value !== null || form1.value.name.trim() !== ''
	if (step.value === 2) return wineId.value !== null || form2.value.name.trim() !== ''
	if (step.value === 3) return vintageId.value !== null || isValidYear.value
	return true
})
const isValidPurchase = computed(() =>
	(vintageId.value !== null || isValidYear.value)
	&& form4.value.quantity >= 1
	&& BOTTLE_SIZES.includes(form4.value.bottleSizeMl)
	&& form4.value.purchasedAt !== '',
)

// --- watchers: populate form from picked entity, clear on deselect ---

const knownVendors = ref<string[]>([])

watch(() => props.open, async (isOpen) => {
	if (isOpen) {
		step.value = 1
		producerId.value = null
		wineId.value = null
		vintageId.value = null
		errorMsg.value = ''
		clearPhoto()
		resetForm1()
		resetForm2()
		resetForm3()
		await store.fetchProducers()
		try {
			knownVendors.value = await listVendors()
		} catch (e) {
			console.error('Vendor list error:', e)
			knownVendors.value = []
		}
	}
}, { immediate: true })

watch(producerId, async (id) => {
	if (id !== null) {
		const p = store.producerById(id)
		if (p) form1.value = { name: p.name, country: p.country ?? '', region: p.region ?? '', website: p.website ?? '', notes: p.notes ?? '' }
		await store.fetchWinesByProducer(id)
	} else {
		resetForm1()
	}
	wineId.value = null
})

watch(wineId, async (id) => {
	if (id !== null) {
		const w = store.wines.find(w => w.id === id)
		if (w) form2.value = { name: w.name, color: w.color, appellation: w.appellation ?? '', barcode: w.barcode ?? '', notes: w.notes ?? '' }
		await store.fetchVintagesByWine(id)
	} else {
		resetForm2()
	}
	vintageId.value = null
})

watch(vintageId, (id) => {
	if (id !== null) {
		const v = store.vintages.find(v => v.id === id)
		if (v) {
			form3.value = {
				year: v.year, alcoholPercent: v.alcoholPercent, grapeVarieties: v.grapeVarieties ?? '',
				drinkFromYear: v.drinkFromYear, drinkUntilYear: v.drinkUntilYear,
				externalRating: v.externalRating, externalRatingSource: v.externalRatingSource ?? '',
				referenceUrl: v.referenceUrl ?? '', description: v.description ?? '',
			}
		}
	} else {
		resetForm3()
	}
})

function resetForm1() { form1.value = { name: '', country: '', region: '', website: '', notes: '' } }
function resetForm2() { form2.value = { name: '', color: 'red', appellation: '', barcode: '', notes: '' } }
function resetForm3() {
	form3.value = {
		year: new Date().getFullYear(), alcoholPercent: null, grapeVarieties: '',
		drinkFromYear: null, drinkUntilYear: null,
		externalRating: null, externalRatingSource: '', referenceUrl: '', description: '',
	}
}

// --- actions ---

function advance() {
	// Nothing is persisted until the wizard is completed; just move forward.
	errorMsg.value = ''
	step.value++
}

function cancel() { emit('close') }

async function complete() {
	if (!isValidPurchase.value) return
	saving.value = true
	errorMsg.value = ''
	try {
		const result = await createPurchaseViaWizard({
			producer: {
				id: producerId.value,
				data: {
					name: form1.value.name,
					country: form1.value.country || null,
					region: form1.value.region || null,
					website: form1.value.website || null,
					notes: form1.value.notes || null,
				},
			},
			wine: {
				id: wineId.value,
				data: {
					name: form2.value.name,
					color: form2.value.color,
					appellation: form2.value.appellation || null,
					barcode: form2.value.barcode || null,
					notes: form2.value.notes || null,
				},
			},
			vintage: {
				id: vintageId.value,
				data: {
					year: form3.value.year,
					alcoholPercent: form3.value.alcoholPercent,
					grapeVarieties: form3.value.grapeVarieties || null,
					drinkFromYear: form3.value.drinkFromYear,
					drinkUntilYear: form3.value.drinkUntilYear,
					externalRating: form3.value.externalRating,
					externalRatingSource: form3.value.externalRatingSource || null,
					referenceUrl: form3.value.referenceUrl || null,
					description: form3.value.description || null,
				},
			},
			purchase: {
				purchasedAt: form4.value.purchasedAt,
				vendor: form4.value.vendor || null,
				unitPrice: form4.value.unitPrice,
				currency: form4.value.currency,
				quantity: form4.value.quantity,
				bottleSizeMl: form4.value.bottleSizeMl,
				notes: form4.value.notes || null,
			},
		})
		// Optionales Etiketten-Foto: für jede neu angelegte Flasche hochladen.
		// Best-effort — Upload-Fehler dürfen den Wizard nicht blockieren, aber wir loggen sie.
		if (photoFile.value && result.bottles.length > 0) {
			try {
				await Promise.all(
					result.bottles.map(b => uploadBottlePhoto(b.id, photoFile.value as File))
				)
			} catch (uploadErr) {
				console.error('Photo upload failed for some bottles:', uploadErr)
			}
		}

		emit('complete', { purchaseId: result.purchase.id, bottleCount: result.bottles.length })
		clearPhoto()
		emit('close')
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Kauf konnte nicht erfasst werden')
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
/* Foto-Capture-Block in Step 4 */
.photo-capture {
	margin-top: 14px;
	padding: 12px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius, 8px);
}
.photo-capture__head {
	display: flex;
	align-items: baseline;
	gap: 8px;
	margin-bottom: 10px;
}
.photo-capture__label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-main-text);
}
.photo-capture__hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
.photo-capture__body {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
}
.photo-capture__preview {
	width: 80px;
	height: 80px;
	object-fit: cover;
	border-radius: 6px;
	border: 1px solid var(--color-border);
}
.photo-capture__btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 8px 14px;
	background: var(--color-primary-element, #0082c9);
	color: #fff;
	font-size: 13px;
	font-weight: 600;
	border-radius: var(--border-radius-element, 8px);
	cursor: pointer;
}
.photo-capture__btn:hover {
	background: var(--color-primary-element-hover, #006aa3);
}
.photo-capture__input {
	display: none;
}

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
	margin-top: 2rem;
}
</style>

<template>
	<NcModal
		v-if="open"
		:name="modalTitle"
		size="large"
		@close="$emit('close')"
	>
		<div class="bd-modal">
			<div v-if="loading" class="bd-loading">
				<p class="muted">{{ t('vinarium', 'Laden...') }}</p>
			</div>

			<template v-else-if="detail">
				<!-- Header -->
				<div class="bd-head">
					<div class="bd-nav">
						<button
							class="bd-nav-btn"
							:disabled="navIndex <= 0"
							:title="t('vinarium', 'Vorherige Flasche')"
							@click="navigate(-1)"
						>‹</button>
						<span v-if="totalCount > 1" class="bd-nav-counter">{{ navIndex + 1 }} / {{ totalCount }}</span>
						<button
							class="bd-nav-btn"
							:disabled="navIndex < 0 || navIndex >= totalCount - 1"
							:title="t('vinarium', 'Nächste Flasche')"
							@click="navigate(1)"
						>›</button>
					</div>
					<span class="bd-dot" :style="{ background: cssColorFor(detail.wine_color) }"></span>
					<div class="bd-title">
						<div>
							<strong class="bd-wine">{{ detail.wine_name }}</strong>
							<span class="bd-year muted">{{ detail.year }}</span>
						</div>
						<div class="bd-producer muted">{{ detail.producer_name }}</div>
					</div>
				</div>

				<!-- Tabs (look identical to InventoryView .subtabs) -->
				<nav class="bd-subtabs" role="tablist" :aria-label="t('vinarium', 'Detail-Reiter')">
					<button
						v-for="tab in tabs"
						:key="tab.key"
						:id="`bd-tab-${tab.key}`"
						role="tab"
						:aria-selected="activeTab === tab.key"
						:aria-controls="`bd-panel-${tab.key}`"
						:tabindex="activeTab === tab.key ? 0 : -1"
						:class="['bd-subtab', { 'bd-subtab--active': activeTab === tab.key }]"
						@click="activeTab = tab.key"
					>
						{{ tab.label }}
					</button>
				</nav>

				<!-- Tab: Flasche (Übersicht, read-only) -->
				<div v-if="activeTab === 'bottle'" id="bd-panel-bottle" role="tabpanel" aria-labelledby="bd-tab-bottle" class="bd-panel">
					<div class="bd-bottle-grid">
						<div class="bd-photo-col">
							<div class="bd-photo-wrap">
								<img
									v-if="detail.photo_file_id !== null"
									:src="photoUrl"
									:key="photoUrl"
									class="bd-photo"
									:alt="detail.wine_name"
								/>
								<div v-else class="bd-photo-placeholder muted">
									{{ t('vinarium', 'Kein Foto') }}
								</div>
							</div>
							<div class="bd-photo-actions">
								<label class="bd-photo-upload" :title="t('vinarium', 'Foto hochladen')">
									<input type="file" accept="image/*" class="bd-photo-input" @change="onPhotoSelected" />
									{{ detail.photo_file_id !== null ? t('vinarium', 'Ersetzen') : t('vinarium', 'Foto hinzufügen') }}
								</label>
								<button
									v-if="detail.photo_file_id !== null"
									class="bd-photo-remove"
									:title="t('vinarium', 'Foto entfernen')"
									@click="onRemovePhoto"
								>✕</button>
							</div>
							<p v-if="photoError" class="bd-error">{{ photoError }}</p>
						</div>

						<dl class="bd-kv">
							<div class="bd-kv-group">{{ t('vinarium', 'Status') }}</div>
							<dt>{{ t('vinarium', 'Status') }}</dt>
							<dd>{{ t('vinarium', BOTTLE_STATUS_LABELS[detail.status as BottleStatus] ?? detail.status) }}</dd>
							<template v-if="detail.slot_id !== null">
								<dt>{{ t('vinarium', 'Slot') }}</dt>
								<dd>
									{{ detail.compartment_label }}
									<span class="muted">
										· {{ t('vinarium', 'Ebene') }} {{ (detail.slot_level ?? 0) + 1 }}
										· {{ detail.slot_row === 'back' ? t('vinarium', 'Hinten') : t('vinarium', 'Vorne') }}
										· {{ t('vinarium', 'Platz') }} {{ (detail.slot_column ?? 0) + 1 }}
									</span>
								</dd>
							</template>

							<template v-if="detail.appellation || detail.grape_varieties || detail.alcohol_percent || detail.drink_from_year || detail.drink_until_year">
								<div class="bd-kv-group">{{ t('vinarium', 'Wein') }}</div>
								<template v-if="detail.appellation">
									<dt>{{ t('vinarium', 'Appellation') }}</dt>
									<dd>{{ detail.appellation }}</dd>
								</template>
								<template v-if="detail.grape_varieties">
									<dt>{{ t('vinarium', 'Rebsorten') }}</dt>
									<dd>{{ detail.grape_varieties }}</dd>
								</template>
								<template v-if="detail.alcohol_percent">
									<dt>{{ t('vinarium', 'Alkohol') }}</dt>
									<dd>{{ detail.alcohol_percent }} %</dd>
								</template>
								<template v-if="detail.drink_from_year || detail.drink_until_year">
									<dt>{{ t('vinarium', 'Trinkfenster') }}</dt>
									<dd>
										<span v-if="detail.drink_from_year">{{ detail.drink_from_year }}</span>
										<span v-if="detail.drink_from_year && detail.drink_until_year"> – </span>
										<span v-if="detail.drink_until_year">{{ detail.drink_until_year }}</span>
									</dd>
								</template>
								<template v-if="detail.external_rating">
									<dt>{{ t('vinarium', 'Externe Bewertung') }}</dt>
									<dd>{{ detail.external_rating }}<span v-if="detail.external_rating_source" class="muted"> ({{ detail.external_rating_source }})</span></dd>
								</template>
							</template>

							<div class="bd-kv-group">{{ t('vinarium', 'Kauf') }}</div>
							<dt>{{ t('vinarium', 'Datum') }}</dt>
							<dd>{{ formatDate(detail.purchased_at) }}</dd>
							<template v-if="detail.vendor">
								<dt>{{ t('vinarium', 'Händler') }}</dt>
								<dd>{{ detail.vendor }}</dd>
							</template>
							<template v-if="detail.unit_price !== null">
								<dt>{{ t('vinarium', 'Preis') }}</dt>
								<dd>{{ detail.unit_price!.toFixed(2) }} {{ detail.currency ?? '€' }}</dd>
							</template>
							<dt>{{ t('vinarium', 'Größe') }}</dt>
							<dd>{{ t('vinarium', BOTTLE_SIZE_LABELS[detail.bottle_size_ml as BottleSizeMl] ?? detail.bottle_size_ml + ' ml') }}</dd>
						</dl>
					</div>
				</div>

				<!-- Tab: Weingut -->
				<form v-if="activeTab === 'producer'" id="bd-panel-producer" role="tabpanel" aria-labelledby="bd-tab-producer" class="bd-panel bd-form" @submit.prevent="saveSection">
					<p class="bd-scope">{{ t('vinarium', 'Wirkt auf alle Flaschen dieses Weinguts.') }}</p>
					<div class="bd-form-grid">
						<label class="bd-full"><span>{{ t('vinarium', 'Name') }}</span><input v-model="form.producer_name" required /></label>
						<label><span>{{ t('vinarium', 'Land') }}</span><input v-model="form.producer_country" /></label>
						<label><span>{{ t('vinarium', 'Region') }}</span><input v-model="form.producer_region" /></label>
						<label class="bd-full"><span>{{ t('vinarium', 'Website') }}</span><input v-model="form.producer_website" type="url" placeholder="https://…" /></label>
					</div>
					<p v-if="editError" class="bd-error">{{ editError }}</p>
				</form>

				<!-- Tab: Wein -->
				<form v-if="activeTab === 'wine'" id="bd-panel-wine" role="tabpanel" aria-labelledby="bd-tab-wine" class="bd-panel bd-form" @submit.prevent="saveSection">
					<p class="bd-scope">{{ t('vinarium', 'Wirkt auf alle Flaschen dieses Weins.') }}</p>
					<div class="bd-form-grid">
						<label class="bd-full"><span>{{ t('vinarium', 'Wein-Name') }}</span><input v-model="form.wine_name" required /></label>
						<label><span>{{ t('vinarium', 'Farbe') }}</span>
							<select v-model="form.wine_color">
								<option v-for="c in WINE_COLORS" :key="c" :value="c">{{ t('vinarium', WINE_COLOR_LABELS[c]) }}</option>
							</select>
						</label>
						<label class="bd-full"><span>{{ t('vinarium', 'Appellation') }}</span><input v-model="form.appellation" /></label>
					</div>
					<p v-if="editError" class="bd-error">{{ editError }}</p>
				</form>

				<!-- Tab: Jahrgang -->
				<form v-if="activeTab === 'vintage'" id="bd-panel-vintage" role="tabpanel" aria-labelledby="bd-tab-vintage" class="bd-panel bd-form" @submit.prevent="saveSection">
					<p class="bd-scope">{{ t('vinarium', 'Wirkt auf alle Flaschen dieses Jahrgangs.') }}</p>
					<div class="bd-form-grid">
						<label><span>{{ t('vinarium', 'Jahr') }}</span><input v-model.number="form.year" type="number" required /></label>
						<label><span>{{ t('vinarium', 'Alkohol (%)') }}</span><input v-model.number="form.alcohol_percent" type="number" step="0.1" /></label>
						<label class="bd-full"><span>{{ t('vinarium', 'Rebsorten') }}</span><input v-model="form.grape_varieties" /></label>
						<label><span>{{ t('vinarium', 'Trinken ab') }}</span><input v-model.number="form.drink_from_year" type="number" /></label>
						<label><span>{{ t('vinarium', 'Trinken bis') }}</span><input v-model.number="form.drink_until_year" type="number" /></label>
						<label><span>{{ t('vinarium', 'Externe Bewertung') }}</span><input v-model.number="form.external_rating" type="number" step="0.1" /></label>
						<label><span>{{ t('vinarium', 'Quelle') }}</span><input v-model="form.external_rating_source" /></label>
					</div>
					<p v-if="editError" class="bd-error">{{ editError }}</p>
				</form>

				<!-- Tab: Kauf -->
				<form v-if="activeTab === 'purchase'" id="bd-panel-purchase" role="tabpanel" aria-labelledby="bd-tab-purchase" class="bd-panel bd-form" @submit.prevent="saveSection">
					<p class="bd-scope">{{ t('vinarium', 'Wirkt auf alle Flaschen dieser Charge.') }}</p>
					<div class="bd-form-grid">
						<label><span>{{ t('vinarium', 'Kaufdatum') }}</span><input v-model="form.purchased_at" type="date" /></label>
						<label><span>{{ t('vinarium', 'Größe') }}</span>
							<select v-model.number="form.bottle_size_ml">
								<option v-for="(label, key) in BOTTLE_SIZE_LABELS" :key="key" :value="Number(key)">{{ t('vinarium', label) }}</option>
							</select>
						</label>
						<label class="bd-full"><span>{{ t('vinarium', 'Händler') }}</span><input v-model="form.vendor" /></label>
						<label><span>{{ t('vinarium', 'Preis') }}</span><input v-model.number="form.unit_price" type="number" step="0.01" /></label>
						<label><span>{{ t('vinarium', 'Währung') }}</span><input v-model="form.currency" maxlength="3" /></label>
					</div>
					<p v-if="editError" class="bd-error">{{ editError }}</p>
				</form>

				<!-- Footer -->
				<div class="bd-foot">
					<div class="bd-foot-left">
						<NcButton v-if="detail.status === 'in_storage'" @click="$emit('uncork', detail.id)">{{ t('vinarium', 'Entkorken') }}</NcButton>
						<NcButton v-if="detail.status === 'in_storage'" @click="$emit('gift', detail.id)">{{ t('vinarium', 'Verschenken') }}</NcButton>
						<NcButton v-if="detail.status === 'in_storage'" @click="$emit('lose', detail.id)">{{ t('vinarium', 'Verloren') }}</NcButton>
					</div>
					<div class="bd-foot-right">
						<NcButton @click="$emit('close')">{{ t('vinarium', 'Schließen') }}</NcButton>
						<NcButton
							v-if="activeTab !== 'bottle'"
							variant="primary"
							:disabled="saving"
							@click="saveSection"
						>
							{{ saving ? t('vinarium', 'Speichert…') : t('vinarium', 'Speichern') }}
						</NcButton>
					</div>
				</div>
			</template>

			<div v-else-if="error" class="bd-hint">
				<p class="muted">{{ error }}</p>
			</div>
		</div>

		<PhotoCropDialog
			:open="cropOpen"
			:file="cropSourceFile"
			@close="onCropCancel"
			@confirm="onCropConfirm"
		/>
	</NcModal>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import PhotoCropDialog from '@/components/PhotoCropDialog.vue'
import { getBottleDetails, getBottlePhotoUrl, uploadBottlePhoto, deleteBottlePhoto, type BottleDetail } from '@/api/bottles'
import { updateProducer } from '@/api/producers'
import { updateWine } from '@/api/wines'
import { updateVintage } from '@/api/vintages'
import { updatePurchase } from '@/api/purchases'
import { BOTTLE_SIZE_LABELS, BOTTLE_STATUS_LABELS, WINE_COLORS, WINE_COLOR_LABELS, type BottleSizeMl, type WineColor, type BottleStatus } from '@/types/api'
import { cssColorFor } from '@/utils/wineColors'
import { formatDate } from '@/utils/date'

type TabKey = 'bottle' | 'producer' | 'wine' | 'vintage' | 'purchase'

const props = withDefaults(defineProps<{
	bottleId: number | null
	bottleIds?: number[]
	initialTab?: TabKey
}>(), {
	bottleIds: () => [],
	initialTab: 'bottle',
})

const emit = defineEmits<{
	(e: 'close'): void
	(e: 'uncork', bottleId: number): void
	(e: 'gift', bottleId: number): void
	(e: 'lose', bottleId: number): void
	(e: 'photo-changed'): void
	(e: 'data-changed'): void
	(e: 'navigate', bottleId: number): void
}>()

const detail = ref<BottleDetail | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const photoError = ref<string | null>(null)
const cropOpen = ref(false)
const cropSourceFile = ref<File | null>(null)
const activeTab = ref<TabKey>(props.initialTab)
const saving = ref(false)
const editError = ref<string | null>(null)
const form = ref<Record<string, any>>({})

const open = computed(() => props.bottleId !== null)

const tabs: { key: TabKey, label: string }[] = [
	{ key: 'bottle', label: t('vinarium', 'Flasche') },
	{ key: 'producer', label: t('vinarium', 'Weingut') },
	{ key: 'wine', label: t('vinarium', 'Wein') },
	{ key: 'vintage', label: t('vinarium', 'Jahrgang') },
	{ key: 'purchase', label: t('vinarium', 'Kauf') },
]

const navIndex = computed(() => {
	if (props.bottleId === null) return -1
	return props.bottleIds.indexOf(props.bottleId)
})
const totalCount = computed(() => props.bottleIds.length)

const modalTitle = computed(() => detail.value
	? `${detail.value.wine_name} ${detail.value.year}`
	: t('vinarium', 'Flasche'))

const photoUrl = computed(() =>
	detail.value !== null && detail.value?.photo_file_id !== null
		? `${getBottlePhotoUrl(detail.value.id)}?v=${detail.value.photo_file_id}`
		: null,
)

watch(() => props.bottleId, async (id) => {
	if (id === null) {
		detail.value = null
		error.value = null
		return
	}
	loading.value = true
	detail.value = null
	error.value = null
	photoError.value = null
	editError.value = null
	saving.value = false
	try {
		detail.value = await getBottleDetails(id)
		prefillForm()
	} catch (e: any) {
		error.value = e?.message ?? t('vinarium', 'Fehler beim Laden')
	} finally {
		loading.value = false
	}
}, { immediate: true })

// When the user switches tabs, prefill the form for that tab from current detail
watch(activeTab, () => prefillForm())

function prefillForm() {
	if (!detail.value) return
	const d = detail.value
	if (activeTab.value === 'producer') {
		form.value = {
			producer_name: d.producer_name,
			producer_country: d.producer_country ?? '',
			producer_region: d.producer_region ?? '',
			producer_website: d.producer_website ?? '',
		}
	} else if (activeTab.value === 'wine') {
		form.value = {
			wine_name: d.wine_name,
			wine_color: d.wine_color,
			appellation: d.appellation ?? '',
		}
	} else if (activeTab.value === 'vintage') {
		form.value = {
			year: d.year,
			alcohol_percent: d.alcohol_percent,
			grape_varieties: d.grape_varieties ?? '',
			drink_from_year: d.drink_from_year,
			drink_until_year: d.drink_until_year,
			external_rating: d.external_rating,
			external_rating_source: d.external_rating_source ?? '',
		}
	} else if (activeTab.value === 'purchase') {
		form.value = {
			purchased_at: d.purchased_at?.slice(0, 10) ?? '',
			vendor: d.vendor ?? '',
			unit_price: d.unit_price,
			currency: d.currency ?? 'EUR',
			bottle_size_ml: d.bottle_size_ml,
		}
	}
	editError.value = null
}

async function saveSection() {
	if (!detail.value) return
	saving.value = true
	editError.value = null
	try {
		if (activeTab.value === 'producer') {
			await updateProducer(detail.value.producer_id, {
				name: form.value.producer_name,
				country: form.value.producer_country || null,
				region: form.value.producer_region || null,
				website: form.value.producer_website || null,
			})
		} else if (activeTab.value === 'wine') {
			await updateWine(detail.value.wine_id, {
				name: form.value.wine_name,
				color: form.value.wine_color as WineColor,
				data: { appellation: form.value.appellation || null },
			})
		} else if (activeTab.value === 'vintage') {
			await updateVintage(detail.value.vintage_id, {
				year: form.value.year,
				data: {
					alcoholPercent: form.value.alcohol_percent ?? null,
					grapeVarieties: form.value.grape_varieties || null,
					drinkFromYear: form.value.drink_from_year ?? null,
					drinkUntilYear: form.value.drink_until_year ?? null,
					externalRating: form.value.external_rating ?? null,
					externalRatingSource: form.value.external_rating_source || null,
				},
			})
		} else if (activeTab.value === 'purchase') {
			await updatePurchase(detail.value.purchase_id, {
				purchasedAt: form.value.purchased_at || undefined,
				vendor: form.value.vendor || null,
				unitPrice: form.value.unit_price ?? null,
				currency: form.value.currency || undefined,
				bottleSizeMl: form.value.bottle_size_ml,
			})
		}
		detail.value = await getBottleDetails(detail.value.id)
		prefillForm()
		emit('data-changed')
	} catch (e: any) {
		editError.value = e?.response?.data?.error ?? e?.message ?? t('vinarium', 'Speichern fehlgeschlagen')
	} finally {
		saving.value = false
	}
}

function navigate(delta: 1 | -1) {
	const i = navIndex.value
	if (i < 0) return
	const next = i + delta
	if (next < 0 || next >= props.bottleIds.length) return
	emit('navigate', props.bottleIds[next])
}

function onKeydown(e: KeyboardEvent) {
	if (!open.value) return
	if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement || e.target instanceof HTMLSelectElement) return
	if (e.key === 'ArrowLeft') { e.preventDefault(); navigate(-1) }
	else if (e.key === 'ArrowRight') { e.preventDefault(); navigate(1) }
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

function onPhotoSelected(event: Event) {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	if (!file || !detail.value) return
	photoError.value = null
	cropSourceFile.value = file
	cropOpen.value = true
	input.value = ''
}

function onCropCancel() {
	cropOpen.value = false
	cropSourceFile.value = null
}

async function onCropConfirm(file: File) {
	cropOpen.value = false
	cropSourceFile.value = null
	if (!detail.value) return
	try {
		const result = await uploadBottlePhoto(detail.value.id, file)
		detail.value = { ...detail.value, photo_file_id: result.photo_file_id }
		emit('photo-changed')
	} catch (e: any) {
		photoError.value = e?.message ?? t('vinarium', 'Upload fehlgeschlagen')
	}
}

async function onRemovePhoto() {
	if (!detail.value) return
	photoError.value = null
	try {
		await deleteBottlePhoto(detail.value.id)
		detail.value = { ...detail.value, photo_file_id: null }
		emit('photo-changed')
	} catch (e: any) {
		photoError.value = e?.message ?? t('vinarium', 'Entfernen fehlgeschlagen')
	}
}
</script>

<style scoped>
.bd-modal {
	display: flex; flex-direction: column;
	min-height: 540px;
	max-height: 80vh;
	width: 100%;
}
/* NcModal teleportiert in body — scoped styles greifen über :deep auf den Wrapper.
   Large default ist auf manchen Viewports zu schmal für die Edit-Forms; auf min 880px ziehen. */
:deep(.modal-container__content) { padding: 0; }
.bd-loading, .bd-hint {
	min-height: 200px;
	display: flex; align-items: center; justify-content: center;
}
.muted { color: var(--color-text-maxcontrast); }

/* Header */
.bd-head {
	display: flex; align-items: flex-start; gap: 14px;
	padding: 18px 24px 6px;
}
.bd-nav {
	display: flex; align-items: center; gap: 4px;
	flex-shrink: 0;
}
.bd-nav-btn {
	width: 32px; height: 32px;
	background: none; border: none; cursor: pointer;
	border-radius: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 22px; line-height: 1;
	display: flex; align-items: center; justify-content: center;
}
.bd-nav-btn:hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}
.bd-nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.bd-nav-counter {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	padding: 0 6px;
	min-width: 56px;
	text-align: center;
	font-variant-numeric: tabular-nums;
}
.bd-dot {
	width: 12px; height: 12px;
	border-radius: 50%;
	margin-top: 7px;
	flex-shrink: 0;
}
.bd-title { flex: 1; min-width: 0; }
.bd-wine { font-size: 18px; font-weight: 700; line-height: 1.2; }
.bd-year { font-size: 14px; margin-left: 6px; font-weight: 400; }
.bd-producer { font-size: 12.5px; margin-top: 2px; }

/* Subtabs (mirror InventoryView .subtabs) */
.bd-subtabs {
	display: flex; gap: 2px;
	border-bottom: 1px solid var(--color-border);
	padding: 0 24px;
	margin-top: 6px;
}
.bd-subtab {
	font-family: inherit;
	font-size: 14px; font-weight: 600;
	color: var(--color-text-maxcontrast);
	background: none; border: none;
	border-bottom: 2px solid transparent;
	border-radius: 0; box-shadow: none;
	padding: 10px 16px; cursor: pointer;
	margin-bottom: -1px;
	outline: none;
	min-height: 0;
}
.bd-subtab:hover { color: var(--color-main-text); background: transparent; box-shadow: none; }
.bd-subtab:focus-visible {
	box-shadow: inset 0 0 0 2px var(--color-primary-element, #0082c9);
}
.bd-subtab--active {
	color: var(--color-primary-element);
	border-bottom-color: var(--color-primary-element);
}

/* Panels */
.bd-panel {
	padding: 22px 26px;
	flex: 1;
	overflow-y: auto;
}

/* Flasche tab: photo + kv */
.bd-bottle-grid {
	display: grid;
	grid-template-columns: 240px 1fr;
	gap: 26px;
	align-items: start;
}
.bd-photo-col { display: flex; flex-direction: column; gap: 8px; }
.bd-photo-wrap {
	width: 240px; aspect-ratio: 3 / 4;
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-background-dark);
}
.bd-photo {
	width: 100%; height: 100%;
	object-fit: contain;
}
.bd-photo-placeholder {
	width: 100%; height: 100%;
	display: flex; align-items: center; justify-content: center;
	font-size: 0.85rem;
}
.bd-photo-actions {
	display: flex; align-items: center; gap: 0.5rem;
}
.bd-photo-upload {
	display: inline-flex; align-items: center;
	padding: 0.3rem 0.75rem;
	background: var(--color-background-dark);
	border: 1px solid var(--color-border);
	border-radius: 6px;
	cursor: pointer;
	font-size: 0.8rem;
}
.bd-photo-upload:hover { background: var(--color-background-hover); }
.bd-photo-input { display: none; }
.bd-photo-remove {
	background: none; border: none; cursor: pointer;
	color: var(--color-text-maxcontrast); font-size: 0.9rem; padding: 0 4px;
}
.bd-photo-remove:hover { color: var(--color-error, #c62828); }

.bd-kv {
	display: grid;
	grid-template-columns: 130px 1fr;
	gap: 8px 18px;
	font-size: 13.5px;
	margin: 0;
}
.bd-kv dt { color: var(--color-text-maxcontrast); }
.bd-kv dd { margin: 0; word-break: break-word; }
.bd-kv-group {
	grid-column: 1 / -1;
	font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em;
	color: var(--color-text-maxcontrast); font-weight: 600;
	margin-top: 8px;
}
.bd-kv-group:first-child { margin-top: 0; }

/* Forms */
.bd-form { display: flex; flex-direction: column; }
.bd-scope {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	font-style: italic;
	margin: 0 0 14px 0;
}
.bd-form-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px 22px;
}
.bd-form-grid label {
	display: flex; flex-direction: column;
	gap: 4px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
.bd-form-grid label span { font-size: 12px; }
/* Input layout rules for the form grid are in the unscoped <style> block at
   the bottom of this file — scoped CSS attribute selectors don't reach the
   inputs reliably because NcModal teleports the slot content to <body>. */
.bd-form-grid input:focus,
.bd-form-grid select:focus,
.bd-form-grid textarea:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -1px;
	border-color: var(--color-primary-element);
}
.bd-full { grid-column: 1 / -1; }

.bd-error {
	font-size: 12px;
	color: var(--color-error, #c62828);
	margin: 12px 0 0;
}

/* Footer */
.bd-foot {
	display: flex; align-items: center; justify-content: space-between;
	gap: 12px;
	padding: 14px 24px;
	border-top: 1px solid var(--color-border);
	background: var(--color-background-hover, #fafafa);
}
.bd-foot-left, .bd-foot-right {
	display: flex; gap: 8px;
}
</style>

<!--
  Unscoped block: NcModal teleports the slot to <body>, which strips the scoped
  data-v attributes from the actual DOM tree. We namespace via `.bd-modal` so
  the rule still only applies to this component's modal contents.
-->
<style>
.bd-modal .bd-form-grid input,
.bd-modal .bd-form-grid select,
.bd-modal .bd-form-grid textarea {
	width: 100% !important;
	padding: 7px 10px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	font-size: 14px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-family: inherit;
	box-sizing: border-box;
}
</style>

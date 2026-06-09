<template>
	<div class="master-data">
		<div v-if="!entityType" class="master-data__tabs">
			<button
				v-for="tab in tabs"
				:key="tab.key"
				:class="['master-data__tab', { 'master-data__tab--active': activeTab === tab.key }]"
				@click="internalTab = tab.key"
			>
				{{ tab.label }} <span class="master-data__count">({{ tab.count }})</span>
			</button>
		</div>

		<!-- Producers -->
		<section v-if="activeTab === 'producers'" class="master-data__panel">
			<div class="master-data__actions">
				<NcButton variant="primary" @click="openCreate('producer')">{{ t('vinarium', '+ Weingut') }}</NcButton>
			</div>
			<p v-if="store.producers.length === 0" class="master-data__empty">{{ t('vinarium', 'Noch keine Weingüter erfasst.') }}</p>
			<div v-else class="md-card">
				<table class="md-tbl">
					<thead>
						<tr>
							<th>{{ t('vinarium', 'Weingut') }}</th>
							<th>{{ t('vinarium', 'Region') }}</th>
							<th>{{ t('vinarium', 'Land') }}</th>
							<th class="r">{{ t('vinarium', 'Weine') }}</th>
							<th class="r"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="p in store.producers" :key="p.id">
							<td><strong>{{ p.name }}</strong></td>
							<td>{{ p.region ?? '—' }}</td>
							<td>{{ p.country ?? '—' }}</td>
							<td class="r">{{ store.winesByProducer(p.id).length }}</td>
							<td class="r">
								<NcActions :aria-label="t('vinarium', 'Aktionen')">
									<NcActionButton @click="editEntity('producer', p.id)">
										<template #icon><Pencil :size="20" /></template>
										{{ t('vinarium', 'Bearbeiten') }}
									</NcActionButton>
									<NcActionButton @click="deleteEntity('producer', p.id)">
										<template #icon><Delete :size="20" /></template>
										{{ t('vinarium', 'Löschen') }}
									</NcActionButton>
								</NcActions>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>

		<!-- Wines & Vintages combined: grouped wine × vintage view -->
		<section v-else-if="activeTab === 'wines'" class="master-data__panel">
			<p v-if="store.wines.length === 0" class="master-data__empty">{{ t('vinarium', 'Noch keine Weine erfasst.') }}</p>
			<div v-else class="md-card">
				<table class="md-tbl md-wines-grouped">
					<thead>
						<tr>
							<th>{{ t('vinarium', 'Wein / Jahrgang') }}</th>
							<th>{{ t('vinarium', 'Appellation') }}</th>
							<th>{{ t('vinarium', 'Trinkfenster') }}</th>
							<th class="r">{{ t('vinarium', 'Alkohol') }}</th>
							<th class="r">{{ t('vinarium', 'Bewertung') }}</th>
							<th class="r">{{ t('vinarium', 'Flaschen') }}</th>
							<th class="r"></th>
						</tr>
					</thead>
					<tbody>
						<template v-for="w in sortedWines" :key="w.id">
							<tr class="wine-head" @click="editEntity('wine', w.id)">
								<td colspan="6">
									<span class="dot" :style="{ background: cssColorFor(w.color) }"></span>
									<strong>{{ w.name }}</strong>
									<span class="subline">
										{{ store.producerById(w.producerId)?.name ?? '—' }}
										<span class="muted"> · {{ t('vinarium', WINE_COLOR_LABELS[w.color]) }}</span>
									</span>
								</td>
								<td class="r icon-cell" @click.stop>
									<button
										class="trash-btn"
										:title="t('vinarium', 'Wein löschen')"
										@click="deleteEntity('wine', w.id)"
									>
										<Delete :size="18" />
									</button>
								</td>
							</tr>
							<tr
								v-for="(v, i) in vintagesForWine(w.id)"
								:key="v.id"
								class="vintage-row"
								:class="{ 'is-last': i === vintagesForWine(w.id).length - 1 }"
								@click="editEntity('vintage', v.id)"
							>
								<td>{{ v.year }}</td>
								<td>{{ w.appellation ?? '—' }}</td>
								<td>
									<span v-if="trinkfensterText(v) !== null" class="tw" :class="trinkfensterClass(v)">{{ trinkfensterText(v) }}</span>
									<span v-else class="muted">—</span>
								</td>
								<td class="r">{{ v.alcoholPercent !== null ? v.alcoholPercent + ' %' : '—' }}</td>
								<td class="r">
									<span v-if="v.externalRating !== null" class="rat">
										<span class="rat__val">{{ Number(v.externalRating).toFixed(1) }}</span>
										<span class="rat__bar"><i :style="{ width: ratingPct(v.externalRating) + '%' }"></i></span>
									</span>
									<span v-else class="muted">—</span>
								</td>
								<td class="r">{{ bottleCountForVintage(v.id) }}</td>
								<td class="r icon-cell" @click.stop>
									<button
										class="trash-btn"
										:title="t('vinarium', 'Jahrgang löschen')"
										@click="deleteEntity('vintage', v.id)"
									>
										<Delete :size="18" />
									</button>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
		</section>

		<!-- Purchases -->
		<section v-else-if="activeTab === 'purchases'" class="master-data__panel">
			<p v-if="store.purchases.length === 0" class="master-data__empty">{{ t('vinarium', 'Noch keine Käufe erfasst.') }}</p>
			<div v-else class="md-card">
				<table class="md-tbl">
					<thead>
						<tr>
							<th>{{ t('vinarium', 'Datum') }}</th>
							<th>{{ t('vinarium', 'Weingut') }}</th>
							<th>{{ t('vinarium', 'Wein') }}</th>
							<th>{{ t('vinarium', 'Jahrgang') }}</th>
							<th class="r">{{ t('vinarium', 'Menge') }}</th>
							<th>{{ t('vinarium', 'Größe') }}</th>
							<th class="r">{{ t('vinarium', 'Preis') }}</th>
							<th>{{ t('vinarium', 'Händler') }}</th>
							<th class="r"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="pu in store.purchases" :key="pu.id">
							<td>{{ formatDate(pu.purchased_at) }}</td>
							<td>{{ pu.producer_name }}</td>
							<td><strong>{{ pu.wine_name }}</strong></td>
							<td>{{ pu.year }}</td>
							<td class="r">{{ pu.quantity }}×</td>
							<td>{{ t('vinarium', BOTTLE_SIZE_LABELS[pu.bottle_size_ml as BottleSizeMl] ?? pu.bottle_size_ml + ' ml') }}</td>
							<td class="r">{{ pu.unit_price !== null ? pu.unit_price.toFixed(2) + ' ' + (pu.currency ?? '€') : '—' }}</td>
							<td>{{ pu.vendor ?? '—' }}</td>
							<td class="r">
								<NcActions :aria-label="t('vinarium', 'Aktionen')">
									<NcActionButton @click="editEntity('purchase', pu.id)">
										<template #icon><Pencil :size="20" /></template>
										{{ t('vinarium', 'Bearbeiten') }}
									</NcActionButton>
									<NcActionButton @click="deleteEntity('purchase', pu.id)">
										<template #icon><Delete :size="20" /></template>
										{{ t('vinarium', 'Löschen') }}
									</NcActionButton>
								</NcActions>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>

		<EntityEditModal
			:open="editOpen"
			:type="editType"
			:entity-id="editId"
			@close="closeEdit"
		/>
		<ConfirmDialog
			:open="deleteConfirmOpen"
			:name="deleteConfirmTitle"
			:message="deleteConfirmMessage"
			:confirm-label="t('vinarium', 'Löschen')"
			:destructive="true"
			@close="deleteConfirmOpen = false"
			@confirm="performDelete"
		/>
		<p v-if="deleteError" class="master-data__error">{{ deleteError }}</p>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { formatDate } from '@/utils/date'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import EntityEditModal from '@/components/EntityEditModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { BOTTLE_SIZE_LABELS, WINE_COLOR_LABELS, type BottleSizeMl, type Vintage } from '@/types/api'
import { useWineStore } from '@/stores/wineStore'
import { cssColorFor } from '@/utils/wineColors'

type EntityType = 'producer' | 'wine' | 'vintage' | 'purchase'
type PanelTab = 'producers' | 'wines' | 'purchases'

const props = defineProps<{ entityType?: PanelTab }>()

const store = useWineStore()
const internalTab = ref<PanelTab>('producers')
const activeTab = computed<PanelTab>(() => props.entityType ?? internalTab.value)
const deleteError = ref<string | null>(null)

const editOpen = ref(false)
const editType = ref<EntityType>('producer')
const editId = ref<number | null>(null)

const tabs = computed(() => [
	{ key: 'producers' as const, label: t('vinarium', 'Weingüter'), count: store.producers.length },
	{ key: 'wines' as const, label: t('vinarium', 'Weine'), count: store.wines.length },
	{ key: 'purchases' as const, label: t('vinarium', 'Käufe'), count: store.purchases.length },
])

onMounted(() => store.fetchAll())

const sortedWines = computed(() => {
	return [...store.wines].sort((a, b) => {
		const pa = store.producerById(a.producerId)?.name ?? ''
		const pb = store.producerById(b.producerId)?.name ?? ''
		return pa.localeCompare(pb) || a.name.localeCompare(b.name)
	})
})

function vintagesForWine(wineId: number): Vintage[] {
	return [...store.vintagesByWine(wineId)].sort((a, b) => b.year - a.year)
}

function bottleCountForVintage(vintageId: number): number {
	return store.purchases
		.filter(pu => pu.vintage_id === vintageId)
		.reduce((sum, pu) => sum + pu.quantity, 0)
}

function ratingPct(rating: number): number {
	return Math.max(0, Math.min(100, Math.round(rating * 10)))
}

// Trinkfenster-Status: future (vor drink_from), active (im Fenster), past (nach drink_until).
const currentYear = new Date().getFullYear()
function trinkfensterClass(v: Vintage): string {
	if (v.drinkFromYear !== null && v.drinkFromYear > currentYear) return 'future'
	if (v.drinkUntilYear !== null && v.drinkUntilYear < currentYear) return 'past'
	return 'active'
}
function trinkfensterText(v: Vintage): string | null {
	const from = v.drinkFromYear
	const until = v.drinkUntilYear
	if (from === null && until === null) return null
	if (from !== null && until !== null) return `${from} – ${until}`
	if (from !== null) return t('vinarium', 'ab {year}', { year: from })
	if (until !== null) return t('vinarium', 'bis {year}', { year: until! })
	return null
}

function openCreate(type: EntityType) {
	editType.value = type
	editId.value = null
	editOpen.value = true
}

function editEntity(type: EntityType, id: number) {
	editType.value = type
	editId.value = id
	editOpen.value = true
}

function closeEdit() {
	editOpen.value = false
	editId.value = null
}

const deleteConfirmOpen = ref(false)
const deletePendingType = ref<EntityType>('producer')
const deletePendingId = ref<number | null>(null)

function labelForType(type: EntityType): string {
	if (type === 'producer') return t('vinarium', 'Weingut')
	if (type === 'wine') return t('vinarium', 'Wein')
	if (type === 'vintage') return t('vinarium', 'Jahrgang')
	return t('vinarium', 'Kauf')
}

const deleteConfirmTitle = computed(() => t('vinarium', '{entity} löschen', { entity: labelForType(deletePendingType.value) }))

const deleteConfirmMessage = computed(() => {
	if (deletePendingType.value === 'purchase') {
		return t('vinarium', 'Diesen Kauf wirklich löschen? Funktioniert nur, wenn dem Kauf keine Flaschen mehr zugeordnet sind.')
	}
	return t('vinarium', '{entity} wirklich löschen? Alle zugehörigen Einträge bleiben erhalten bis du sie separat löschst.', { entity: labelForType(deletePendingType.value) })
})

function deleteEntity(type: EntityType, id: number) {
	deletePendingType.value = type
	deletePendingId.value = id
	deleteConfirmOpen.value = true
}

async function performDelete() {
	deleteConfirmOpen.value = false
	const id = deletePendingId.value
	if (id === null) return
	const type = deletePendingType.value
	deleteError.value = null
	try {
		if (type === 'producer') await store.deleteProducer(id)
		else if (type === 'wine') await store.deleteWine(id)
		else if (type === 'vintage') await store.deleteVintage(id)
		else if (type === 'purchase') await store.deletePurchase(id)
		deletePendingId.value = null
	} catch (e: any) {
		deleteError.value = e?.message ?? t('vinarium', 'Löschen fehlgeschlagen')
	}
}
</script>

<style scoped>
/* v4 Stammdaten-Tabellen Pattern (analog Bestand-Flaschen) */
.md-card {
	background: var(--color-main-background, #fff);
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: var(--border-radius, 8px);
	overflow: hidden;
}
.md-tbl {
	width: 100%;
	border-collapse: collapse;
	font-size: 14px;
}
.md-tbl th {
	text-align: left;
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	padding: 9px 10px;
	border-bottom: 1px solid var(--color-border, #d2d4d7);
	background: transparent;
}
.md-tbl td {
	padding: 11px 10px;
	border-bottom: 1px solid var(--color-border-light, #e2e3e5);
	vertical-align: middle;
}
.md-tbl tbody tr:last-child td { border-bottom: none; }
.md-tbl tbody tr:hover { background: var(--color-background-hover); }
.md-tbl .r { text-align: right; }

/* Grouped wines × vintages view (#132) */
.md-wines-grouped tbody tr.wine-head td {
	padding-top: 14px;
	padding-bottom: 4px;
	font-weight: 700;
	border-top: 1px solid var(--color-border, #d2d4d7);
	border-bottom: none;
	background: transparent;
}
.md-wines-grouped tbody tr.wine-head:first-child td { border-top: none; }
.md-wines-grouped tbody tr.wine-head:hover { background: var(--color-background-hover); cursor: pointer; }
.md-wines-grouped tbody tr.wine-head td .subline {
	font-weight: 400;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-left: 8px;
}
.md-wines-grouped tbody tr.wine-head .dot {
	display: inline-block;
	width: 10px;
	height: 10px;
	border-radius: 50%;
	margin-right: 8px;
	vertical-align: middle;
}

/* Vintage rows are indented and clickable. Border-bottom collapsed away;
   thin divider drawn as ::after pseudo-element so the line can start at the
   year-position (after the indent) instead of at the table edge. */
.md-wines-grouped tbody tr.vintage-row { cursor: pointer; }
.md-wines-grouped tbody tr.vintage-row td {
	border-bottom: none;
	position: relative;
}
.md-wines-grouped tbody tr.vintage-row td:first-child {
	padding-left: 30px;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}
.md-wines-grouped tbody tr.vintage-row:not(.is-last) td::after {
	content: '';
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
	height: 1px;
	background: var(--color-border-light, #e2e3e5);
}
.md-wines-grouped tbody tr.vintage-row:not(.is-last) td:first-child::after {
	left: 30px;
}
/* Trennstrich zwischen Wein-Header und der ersten Jahrgangs-Zeile darunter.
   Damit visuell klar wird: zwei getrennte Klick-Targets (Header = Wein,
   Zeile = Jahrgang). Strich folgt dem v7-Pattern: ab Jahres-Position. */
.md-wines-grouped tbody tr.wine-head + tr.vintage-row td::before {
	content: '';
	position: absolute;
	left: 0;
	right: 0;
	top: 0;
	height: 1px;
	background: var(--color-border-light, #e2e3e5);
}
.md-wines-grouped tbody tr.wine-head + tr.vintage-row td:first-child::before {
	left: 30px;
}

/* Trash button (small, inline; replaces the kebab menu in the grouped wine view). */
.md-wines-grouped .icon-cell { width: 44px; padding: 4px 8px; }
.md-wines-grouped .trash-btn {
	background: none;
	border: none;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	padding: 4px 6px;
	border-radius: 4px;
	display: inline-flex;
	align-items: center;
	opacity: 0.6;
}
.md-wines-grouped tr:hover .trash-btn { opacity: 1; }
.md-wines-grouped .trash-btn:hover {
	background: var(--color-background-hover);
	color: var(--color-error, #c62828);
}

/* Trinkfenster pill (kept generic so it could be reused) */
.tw {
	display: inline-flex;
	align-items: center;
	padding: 2px 9px;
	border-radius: 11px;
	font-size: 12.5px;
	font-weight: 500;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}
.tw.future { background: #fff5e0; color: #b87600; }
.tw.active { background: #e6f4e8; color: #1e6b2a; }
.tw.past   { background: #fce8e8; color: #8a2828; }

/* Rating bar mirrors InventoryView .rat */
.rat {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}
.rat__val {
	font-weight: 700;
	font-size: 12.5px;
	color: var(--color-primary-element, #0082c9);
	min-width: 24px;
	text-align: right;
}
.rat__bar {
	display: inline-block;
	width: 60px;
	height: 6px;
	background: var(--color-background-dark, #ededef);
	border-radius: 3px;
	overflow: hidden;
}
.rat__bar i {
	display: block;
	height: 100%;
	background: var(--color-primary-element, #0082c9);
	border-radius: 3px;
}

.muted { color: var(--color-text-maxcontrast); }

.master-data__tabs {
	display: inline-flex;
	background: var(--color-background-dark, #e9eaec);
	border-radius: var(--border-radius-element, 8px);
	padding: 3px;
	margin-bottom: 14px;
}
.master-data__tab {
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	color: #555;
	background: none;
	border: none;
	padding: 6px 14px;
	border-radius: var(--border-radius-element, 8px);
	cursor: pointer;
}
.master-data__tab--active {
	background: #fff;
	color: var(--color-primary-element, #0082c9);
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
}
.master-data__count {
	font-weight: 400;
	color: var(--color-text-maxcontrast);
}
.master-data__tab--active .master-data__count {
	color: var(--color-primary-element, #0082c9);
}

.master-data__actions {
	display: flex;
	justify-content: flex-end;
	margin-bottom: 0.75rem;
}
.master-data__empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 1rem;
}
.master-data__error {
	margin: 1rem 0 0;
	padding: 0.5rem 0.75rem;
	background: rgba(198, 40, 40, 0.1);
	border-left: 3px solid #c62828;
	border-radius: var(--border-radius);
	color: #c62828;
	font-size: 0.9rem;
}
</style>

<template>
	<div class="tastings-view">
		<header class="tastings-view__header">
			<h2>{{ t('vinarium', 'Verkostungen') }}</h2>
			<NcButton variant="primary" @click="openPicker">{{ t('vinarium', 'Flasche entkorken') }}</NcButton>
		</header>

		<!-- KPI-Reihe -->
		<section v-if="stats" class="kpis" :aria-label="t('vinarium', 'Kennzahlen')">
			<article class="kpi">
				<div class="kpi__label">{{ t('vinarium', 'Verkostungen {y}', { y: stats.year }) }}</div>
				<div class="kpi__value">{{ stats.count_year }}</div>
			</article>
			<article class="kpi">
				<div class="kpi__label">{{ t('vinarium', 'Ø Bewertung') }}</div>
				<div class="kpi__value">
					<template v-if="stats.avg_rating !== null">
						{{ Number(stats.avg_rating).toFixed(1) }}
					</template>
					<span v-else class="kpi__empty">—</span>
				</div>
			</article>
			<article class="kpi">
				<div class="kpi__label">{{ t('vinarium', 'Bester Wein') }}</div>
				<div v-if="stats.best_wine" class="kpi__best">
					<div class="kpi__best-name">{{ stats.best_wine.wine_name }} {{ stats.best_wine.year }}</div>
					<div class="kpi__best-meta">
						<span class="kpi__best-producer">{{ stats.best_wine.producer_name }}</span>
						<span class="rat">
							<span class="rat__val">{{ Number(stats.best_wine.rating).toFixed(1) }}</span>
							<span class="rat__bar"><i :style="{ width: ratingPct(stats.best_wine.rating) + '%' }" /></span>
						</span>
					</div>
				</div>
				<div v-else class="kpi__value"><span class="kpi__empty">—</span></div>
			</article>
			<article class="kpi">
				<div class="kpi__label">{{ t('vinarium', 'Mit Fotos') }}</div>
				<div class="kpi__value">{{ stats.with_photos_count }}</div>
			</article>
		</section>

		<p v-if="loading" class="muted">{{ t('vinarium', 'Laden...') }}</p>
		<p v-else-if="tastings.length === 0" class="empty">{{ t('vinarium', 'Noch keine Verkostungen erfasst.') }}</p>
		<table v-else class="tastings-table">
			<thead>
				<tr>
					<th>{{ t('vinarium', 'Datum') }}</th>
					<th>{{ t('vinarium', 'Weingut') }}</th>
					<th>{{ t('vinarium', 'Wein') }}</th>
					<th>{{ t('vinarium', 'Jahrgang') }}</th>
					<th>{{ t('vinarium', 'Bewertung') }}</th>
					<th>{{ t('vinarium', 'Anlass') }}</th>
					<th>{{ t('vinarium', 'Notizen') }}</th>
					<th class="photo-col"></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="tasting in tastings" :key="tasting.id" @click="openDetail(tasting.id)">
					<td>{{ formatDate(tasting.tasted_at) }}</td>
					<td class="wrap-cell producer-cell">{{ tasting.producer_name }}</td>
					<td>
						<span class="dot" :style="{ background: cssColorFor(tasting.wine_color) }" />
						{{ tasting.wine_name }}
					</td>
					<td>{{ tasting.year }}</td>
					<td>
						<span v-if="tasting.rating !== null" class="rat">
							<span class="rat__val">{{ Number(tasting.rating).toFixed(1) }}</span>
							<span class="rat__bar"><i :style="{ width: ratingPct(tasting.rating) + '%' }" /></span>
						</span>
						<span v-else class="muted">—</span>
					</td>
					<td class="wrap-cell occasion-cell">{{ tasting.occasion ?? '—' }}</td>
					<td class="notes-cell">
						<div class="notes-text">{{ tasting.notes ?? '—' }}</div>
					</td>
					<td class="photo-col">
						<span
							v-if="tasting.photo_file_ids && tasting.photo_file_ids.length > 0"
							class="photo-badge"
							:title="t('vinarium', 'Fotos ({count})', { count: tasting.photo_file_ids.length })"
						>
							<svg class="photo-badge__icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
								<path d="M4,4H7L9,2H15L17,4H20A2,2 0 0,1 22,6V18A2,2 0 0,1 20,20H4A2,2 0 0,1 2,18V6A2,2 0 0,1 4,4M12,7A5,5 0 0,0 7,12A5,5 0 0,0 12,17A5,5 0 0,0 17,12A5,5 0 0,0 12,7M12,9A3,3 0 0,1 15,12A3,3 0 0,1 12,15A3,3 0 0,1 9,12A3,3 0 0,1 12,9Z" />
							</svg>
							{{ tasting.photo_file_ids.length }}
						</span>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Bottle picker dialog -->
		<NcDialog
			:open="pickerOpen"
			:name="t('vinarium', 'Flasche entkorken')"
			@update:open="pickerOpen = false"
		>
			<div class="picker">
				<p v-if="pickerLoading" class="muted">{{ t('vinarium', 'Laden...') }}</p>
				<p v-else-if="pickerError" class="picker-error">{{ pickerError }}</p>
				<p v-else-if="pickerBottles.length === 0" class="empty">{{ t('vinarium', 'Keine Flaschen im Bestand.') }}</p>
				<ul v-else class="picker-list">
					<li
						v-for="b in pickerBottles"
						:key="b.id"
						:class="['picker-item', { 'picker-item--selected': pickerSelectedId === b.id }]"
						@click="pickerSelectedId = b.id"
					>
						<span class="dot" :style="{ background: cssColorFor(b.wine_color) }" />
						<span class="picker-item__label">{{ b.producer_name }} · {{ b.wine_name }} · {{ b.year }}</span>
					</li>
				</ul>
			</div>
			<template #actions>
				<NcButton @click="pickerOpen = false">{{ t('vinarium', 'Abbrechen') }}</NcButton>
				<NcButton variant="primary" :disabled="!pickerSelectedId" @click="startUncork">
					{{ t('vinarium', 'Entkorken') }}
				</NcButton>
			</template>
		</NcDialog>

		<!-- Detail modal -->
		<TastingDetailModal
			:open="detailModal.open"
			:tasting-id="detailModal.tastingId"
			@close="detailModal.open = false"
			@edit="onDetailEdit"
		/>

		<!-- Tasting dialog for editing existing tastings -->
		<TastingDialog
			:open="editDialog.open"
			:tasting="editDialog.tasting"
			@close="editDialog.open = false"
			@updated="onUpdated"
		/>

		<!-- Tasting dialog for consuming a bottle -->
		<TastingDialog
			:open="consumeDialog.open"
			:bottle-id="consumeDialog.bottleId"
			@close="consumeDialog.open = false"
			@consumed="onConsumed"
		/>
	</div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { formatDate } from '@/utils/date'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { fetchTastingStats, listAllTastings, type TastingDetail, type TastingListItem, type TastingStats } from '@/api/tastings'
import { listBottles } from '@/api/bottles'
import type { BottleListItem } from '@/types/api'
import TastingDialog from '@/components/TastingDialog.vue'
import TastingDetailModal from '@/components/TastingDetailModal.vue'
import { cssColorFor } from '@/utils/wineColors'

const tastings = ref<TastingListItem[]>([])
const loading = ref(true)
const stats = ref<TastingStats | null>(null)

const detailModal = reactive({
	open: false,
	tastingId: null as number | null,
})

const editDialog = reactive({
	open: false,
	tasting: null as TastingListItem | null,
})

const consumeDialog = reactive({
	open: false,
	bottleId: null as number | null,
})

const pickerOpen = ref(false)
const pickerLoading = ref(false)
const pickerBottles = ref<BottleListItem[]>([])
const pickerSelectedId = ref<number | null>(null)
const pickerError = ref<string | null>(null)

function ratingPct(rating: number | null): number {
	if (rating === null) return 0
	return Math.round((rating / 10) * 100)
}

function openDetail(id: number) {
	detailModal.tastingId = id
	detailModal.open = true
}

function onDetailEdit(detail: TastingDetail) {
	detailModal.open = false
	const listItem = tastings.value.find(item => item.id === detail.id) ?? null
	editDialog.tasting = listItem
	editDialog.open = true
}

async function onUpdated(updated: TastingListItem) {
	const idx = tastings.value.findIndex(item => item.id === updated.id)
	if (idx !== -1) tastings.value[idx] = updated
	await loadStats()
}

async function openPicker() {
	pickerOpen.value = true
	pickerLoading.value = true
	pickerSelectedId.value = null
	pickerError.value = null
	pickerBottles.value = []
	try {
		pickerBottles.value = await listBottles({ status: 'in_storage' })
	} catch (e: any) {
		pickerError.value = e?.message ?? t('vinarium', 'Flaschen konnten nicht geladen werden')
	} finally {
		pickerLoading.value = false
	}
}

function startUncork() {
	if (!pickerSelectedId.value) return
	consumeDialog.bottleId = pickerSelectedId.value
	pickerOpen.value = false
	consumeDialog.open = true
}

async function onConsumed() {
	tastings.value = await listAllTastings()
	await loadStats()
}

async function loadStats() {
	try {
		stats.value = await fetchTastingStats()
	} catch (e) {
		console.error('Tasting stats error:', e)
	}
}

onMounted(async () => {
	try {
		const [list] = await Promise.all([
			listAllTastings(),
			loadStats(),
		])
		tastings.value = list
	} finally {
		loading.value = false
	}
})

</script>

<style scoped>
.tastings-view { padding: 20px 24px; max-width: 1400px; }
.tastings-view__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 18px;
}
.tastings-view__header h2 {
	font-size: 24px;
	font-weight: 600;
	letter-spacing: -0.01em;
}

/* KPI-Reihe — Dashboard-Card-Pattern (stock-Hero) */
.kpis {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 14px;
	margin-bottom: 18px;
}
@media (max-width: 900px) {
	.kpis { grid-template-columns: repeat(2, 1fr); }
}
.kpi {
	background: #fff;
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: 12px;
	padding: 16px 18px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-height: 90px;
}
.kpi__label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.kpi__value {
	font-size: 28px;
	font-weight: 700;
	line-height: 1;
	font-variant-numeric: tabular-nums;
	color: var(--color-main-text);
}
.kpi__empty { color: var(--color-text-maxcontrast); font-weight: 400; }
.kpi__best {
	display: flex;
	flex-direction: column;
	gap: 6px;
}
.kpi__best-name {
	font-size: 15px;
	font-weight: 700;
	color: var(--color-main-text);
	line-height: 1.25;
	display: -webkit-box;
	-webkit-line-clamp: 1;
	-webkit-box-orient: vertical;
	overflow: hidden;
}
.kpi__best-meta {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
}
.kpi__best-producer {
	font-size: 12.5px;
	color: var(--color-text-maxcontrast);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Bewertungs-Bar (analog DashboardView .rat) */
.rat {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	font-variant-numeric: tabular-nums;
}
.rat__val {
	font-weight: 700;
	color: var(--color-primary-element, #0082c9);
	font-size: 14px;
	min-width: 30px;
	text-align: right;
}
.rat__bar {
	width: 70px;
	height: 7px;
	background: var(--color-background-dark, #e9eaec);
	border-radius: var(--border-radius-element, 8px);
	overflow: hidden;
}
.rat__bar > i {
	display: block;
	height: 100%;
	background: var(--color-primary-element, #0082c9);
	border-radius: var(--border-radius-element, 8px);
}

/* Tabelle */
.tastings-table { width: 100%; border-collapse: collapse; }
.tastings-table th, .tastings-table td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border); }
.tastings-table th { background: var(--color-background-hover); font-weight: 500; font-size: 0.9rem; }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 0.25rem; }
.wrap-cell { white-space: normal; word-break: break-word; }
.producer-cell { min-width: 180px; max-width: 220px; }
.occasion-cell { min-width: 200px; max-width: 300px; }
.notes-cell { min-width: 280px; max-width: 470px; }
.notes-text { white-space: normal; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.tastings-table tbody tr { cursor: pointer; }
.tastings-table tbody tr:hover { background: var(--color-background-hover); }
.muted { color: var(--color-text-maxcontrast); }
.empty { color: var(--color-text-maxcontrast); font-style: italic; padding: 1rem 0; }

/* Picker-Dialog */
.picker-error {
	margin: 0;
	padding: 0.5rem 0.75rem;
	background: rgba(198, 40, 40, 0.1);
	border-left: 3px solid #c62828;
	border-radius: var(--border-radius);
	color: #c62828;
	font-size: 0.9rem;
}
.picker { padding: 0.5rem 0; min-width: 400px; }
.picker-list { list-style: none; padding: 0; margin: 0; max-height: 320px; overflow-y: auto; }
.picker-item {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	padding: 0.6rem 0.75rem;
	border-radius: var(--border-radius);
	cursor: pointer;
	border: 2px solid transparent;
}
.picker-item:hover { background: var(--color-background-hover); }
.picker-item--selected { border-color: var(--color-primary-element); background: var(--color-primary-element-light, #e8f4ff); }
.picker-item__label { font-size: 0.9rem; }

/* Foto-Badge mit Kamera-Icon */
.photo-col { width: 60px; white-space: nowrap; }
.photo-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	color: var(--color-text-maxcontrast);
	font-size: 12.5px;
	font-variant-numeric: tabular-nums;
}
.photo-badge__icon { flex-shrink: 0; }
</style>

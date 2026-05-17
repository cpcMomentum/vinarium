<template>
	<div class="tastings-view">
		<header class="tastings-view__header">
			<h2>{{ t('vinarium', 'Verkostungen') }}</h2>
			<NcButton type="primary" @click="openPicker">{{ t('vinarium', 'Flasche entkorken') }}</NcButton>
		</header>

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
				</tr>
			</thead>
			<tbody>
				<tr v-for="tasting in tastings" :key="tasting.id" @click="openEdit(tasting)">
					<td>{{ formatDate(tasting.tasted_at) }}</td>
					<td class="wrap-cell producer-cell">{{ tasting.producer_name }}</td>
					<td>
						<span class="dot" :style="{ background: cssColorFor(tasting.wine_color) }"></span>
						{{ tasting.wine_name }}
					</td>
					<td>{{ tasting.year }}</td>
					<td>
						<span v-if="tasting.rating !== null" class="rating">{{ Number(tasting.rating).toFixed(1) }}</span>
						<span v-else class="muted">—</span>
					</td>
					<td class="wrap-cell occasion-cell">{{ tasting.occasion ?? '—' }}</td>
					<td class="notes-cell">
						<div class="notes-text">{{ tasting.notes ?? '—' }}</div>
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
				<p v-else-if="pickerBottles.length === 0" class="empty">{{ t('vinarium', 'Keine Flaschen im Bestand.') }}</p>
				<ul v-else class="picker-list">
					<li
						v-for="b in pickerBottles"
						:key="b.id"
						:class="['picker-item', { 'picker-item--selected': pickerSelectedId === b.id }]"
						@click="pickerSelectedId = b.id"
					>
						<span class="dot" :style="{ background: cssColorFor(b.wine_color) }"></span>
						<span class="picker-item__label">{{ b.producer_name }} · {{ b.wine_name }} · {{ b.year }}</span>
					</li>
				</ul>
			</div>
			<template #actions>
				<NcButton @click="pickerOpen = false">{{ t('vinarium', 'Abbrechen') }}</NcButton>
				<NcButton type="primary" :disabled="!pickerSelectedId" @click="startUncork">
					{{ t('vinarium', 'Entkorken') }}
				</NcButton>
			</template>
		</NcDialog>

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
import moment from '@nextcloud/moment'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { listAllTastings, type TastingListItem } from '@/api/tastings'
import { listBottles } from '@/api/bottles'
import type { BottleListItem } from '@/types/api'
import TastingDialog from '@/components/TastingDialog.vue'

const tastings = ref<TastingListItem[]>([])
const loading = ref(true)

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

function openEdit(tasting: TastingListItem) {
	editDialog.tasting = tasting
	editDialog.open = true
}

function onUpdated(updated: TastingListItem) {
	const idx = tastings.value.findIndex(item => item.id === updated.id)
	if (idx !== -1) tastings.value[idx] = updated
}

async function openPicker() {
	pickerOpen.value = true
	pickerLoading.value = true
	pickerSelectedId.value = null
	try {
		pickerBottles.value = await listBottles({ status: 'in_storage' })
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
}

onMounted(async () => {
	try {
		tastings.value = await listAllTastings()
	} finally {
		loading.value = false
	}
})

function formatDate(iso: string): string {
	try { return moment(iso).format('L') }
	catch { return iso }
}

function cssColorFor(color: string): string {
	const palette: Record<string, string> = {
		red: '#7a1c1c', white: '#e8d57a', rose: '#e8a3b8',
		sparkling: '#fff7c0', dessert: '#c2934e', fortified: '#4a1010',
	}
	return palette[color] ?? '#999'
}
</script>

<style scoped>
.tastings-view { padding: 2rem 2rem 2rem 50px; max-width: 1400px; }
.tastings-view__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 1.5rem;
}
.tastings-table { width: 100%; border-collapse: collapse; }
.tastings-table th, .tastings-table td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border); }
.tastings-table th { background: var(--color-background-hover); font-weight: 500; font-size: 0.9rem; }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 0.25rem; }
.rating { font-weight: 600; color: var(--color-main-text); }
.wrap-cell { white-space: normal; word-break: break-word; }
.producer-cell { min-width: 180px; max-width: 220px; }
.occasion-cell { min-width: 200px; max-width: 300px; }
.notes-cell { min-width: 280px; max-width: 470px; }
.notes-text { white-space: normal; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.tastings-table tbody tr { cursor: pointer; }
.tastings-table tbody tr:hover { background: var(--color-background-hover); }
.muted { color: var(--color-text-maxcontrast); }
.empty { color: var(--color-text-maxcontrast); font-style: italic; padding: 1rem 0; }
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
</style>

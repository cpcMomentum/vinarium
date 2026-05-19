<template>
	<div class="inventory-view">
		<header class="inventory-view__header">
			<h2>{{ t('vinarium', 'Bestand') }}</h2>
			<span class="count">{{ n('vinarium', '{count} Flasche', '{count} Flaschen', store.totalCount, { count: store.totalCount }) }}</span>
		</header>

		<section class="filters">
			<label>
				{{ t('vinarium', 'Farbe') }}
				<select v-model="filterColor" class="input" @change="applyFilter">
					<option value="">{{ t('vinarium', 'alle') }}</option>
					<option v-for="c in WINE_COLORS" :key="c" :value="c">{{ t('vinarium', WINE_COLOR_LABELS[c]) }}</option>
				</select>
			</label>
			<label>
				{{ t('vinarium', 'Status') }}
				<select v-model="filterStatus" class="input" @change="applyFilter">
					<option value="">{{ t('vinarium', 'alle') }}</option>
					<option v-for="(label, key) in BOTTLE_STATUS_LABELS" :key="key" :value="key">{{ t('vinarium', label) }}</option>
				</select>
			</label>
			<label>
				{{ t('vinarium', 'Jahrgang') }}
				<input v-model.number.lazy="filterYear" type="number" class="input" :placeholder="t('vinarium', 'z. B. 2020')" @change="applyFilter" />
			</label>
			<button class="reset" @click="resetFilter">{{ t('vinarium', 'Filter zurücksetzen') }}</button>
		</section>

		<table v-if="store.bottles.length > 0" class="bottles">
			<thead>
				<tr>
					<th class="photo-col"></th>
					<th>{{ t('vinarium', 'Weingut') }}</th>
					<th>{{ t('vinarium', 'Wein') }}</th>
					<th>{{ t('vinarium', 'Jahrgang') }}</th>
					<th>{{ t('vinarium', 'Farbe') }}</th>
					<th>{{ t('vinarium', 'Status') }}</th>
					<th>{{ t('vinarium', 'Slot') }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="b in store.bottles" :key="b.id">
					<td class="photo-cell">
						<img
							v-if="b.photo_file_id !== null"
							:src="bottlePhotoUrl(b.id)"
							class="bottle-thumb"
							:alt="b.wine_name"
						/>
					</td>
					<td>{{ b.producer_name }}</td>
					<td>{{ b.wine_name }}</td>
					<td>{{ b.year }}</td>
					<td>
						<span class="dot" :style="{ background: cssColorFor(b.wine_color) }"></span>
						{{ t('vinarium', WINE_COLOR_LABELS[b.wine_color]) }}
					</td>
					<td>{{ t('vinarium', BOTTLE_STATUS_LABELS[b.status]) }}</td>
					<td>{{ formatSlotLabel(b) }}</td>
					<td>
						<NcButton v-if="b.status === 'in_storage'" type="tertiary" @click="openTasting(b.id)">{{ t('vinarium', 'Entkorken') }}</NcButton>
						<NcButton v-else type="tertiary" @click="doRestore(b.id)">{{ t('vinarium', 'Zurück in Bestand') }}</NcButton>
					</td>
				</tr>
			</tbody>
		</table>
		<p v-else class="empty">{{ t('vinarium', 'Keine Flaschen gefunden.') }}</p>

		<TastingDialog :open="tastingOpen" :bottle-id="tastingBottleId" @close="tastingOpen = false" @consumed="onConsumed" />
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import TastingDialog from '@/components/TastingDialog.vue'
import { BOTTLE_STATUS_LABELS, WINE_COLORS, WINE_COLOR_LABELS, type BottleStatus, type WineColor } from '@/types/api'
import { useBottleStore } from '@/stores/bottleStore'
import { getBottlePhotoUrl } from '@/api/bottles'

const store = useBottleStore()
const tastingOpen = ref(false)
const tastingBottleId = ref<number | null>(null)
const filterColor = ref<WineColor | ''>('')
const filterStatus = ref<BottleStatus | ''>('in_storage')
const filterYear = ref<number | null>(null)

onMounted(async () => {
	await store.fetchBottles({ status: 'in_storage' })
})

async function applyFilter() {
	await store.fetchBottles({
		color: filterColor.value || undefined,
		status: filterStatus.value || undefined,
		year: filterYear.value ?? undefined,
	})
}

// silence unused var when filter changes lock (kept for future drink-until-year filter)

async function resetFilter() {
	filterColor.value = ''
	filterStatus.value = ''
	filterYear.value = null
	await store.fetchBottles({})
}

function openTasting(bottleId: number) {
	tastingBottleId.value = bottleId
	tastingOpen.value = true
}

async function onConsumed() {
	await store.fetchBottles(store.filter)
}

async function doRestore(id: number) {
	await store.restoreBottle(id)
}

function cssColorFor(color: WineColor): string {
	const palette: Record<WineColor, string> = {
		red: '#7a1c1c',
		white: '#e8d57a',
		rose: '#e8a3b8',
		sparkling: '#fff7c0',
		dessert: '#c2934e',
		fortified: '#4a1010',
	}
	return palette[color]
}

function bottlePhotoUrl(id: number): string {
	return getBottlePhotoUrl(id)
}

function formatSlotLabel(b: { status: BottleStatus; slot_id: number | null; slot_level: number | null; slot_row: string | null; slot_column: number | null; compartment_label: string | null }): string {
	if (b.status !== 'in_storage') return '—'
	if (!b.slot_id) return t('vinarium', 'Parkzone')
	const level = (b.slot_level ?? 0) + 1
	const col = (b.slot_column ?? 0) + 1
	const label = b.compartment_label ?? '?'
	if (b.slot_row === 'back') {
		return t('vinarium', '{label}, E{level}, H{col}', { label, level, col })
	}
	return t('vinarium', '{label}, E{level}, V{col}', { label, level, col })
}
</script>

<style scoped>
.inventory-view {
	padding: 2rem 2rem 2rem 50px;
	max-width: 1100px;
}
.inventory-view__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 1.5rem;
}
.counts {
	display: flex;
	gap: 1rem;
}
.count {
	padding: 0.25rem 0.75rem;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
	font-size: 0.9rem;
}
.count--park {
	background: var(--color-warning, #e3a000);
	color: white;
	font-weight: 500;
}
.parkzone {
	margin-bottom: 1.5rem;
	padding: 1rem;
	background: var(--color-background-hover);
	border-left: 3px solid var(--color-warning, #e3a000);
	border-radius: var(--border-radius);
}
.parkzone h3 {
	margin: 0 0 0.75rem 0;
}
.park-list {
	list-style: none;
	padding: 0;
	margin: 0;
}
.park-item {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	padding: 0.25rem 0;
}
.park-item__label {
	font-weight: 500;
}
.dot {
	display: inline-block;
	width: 14px;
	height: 14px;
	border-radius: 50%;
	border: 1px solid var(--color-border);
}
.muted {
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}
.filters {
	display: flex;
	gap: 1rem;
	align-items: end;
	margin-bottom: 1rem;
	padding: 1rem;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
}
.filters label {
	display: flex;
	flex-direction: column;
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
}
.input {
	margin-top: 0.25rem;
	padding: 0.4rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}
.reset {
	background: none;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 0.5rem 0.75rem;
	cursor: pointer;
	color: var(--color-main-text);
}
.bottles {
	width: 100%;
	border-collapse: collapse;
}
.bottles th, .bottles td {
	text-align: left;
	padding: 0.5rem 0.75rem;
	border-bottom: 1px solid var(--color-border);
}
.bottles th {
	background: var(--color-background-hover);
	font-weight: 500;
	font-size: 0.9rem;
}
.empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 2rem;
	text-align: center;
}
.photo-col { width: 48px; }
.photo-cell {
	padding: 0.25rem 0.5rem;
	width: 48px;
}
.bottle-thumb {
	width: 40px;
	height: 40px;
	object-fit: cover;
	border-radius: 4px;
	display: block;
}
</style>

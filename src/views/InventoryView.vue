<template>
	<div class="inventory-view">
		<div ref="headEl" class="inventory-head">
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
		</div>

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
					<td>
						{{ t('vinarium', BOTTLE_STATUS_LABELS[b.status]) }}
						<span v-if="b.status === 'gifted' && b.event_recipient" class="event-info" :title="giftTooltip(b)">→ {{ b.event_recipient }}</span>
						<span v-else-if="b.status === 'lost' && b.event_note" class="event-info">({{ b.event_note }})</span>
					</td>
					<td>{{ formatSlotLabel(b) }}</td>
					<td class="actions-cell">
						<template v-if="b.status === 'in_storage'">
							<NcButton variant="tertiary" @click="openTasting(b.id)">{{ t('vinarium', 'Entkorken') }}</NcButton>
							<NcButton variant="tertiary" @click="openEvent(b.id, 'gift')">{{ t('vinarium', 'Verschenken') }}</NcButton>
							<NcButton variant="tertiary" @click="openEvent(b.id, 'lost')">{{ t('vinarium', 'Verloren') }}</NcButton>
						</template>
						<NcButton v-else variant="tertiary" @click="doRestore(b.id)">{{ t('vinarium', 'Zurück in Bestand') }}</NcButton>
					</td>
				</tr>
			</tbody>
		</table>
		<p v-else class="empty">{{ t('vinarium', 'Keine Flaschen gefunden.') }}</p>

		<TastingDialog :open="tastingOpen" :bottle-id="tastingBottleId" @close="tastingOpen = false" @consumed="onConsumed" />
		<BottleEventDialog :open="eventOpen" :bottle-id="eventBottleId" :mode="eventMode" @close="eventOpen = false" @done="onEventDone" />
	</div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import TastingDialog from '@/components/TastingDialog.vue'
import BottleEventDialog from '@/components/BottleEventDialog.vue'
import { BOTTLE_STATUS_LABELS, WINE_COLORS, WINE_COLOR_LABELS, type BottleListItem, type BottleStatus, type WineColor } from '@/types/api'
import { useBottleStore } from '@/stores/bottleStore'
import { getBottlePhotoUrl } from '@/api/bottles'
import { cssColorFor } from '@/utils/wineColors'

const store = useBottleStore()
const tastingOpen = ref(false)
const tastingBottleId = ref<number | null>(null)
const eventOpen = ref(false)
const eventBottleId = ref<number | null>(null)
const eventMode = ref<'gift' | 'lost'>('gift')
const filterColor = ref<WineColor | ''>('')
const filterStatus = ref<BottleStatus | ''>('in_storage')
const filterYear = ref<number | null>(null)

const headEl = ref<HTMLElement | null>(null)
let headObserver: ResizeObserver | null = null

onMounted(async () => {
	await store.fetchBottles({ status: 'in_storage' })
	if (headEl.value) {
		const apply = () => {
			const h = headEl.value?.offsetHeight ?? 0
			headEl.value?.parentElement?.style.setProperty('--inventory-head-h', `${h}px`)
		}
		apply()
		headObserver = new ResizeObserver(apply)
		headObserver.observe(headEl.value)
	}
})

onBeforeUnmount(() => {
	headObserver?.disconnect()
})

async function applyFilter() {
	await store.fetchBottles({
		color: filterColor.value || undefined,
		status: filterStatus.value || undefined,
		year: filterYear.value ?? undefined,
	})
}

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

function openEvent(bottleId: number, mode: 'gift' | 'lost') {
	eventBottleId.value = bottleId
	eventMode.value = mode
	eventOpen.value = true
}

async function onEventDone() {
	await store.fetchBottles(store.filter)
}

function giftTooltip(b: BottleListItem): string {
	const parts: string[] = []
	if (b.event_recipient) parts.push(b.event_recipient)
	if (b.event_date) parts.push(b.event_date)
	if (b.event_note) parts.push(b.event_note)
	return parts.join(' · ')
}

async function doRestore(id: number) {
	await store.restoreBottle(id)
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
.inventory-head {
	position: sticky;
	top: 0;
	z-index: 20;
	background: var(--color-main-background);
	padding-bottom: 1rem;
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
	margin-bottom: 0;
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
.event-info {
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
	margin-left: 0.25rem;
}
.actions-cell {
	display: flex;
	flex-wrap: wrap;
	gap: 0.25rem;
}
.bottles th, .bottles td {
	text-align: left;
	padding: 0.5rem 0.75rem;
	border-bottom: 1px solid var(--color-border);
}
.bottles th {
	position: sticky;
	top: var(--inventory-head-h, 0px);
	z-index: 10;
	background: var(--color-background-dark);
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

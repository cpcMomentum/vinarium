<template>
	<div class="inventory-view">
		<div ref="headEl" class="inventory-head">
			<header class="inventory-view__header">
				<h2>{{ t('vinarium', 'Bestand') }}</h2>
				<span class="count">{{ n('vinarium', '{count} Flasche', '{count} Flaschen', store.totalCount, { count: store.totalCount }) }}</span>
				<div class="inventory-view__sp"></div>
				<NcButton variant="primary" @click="wizardOpen = true">{{ t('vinarium', '+ Kauf erfassen') }}</NcButton>
			</header>

			<!-- Sub-Tabs Flaschen / Stammdaten -->
			<nav class="subtabs">
				<button
					:class="['subtab', { 'subtab--active': activeTab === 'bottles' }]"
					@click="setTab('bottles')"
				>
					{{ t('vinarium', 'Flaschen') }}
				</button>
				<button
					:class="['subtab', { 'subtab--active': activeTab === 'masterdata' }]"
					@click="setTab('masterdata')"
				>
					{{ t('vinarium', 'Stammdaten') }}
				</button>
			</nav>

			<!-- Filter nur im Bottles-Tab -->
			<section v-if="activeTab === 'bottles'" class="filters">
				<label>
					{{ t('vinarium', 'Kategorie') }}
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

		<!-- Bottles-Tab -->
		<div v-show="activeTab === 'bottles'">
			<table v-if="store.bottles.length > 0" class="bottles">
				<thead>
					<tr>
						<th class="photo-col"></th>
						<th>{{ t('vinarium', 'Weingut') }}</th>
						<th>{{ t('vinarium', 'Wein') }}</th>
						<th>{{ t('vinarium', 'Jahrgang') }}</th>
						<th>{{ t('vinarium', 'Kategorie') }}</th>
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
						<td>
							<div v-if="b.status === 'in_storage'" class="actions-cell">
								<NcButton variant="secondary" @click="openTasting(b.id)">{{ t('vinarium', 'Entkorken') }}</NcButton>
								<NcActions :aria-label="t('vinarium', 'Weitere Aktionen')">
									<NcActionButton @click="openEvent(b.id, 'gift')">
										<template #icon><Gift :size="20" /></template>
										{{ t('vinarium', 'Verschenken') }}
									</NcActionButton>
									<NcActionButton @click="openEvent(b.id, 'lost')">
										<template #icon><CloseCircleOutline :size="20" /></template>
										{{ t('vinarium', 'Verloren') }}
									</NcActionButton>
								</NcActions>
							</div>
							<NcButton v-else-if="b.status === 'gifted' || b.status === 'lost'" variant="tertiary" @click="doRestore(b.id)">{{ t('vinarium', 'Zurück in Bestand') }}</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
			<p v-else class="empty">{{ t('vinarium', 'Keine Flaschen gefunden.') }}</p>
			<p v-if="restoreError" class="restore-error">{{ restoreError }}</p>
		</div>

		<!-- Masterdata-Tab -->
		<div v-show="activeTab === 'masterdata'">
			<MasterDataPanel />
		</div>

		<!-- Modals -->
		<TastingDialog
			:open="tastingOpen"
			:bottle-id="tastingBottleId"
			@close="tastingOpen = false"
			@consumed="onConsumed"
		/>
		<BottleEventDialog
			:open="eventOpen"
			:bottle-id="eventBottleId"
			:mode="eventMode"
			@close="eventOpen = false"
			@done="onEventDone"
		/>
		<PurchaseWizardModal :open="wizardOpen" @close="wizardOpen = false" @complete="onWizardComplete" />
	</div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import Gift from 'vue-material-design-icons/Gift.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import TastingDialog from '@/components/TastingDialog.vue'
import BottleEventDialog from '@/components/BottleEventDialog.vue'
import PurchaseWizardModal from '@/components/PurchaseWizardModal.vue'
import MasterDataPanel from '@/components/MasterDataPanel.vue'
import { BOTTLE_STATUS_LABELS, WINE_COLORS, WINE_COLOR_LABELS, type BottleListItem, type BottleStatus, type WineColor } from '@/types/api'
import { useBottleStore } from '@/stores/bottleStore'
import { getBottlePhotoUrl } from '@/api/bottles'
import { cssColorFor } from '@/utils/wineColors'

type SubTab = 'bottles' | 'masterdata'

const route = useRoute()
const router = useRouter()
const store = useBottleStore()

const tastingOpen = ref(false)
const tastingBottleId = ref<number | null>(null)
const eventOpen = ref(false)
const eventBottleId = ref<number | null>(null)
const eventMode = ref<'gift' | 'lost'>('gift')
const restoreError = ref<string | null>(null)
const wizardOpen = ref(false)
const filterColor = ref<WineColor | ''>('')
const filterStatus = ref<BottleStatus | ''>('in_storage')
const filterYear = ref<number | null>(null)

const activeTab = ref<SubTab>((route.query.tab === 'stammdaten' || route.query.tab === 'masterdata') ? 'masterdata' : 'bottles')

watch(() => route.query.tab, (q) => {
	activeTab.value = (q === 'stammdaten' || q === 'masterdata') ? 'masterdata' : 'bottles'
})

function setTab(tab: SubTab) {
	activeTab.value = tab
	const target = tab === 'masterdata' ? 'stammdaten' : undefined
	router.replace({ query: { ...route.query, tab: target } })
}

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

async function onWizardComplete(_payload: { purchaseId: number; bottleCount: number }) {
	wizardOpen.value = false
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
	restoreError.value = null
	try {
		await store.restoreBottle(id)
	} catch (e: any) {
		restoreError.value = e?.message ?? t('vinarium', 'Zurücksetzen fehlgeschlagen')
	}
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
	align-items: center;
	gap: 1rem;
	margin-bottom: 1rem;
	flex-wrap: wrap;
}
.inventory-view__sp { flex: 1; }
.count {
	padding: 0.25rem 0.75rem;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
	font-size: 0.9rem;
}

/* Sub-Tabs */
.subtabs {
	display: flex;
	gap: 2px;
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 1rem;
}
.subtab {
	font-family: inherit;
	font-size: 14px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	background: none;
	border: none;
	border-bottom: 2px solid transparent;
	padding: 10px 16px;
	cursor: pointer;
	margin-bottom: -1px;
}
.subtab:hover {
	color: var(--color-main-text);
}
.subtab--active {
	color: var(--color-primary-element, #0082c9);
	border-bottom-color: var(--color-primary-element, #0082c9);
}

.dot {
	display: inline-block;
	width: 14px;
	height: 14px;
	border-radius: 50%;
	border: 1px solid var(--color-border);
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
.restore-error {
	margin: 1rem 0 0;
	padding: 0.5rem 0.75rem;
	background: rgba(198, 40, 40, 0.1);
	border-left: 3px solid #c62828;
	border-radius: var(--border-radius);
	color: #c62828;
	font-size: 0.9rem;
}
.actions-cell {
	display: flex;
	flex-wrap: nowrap;
	align-items: center;
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

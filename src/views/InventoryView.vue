<template>
	<div class="inventory-view">
		<div ref="headEl" class="inventory-head">
			<header class="inventory-view__header">
				<h2>{{ t('vinarium', 'Bestand') }}</h2>
				<div class="seg" role="group" :aria-label="t('vinarium', 'Ansicht')">
					<button
						class="seg-btn"
						:class="{ active: activeTab === 'bottles' }"
						@click="setTab('bottles')"
					>
						{{ t('vinarium', 'Flaschen') }}
					</button>
					<button
						class="seg-btn"
						:class="{ active: activeTab === 'masterdata' }"
						@click="setTab('masterdata')"
					>
						{{ t('vinarium', 'Stammdaten') }}
					</button>
				</div>
				<span class="count">{{ n('vinarium', '{count} Flasche', '{count} Flaschen', store.totalCount, { count: store.totalCount }) }}</span>
				<div class="inventory-view__sp"></div>
				<NcButton variant="primary" @click="wizardOpen = true">{{ t('vinarium', '+ Kauf erfassen') }}</NcButton>
			</header>

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
			<div v-if="store.bottles.length > 0" class="bottles-card">
				<table class="bottles">
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
								<span v-else class="bottle-tile" :class="'bottle-tile--' + b.wine_color" :title="t('vinarium', WINE_COLOR_LABELS[b.wine_color])"></span>
							</td>
							<td>{{ b.producer_name }}</td>
							<td>{{ b.wine_name }}</td>
							<td>{{ b.year }}</td>
							<td>
								<span class="cat">
									<span class="dot" :style="{ background: cssColorFor(b.wine_color) }"></span>
									{{ t('vinarium', WINE_COLOR_LABELS[b.wine_color]) }}
								</span>
							</td>
							<td>
								<span class="chip" :class="chipClass(b.status)">{{ t('vinarium', BOTTLE_STATUS_LABELS[b.status]) }}</span>
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
			</div>
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

function chipClass(status: BottleStatus): string {
	switch (status) {
		case 'in_storage': return 'chip--stk'
		case 'consumed': return 'chip--csm'
		case 'gifted': return 'chip--gft'
		case 'lost': return 'chip--lst'
	}
	return ''
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

/* Segmented Toggle (Worktime-Style) */
.seg {
	display: inline-flex;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-element, 8px);
	padding: 3px;
}
.seg-btn {
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	background: none;
	border: none;
	padding: 6px 14px;
	border-radius: var(--border-radius-element, 8px);
	cursor: pointer;
}
.seg-btn:hover {
	color: var(--color-main-text);
}
.seg-btn.active {
	background: var(--color-main-background);
	color: var(--color-primary-element, #0082c9);
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
}

.cat {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	white-space: nowrap;
}
.dot {
	display: inline-block;
	width: 10px;
	height: 10px;
	border-radius: 50%;
	flex-shrink: 0;
}

/* Filter-Bar als helle Card */
.filters {
	display: flex;
	gap: 1rem;
	align-items: end;
	margin-bottom: 0;
	padding: 0.875rem 1rem;
	background: #fff;
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: var(--border-radius);
}
.filters label {
	display: flex;
	flex-direction: column;
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}
.input {
	margin-top: 0.25rem;
	padding: 0.4rem 0.6rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-element, 8px);
	background: var(--color-main-background);
	color: var(--color-main-text);
	min-width: 140px;
}
.reset {
	background: none;
	border: none;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
	padding: 0.5rem 0.25rem;
	cursor: pointer;
	align-self: end;
}
.reset:hover { color: var(--color-main-text); }

/* Tabelle in Card-Wrapper */
.bottles-card {
	background: #fff;
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: var(--border-radius);
	overflow: hidden;
}
.bottles {
	width: 100%;
	border-collapse: collapse;
}
.bottles tbody tr:hover {
	background: var(--color-background-hover);
}

/* Status-Chips (analog Dashboard) */
.chip {
	font-size: 11.5px;
	font-weight: 600;
	border-radius: var(--border-radius-element, 8px);
	padding: 3px 10px;
	display: inline-flex;
	align-items: center;
	gap: 5px;
	white-space: nowrap;
}
.chip--stk { background: #eaf5ee; color: #2f7d49; }
.chip--csm { background: #eeeeee; color: #5a5a5a; }
.chip--gft { background: #fbf3e6; color: #9a6c25; }
.chip--lst { background: #fbecea; color: #b03b33; }

/* Fallback-Tile, wenn keine Foto-URL existiert */
.bottle-tile {
	display: inline-block;
	width: 40px;
	height: 40px;
	border-radius: 4px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}
.bottle-tile--red { background: linear-gradient(135deg, #efd9d8, #7a2a28); }
.bottle-tile--white { background: linear-gradient(135deg, #f7f1d4, #c9b85a); }
.bottle-tile--rose { background: linear-gradient(135deg, #f6e0e1, #cf8c8d); }
.bottle-tile--sparkling { background: linear-gradient(135deg, #f4ecbf, #b8a64e); }
.bottle-tile--dessert { background: linear-gradient(135deg, #f0dcb8, #b07d3a); }
.bottle-tile--fortified { background: linear-gradient(135deg, #dec1ad, #6e3a2a); }

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
	padding: 0.6rem 0.75rem;
	border-bottom: 1px solid var(--color-border-light, #e2e3e5);
	vertical-align: middle;
}
.bottles tbody tr:last-child td { border-bottom: none; }
.bottles th {
	position: sticky;
	top: var(--inventory-head-h, 0px);
	z-index: 10;
	background: #fff;
	font-weight: 600;
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.04em;
	border-bottom: 1px solid var(--color-border, #d2d4d7);
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

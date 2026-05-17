<template>
	<div class="shelf-view">
		<div class="shelf-layout">
			<div class="shelf-main">
				<header class="shelf-view__header">
					<h2>{{ t('vinarium', 'Regal') }}</h2>
					<NcButton type="primary" @click="newShelfOpen = true">{{ t('vinarium', '+ Neues Regal') }}</NcButton>
				</header>

				<!-- Kein Keller -->
				<div v-if="!cellar" class="shelf-view__empty">
					<p>{{ t('vinarium', 'Noch kein Weinkeller angelegt.') }}</p>
					<NcButton type="primary" :disabled="creating" @click="createDefault">{{ t('vinarium', 'Standard-Regal anlegen') }}</NcButton>
				</div>

				<template v-else>
					<!-- Parkzone -->
					<section
						:class="['parkzone', { 'parkzone--drag-over': parkzoneDragOver }]"
						@dragover.prevent="parkzoneDragOver = true"
						@dragleave="onParkzoneDragLeave"
						@drop.prevent="onDropToParkzone"
					>
						<h3>{{ t('vinarium', 'Parkzone ({n})', { n: parkedBottles.length }) }}</h3>
						<template v-if="parkedBottles.length > 0">
							<p class="muted">{{ t('vinarium', 'Flaschen per Drag & Drop in einen Slot ziehen.') }}</p>
							<ul class="park-list">
								<li
									v-for="b in parkedBottles"
									:key="b.id"
									:class="['park-card', { selected: selectedBottleId === b.id }]"
									draggable="true"
									@dragstart="onDragStart(b.id, $event)"
									@dragend="onDragEnd"
									@click="onParkCardClick(b.id)"
								>
									<span class="park-card__dot" :style="{ background: cssColorFor(b.wine_color) }"></span>
									<span class="park-card__label">{{ b.wine_name }} {{ b.year }}</span>
								</li>
							</ul>
						</template>
						<p v-else class="empty-park">{{ t('vinarium', 'Keine Flaschen in der Parkzone.') }}</p>
					</section>

					<!-- Regal-Tabs -->
					<div v-if="shelves.length > 1" class="shelf-tabs">
						<button
							v-for="entry in shelves"
							:key="entry.shelf.id"
							:class="['shelf-tab', { active: activeShelfId === entry.shelf.id }]"
							@click="activeShelfId = entry.shelf.id"
						>
							{{ entry.shelf.name }}
						</button>
					</div>

					<!-- Aktives Regal -->
					<div v-if="activeShelf" class="shelves">
						<div class="shelf-title-row">
							<h3 class="shelf-title">{{ activeShelf.shelf.name }}</h3>
							<button
								v-if="shelves.length > 1"
								class="shelf-delete-btn"
								:title="t('vinarium', 'Regal löschen')"
								@click="confirmDeleteShelf"
							>✕</button>
						</div>

						<div v-for="compData in activeShelf.compartments" :key="compData.compartment.id" class="compartment">
							<div class="compartment__header">
								<h4 class="compartment__title">{{ compData.compartment.label }}</h4>
								<button class="compartment__config-btn" :title="t('vinarium', 'Fach konfigurieren')" @click="openConfig(compData)">⚙</button>
							</div>

							<div v-for="level in reversedLevels(compData.levels)" :key="level.id" class="level">
								<div class="level__label-col">
									<span class="level__label">{{ t('vinarium', 'Ebene {n}', { n: level.levelNumber + 1 }) }}</span>
								</div>
								<div class="level__content">
									<div v-if="level.columnsBack !== null" class="row-group">
										<span class="row-label">{{ t('vinarium', 'Hinten') }}</span>
										<div class="slot-row">
											<div
												v-for="slot in slotsFor(compData.compartment.id, level.levelNumber, 'back')"
												:key="slot.id"
												:class="['slot', slotClasses(slot.id)]"
												:style="bottleInSlot(slot.id) ? { background: cssColorFor(bottleInSlot(slot.id)!.wine_color) } : {}"
												:title="slotTooltip(slot)"
												@dragover.prevent="onDragOver(slot.id, $event)"
												@dragleave="onDragLeave($event)"
												@drop.prevent="onDrop(slot.id)"
												@click="onSlotClick(slot.id)"
											>
												<div
													v-if="bottleInSlot(slot.id)"
													class="slot__bottle"
													draggable="true"
													@dragstart.stop="onDragStart(bottleInSlot(slot.id)!.id, $event)"
													@dragend="onDragEnd"
												>
													<span class="slot__name">{{ bottleFullName(slot.id) }}</span>
													<span class="slot__year">{{ bottleYear(slot.id) }}</span>
												</div>
											</div>
										</div>
									</div>
									<div class="row-group">
										<span class="row-label">{{ t('vinarium', 'Vorne') }}</span>
										<div class="slot-row">
											<div
												v-for="slot in slotsFor(compData.compartment.id, level.levelNumber, 'front')"
												:key="slot.id"
												:class="['slot', slotClasses(slot.id)]"
												:style="bottleInSlot(slot.id) ? { background: cssColorFor(bottleInSlot(slot.id)!.wine_color) } : {}"
												:title="slotTooltip(slot)"
												@dragover.prevent="onDragOver(slot.id, $event)"
												@dragleave="onDragLeave($event)"
												@drop.prevent="onDrop(slot.id)"
												@click="onSlotClick(slot.id)"
											>
												<div
													v-if="bottleInSlot(slot.id)"
													class="slot__bottle"
													draggable="true"
													@dragstart.stop="onDragStart(bottleInSlot(slot.id)!.id, $event)"
													@dragend="onDragEnd"
												>
													<span class="slot__name">{{ bottleFullName(slot.id) }}</span>
													<span class="slot__year">{{ bottleYear(slot.id) }}</span>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</template>

				<p v-if="errorMsg" class="error">{{ errorMsg }}</p>
			</div>

			<BottleDetailPanel
				v-if="detailBottleId !== null"
				:bottleId="detailBottleId"
				@close="closeDetail"
				@uncork="onUncork"
			/>
		</div>

		<NewShelfDialog :open="newShelfOpen" @close="newShelfOpen = false" @created="onShelfCreated" />
		<ShelfConfigDialog
			v-if="configTarget"
			:open="configOpen"
			:compartment="configTarget"
			@close="configOpen = false"
			@reconfigured="onReconfigured"
		/>
		<TastingDialog
			:open="uncorkOpen"
			:bottleId="uncorkBottleId"
			@close="uncorkOpen = false"
			@consumed="onConsumed"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NewShelfDialog from '@/components/NewShelfDialog.vue'
import ShelfConfigDialog from '@/components/ShelfConfigDialog.vue'
import BottleDetailPanel from '@/components/BottleDetailPanel.vue'
import TastingDialog from '@/components/TastingDialog.vue'
import type { BottleListItem, CompartmentWithLevels, Level, Slot, WineColor } from '@/types/api'
import type { CellarResponse } from '@/api/cellar'
import { createDefaultCellar, destroyShelf, fetchCellar, fetchSlots } from '@/api/cellar'
import { useBottleStore } from '@/stores/bottleStore'

const store = useBottleStore()

const cellar = ref<CellarResponse['cellar'] | null>(null)
const shelves = ref<CellarResponse['shelves']>([])
const allSlots = ref<Slot[]>([])
const activeShelfId = ref<number | null>(null)

const selectedBottleId = ref<number | null>(null)
const detailBottleId = ref<number | null>(null)
const draggedBottleId = ref<number | null>(null)
const parkzoneDragOver = ref(false)
const creating = ref(false)
const errorMsg = ref('')

const newShelfOpen = ref(false)
const configOpen = ref(false)
const configTarget = ref<CompartmentWithLevels | null>(null)

const uncorkOpen = ref(false)
const uncorkBottleId = ref<number | null>(null)

const parkedBottles = computed(() => store.bottles.filter(b => b.slot_id === null && b.status === 'in_storage'))

const activeShelf = computed(() => shelves.value.find(e => e.shelf.id === activeShelfId.value) ?? null)

const bottleBySlotId = computed(() => {
	const map = new Map<number, BottleListItem>()
	for (const b of store.bottles) {
		if (b.slot_id !== null && b.status === 'in_storage') map.set(b.slot_id, b)
	}
	return map
})

function bottleInSlot(slotId: number): BottleListItem | undefined {
	return bottleBySlotId.value.get(slotId)
}
function bottleFullName(slotId: number): string {
	return bottleBySlotId.value.get(slotId)?.wine_name ?? ''
}
function bottleYear(slotId: number): string {
	const b = bottleBySlotId.value.get(slotId)
	return b ? String(b.year) : ''
}

function reversedLevels(levels: Level[]): Level[] {
	return [...levels].sort((a, b) => b.levelNumber - a.levelNumber)
}

function slotsFor(compartmentId: number, levelNumber: number, row: string): Slot[] {
	return allSlots.value
		.filter(s => s.compartmentId === compartmentId && s.level === levelNumber && s.row === row)
		.sort((a, b) => a.column - b.column)
}

function cssColorFor(color: WineColor): string {
	const palette: Record<WineColor, string> = {
		red: '#7a1c1c', white: '#e8d57a', rose: '#e8a3b8',
		sparkling: '#fff7c0', dessert: '#c2934e', fortified: '#4a1010',
	}
	return palette[color] ?? '#999'
}

function slotClasses(slotId: number): Record<string, boolean> {
	const b = bottleInSlot(slotId)
	return {
		occupied: b !== undefined,
		'selected-source': b !== undefined && selectedBottleId.value === b.id,
		'drag-source': b !== undefined && draggedBottleId.value === b.id,
	}
}

function slotTooltip(slot: Slot): string {
	const b = bottleInSlot(slot.id)
	if (b) return `${b.wine_name} ${b.year} (${b.producer_name})`
	const row = slot.row === 'front' ? t('vinarium', 'Vorne') : t('vinarium', 'Hinten')
	return t('vinarium', 'Frei — Ebene {level}, {row}, Platz {col}', {
		level: slot.level + 1, row, col: slot.column + 1,
	})
}

// --- Drag & Drop ---

function onDragStart(bottleId: number, event: DragEvent) {
	draggedBottleId.value = bottleId
	selectedBottleId.value = bottleId
	event.dataTransfer!.effectAllowed = 'move'
	event.dataTransfer!.setData('text/plain', String(bottleId))
}
function onDragEnd() { draggedBottleId.value = null }

function onDragOver(slotId: number, event: DragEvent) {
	(event.currentTarget as HTMLElement).classList.add('drag-over')
}
function onDragLeave(event: DragEvent) {
	const target = event.currentTarget as HTMLElement
	const related = event.relatedTarget as Node | null
	if (!related || !target.contains(related)) target.classList.remove('drag-over')
}

async function onDrop(slotId: number) {
	const bottleId = draggedBottleId.value ?? selectedBottleId.value
	if (!bottleId) return
	const target = bottleInSlot(slotId)
	errorMsg.value = ''
	try {
		if (target && target.id !== bottleId) {
			await store.swapBottles(bottleId, target.id)
		} else if (!target) {
			await store.moveBottle(bottleId, slotId)
			await store.fetchBottles({ status: 'in_storage' })
		}
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Verschieben fehlgeschlagen')
	} finally {
		selectedBottleId.value = null
		draggedBottleId.value = null
		detailBottleId.value = null
	}
}

async function onDropToParkzone() {
	parkzoneDragOver.value = false
	const bottleId = draggedBottleId.value ?? selectedBottleId.value
	if (!bottleId) return
	const bottle = store.bottles.find(b => b.id === bottleId)
	if (!bottle || bottle.slot_id === null) return
	errorMsg.value = ''
	try {
		await store.moveBottle(bottleId, null)
		await store.fetchBottles({ status: 'in_storage' })
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Verschieben fehlgeschlagen')
	} finally {
		selectedBottleId.value = null
		draggedBottleId.value = null
		detailBottleId.value = null
	}
}

function onParkzoneDragLeave(event: DragEvent) {
	const related = event.relatedTarget as Node | null
	const section = event.currentTarget as HTMLElement
	if (!related || !section.contains(related)) parkzoneDragOver.value = false
}

function onSlotClick(slotId: number) {
	const b = bottleInSlot(slotId)
	if (b) {
		if (selectedBottleId.value === b.id) {
			selectedBottleId.value = null
			detailBottleId.value = null
		} else {
			selectedBottleId.value = b.id
			detailBottleId.value = b.id
		}
	} else if (selectedBottleId.value) {
		onDrop(slotId)
	}
}

function onParkCardClick(bottleId: number) {
	if (selectedBottleId.value === bottleId) {
		selectedBottleId.value = null
		detailBottleId.value = null
	} else {
		selectedBottleId.value = bottleId
		detailBottleId.value = bottleId
	}
}

function closeDetail() {
	selectedBottleId.value = null
	detailBottleId.value = null
}

function onUncork(bottleId: number) {
	uncorkBottleId.value = bottleId
	uncorkOpen.value = true
}

async function onConsumed() {
	uncorkOpen.value = false
	uncorkBottleId.value = null
	detailBottleId.value = null
	selectedBottleId.value = null
	await store.fetchBottles({ status: 'in_storage' })
}

// --- Config Dialog ---

function openConfig(compData: CompartmentWithLevels) {
	configTarget.value = compData
	configOpen.value = true
}

async function onReconfigured() {
	await reload()
	await store.fetchBottles({ status: 'in_storage' })
}

// --- Shelf management ---

async function confirmDeleteShelf() {
	if (!activeShelf.value) return
	const name = activeShelf.value.shelf.name
	if (!confirm(t('vinarium', 'Regal "{name}" wirklich löschen? Alle Flaschen kommen in die Parkzone.', { name }))) return
	try {
		await destroyShelf(activeShelf.value.shelf.id)
		await reload()
		await store.fetchBottles({ status: 'in_storage' })
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Löschen fehlgeschlagen')
	}
}

async function onShelfCreated() {
	await reload()
	// Switch to the newly created shelf (last in list)
	if (shelves.value.length > 0) {
		activeShelfId.value = shelves.value[shelves.value.length - 1].shelf.id
	}
}

// --- Data loading ---

onMounted(async () => {
	await Promise.all([reload(), store.fetchBottles({ status: 'in_storage' })])
})

async function reload() {
	errorMsg.value = ''
	try {
		const data = await fetchCellar()
		cellar.value = data.cellar
		shelves.value = data.shelves
		if (activeShelfId.value === null || !shelves.value.find(e => e.shelf.id === activeShelfId.value)) {
			activeShelfId.value = shelves.value[0]?.shelf.id ?? null
		}
		await loadAllSlots()
	} catch (e: any) {
		if (e?.status === 404) cellar.value = null
		else errorMsg.value = e?.message ?? t('vinarium', 'Fehler beim Laden')
	}
}

async function loadAllSlots() {
	const slots: Slot[] = []
	for (const entry of shelves.value) {
		for (const compData of entry.compartments) {
			const compSlots = await fetchSlots(compData.compartment.id)
			slots.push(...compSlots)
		}
	}
	allSlots.value = slots
}

async function createDefault() {
	creating.value = true
	errorMsg.value = ''
	try {
		await createDefaultCellar()
		await reload()
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Anlegen fehlgeschlagen')
	} finally {
		creating.value = false
	}
}
</script>

<style scoped>
.shelf-view {
	padding: 2rem 2rem 2rem 50px;
}
.shelf-layout {
	display: flex;
	align-items: flex-start;
	gap: 1.5rem;
	max-width: 1300px;
}
.shelf-main {
	flex: 1;
	min-width: 0;
}
.shelf-view__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 1.5rem;
}
.shelf-view__empty {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 1rem;
	padding: 2rem;
}
.parkzone {
	background: var(--color-background-hover);
	border: 1px solid var(--color-border-dark, #bbb);
	border-left: 4px solid var(--color-warning, #e3a000);
	border-radius: var(--border-radius);
	padding: 1rem;
	margin-bottom: 1.5rem;
	min-height: 60px;
}
.parkzone--drag-over {
	background: var(--color-primary-element-light, #e8f0fe);
	border-color: var(--color-primary-element);
}
.empty-park {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	margin: 0;
}
.park-list {
	list-style: none;
	padding: 0;
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
}
.park-card {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	padding: 0.4rem 0.75rem;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: grab;
	user-select: none;
	font-size: 0.85rem;
}
.park-card:active { cursor: grabbing; }
.park-card.selected {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	border-color: var(--color-primary-element);
}
.park-card__dot {
	width: 10px; height: 10px;
	border-radius: 50%;
	flex-shrink: 0;
}
.muted {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}
.shelf-tabs {
	display: flex;
	gap: 0;
	border-bottom: 2px solid var(--color-border);
	margin-bottom: 1.5rem;
}
.shelf-tab {
	padding: 0.5rem 1.25rem;
	background: none;
	border: none;
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	cursor: pointer;
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast);
}
.shelf-tab.active {
	color: var(--color-main-text);
	border-bottom-color: var(--color-primary-element);
	font-weight: 500;
}
.shelf-title-row {
	display: flex;
	align-items: center;
	gap: 1rem;
	margin-bottom: 1rem;
}
.shelf-title {
	font-size: 1.2rem;
	margin: 0;
}
.shelf-delete-btn {
	background: none;
	border: none;
	color: #c0392b;
	font-size: 1.1rem;
	font-weight: bold;
	padding: 0 4px;
	line-height: 1;
	cursor: pointer;
}
.compartment {
	border: 2px solid var(--color-border-dark, #999);
	border-radius: var(--border-radius);
	padding: 1rem;
	margin-bottom: 1rem;
	max-width: 720px;
}
.compartment__header {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	margin-bottom: 0.75rem;
}
.compartment__title {
	margin: 0;
	font-size: 1rem;
	flex: 1;
}
.compartment__config-btn {
	background: none;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 0.15rem 0.4rem;
	cursor: pointer;
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast);
}
.compartment__config-btn:hover { background: var(--color-background-hover); }
.level {
	display: flex;
	align-items: stretch;
	border-bottom: 2px solid var(--color-border);
	padding: 0.5rem 0;
}
.level:last-child { border-bottom: none; }
.level__label-col {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	flex-shrink: 0;
	margin-right: 0.5rem;
}
.level__label {
	writing-mode: vertical-rl;
	transform: rotate(180deg);
	font-size: 0.65rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}
.level__content {
	display: flex;
	flex-direction: column;
	gap: 3px;
	flex: 1;
}
.row-group {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}
.row-label {
	font-size: 0.7rem;
	color: var(--color-text-maxcontrast);
	width: 40px;
	text-align: right;
	flex-shrink: 0;
}
.slot-row {
	display: flex;
	gap: 3px;
}
.slot {
	width: 70px;
	height: 48px;
	border: 2px solid var(--color-border-dark, #aaa);
	background: var(--color-background-dark, #f0f0f0);
	color: var(--color-text-maxcontrast);
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 4px;
	transition: background 0.1s, outline 0.1s;
	cursor: pointer;
}
.slot.occupied { color: white; cursor: grab; }
.slot.occupied:active { cursor: grabbing; }
.slot.selected-source { outline: 2px solid var(--color-primary-element); outline-offset: 1px; }
.slot.drag-source { opacity: 0.4; }
.slot:not(.occupied):hover, .slot.drag-over {
	background: var(--color-primary-element-light, #e8f0fe);
	border-color: var(--color-primary-element);
}
.slot__bottle {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 100%;
	padding: 1px 2px;
	line-height: 1.2;
	gap: 0;
}
.slot__name {
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-align: center;
	font-size: 0.6rem;
	line-height: 1.15;
	max-width: 66px;
	word-break: break-word;
}
.slot__year { font-size: 0.6rem; opacity: 0.85; }
.error {
	margin-top: 1rem;
	padding: 0.75rem;
	background: var(--color-error, #c62828);
	color: white;
	border-radius: var(--border-radius);
}
</style>

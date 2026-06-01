<template>
	<div class="shelf-view">
		<div class="shelf-layout">
			<!-- Parkzone als sticky linke Spalte -->
			<aside class="parkzone-col">
				<section
					:class="['parkzone', { 'parkzone--drag-over': parkzoneDragOver }]"
					@dragover.prevent="parkzoneDragOver = true"
					@dragleave="onParkzoneDragLeave"
					@drop.prevent="onDropToParkzone"
				>
					<h3 class="parkzone__title">
						{{ t('vinarium', 'Parkzone') }}
						<span class="parkzone__count">{{ parkedBottles.length }}</span>
					</h3>
					<template v-if="parkedBottles.length > 0">
						<p class="parkzone__hint">{{ t('vinarium', 'Flaschen per Drag & Drop in einen Slot ziehen.') }}</p>
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
			</aside>

			<div class="shelf-main">
				<div class="shelf-main__inner">
				<div class="shelf-head">
				<header class="shelf-view__header">
					<h2>{{ t('vinarium', 'Regal') }}</h2>
					<NcButton variant="primary" @click="newShelfOpen = true">{{ t('vinarium', '+ Neues Regal') }}</NcButton>
				</header>

				<!-- Regal-Tabs (immer sichtbar) -->
				<div class="shelf-tabs">
					<div
						v-for="entry in shelves"
						:key="entry.shelf.id"
						:class="['shelf-tab', { active: activeShelfId === entry.shelf.id }]"
					>
						<input
							v-if="renamingShelfId === entry.shelf.id"
							:ref="setRenameInput"
							v-model="renameValue"
							class="shelf-tab__input"
							@keyup.enter="commitRename"
							@keyup.esc="cancelRename"
							@blur="commitRename"
						>
						<template v-else>
							<button class="shelf-tab__label" @click="onTabClick(entry.shelf.id)">{{ entry.shelf.name }}</button>
							<button
								v-if="activeShelfId === entry.shelf.id"
								class="shelf-tab__delete"
								:title="t('vinarium', 'Regal löschen')"
								@click.stop="confirmDeleteShelf"
							>✕</button>
						</template>
					</div>
					<button
						class="shelf-tab shelf-tab--add"
						:title="t('vinarium', 'Neues Regal anlegen')"
						@click="newShelfOpen = true"
					>+</button>
				</div>
				</div>

				<!-- Kein Regal -->
				<div v-if="shelves.length === 0" class="shelf-view__empty">
					<p>{{ t('vinarium', 'Noch kein Regal angelegt.') }}</p>
				</div>

				<!-- Aktives Regal -->
				<div v-else-if="activeShelf" class="shelves">

						<div v-for="compData in activeShelf.compartments" :key="compData.compartment.id" class="compartment">
							<div class="compartment__header">
								<input
									v-if="renamingCompartmentId === compData.compartment.id"
									:ref="setCompartmentRenameInput"
									v-model="compartmentRenameValue"
									class="compartment__title-input"
									@keyup.enter="commitCompartmentRename"
									@keyup.esc="cancelCompartmentRename"
									@blur="commitCompartmentRename"
								>
								<h4
									v-else
									class="compartment__title compartment__title--editable"
									:title="t('vinarium', 'Zum Umbenennen klicken')"
									@click="startCompartmentRename(compData.compartment.id, compData.compartment.label)"
								>{{ compData.compartment.label }}</h4>
								<button class="compartment__config-btn" :title="t('vinarium', 'Fach konfigurieren')" @click="openConfig(compData)">⚙</button>
								<button
									class="compartment__delete-btn"
									:title="t('vinarium', 'Fach löschen')"
									@click="confirmDeleteCompartment(compData)"
								>✕</button>
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
												:style="bottleInSlot(slot.id) ? { background: cssSlotGradient(bottleInSlot(slot.id)!.wine_color) } : {}"
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
												:style="bottleInSlot(slot.id) ? { background: cssSlotGradient(bottleInSlot(slot.id)!.wine_color) } : {}"
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

						<div class="add-compartment-row">
							<NcButton :disabled="addingCompartment" @click="onAddCompartment">
								{{ t('vinarium', '+ Fach hinzufügen') }}
							</NcButton>
						</div>
					</div>

				<p v-if="errorMsg" class="error">{{ errorMsg }}</p>
				</div>
				<BottleDetailPanel
					v-if="detailBottleId !== null"
					:bottleId="detailBottleId"
					@close="closeDetail"
					@uncork="onUncork"
					@gift="onGift"
					@lose="onLose"
				/>
			</div>

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
		<BottleEventDialog
			:open="eventDialogOpen"
			:bottleId="eventBottleId"
			:mode="eventDialogMode"
			@close="eventDialogOpen = false"
			@done="onEventDone"
		/>
		<ConfirmDialog
			:open="deleteConfirmOpen"
			:name="t('vinarium', 'Regal löschen')"
			:message="deleteConfirmMessage"
			:confirm-label="t('vinarium', 'Löschen')"
			:destructive="true"
			@close="deleteConfirmOpen = false"
			@confirm="performDeleteShelf"
		/>
		<ConfirmDialog
			:open="deleteCompartmentConfirmOpen"
			:name="t('vinarium', 'Fach löschen')"
			:message="deleteCompartmentConfirmMessage"
			:confirm-label="t('vinarium', 'Löschen')"
			:destructive="true"
			@close="deleteCompartmentConfirmOpen = false"
			@confirm="performDeleteCompartment"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NewShelfDialog from '@/components/NewShelfDialog.vue'
import ShelfConfigDialog from '@/components/ShelfConfigDialog.vue'
import BottleDetailPanel from '@/components/BottleDetailPanel.vue'
import TastingDialog from '@/components/TastingDialog.vue'
import BottleEventDialog from '@/components/BottleEventDialog.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import type { BottleListItem, CompartmentWithLevels, Level, Slot, WineColor } from '@/types/api'
import type { CellarResponse } from '@/api/cellar'
import { addCompartment, destroyCompartment, destroyShelf, fetchCellar, fetchSlots, updateCompartment, updateShelf } from '@/api/cellar'
import { useBottleStore } from '@/stores/bottleStore'
import { cssColorFor, cssSlotGradient } from '@/utils/wineColors'

const store = useBottleStore()

const shelves = ref<CellarResponse['shelves']>([])
const allSlots = ref<Slot[]>([])
const activeShelfId = ref<number | null>(null)

const selectedBottleId = ref<number | null>(null)
const detailBottleId = ref<number | null>(null)
const draggedBottleId = ref<number | null>(null)
const parkzoneDragOver = ref(false)
const errorMsg = ref('')

const newShelfOpen = ref(false)
const renamingShelfId = ref<number | null>(null)
const renameValue = ref('')
let renameInputEl: HTMLInputElement | null = null
const renamingCompartmentId = ref<number | null>(null)
const compartmentRenameValue = ref('')
let compartmentRenameInputEl: HTMLInputElement | null = null
const configOpen = ref(false)
const configTarget = ref<CompartmentWithLevels | null>(null)

const uncorkOpen = ref(false)
const uncorkBottleId = ref<number | null>(null)

const eventDialogOpen = ref(false)
const eventDialogMode = ref<'gift' | 'lost'>('gift')
const eventBottleId = ref<number | null>(null)

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

async function onSlotClick(slotId: number) {
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
		await onDrop(slotId)
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

function onGift(bottleId: number) {
	eventBottleId.value = bottleId
	eventDialogMode.value = 'gift'
	eventDialogOpen.value = true
}

function onLose(bottleId: number) {
	eventBottleId.value = bottleId
	eventDialogMode.value = 'lost'
	eventDialogOpen.value = true
}

async function onEventDone() {
	eventDialogOpen.value = false
	eventBottleId.value = null
	detailBottleId.value = null
	selectedBottleId.value = null
	await reload()
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

const deleteConfirmOpen = ref(false)
const deleteConfirmMessage = computed(() => {
	const name = activeShelf.value?.shelf.name ?? ''
	return t('vinarium', 'Regal "{name}" wirklich löschen? Alle Flaschen kommen in die Parkzone.', { name })
})

function setRenameInput(el: unknown) {
	renameInputEl = (el as HTMLInputElement | null) ?? null
}

function onTabClick(shelfId: number) {
	if (activeShelfId.value === shelfId) {
		startRename(shelfId)
	} else {
		activeShelfId.value = shelfId
	}
}

function startRename(shelfId: number) {
	const entry = shelves.value.find(e => e.shelf.id === shelfId)
	if (!entry) return
	renamingShelfId.value = shelfId
	renameValue.value = entry.shelf.name
	nextTick(() => renameInputEl?.focus())
}

async function commitRename() {
	const id = renamingShelfId.value
	if (id === null) return
	const entry = shelves.value.find(e => e.shelf.id === id)
	const newName = renameValue.value.trim()
	renamingShelfId.value = null
	if (!entry || newName === '' || newName === entry.shelf.name) return
	try {
		await updateShelf(id, newName)
		await reload()
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Umbenennen fehlgeschlagen')
	}
}

function cancelRename() {
	renamingShelfId.value = null
}

function setCompartmentRenameInput(el: unknown) {
	compartmentRenameInputEl = (el as HTMLInputElement | null) ?? null
}

function startCompartmentRename(compartmentId: number, currentLabel: string) {
	renamingCompartmentId.value = compartmentId
	compartmentRenameValue.value = currentLabel
	nextTick(() => compartmentRenameInputEl?.focus())
}

async function commitCompartmentRename() {
	const id = renamingCompartmentId.value
	if (id === null) return
	const newLabel = compartmentRenameValue.value.trim()
	const current = activeShelf.value?.compartments.find(c => c.compartment.id === id)?.compartment.label
	renamingCompartmentId.value = null
	if (newLabel === '' || newLabel === current) return
	try {
		await updateCompartment(id, newLabel)
		await reload()
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Umbenennen fehlgeschlagen')
	}
}

function cancelCompartmentRename() {
	renamingCompartmentId.value = null
}

function confirmDeleteShelf() {
	if (!activeShelf.value) return
	deleteConfirmOpen.value = true
}

async function performDeleteShelf() {
	deleteConfirmOpen.value = false
	if (!activeShelf.value) return
	try {
		await destroyShelf(activeShelf.value.shelf.id)
		await reload()
		await store.fetchBottles({ status: 'in_storage' })
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Löschen fehlgeschlagen')
	}
}

// --- Compartment add/delete ---

const addingCompartment = ref(false)
const deleteCompartmentConfirmOpen = ref(false)
const deleteCompartmentTarget = ref<CompartmentWithLevels | null>(null)
const deleteCompartmentConfirmMessage = computed(() => {
	const label = deleteCompartmentTarget.value?.compartment.label ?? ''
	return t('vinarium', 'Fach "{label}" wirklich löschen? Alle Flaschen kommen in die Parkzone.', { label })
})

async function onAddCompartment() {
	if (!activeShelf.value || addingCompartment.value) return
	addingCompartment.value = true
	errorMsg.value = ''
	try {
		// Default config: 3 levels, 6 columns front, 7 columns back (matches CellarService defaults)
		const defaultLevels = [
			{ columnsFront: 6, columnsBack: 7 },
			{ columnsFront: 6, columnsBack: 7 },
			{ columnsFront: 6, columnsBack: 7 },
		]
		await addCompartment(activeShelf.value.shelf.id, defaultLevels)
		await reload()
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Fach hinzufügen fehlgeschlagen')
	} finally {
		addingCompartment.value = false
	}
}

function confirmDeleteCompartment(compData: CompartmentWithLevels) {
	deleteCompartmentTarget.value = compData
	deleteCompartmentConfirmOpen.value = true
}

async function performDeleteCompartment() {
	deleteCompartmentConfirmOpen.value = false
	const target = deleteCompartmentTarget.value
	deleteCompartmentTarget.value = null
	if (!target) return
	try {
		await destroyCompartment(target.compartment.id)
		await reload()
		await store.fetchBottles({ status: 'in_storage' })
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Fach löschen fehlgeschlagen')
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
		shelves.value = data.shelves
		if (activeShelfId.value === null || !shelves.value.find(e => e.shelf.id === activeShelfId.value)) {
			activeShelfId.value = shelves.value[0]?.shelf.id ?? null
		}
		await loadAllSlots()
	} catch (e: any) {
		if (e?.status === 404) {
			shelves.value = []
			activeShelfId.value = null
			allSlots.value = []
		} else {
			errorMsg.value = e?.message ?? t('vinarium', 'Fehler beim Laden')
		}
	}
}

async function loadAllSlots() {
	const ids = shelves.value.flatMap(e => e.compartments.map(c => c.compartment.id))
	const results = await Promise.all(ids.map(id => fetchSlots(id)))
	allSlots.value = results.flat()
}

</script>

<style scoped>
.shelf-view {
	padding: 2rem 2rem 2rem 50px;
}
.shelf-layout {
	display: grid;
	grid-template-columns: 280px 1fr;
	gap: 18px;
	align-items: start;
	max-width: 1300px;
}
@media (max-width: 900px) {
	.shelf-layout { grid-template-columns: 1fr; }
}

/* Parkzone als sticky linke Spalte */
.parkzone-col {
	position: sticky;
	top: 0;
}
.parkzone {
	display: flex;
	flex-direction: column;
	max-height: calc(100vh - 140px);
	background: #fff;
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: var(--border-radius);
	padding: 14px;
}
.parkzone--drag-over {
	background: var(--color-primary-element-light, #e8f0fe);
	border-color: var(--color-primary-element);
}
.parkzone__title {
	font-size: 15px;
	font-weight: 600;
	margin: 0 0 4px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-shrink: 0;
}
.parkzone__count {
	font-size: 11.5px;
	font-weight: 600;
	background: #e9f0f9;
	color: #5481b8;
	border-radius: 10px;
	padding: 2px 9px;
}
.parkzone__hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin: 0 0 10px;
	flex-shrink: 0;
}
.empty-park {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	margin: 0;
	font-size: 13px;
}
.park-list {
	list-style: none;
	padding: 0 4px 0 0;
	margin: 0 -4px 0 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
	overflow-y: auto;
	flex: 1;
	min-height: 0;
}
.park-list { scrollbar-width: thin; scrollbar-color: var(--color-background-dark) transparent; }
.park-list::-webkit-scrollbar { width: 6px; }
.park-list::-webkit-scrollbar-thumb { background: var(--color-background-dark); border-radius: 3px; }
.park-list::-webkit-scrollbar-thumb:hover { background: #c8c9cc; }

.park-card {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 10px;
	background: #fff;
	border: 1px solid var(--color-border-light, #e2e3e5);
	border-radius: var(--border-radius-element, 8px);
	cursor: grab;
	user-select: none;
	font-size: 13.5px;
	flex-shrink: 0;
}
.park-card:hover {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light, #e8f0fe);
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
.park-card__label {
	flex: 1;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.shelf-main {
	min-width: 0;
	display: flex;
	gap: 18px;
	align-items: flex-start;
}
.shelf-main__inner {
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
.shelf-head {
	position: sticky;
	top: 0;
	z-index: 20;
	background: var(--color-main-background);
	padding-bottom: 1rem;
	margin-bottom: 1rem;
}
.muted {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}
.shelf-tabs {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 0;
	border-bottom: 2px solid var(--color-border);
}
.shelf-tab {
	display: inline-flex;
	align-items: center;
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	color: var(--color-text-maxcontrast);
}
.shelf-tab.active {
	color: var(--color-main-text);
	border-bottom-color: var(--color-primary-element);
}
.shelf-tab__label {
	padding: 0.5rem 0.5rem 0.5rem 1.25rem;
	background: none;
	border: none;
	cursor: pointer;
	font-size: 0.9rem;
	color: inherit;
}
.shelf-tab.active .shelf-tab__label {
	font-weight: 500;
}
.shelf-tab__delete {
	background: none;
	border: none;
	color: #c0392b;
	font-size: 1rem;
	font-weight: bold;
	padding: 0 0.6rem 0 0.25rem;
	line-height: 1;
	cursor: pointer;
}
.shelf-tab__delete:hover {
	color: #b71c1c;
}
.shelf-tab__input {
	margin: 0.25rem 0.5rem;
	padding: 0.25rem 0.5rem;
	font-size: 0.9rem;
	border: 1px solid var(--color-primary-element);
	border-radius: var(--border-radius);
	min-width: 8rem;
}
.shelf-tab--add {
	padding: 0.5rem 1rem;
	background: none;
	border: none;
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	cursor: pointer;
	font-size: 1.1rem;
	font-weight: bold;
	color: var(--color-text-maxcontrast);
}
.shelf-tab--add:hover {
	color: var(--color-main-text);
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
.compartment__title--editable {
	cursor: pointer;
	border-radius: var(--border-radius);
	padding: 0.1rem 0.3rem;
	margin: -0.1rem -0.3rem;
}
.compartment__title--editable:hover {
	background: var(--color-background-hover);
}
.compartment__title-input {
	flex: 1;
	font-size: 1rem;
	padding: 0.2rem 0.4rem;
	border: 1px solid var(--color-primary-element);
	border-radius: var(--border-radius);
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
.compartment__delete-btn {
	background: none;
	border: none;
	color: #c0392b;
	font-size: 1rem;
	font-weight: bold;
	padding: 0 6px;
	line-height: 1;
	cursor: pointer;
}
.compartment__delete-btn:hover { color: #b71c1c; }
.add-compartment-row {
	margin-top: 0.5rem;
	max-width: 720px;
	display: flex;
	justify-content: flex-start;
}
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
	border: 1px solid var(--color-border-light, #e2e3e5);
	background: #fcfcfd;
	color: var(--color-text-maxcontrast);
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 6px;
	transition: background 0.1s, outline 0.1s, transform 0.1s;
	cursor: pointer;
	font-size: 0.72rem;
}
.slot.occupied {
	color: #fff;
	cursor: grab;
	border: none;
	text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
}
.slot.occupied:hover { transform: translateY(-1px); }
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

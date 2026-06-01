<template>
	<div class="shelf-view">
		<header class="shelf-view__top">
			<h2>{{ t('vinarium', 'Weinkeller') }}</h2>
			<div class="shelf-view__top-actions">
				<NcButton
					v-if="activeShelf && activeShelf.compartments.length === 1"
					@click="openConfig(activeShelf.compartments[0])"
				>
					⚙ {{ t('vinarium', 'Regal konfigurieren') }}
				</NcButton>
				<NcButton variant="primary" @click="newShelfOpen = true">{{ t('vinarium', '+ Neues Regal') }}</NcButton>
			</div>
		</header>

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
							<!-- Compartment-Header immer rendern (Card-Header mit Title und Hover-Actions) -->
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

							<div
								v-for="(level, idx) in reversedLevels(compData.levels)"
								:key="level.id"
								class="level"
								:style="{
									'--front-cols': level.columnsFront,
									'--back-cols': level.columnsBack ?? 0,
									'--max-cols': Math.max(level.columnsFront, level.columnsBack ?? 0),
								}"
							>
								<div class="level__header">
									<span class="level__title">
										{{ t('vinarium', 'Ebene') }} {{ level.levelNumber + 1 }}<span v-if="levelPositionLabel(idx, compData.levels.length)" class="level__position"> · {{ levelPositionLabel(idx, compData.levels.length) }}</span>
									</span>
									<span class="level__occupancy">
										<strong>{{ levelOccupancy(compData.compartment.id, level.levelNumber).filled }}</strong>
										/ {{ levelOccupancy(compData.compartment.id, level.levelNumber).total }} {{ t('vinarium', 'belegt') }}
									</span>
								</div>

								<!-- Hinten (oben, versetzt) -->
								<div v-if="level.columnsBack !== null && level.columnsBack > 0" class="slot-row slot-row--back">
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
										<span class="slot__id">{{ slotShortId(slot) }}<span class="slot__id-h">H</span></span>
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

								<!-- Vorne (Hauptreihe unten) -->
								<div class="slot-row slot-row--front">
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
										<span class="slot__id">{{ slotShortId(slot) }}</span>
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

						<!-- Legende + Aktionen -->
						<div class="shelf-footer">
							<div class="shelf-legend">
								<span><span class="legend-sw legend-sw--red"></span> {{ t('vinarium', 'Rot') }}</span>
								<span><span class="legend-sw legend-sw--white"></span> {{ t('vinarium', 'Weiß') }}</span>
								<span><span class="legend-sw legend-sw--rose"></span> {{ t('vinarium', 'Rosé') }}</span>
								<span><span class="legend-sw legend-sw--sparkling"></span> {{ t('vinarium', 'Schaumwein') }}</span>
								<span><span class="legend-sw legend-sw--dessert"></span> {{ t('vinarium', 'Dessertwein') }}</span>
								<span><span class="legend-sw legend-sw--fortified"></span> {{ t('vinarium', 'Likörwein') }}</span>
								<span><span class="legend-sw legend-sw--empty"></span> {{ t('vinarium', 'frei') }}</span>
							</div>
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

/** Ebenen sortiert für die Anzeige: Top zuerst (höchste Nummer oben) — Ebene 1 ist also unten */
function reversedLevels(levels: Level[]): Level[] {
	return [...levels].sort((a, b) => b.levelNumber - a.levelNumber)
}

/** Positions-Label (TOP / MITTE / BODEN) je nach Ebenen-Position */
function levelPositionLabel(idx: number, total: number): string {
	if (total === 1) return ''
	if (idx === 0) return t('vinarium', 'TOP')
	if (idx === total - 1) return t('vinarium', 'BODEN')
	if (total === 3) return t('vinarium', 'MITTE')
	if (idx === Math.floor(total / 2)) return t('vinarium', 'MITTE')
	return ''
}

/** Slot-ID nur Spaltenzahl, weil Ebene schon im Header steht (Hinten bekommt H-Suffix im Template) */
function slotShortId(slot: Slot): string {
	return String(slot.column + 1)
}

/** Belegung pro Ebene (alle Slots dieser Ebene über vorne + hinten) */
function levelOccupancy(compartmentId: number, levelNumber: number): { filled: number; total: number } {
	const slots = allSlots.value.filter(s => s.compartmentId === compartmentId && s.level === levelNumber)
	let filled = 0
	for (const s of slots) {
		if (bottleInSlot(s.id) !== undefined) filled++
	}
	return { filled, total: slots.length }
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
	border-radius: 12px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
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
}
.shelf-main__inner {
	min-width: 0;
}
/* Slot-Grid: Compartments sitzen untereinander, jeweils als eigene self-fitting Card */
.shelves {
	margin-top: 12px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	align-items: flex-start;
}
.shelf-view__top {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin: 0 0 1.25rem 0;
	gap: 1rem;
}
.shelf-view__top h2 {
	font-size: 24px;
	font-weight: 600;
	letter-spacing: -0.01em;
}
.shelf-view__top-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
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
/* Regal-Tabs als NC-Pill-Buttons (Worktime-Style) */
.shelf-tabs {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}
.shelf-tab {
	display: inline-flex;
	align-items: center;
	background: #fff;
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: var(--border-radius-element, 8px);
	color: var(--color-text-maxcontrast);
	overflow: hidden;
	transition: background 0.1s, border-color 0.1s;
}
.shelf-tab.active {
	background: var(--color-primary-element, #0082c9);
	border-color: var(--color-primary-element, #0082c9);
	color: #fff;
}
.shelf-tab__label {
	padding: 7px 14px;
	background: none;
	border: none;
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
	color: inherit;
	font-family: inherit;
}
.shelf-tab__delete {
	background: none;
	border: none;
	color: inherit;
	opacity: 0;
	font-size: 0.95rem;
	font-weight: bold;
	padding: 0 9px 0 4px;
	line-height: 1;
	cursor: pointer;
	transition: opacity 0.12s;
}
.shelf-tab:hover .shelf-tab__delete,
.shelf-tab.active .shelf-tab__delete:focus { opacity: 0.75; }
.shelf-tab__delete:hover { opacity: 1 !important; }
.shelf-tab__input {
	margin: 4px 8px;
	padding: 4px 8px;
	font-size: 13px;
	border: 1px solid var(--color-primary-element);
	border-radius: var(--border-radius-element, 8px);
	min-width: 8rem;
}
.shelf-tab--add {
	padding: 7px 14px;
	background: transparent;
	border: 1px dashed var(--color-border, #d2d4d7);
	border-radius: var(--border-radius-element, 8px);
	cursor: pointer;
	font-size: 1rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}
.shelf-tab--add:hover {
	color: var(--color-main-text);
	border-color: var(--color-primary-element);
}

/* Compartment als self-fitting Card (Card-System aus Dashboard v4) */
.compartment {
	background: #fff;
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: 12px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
	overflow: visible;
	width: fit-content;
}
.compartment__header {
	display: flex;
	align-items: center;
	gap: 18px;
	padding: 14px 18px;
	background: linear-gradient(180deg, #f4f4f4 0%, #fdfdfd 100%);
	border-bottom: 1px solid var(--color-border, #d2d4d7);
	border-radius: 12px 12px 0 0;
}
.compartment__title {
	margin: 0;
	font-size: 12.5px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin-right: auto;
}
.compartment__title--editable {
	cursor: pointer;
	border-radius: 4px;
	padding: 2px 6px;
	margin: -2px 0 -2px -6px;
}
.compartment__title--editable:hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}
.compartment__title-input {
	font-size: 12.5px;
	padding: 2px 6px;
	border: 1px solid var(--color-primary-element);
	border-radius: 4px;
	margin-right: auto;
}
.compartment__config-btn,
.compartment__delete-btn {
	background: none;
	border: none;
	color: var(--color-text-maxcontrast);
	font-size: 0.95rem;
	padding: 2px 8px;
	cursor: pointer;
	opacity: 0;
	border-radius: 4px;
	transition: opacity 0.12s, background 0.12s;
}
.compartment:hover .compartment__config-btn,
.compartment:hover .compartment__delete-btn { opacity: 0.7; }
.compartment__config-btn:hover { background: var(--color-background-hover); opacity: 1 !important; color: var(--color-main-text); }
.compartment__delete-btn:hover { background: var(--color-background-hover); opacity: 1 !important; color: #b03b33; }

/* Ebene als Sub-Sektion innerhalb der Compartment-Card */
.level {
	padding: 14px 18px;
	border-bottom: 1px solid #c8c8c8;
	/* Fixe Slot-Größe — alle Slots gleich groß, völlig unabhängig vom Container */
	--slot-w: 138px;
	--gap: 8px;
}
.level:last-child { border-bottom: none; }
.level__header {
	display: flex;
	align-items: baseline;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 12px;
}
.level__title {
	font-size: 13px;
	font-weight: 700;
	color: var(--color-main-text);
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.level__position {
	color: var(--color-text-maxcontrast);
	font-weight: 500;
}
.level__occupancy {
	font-size: 11.5px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-hover);
	border-radius: 10px;
	padding: 3px 10px;
	display: inline-flex;
	align-items: center;
	gap: 5px;
}
.level__occupancy strong {
	color: var(--color-main-text);
	font-weight: 700;
}

/* Slot-Row — die längere Reihe ist linksbündig, die kürzere um halbe Slot-Breite eingerückt
 * (Pflastersteinen-Versatz: erste Box der längeren Reihe sitzt halb links über der ersten der kürzeren) */
.slot-row {
	display: flex;
	gap: var(--gap);
	margin-bottom: var(--gap);
}
.slot-row:last-child { margin-bottom: 0; }
.slot-row--front {
	margin-left: calc((var(--max-cols) - var(--front-cols)) * (var(--slot-w) + var(--gap)) / 2);
}
.slot-row--back {
	margin-left: calc((var(--max-cols) - var(--back-cols)) * (var(--slot-w) + var(--gap)) / 2);
}

/* Slot: feste Breite — alle Slots gleich groß, unabhängig vom Container */
.slot {
	flex: 0 0 var(--slot-w);
	width: var(--slot-w);
	height: 72px;
	border: 1px solid var(--color-border, #d2d4d7);
	background: #fff;
	color: var(--color-text-maxcontrast);
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 8px;
	transition: background 0.1s, outline 0.1s, transform 0.1s;
	cursor: pointer;
	position: relative;
	padding: 8px;
	overflow: hidden;
}
.slot.occupied {
	color: #fff;
	cursor: grab;
	border: none;
	text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
}
.slot.occupied:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12); }
.slot.occupied:active { cursor: grabbing; }
.slot.selected-source { outline: 2px solid var(--color-primary-element); outline-offset: 1px; }
.slot.drag-source { opacity: 0.4; }
.slot:not(.occupied):hover, .slot.drag-over {
	background: var(--color-primary-element-light, #e8f0fe);
	border-color: var(--color-primary-element);
}

.slot__id {
	position: absolute;
	top: 5px;
	left: 7px;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.02em;
	color: rgba(255, 255, 255, 0.85);
}
.slot:not(.occupied) .slot__id {
	color: #555;
}
.slot__id-h {
	margin-left: 1px;
	font-size: 9px;
	font-weight: 600;
	opacity: 0.85;
}

.slot__bottle {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 100%;
	line-height: 1.2;
	gap: 1px;
}
.slot__name {
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-align: center;
	font-size: 0.78rem;
	font-weight: 600;
	line-height: 1.2;
	max-width: 124px;
	word-break: break-word;
}
.slot__year {
	font-size: 0.72rem;
	opacity: 0.92;
	font-variant-numeric: tabular-nums;
	margin-top: 1px;
}

/* Footer mit Legende + Compartment-Add-Button */
.shelf-footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 1rem;
	margin-top: 14px;
	padding: 10px 4px 0;
	flex-wrap: wrap;
}
.shelf-legend {
	display: flex;
	gap: 14px;
	flex-wrap: wrap;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
.shelf-legend > span {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.legend-sw {
	width: 14px;
	height: 14px;
	border-radius: 3px;
	display: inline-block;
}
.legend-sw--red { background: linear-gradient(160deg, #9a3b39, #6e2624); }
.legend-sw--white { background: linear-gradient(160deg, #d6c468, #a4943a); }
.legend-sw--rose { background: linear-gradient(160deg, #e0a3a4, #b56e6f); }
.legend-sw--sparkling { background: linear-gradient(160deg, #d4be58, #9a8b3a); }
.legend-sw--dessert { background: linear-gradient(160deg, #c89352, #8e6128); }
.legend-sw--fortified { background: linear-gradient(160deg, #86462f, #532b1f); }
.legend-sw--empty {
	background: #fcfcfd;
	border: 1px solid var(--color-border-light, #e2e3e5);
}
.error {
	margin-top: 1rem;
	padding: 0.75rem;
	background: var(--color-error, #c62828);
	color: white;
	border-radius: var(--border-radius);
}
</style>

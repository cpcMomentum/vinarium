<template>
	<div class="shelf-view">
		<header class="shelf-view__header">
			<h2>Regal</h2>
			<NcButton v-if="!cellar" type="primary" :disabled="creating" @click="createDefault">
				Standard-Regal anlegen (234 Slots)
			</NcButton>
		</header>

		<section
			:class="['parkzone', { 'parkzone--drag-over': parkzoneDragOver }]"
			@dragover.prevent="parkzoneDragOver = true"
			@dragleave="onParkzoneDragLeave"
			@drop.prevent="onDropToParkzone"
		>
			<h3>Parkzone ({{ parkedBottles.length }})</h3>
			<template v-if="parkedBottles.length > 0">
				<p class="muted">Flaschen per Drag & Drop oder Klick in einen Slot ziehen.</p>
				<ul class="park-list">
					<li
						v-for="b in parkedBottles"
						:key="b.id"
						:class="['park-card', { selected: selectedBottleId === b.id }]"
						draggable="true"
						@dragstart="onDragStart(b.id, $event)"
						@dragend="onDragEnd"
						@click="selectedBottleId = selectedBottleId === b.id ? null : b.id"
					>
						<span class="park-card__dot" :style="{ background: cssColorFor(b.wine_color) }"></span>
						<span class="park-card__label">{{ b.wine_name }} {{ b.year }}</span>
					</li>
				</ul>
			</template>
			<p v-else class="empty-park">Keine Flaschen in der Parkzone.</p>
		</section>

		<div v-if="cellar && shelves.length > 0" class="shelves">
			<h3 class="shelf-title">{{ shelves[0]?.shelf.name ?? 'Regal' }}</h3>
			<div v-for="entry in shelves" :key="entry.shelf.id">
				<div v-for="comp in entry.compartments" :key="comp.id" class="compartment">
					<h4 class="compartment__title">{{ comp.label }}</h4>
					<div v-for="level in reversedLevels(comp.levels)" :key="level" class="level">
						<div class="level__label-col">
							<span class="level__label">Ebene {{ level }}</span>
						</div>
						<div class="level__content">
							<div class="row-group">
								<span class="row-label">Hinten</span>
								<div class="slot-row">
									<div
										v-for="slot in slotsFor(comp.id, level - 1, 'back')"
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
								<span class="row-label">Vorne</span>
								<div class="slot-row">
									<div
										v-for="slot in slotsFor(comp.id, level - 1, 'front')"
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
		</div>

		<p v-if="errorMsg" class="error">{{ errorMsg }}</p>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { BottleListItem, Cellar, Compartment, Shelf, Slot, WineColor } from '@/types/api'
import { useBottleStore } from '@/stores/bottleStore'

interface ShelfEntry { shelf: Shelf; compartments: Compartment[] }

const store = useBottleStore()
const cellar = ref<Cellar | null>(null)
const shelves = ref<ShelfEntry[]>([])
const allSlots = ref<Slot[]>([])
const selectedBottleId = ref<number | null>(null)
const draggedBottleId = ref<number | null>(null)
const creating = ref(false)
const errorMsg = ref('')
const parkzoneDragOver = ref(false)

const parkedBottles = computed(() => store.bottles.filter(b => b.slot_id === null && b.status === 'in_storage'))

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

function reversedLevels(count: number): number[] {
	const arr: number[] = []
	for (let i = count; i >= 1; i--) arr.push(i)
	return arr
}

function slotsFor(compartmentId: number, level: number, row: string): Slot[] {
	return allSlots.value
		.filter(s => s.compartmentId === compartmentId && s.level === level && s.row === row)
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
	return `Frei — Ebene ${slot.level + 1}, ${slot.row === 'front' ? 'Vorne' : 'Hinten'}, Platz ${slot.column + 1}`
}

// --- Drag & Drop ---

function onDragStart(bottleId: number, event: DragEvent) {
	draggedBottleId.value = bottleId
	selectedBottleId.value = bottleId
	event.dataTransfer!.effectAllowed = 'move'
	event.dataTransfer!.setData('text/plain', String(bottleId))
}

function onDragEnd() {
	draggedBottleId.value = null
}

function onDragOver(slotId: number, event: DragEvent) {
	const target = event.currentTarget as HTMLElement
	target.classList.add('drag-over')
}

function onDragLeave(event: DragEvent) {
	const target = event.currentTarget as HTMLElement
	const related = event.relatedTarget as Node | null
	if (!related || !target.contains(related)) {
		target.classList.remove('drag-over')
	}
}

function onParkzoneDragLeave(event: DragEvent) {
	const related = event.relatedTarget as Node | null
	const section = event.currentTarget as Element
	if (!related || !section.contains(related)) {
		parkzoneDragOver.value = false
	}
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
		selectedBottleId.value = null
		draggedBottleId.value = null
	} catch (e: any) {
		errorMsg.value = e?.message ?? 'Verschieben fehlgeschlagen'
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
		errorMsg.value = e?.message ?? 'Verschieben fehlgeschlagen'
	} finally {
		selectedBottleId.value = null
		draggedBottleId.value = null
	}
}

// --- Click fallback ---

function onSlotClick(slotId: number) {
	const b = bottleInSlot(slotId)
	if (b) {
		selectedBottleId.value = selectedBottleId.value === b.id ? null : b.id
	} else if (selectedBottleId.value) {
		onDrop(slotId)
	}
}

// --- Data loading ---

onMounted(async () => {
	await Promise.all([loadCellar(), store.fetchBottles({ status: 'in_storage' })])
})

async function loadCellar() {
	try {
		const { data } = await axios.get<{ cellar: Cellar; shelves: ShelfEntry[] }>(
			generateUrl('/apps/vinarium/api/v1/cellar'),
		)
		cellar.value = data.cellar
		shelves.value = data.shelves
		await loadAllSlots()
	} catch (e: any) {
		if (e?.response?.status === 404) cellar.value = null
		else errorMsg.value = e?.message ?? 'Fehler beim Laden'
	}
}

async function loadAllSlots() {
	const slots: Slot[] = []
	for (const entry of shelves.value) {
		for (const comp of entry.compartments) {
			const { data } = await axios.get<Slot[]>(
				generateUrl(`/apps/vinarium/api/v1/compartments/${comp.id}/slots`),
			)
			slots.push(...data)
		}
	}
	allSlots.value = slots
}

async function createDefault() {
	creating.value = true
	errorMsg.value = ''
	try {
		await axios.post(generateUrl('/apps/vinarium/api/v1/cellar'), {})
		await loadCellar()
	} catch (e: any) {
		errorMsg.value = e?.response?.data?.error ?? e?.message ?? 'Anlegen fehlgeschlagen'
	} finally {
		creating.value = false
	}
}
</script>

<style scoped>
.shelf-view {
	padding: 2rem 2rem 2rem 50px;
	max-width: 1000px;
}
.shelf-view__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 1.5rem;
}
.parkzone {
	background: var(--color-background-hover);
	border: 1px solid var(--color-border-dark, #bbb);
	border-left: 4px solid var(--color-warning, #e3a000);
	border-radius: var(--border-radius);
	padding: 1rem;
	margin-bottom: 2.5rem;
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
	width: 10px;
	height: 10px;
	border-radius: 50%;
	flex-shrink: 0;
}
.muted {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}
.shelf-title {
	font-size: 1.2rem;
	margin: 0 0 1rem 0;
}
.compartment {
	border: 2px solid var(--color-border-dark, #999);
	border-radius: var(--border-radius);
	padding: 1rem;
	margin-bottom: 1rem;
	max-width: 720px;
}
.compartment__title {
	margin: 0 0 0.75rem 0;
	font-size: 1rem;
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
.slot.occupied {
	color: white;
	cursor: grab;
}
.slot.occupied:active { cursor: grabbing; }
.slot.selected-source {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}
.slot.drag-source {
	opacity: 0.4;
}
.slot:not(.occupied):hover,
.slot.drag-over {
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
.slot__year {
	font-size: 0.6rem;
	opacity: 0.85;
}
.error {
	margin-top: 1rem;
	padding: 0.75rem;
	background: var(--color-error, #c62828);
	color: white;
	border-radius: var(--border-radius);
}
</style>

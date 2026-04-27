<template>
	<div class="shelf-view">
		<header class="shelf-view__header">
			<h2>Regal</h2>
			<NcButton v-if="!cellar" type="primary" :disabled="creating" @click="createDefault">
				Standard-Regal anlegen (234 Slots)
			</NcButton>
		</header>

		<section v-if="parkedBottles.length > 0" class="parkzone">
			<h3>Parkzone ({{ parkedBottles.length }} Flasche{{ parkedBottles.length === 1 ? '' : 'n' }})</h3>
			<p class="muted">Flasche markieren, dann auf einen freien Slot klicken. Belegte Slots anklicken zum Verschieben.</p>
			<ul class="park-list">
				<li
					v-for="b in parkedBottles"
					:key="b.id"
					:class="['park-card', { selected: selectedBottleId === b.id }]"
					@click="selectedBottleId = selectedBottleId === b.id ? null : b.id"
				>
					<span class="park-card__dot" :style="{ background: cssColorFor(b.wine_color) }"></span>
					<span class="park-card__label">{{ b.wine_name }} {{ b.year }}</span>
				</li>
			</ul>
		</section>
		<p v-else-if="cellar" class="muted">Keine Flaschen in der Parkzone.</p>

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
									<button
										v-for="slot in slotsFor(comp.id, level - 1, 'back')"
										:key="slot.id"
										:class="['slot', slotClasses(slot.id)]"
										:style="bottleInSlot(slot.id) ? { background: cssColorFor(bottleInSlot(slot.id)!.wine_color) } : {}"
										:disabled="!canClickSlot(slot.id)"
										:title="slotTooltip(slot)"
										@click="onSlotClick(slot.id)"
									>
										<template v-if="bottleInSlot(slot.id)">
											<span class="slot__name">{{ bottleFullName(slot.id) }}</span>
											<span class="slot__year">{{ bottleYear(slot.id) }}</span>
										</template>
									</button>
								</div>
							</div>
							<div class="row-group">
								<span class="row-label">Vorne</span>
								<div class="slot-row">
									<button
										v-for="slot in slotsFor(comp.id, level - 1, 'front')"
										:key="slot.id"
										:class="['slot', slotClasses(slot.id)]"
										:style="bottleInSlot(slot.id) ? { background: cssColorFor(bottleInSlot(slot.id)!.wine_color) } : {}"
										:disabled="!canClickSlot(slot.id)"
										:title="slotTooltip(slot)"
										@click="onSlotClick(slot.id)"
									>
										<template v-if="bottleInSlot(slot.id)">
											<span class="slot__name">{{ bottleFullName(slot.id) }}</span>
											<span class="slot__year">{{ bottleYear(slot.id) }}</span>
										</template>
									</button>
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
const creating = ref(false)
const errorMsg = ref('')

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
	const b = bottleBySlotId.value.get(slotId)
	return b?.wine_name ?? ''
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
		pickable: !b && !!selectedBottleId.value,
		'selected-source': b !== undefined && selectedBottleId.value === b.id,
	}
}

function canClickSlot(slotId: number): boolean {
	const b = bottleInSlot(slotId)
	if (b) return true
	return !!selectedBottleId.value
}

function slotTooltip(slot: Slot): string {
	const b = bottleInSlot(slot.id)
	if (b) return `${b.wine_name} ${b.year} (${b.producer_name}) — Klick zum Auswählen`
	return `Frei — Ebene ${slot.level + 1}, ${slot.row === 'front' ? 'Vorne' : 'Hinten'}, Platz ${slot.column + 1}`
}

function onSlotClick(slotId: number) {
	const b = bottleInSlot(slotId)
	if (b) {
		selectedBottleId.value = selectedBottleId.value === b.id ? null : b.id
	} else if (selectedBottleId.value) {
		onPlace(slotId)
	}
}

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

async function onPlace(slotId: number) {
	if (!selectedBottleId.value) return
	errorMsg.value = ''
	try {
		await store.moveBottle(selectedBottleId.value, slotId)
		selectedBottleId.value = null
		await store.fetchBottles({ status: 'in_storage' })
	} catch (e: any) {
		errorMsg.value = e?.message ?? 'Platzieren fehlgeschlagen'
	}
}
</script>

<style scoped>
.shelf-view {
	padding: 2rem;
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
	border-left: 3px solid var(--color-warning, #e3a000);
	border-radius: var(--border-radius);
	padding: 1rem;
	margin-bottom: 2rem;
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
	cursor: pointer;
	user-select: none;
	font-size: 0.85rem;
}
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
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 1rem;
	margin-bottom: 1rem;
	max-width: 720px;
}
.compartment__title {
	margin: 0 0 0.75rem 0;
	font-size: 1rem;
	color: var(--color-main-text);
}
.level {
	display: flex;
	align-items: stretch;
	border-bottom: 2px solid var(--color-border);
	padding: 0.5rem 0;
}
.level:last-child {
	border-bottom: none;
}
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
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	font-size: 0.6rem;
	cursor: pointer;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	border-radius: 4px;
	transition: background 0.1s;
	padding: 1px 2px;
	line-height: 1.2;
	gap: 0;
}
.slot.occupied {
	color: white;
	cursor: pointer;
	font-weight: 500;
}
.slot.occupied:hover {
	opacity: 0.85;
}
.slot.selected-source {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}
.slot.pickable:not(.occupied):hover {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}
.slot:disabled:not(.occupied) {
	cursor: default;
	opacity: 0.5;
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

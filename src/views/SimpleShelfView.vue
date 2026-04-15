<template>
	<div class="shelf-view">
		<header class="shelf-view__header">
			<h2>Regal</h2>
			<NcButton v-if="!cellar" type="primary" :disabled="creating" @click="createDefault">
				Standard-Regal anlegen (234 Slots)
			</NcButton>
		</header>

		<section v-if="store.parkedCount > 0" class="parkzone">
			<h3>Parkzone ({{ store.parkedCount }} Flasche{{ store.parkedCount === 1 ? '' : 'n' }})</h3>
			<p class="muted">Eine Flasche markieren, dann unten auf einen freien Slot klicken.</p>
			<ul class="park-list">
				<li
					v-for="b in store.parked"
					:key="b.id"
					:class="['park-card', { selected: selectedBottleId === b.id }]"
					@click="selectedBottleId = selectedBottleId === b.id ? null : b.id"
				>
					Flasche #{{ b.id }}
				</li>
			</ul>
		</section>
		<p v-else class="muted">Keine Flaschen in der Parkzone.</p>

		<div v-if="cellar && shelves.length > 0" class="shelves">
			<div v-for="entry in shelves" :key="entry.shelf.id" class="shelf">
				<h3>{{ entry.shelf.name }}</h3>
				<div class="compartments">
					<div v-for="comp in entry.compartments" :key="comp.id" class="compartment">
						<h4>{{ comp.label }}</h4>
						<div class="grid">
							<button
								v-for="slot in slotsByCompartment[comp.id] || []"
								:key="slot.id"
								:class="['slot', { occupied: occupiedSlotIds.has(slot.id), pickable: !!selectedBottleId }]"
								:title="slotTitle(slot)"
								:disabled="occupiedSlotIds.has(slot.id) || !selectedBottleId"
								@click="onPlace(slot.id)"
							>
								<span class="slot__pos">{{ slot.row[0].toUpperCase() }}{{ slot.column + 1 }}</span>
							</button>
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
import type { Cellar, Compartment, Shelf, Slot } from '@/types/api'
import { useBottleStore } from '@/stores/bottleStore'

interface ShelfEntry {
	shelf: Shelf
	compartments: Compartment[]
}

const store = useBottleStore()
const cellar = ref<Cellar | null>(null)
const shelves = ref<ShelfEntry[]>([])
const slotsByCompartment = ref<Record<number, Slot[]>>({})
const selectedBottleId = ref<number | null>(null)
const creating = ref(false)
const errorMsg = ref('')

const occupiedSlotIds = computed(() => {
	const set = new Set<number>()
	for (const b of store.bottles) {
		if (b.slot_id !== null && b.status === 'in_storage') set.add(b.slot_id)
	}
	return set
})

onMounted(async () => {
	await Promise.all([loadCellar(), store.fetchParked(), store.fetchBottles({ status: 'in_storage' })])
})

async function loadCellar() {
	try {
		const { data } = await axios.get<{ cellar: Cellar; shelves: ShelfEntry[] }>(
			generateUrl('/apps/vinarium/api/v1/cellar'),
		)
		cellar.value = data.cellar
		shelves.value = data.shelves
		await loadSlots()
	} catch (e: any) {
		if (e?.response?.status === 404) {
			cellar.value = null
		} else {
			errorMsg.value = e?.message ?? 'Unbekannter Fehler beim Laden des Kellers'
		}
	}
}

async function loadSlots() {
	const map: Record<number, Slot[]> = {}
	for (const entry of shelves.value) {
		for (const comp of entry.compartments) {
			const { data } = await axios.get<Slot[]>(
				generateUrl(`/apps/vinarium/api/v1/compartments/${comp.id}/slots`),
			)
			map[comp.id] = data
		}
	}
	slotsByCompartment.value = map
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

function slotTitle(slot: Slot): string {
	const where = `Ebene ${slot.level + 1} · ${slot.row === 'front' ? 'Vorne' : 'Hinten'} · Spalte ${slot.column + 1}`
	return occupiedSlotIds.value.has(slot.id) ? `${where} (belegt)` : where
}
</script>

<style scoped>
.shelf-view {
	padding: 2rem;
	max-width: 1200px;
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
	margin-bottom: 1.5rem;
}
.park-list {
	list-style: none;
	padding: 0;
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
}
.park-card {
	padding: 0.4rem 0.75rem;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
	user-select: none;
}
.park-card.selected {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	border-color: var(--color-primary-element);
}
.muted {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}
.shelves {
	display: flex;
	flex-direction: column;
	gap: 2rem;
}
.shelf h3 {
	margin: 0 0 0.5rem 0;
	color: var(--color-main-text);
}
.compartments {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: 1rem;
}
.compartment {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 0.75rem;
}
.compartment h4 {
	margin: 0 0 0.5rem 0;
	font-size: 0.95rem;
	color: var(--color-text-maxcontrast);
}
.grid {
	display: grid;
	grid-template-columns: repeat(7, minmax(0, 1fr));
	gap: 3px;
}
.slot {
	aspect-ratio: 1;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	font-size: 0.7rem;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
}
.slot.occupied {
	background: #444;
	color: white;
	cursor: not-allowed;
}
.slot.pickable:not(.occupied):hover {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}
.slot:disabled {
	cursor: default;
}
.error {
	margin-top: 1rem;
	padding: 0.75rem;
	background: var(--color-error, #c62828);
	color: white;
	border-radius: var(--border-radius);
}
</style>

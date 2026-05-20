<template>
	<div class="wines-view">
		<header class="wines-view__header">
			<h2>{{ t('vinarium', 'Weine') }}</h2>
			<NcButton variant="primary" @click="wizardOpen = true">{{ t('vinarium', '+ Kauf erfassen') }}</NcButton>
		</header>

		<div class="tabs">
			<button
				v-for="tab in tabs"
				:key="tab.key"
				:class="['tab', { active: activeTab === tab.key }]"
				@click="activeTab = tab.key"
			>
				{{ tab.label }} ({{ tab.count }})
			</button>
		</div>

		<section v-if="activeTab === 'producers'" class="tab-panel">
			<div class="tab-panel__actions">
				<NcButton variant="primary" @click="openCreate('producer')">{{ t('vinarium', '+ Weingut') }}</NcButton>
			</div>
			<p v-if="store.producers.length === 0" class="empty">{{ t('vinarium', 'Noch keine Weingüter erfasst.') }}</p>
			<ul v-else class="list">
				<li v-for="p in store.producers" :key="p.id" class="list-item">
					<div class="list-item__main">
						<strong>{{ p.name }}</strong>
						<span v-if="p.region" class="muted"> · {{ p.region }}</span>
						<span v-if="p.country" class="muted"> · {{ p.country }}</span>
					</div>
					<div class="list-item__actions">
						<NcButton @click="editEntity('producer', p.id)">{{ t('vinarium', 'Bearbeiten') }}</NcButton>
						<NcButton variant="tertiary" @click="deleteEntity('producer', p.id)">{{ t('vinarium', 'Löschen') }}</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<section v-else-if="activeTab === 'wines'" class="tab-panel">
			<p v-if="store.wines.length === 0" class="empty">{{ t('vinarium', 'Noch keine Weine erfasst.') }}</p>
			<ul v-else class="list">
				<li v-for="w in store.wines" :key="w.id" class="list-item">
					<div class="list-item__main">
						<strong>{{ w.name }}</strong>
						<span class="muted"> · {{ t('vinarium', WINE_COLOR_LABELS[w.color]) }}</span>
						<span v-if="store.producerById(w.producerId)" class="muted"> · {{ store.producerById(w.producerId)?.name }}</span>
					</div>
					<div class="list-item__actions">
						<NcButton @click="editEntity('wine', w.id)">{{ t('vinarium', 'Bearbeiten') }}</NcButton>
						<NcButton variant="tertiary" @click="deleteEntity('wine', w.id)">{{ t('vinarium', 'Löschen') }}</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<section v-else-if="activeTab === 'vintages'" class="tab-panel">
			<p v-if="store.vintages.length === 0" class="empty">{{ t('vinarium', 'Noch keine Jahrgänge erfasst.') }}</p>
			<ul v-else class="list">
				<li v-for="v in store.vintages" :key="v.id" class="list-item">
					<div class="list-item__main">
						<strong>{{ v.year }}</strong>
						<span v-if="v.alcoholPercent" class="muted"> · {{ v.alcoholPercent }}%</span>
					</div>
					<div class="list-item__actions">
						<NcButton @click="editEntity('vintage', v.id)">{{ t('vinarium', 'Bearbeiten') }}</NcButton>
						<NcButton variant="tertiary" @click="deleteEntity('vintage', v.id)">{{ t('vinarium', 'Löschen') }}</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<section v-else-if="activeTab === 'purchases'" class="tab-panel">
			<p v-if="store.purchases.length === 0" class="empty">{{ t('vinarium', 'Noch keine Käufe erfasst.') }}</p>
			<table v-else class="purchases-table">
				<thead>
					<tr>
						<th>{{ t('vinarium', 'Datum') }}</th>
						<th>{{ t('vinarium', 'Weingut') }}</th>
						<th>{{ t('vinarium', 'Wein') }}</th>
						<th>{{ t('vinarium', 'Jahrgang') }}</th>
						<th>{{ t('vinarium', 'Menge') }}</th>
						<th>{{ t('vinarium', 'Größe') }}</th>
						<th>{{ t('vinarium', 'Preis') }}</th>
						<th>{{ t('vinarium', 'Händler') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="pu in store.purchases" :key="pu.id">
						<td>{{ formatDate(pu.purchased_at) }}</td>
						<td>{{ pu.producer_name }}</td>
						<td>{{ pu.wine_name }}</td>
						<td>{{ pu.year }}</td>
						<td>{{ pu.quantity }}×</td>
						<td>{{ t('vinarium', BOTTLE_SIZE_LABELS[pu.bottle_size_ml as BottleSizeMl] ?? pu.bottle_size_ml + ' ml') }}</td>
						<td>{{ pu.unit_price !== null ? pu.unit_price.toFixed(2) + ' ' + (pu.currency ?? '€') : '—' }}</td>
						<td>{{ pu.vendor ?? '—' }}</td>
					</tr>
				</tbody>
			</table>
		</section>

		<PurchaseWizardModal :open="wizardOpen" @close="wizardOpen = false" @complete="onComplete" />
		<EntityEditModal
			:open="editOpen"
			:type="editType"
			:entity-id="editId"
			@close="closeEdit"
		/>
		<ConfirmDialog
			:open="deleteConfirmOpen"
			:name="deleteConfirmTitle"
			:message="deleteConfirmMessage"
			:confirm-label="t('vinarium', 'Löschen')"
			:destructive="true"
			@close="deleteConfirmOpen = false"
			@confirm="performDelete"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import NcButton from '@nextcloud/vue/components/NcButton'
import PurchaseWizardModal from '@/components/PurchaseWizardModal.vue'
import EntityEditModal from '@/components/EntityEditModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { BOTTLE_SIZE_LABELS, WINE_COLOR_LABELS, type BottleSizeMl } from '@/types/api'
import { useWineStore } from '@/stores/wineStore'

type EntityType = 'producer' | 'wine' | 'vintage'

const store = useWineStore()
const wizardOpen = ref(false)
const activeTab = ref<'producers' | 'wines' | 'vintages' | 'purchases'>('producers')

const editOpen = ref(false)
const editType = ref<EntityType>('producer')
const editId = ref<number | null>(null)

const tabs = computed(() => [
	{ key: 'producers' as const, label: t('vinarium', 'Weingüter'), count: store.producers.length },
	{ key: 'wines' as const, label: t('vinarium', 'Weine'), count: store.wines.length },
	{ key: 'vintages' as const, label: t('vinarium', 'Jahrgänge'), count: store.vintages.length },
	{ key: 'purchases' as const, label: t('vinarium', 'Käufe'), count: store.purchases.length },
])

onMounted(async () => {
	await Promise.all([store.fetchProducers(), store.fetchPurchases()])
	for (const p of store.producers) {
		await store.fetchWinesByProducer(p.id)
	}
	for (const w of store.wines) {
		await store.fetchVintagesByWine(w.id)
	}
})

async function onComplete(_payload: { purchaseId: number; bottleCount: number }) {
	wizardOpen.value = false
	await Promise.all([store.fetchProducers(), store.fetchPurchases()])
	for (const p of store.producers) {
		await store.fetchWinesByProducer(p.id)
	}
	for (const w of store.wines) {
		await store.fetchVintagesByWine(w.id)
	}
}

function formatDate(iso: string): string {
	try {
		return moment(iso).format('DD.MM.YYYY')
	} catch {
		return iso
	}
}

function openCreate(type: EntityType) {
	editType.value = type
	editId.value = null
	editOpen.value = true
}

function editEntity(type: EntityType, id: number) {
	editType.value = type
	editId.value = id
	editOpen.value = true
}

function closeEdit() {
	editOpen.value = false
	editId.value = null
}

const deleteConfirmOpen = ref(false)
const deletePendingType = ref<EntityType>('producer')
const deletePendingId = ref<number | null>(null)

const deleteConfirmTitle = computed(() => {
	const label = deletePendingType.value === 'producer'
		? t('vinarium', 'Weingut')
		: deletePendingType.value === 'wine'
			? t('vinarium', 'Wein')
			: t('vinarium', 'Jahrgang')
	return t('vinarium', '{entity} löschen', { entity: label })
})

const deleteConfirmMessage = computed(() => {
	const label = deletePendingType.value === 'producer'
		? t('vinarium', 'Weingut')
		: deletePendingType.value === 'wine'
			? t('vinarium', 'Wein')
			: t('vinarium', 'Jahrgang')
	return t('vinarium', '{entity} wirklich löschen? Alle zugehörigen Einträge bleiben erhalten bis du sie separat löschst.', { entity: label })
})

function deleteEntity(type: EntityType, id: number) {
	deletePendingType.value = type
	deletePendingId.value = id
	deleteConfirmOpen.value = true
}

async function performDelete() {
	deleteConfirmOpen.value = false
	const id = deletePendingId.value
	if (id === null) return
	const type = deletePendingType.value
	if (type === 'producer') await store.deleteProducer(id)
	else if (type === 'wine') await store.deleteWine(id)
	else await store.deleteVintage(id)
	deletePendingId.value = null
}
</script>

<style scoped>
.wines-view {
	padding: 2rem 2rem 2rem 50px;
	max-width: 900px;
}
.wines-view__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 1.5rem;
}
.tabs {
	display: flex;
	gap: 0.25rem;
	border-bottom: 2px solid var(--color-border);
	margin-bottom: 1rem;
}
.tab {
	padding: 0.5rem 1rem;
	background: none;
	border: none;
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	font-weight: 500;
}
.tab.active {
	border-bottom-color: var(--color-primary-element);
	color: var(--color-main-text);
}
.tab-panel__actions {
	display: flex;
	justify-content: flex-end;
	margin-bottom: 0.75rem;
}
.list {
	list-style: none;
	padding: 0;
}
.list-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0.75rem;
	border-bottom: 1px solid var(--color-border);
	gap: 1rem;
}
.list-item__main {
	flex: 1;
}
.list-item__actions {
	display: flex;
	gap: 0.5rem;
}
.muted {
	color: var(--color-text-maxcontrast);
}
.empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 1rem;
}
.purchases-table {
	width: 100%;
	border-collapse: collapse;
}
.purchases-table th, .purchases-table td {
	text-align: left;
	padding: 0.5rem 0.75rem;
	border-bottom: 1px solid var(--color-border);
}
.purchases-table th {
	background: var(--color-background-hover);
	font-weight: 500;
	font-size: 0.9rem;
}
</style>

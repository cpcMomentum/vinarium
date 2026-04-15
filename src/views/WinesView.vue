<template>
	<div class="wines-view">
		<header class="wines-view__header">
			<h2>Weine</h2>
			<NcButton type="primary" @click="wizardOpen = true">+ Kauf erfassen</NcButton>
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
			<p v-if="store.producers.length === 0" class="empty">Noch keine Weingüter erfasst.</p>
			<ul v-else class="list">
				<li v-for="p in store.producers" :key="p.id" class="list-item">
					<div class="list-item__main">
						<strong>{{ p.name }}</strong>
						<span v-if="p.region" class="muted"> · {{ p.region }}</span>
						<span v-if="p.country" class="muted"> · {{ p.country }}</span>
					</div>
					<div class="list-item__actions">
						<NcButton @click="editEntity('producer', p.id)">Bearbeiten</NcButton>
						<NcButton type="tertiary" @click="deleteEntity('producer', p.id)">Löschen</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<section v-else-if="activeTab === 'wines'" class="tab-panel">
			<p v-if="store.wines.length === 0" class="empty">Noch keine Weine erfasst.</p>
			<ul v-else class="list">
				<li v-for="w in store.wines" :key="w.id" class="list-item">
					<div class="list-item__main">
						<strong>{{ w.name }}</strong>
						<span class="muted"> · {{ WINE_COLOR_LABELS[w.color] }}</span>
						<span v-if="store.producerById(w.producerId)" class="muted"> · {{ store.producerById(w.producerId)?.name }}</span>
					</div>
					<div class="list-item__actions">
						<NcButton @click="editEntity('wine', w.id)">Bearbeiten</NcButton>
						<NcButton type="tertiary" @click="deleteEntity('wine', w.id)">Löschen</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<section v-else-if="activeTab === 'vintages'" class="tab-panel">
			<p v-if="store.vintages.length === 0" class="empty">Noch keine Jahrgänge erfasst.</p>
			<ul v-else class="list">
				<li v-for="v in store.vintages" :key="v.id" class="list-item">
					<div class="list-item__main">
						<strong>{{ v.year }}</strong>
						<span v-if="v.alcoholPercent" class="muted"> · {{ v.alcoholPercent }}%</span>
					</div>
					<div class="list-item__actions">
						<NcButton @click="editEntity('vintage', v.id)">Bearbeiten</NcButton>
						<NcButton type="tertiary" @click="deleteEntity('vintage', v.id)">Löschen</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<PurchaseWizardModal :open="wizardOpen" @close="wizardOpen = false" @complete="onComplete" />
		<EntityEditModal
			:open="editOpen"
			:type="editType"
			:entity-id="editId"
			@close="closeEdit"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import PurchaseWizardModal from '@/components/PurchaseWizardModal.vue'
import EntityEditModal from '@/components/EntityEditModal.vue'
import { WINE_COLOR_LABELS } from '@/types/api'
import { useWineStore } from '@/stores/wineStore'

type EntityType = 'producer' | 'wine' | 'vintage'

const store = useWineStore()
const wizardOpen = ref(false)
const activeTab = ref<'producers' | 'wines' | 'vintages'>('producers')

const editOpen = ref(false)
const editType = ref<EntityType>('producer')
const editId = ref<number | null>(null)

const tabs = computed(() => [
	{ key: 'producers' as const, label: 'Weingüter', count: store.producers.length },
	{ key: 'wines' as const, label: 'Weine', count: store.wines.length },
	{ key: 'vintages' as const, label: 'Jahrgänge', count: store.vintages.length },
])

onMounted(async () => {
	await store.fetchProducers()
})

async function onComplete(_payload: { vintageId: number }) {
	wizardOpen.value = false
	await store.fetchProducers()
	for (const p of store.producers) {
		await store.fetchWinesByProducer(p.id)
	}
	for (const w of store.wines) {
		await store.fetchVintagesByWine(w.id)
	}
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

async function deleteEntity(type: EntityType, id: number) {
	const label = type === 'producer' ? 'Weingut' : type === 'wine' ? 'Wein' : 'Jahrgang'
	if (!window.confirm(`${label} wirklich löschen? Alle zugehörigen Einträge bleiben erhalten bis du sie separat löschst.`)) return
	if (type === 'producer') await store.deleteProducer(id)
	else if (type === 'wine') await store.deleteWine(id)
	else await store.deleteVintage(id)
}
</script>

<style scoped>
.wines-view {
	padding: 2rem;
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
</style>

<template>
	<div class="master-data">
		<div v-if="!entityType" class="master-data__tabs">
			<button
				v-for="tab in tabs"
				:key="tab.key"
				:class="['master-data__tab', { 'master-data__tab--active': activeTab === tab.key }]"
				@click="internalTab = tab.key"
			>
				{{ tab.label }} <span class="master-data__count">({{ tab.count }})</span>
			</button>
		</div>

		<!-- Producers -->
		<section v-if="activeTab === 'producers'" class="master-data__panel">
			<div class="master-data__actions">
				<NcButton variant="primary" @click="openCreate('producer')">{{ t('vinarium', '+ Weingut') }}</NcButton>
			</div>
			<p v-if="store.producers.length === 0" class="master-data__empty">{{ t('vinarium', 'Noch keine Weingüter erfasst.') }}</p>
			<ul v-else class="master-data__list">
				<li v-for="p in store.producers" :key="p.id" class="master-data__item">
					<div class="master-data__item-main">
						<strong>{{ p.name }}</strong>
						<span v-if="p.region" class="muted"> · {{ p.region }}</span>
						<span v-if="p.country" class="muted"> · {{ p.country }}</span>
					</div>
					<div class="master-data__item-actions">
						<NcButton @click="editEntity('producer', p.id)">{{ t('vinarium', 'Bearbeiten') }}</NcButton>
						<NcButton variant="tertiary" @click="deleteEntity('producer', p.id)">{{ t('vinarium', 'Löschen') }}</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<!-- Wines -->
		<section v-else-if="activeTab === 'wines'" class="master-data__panel">
			<p v-if="store.wines.length === 0" class="master-data__empty">{{ t('vinarium', 'Noch keine Weine erfasst.') }}</p>
			<ul v-else class="master-data__list">
				<li v-for="w in store.wines" :key="w.id" class="master-data__item">
					<div class="master-data__item-main">
						<strong>{{ w.name }}</strong>
						<span class="muted"> · {{ t('vinarium', WINE_COLOR_LABELS[w.color]) }}</span>
						<span v-if="store.producerById(w.producerId)" class="muted"> · {{ store.producerById(w.producerId)?.name }}</span>
					</div>
					<div class="master-data__item-actions">
						<NcButton @click="editEntity('wine', w.id)">{{ t('vinarium', 'Bearbeiten') }}</NcButton>
						<NcButton variant="tertiary" @click="deleteEntity('wine', w.id)">{{ t('vinarium', 'Löschen') }}</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<!-- Vintages -->
		<section v-else-if="activeTab === 'vintages'" class="master-data__panel">
			<p v-if="store.vintages.length === 0" class="master-data__empty">{{ t('vinarium', 'Noch keine Jahrgänge erfasst.') }}</p>
			<ul v-else class="master-data__list">
				<li v-for="v in store.vintages" :key="v.id" class="master-data__item">
					<div class="master-data__item-main">
						<strong>{{ v.year }}</strong>
						<span v-if="v.alcoholPercent" class="muted"> · {{ v.alcoholPercent }}%</span>
					</div>
					<div class="master-data__item-actions">
						<NcButton @click="editEntity('vintage', v.id)">{{ t('vinarium', 'Bearbeiten') }}</NcButton>
						<NcButton variant="tertiary" @click="deleteEntity('vintage', v.id)">{{ t('vinarium', 'Löschen') }}</NcButton>
					</div>
				</li>
			</ul>
		</section>

		<!-- Purchases -->
		<section v-else-if="activeTab === 'purchases'" class="master-data__panel">
			<p v-if="store.purchases.length === 0" class="master-data__empty">{{ t('vinarium', 'Noch keine Käufe erfasst.') }}</p>
			<table v-else class="master-data__table">
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
						<th></th>
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
						<td class="master-data__row-actions">
							<NcButton @click="editEntity('purchase', pu.id)">{{ t('vinarium', 'Bearbeiten') }}</NcButton>
							<NcButton variant="tertiary" @click="deleteEntity('purchase', pu.id)">{{ t('vinarium', 'Löschen') }}</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

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
		<p v-if="deleteError" class="master-data__error">{{ deleteError }}</p>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { formatDate } from '@/utils/date'
import NcButton from '@nextcloud/vue/components/NcButton'
import EntityEditModal from '@/components/EntityEditModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { BOTTLE_SIZE_LABELS, WINE_COLOR_LABELS, type BottleSizeMl } from '@/types/api'
import { useWineStore } from '@/stores/wineStore'

type EntityType = 'producer' | 'wine' | 'vintage' | 'purchase'
type PanelTab = 'producers' | 'wines' | 'vintages' | 'purchases'

const props = defineProps<{ entityType?: PanelTab }>()

const store = useWineStore()
const internalTab = ref<PanelTab>('producers')
const activeTab = computed<PanelTab>(() => props.entityType ?? internalTab.value)
const deleteError = ref<string | null>(null)

const editOpen = ref(false)
const editType = ref<EntityType>('producer')
const editId = ref<number | null>(null)

const tabs = computed(() => [
	{ key: 'producers' as const, label: t('vinarium', 'Weingüter'), count: store.producers.length },
	{ key: 'wines' as const, label: t('vinarium', 'Weine'), count: store.wines.length },
	{ key: 'vintages' as const, label: t('vinarium', 'Jahrgänge'), count: store.vintages.length },
	{ key: 'purchases' as const, label: t('vinarium', 'Käufe'), count: store.purchases.length },
])

onMounted(() => store.fetchAll())

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

function labelForType(type: EntityType): string {
	if (type === 'producer') return t('vinarium', 'Weingut')
	if (type === 'wine') return t('vinarium', 'Wein')
	if (type === 'vintage') return t('vinarium', 'Jahrgang')
	return t('vinarium', 'Kauf')
}

const deleteConfirmTitle = computed(() => t('vinarium', '{entity} löschen', { entity: labelForType(deletePendingType.value) }))

const deleteConfirmMessage = computed(() => {
	if (deletePendingType.value === 'purchase') {
		return t('vinarium', 'Diesen Kauf wirklich löschen? Funktioniert nur, wenn dem Kauf keine Flaschen mehr zugeordnet sind.')
	}
	return t('vinarium', '{entity} wirklich löschen? Alle zugehörigen Einträge bleiben erhalten bis du sie separat löschst.', { entity: labelForType(deletePendingType.value) })
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
	deleteError.value = null
	try {
		if (type === 'producer') await store.deleteProducer(id)
		else if (type === 'wine') await store.deleteWine(id)
		else if (type === 'vintage') await store.deleteVintage(id)
		else if (type === 'purchase') await store.deletePurchase(id)
		deletePendingId.value = null
	} catch (e: any) {
		deleteError.value = e?.message ?? t('vinarium', 'Löschen fehlgeschlagen')
	}
}
</script>

<style scoped>
.master-data__tabs {
	display: inline-flex;
	background: var(--color-background-dark, #e9eaec);
	border-radius: var(--border-radius-element, 8px);
	padding: 3px;
	margin-bottom: 14px;
}
.master-data__tab {
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	color: #555;
	background: none;
	border: none;
	padding: 6px 14px;
	border-radius: var(--border-radius-element, 8px);
	cursor: pointer;
}
.master-data__tab--active {
	background: #fff;
	color: var(--color-primary-element, #0082c9);
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
}
.master-data__count {
	font-weight: 400;
	color: var(--color-text-maxcontrast);
}
.master-data__tab--active .master-data__count {
	color: var(--color-primary-element, #0082c9);
}

.master-data__actions {
	display: flex;
	justify-content: flex-end;
	margin-bottom: 0.75rem;
}
.master-data__list {
	list-style: none;
	padding: 0;
	margin: 0;
}
.master-data__item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0.75rem;
	border-bottom: 1px solid var(--color-border-light, #e2e3e5);
	gap: 1rem;
}
.master-data__item:last-child {
	border-bottom: none;
}
.master-data__item-main {
	flex: 1;
}
.master-data__item-actions {
	display: flex;
	gap: 0.5rem;
}
.muted {
	color: var(--color-text-maxcontrast);
}
.master-data__empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 1rem;
}
.master-data__table {
	width: 100%;
	border-collapse: collapse;
}
.master-data__table th,
.master-data__table td {
	text-align: left;
	padding: 0.5rem 0.75rem;
	border-bottom: 1px solid var(--color-border-light, #e2e3e5);
}
.master-data__table th {
	background: var(--color-background-hover);
	font-weight: 500;
	font-size: 0.9rem;
}
.master-data__row-actions {
	white-space: nowrap;
	text-align: right;
}
.master-data__row-actions :deep(.button-vue) {
	display: inline-flex;
}
.master-data__error {
	margin: 1rem 0 0;
	padding: 0.5rem 0.75rem;
	background: rgba(198, 40, 40, 0.1);
	border-left: 3px solid #c62828;
	border-radius: var(--border-radius);
	color: #c62828;
	font-size: 0.9rem;
}
</style>

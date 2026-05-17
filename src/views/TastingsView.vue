<template>
	<div class="tastings-view">
		<h2>{{ t('vinarium', 'Verkostungen') }}</h2>
		<p v-if="loading" class="muted">{{ t('vinarium', 'Laden...') }}</p>
		<p v-else-if="tastings.length === 0" class="empty">{{ t('vinarium', 'Noch keine Verkostungen erfasst.') }}</p>
		<table v-else class="tastings-table">
			<thead>
				<tr>
					<th>{{ t('vinarium', 'Datum') }}</th>
					<th>{{ t('vinarium', 'Weingut') }}</th>
					<th>{{ t('vinarium', 'Wein') }}</th>
					<th>{{ t('vinarium', 'Jahrgang') }}</th>
					<th>{{ t('vinarium', 'Bewertung') }}</th>
					<th>{{ t('vinarium', 'Anlass') }}</th>
					<th>{{ t('vinarium', 'Notizen') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="tasting in tastings" :key="tasting.id" @click="openEdit(tasting)">
					<td>{{ formatDate(tasting.tasted_at) }}</td>
					<td class="wrap-cell producer-cell">{{ tasting.producer_name }}</td>
					<td>
						<span class="dot" :style="{ background: cssColorFor(tasting.wine_color) }"></span>
						{{ tasting.wine_name }}
					</td>
					<td>{{ tasting.year }}</td>
					<td>
						<span v-if="tasting.rating !== null" class="rating">{{ Number(tasting.rating).toFixed(1) }}</span>
						<span v-else class="muted">—</span>
					</td>
					<td class="wrap-cell occasion-cell">{{ tasting.occasion ?? '—' }}</td>
					<td class="notes-cell">
						<div class="notes-text">{{ tasting.notes ?? '—' }}</div>
					</td>
				</tr>
			</tbody>
		</table>

		<TastingDialog
			:open="editDialog.open"
			:tasting="editDialog.tasting"
			@close="editDialog.open = false"
			@updated="onUpdated"
		/>
	</div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { listAllTastings, type TastingListItem } from '@/api/tastings'
import TastingDialog from '@/components/TastingDialog.vue'

const tastings = ref<TastingListItem[]>([])
const loading = ref(true)

const editDialog = reactive({
	open: false,
	tasting: null as TastingListItem | null,
})

function openEdit(tasting: TastingListItem) {
	editDialog.tasting = tasting
	editDialog.open = true
}

function onUpdated(updated: TastingListItem) {
	const idx = tastings.value.findIndex(item => item.id === updated.id)
	if (idx !== -1) tastings.value[idx] = updated
}

onMounted(async () => {
	try {
		tastings.value = await listAllTastings()
	} finally {
		loading.value = false
	}
})

function formatDate(iso: string): string {
	try { return moment(iso).format('L') }
	catch { return iso }
}

function cssColorFor(color: string): string {
	const palette: Record<string, string> = {
		red: '#7a1c1c', white: '#e8d57a', rose: '#e8a3b8',
		sparkling: '#fff7c0', dessert: '#c2934e', fortified: '#4a1010',
	}
	return palette[color] ?? '#999'
}
</script>

<style scoped>
.tastings-view { padding: 2rem 2rem 2rem 50px; max-width: 1400px; }
.tastings-table { width: 100%; border-collapse: collapse; }
.tastings-table th, .tastings-table td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border); }
.tastings-table th { background: var(--color-background-hover); font-weight: 500; font-size: 0.9rem; }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 0.25rem; }
.rating { font-weight: 600; color: var(--color-main-text); }
.wrap-cell { white-space: normal; word-break: break-word; }
.producer-cell { min-width: 180px; max-width: 220px; }
.occasion-cell { min-width: 200px; max-width: 300px; }
.notes-cell { min-width: 280px; max-width: 470px; }
.notes-text { white-space: normal; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.tastings-table tbody tr { cursor: pointer; }
.tastings-table tbody tr:hover { background: var(--color-background-hover); }
.muted { color: var(--color-text-maxcontrast); }
.empty { color: var(--color-text-maxcontrast); font-style: italic; padding: 2rem; }
</style>

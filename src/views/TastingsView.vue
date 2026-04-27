<template>
	<div class="tastings-view">
		<h2>Verkostungen</h2>
		<p v-if="loading" class="muted">Laden...</p>
		<p v-else-if="tastings.length === 0" class="empty">Noch keine Verkostungen erfasst.</p>
		<table v-else class="tastings-table">
			<thead>
				<tr>
					<th>Datum</th>
					<th>Weingut</th>
					<th>Wein</th>
					<th>Jahrgang</th>
					<th>Bewertung</th>
					<th>Anlass</th>
					<th>Notizen</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="t in tastings" :key="t.id" @click="toggleExpand(t.id)">
					<td>{{ formatDate(t.tasted_at) }}</td>
					<td class="wrap-cell producer-cell">{{ t.producer_name }}</td>
					<td>
						<span class="dot" :style="{ background: cssColorFor(t.wine_color) }"></span>
						{{ t.wine_name }}
					</td>
					<td>{{ t.year }}</td>
					<td>
						<span v-if="t.rating !== null" class="rating">{{ Number(t.rating).toFixed(1) }}</span>
						<span v-else class="muted">—</span>
					</td>
					<td class="wrap-cell occasion-cell">{{ t.occasion ?? '—' }}</td>
					<td class="notes-cell">
						<div :class="['notes-text', { expanded: expandedIds.has(t.id) }]">{{ t.notes ?? '—' }}</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { listAllTastings, type TastingListItem } from '@/api/tastings'
import type { WineColor } from '@/types/api'

const tastings = ref<TastingListItem[]>([])
const loading = ref(true)
const expandedIds = ref(new Set<number>())

function toggleExpand(id: number) {
	if (expandedIds.value.has(id)) expandedIds.value.delete(id)
	else expandedIds.value.add(id)
}

onMounted(async () => {
	try {
		tastings.value = await listAllTastings()
	} finally {
		loading.value = false
	}
})

function formatDate(iso: string): string {
	try { return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) }
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
.tastings-view { padding: 2rem; max-width: 1400px; }
.tastings-table { width: 100%; border-collapse: collapse; }
.tastings-table th, .tastings-table td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border); }
.tastings-table th { background: var(--color-background-hover); font-weight: 500; font-size: 0.9rem; }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 0.25rem; }
.rating { font-weight: 600; color: var(--color-main-text); }
.wrap-cell { white-space: normal; word-break: break-word; }
.producer-cell { min-width: 180px; max-width: 220px; }
.occasion-cell { min-width: 200px; max-width: 300px; }
.notes-cell { min-width: 280px; max-width: 470px; }
.notes-text { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer; white-space: normal; word-break: break-word; }
.notes-text.expanded { display: block; -webkit-line-clamp: unset; white-space: pre-wrap; }
.tastings-table tbody tr { cursor: pointer; }
.tastings-table tbody tr:hover { background: var(--color-background-hover); }
.muted { color: var(--color-text-maxcontrast); }
.empty { color: var(--color-text-maxcontrast); font-style: italic; padding: 2rem; }
</style>

<template>
	<div class="dashboard">
		<h2>{{ t('vinarium', 'Dashboard') }}</h2>

		<div v-if="stats" class="widgets">
			<div class="widget">
				<div class="widget__value">{{ stats.inStorage }}</div>
				<div class="widget__label">{{ t('vinarium', 'Im Bestand') }}</div>
			</div>
			<div class="widget">
				<div class="widget__value">{{ stats.consumed }}</div>
				<div class="widget__label">{{ t('vinarium', 'Getrunken') }}</div>
			</div>
			<div class="widget">
				<div class="widget__value">{{ stats.parked }}</div>
				<div class="widget__label">{{ t('vinarium', 'In Parkzone') }}</div>
			</div>
			<div class="widget">
				<div class="widget__value">{{ stats.totalBottles }}</div>
				<div class="widget__label">{{ t('vinarium', 'Gesamt') }}</div>
			</div>
		</div>

		<div v-if="stats" class="sections">
			<section v-if="Object.keys(stats.colorDistribution).length > 0" class="section">
				<h3>{{ t('vinarium', 'Farb-Verteilung') }}</h3>
				<div class="color-bars">
					<div v-for="(count, color) in stats.colorDistribution" :key="color" class="color-bar">
						<div class="color-bar__fill" :style="{ background: cssColorFor(color as any), width: barWidth(count) }"></div>
						<span class="color-bar__label">{{ t('vinarium', WINE_COLOR_LABELS[color as WineColor] ?? color) }} ({{ count }})</span>
					</div>
				</div>
			</section>

			<section v-if="stats.drinkSoon.length > 0" class="section">
				<h3>{{ t('vinarium', 'Bald trinken') }}</h3>
				<ul class="drink-soon">
					<li v-for="(d, i) in stats.drinkSoon" :key="i">
						<strong>{{ d.producer_name }}</strong> · {{ d.wine_name }} {{ d.year }}
						— {{ t('vinarium', 'bis {year} ({count}×)', { year: d.drink_until_year, count: d.bottle_count }) }}
					</li>
				</ul>
			</section>

			<section v-if="stats.recentTastings.length > 0" class="section">
				<h3>{{ t('vinarium', 'Letzte Verkostungen') }}</h3>
				<ul class="recent">
					<li v-for="(tasting, i) in stats.recentTastings" :key="i">
						<strong>{{ tasting.wine_name }} {{ tasting.year }}</strong>
						<span v-if="tasting.rating" class="rating">{{ Number(tasting.rating).toFixed(1) }}</span>
						<span class="muted"> · {{ tasting.producer_name }}</span>
					</li>
				</ul>
			</section>
		</div>

		<p v-if="errorMsg" class="error">{{ errorMsg }}</p>

		<div class="export">
			<a :href="csvUrl" class="export-link">{{ t('vinarium', 'CSV-Export herunterladen') }}</a>
		</div>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { fetchStats, exportCsvUrl, type DashboardStats } from '@/api/dashboard'
import { WINE_COLOR_LABELS, type WineColor } from '@/types/api'
import { cssColorFor } from '@/utils/wineColors'

const stats = ref<DashboardStats | null>(null)
const errorMsg = ref('')
const csvUrl = exportCsvUrl()

onMounted(async () => {
	try {
		stats.value = await fetchStats()
	} catch (e: any) {
		errorMsg.value = e?.message ?? JSON.stringify(e)
		console.error('Dashboard stats error:', e)
	}
})

function barWidth(count: number): string {
	if (!stats.value) return '0'
	const max = Math.max(...Object.values(stats.value.colorDistribution), 1)
	return `${Math.round((count / max) * 100)}%`
}
</script>

<style scoped>
.dashboard { padding: 2rem 2rem 2rem 50px; max-width: 900px; }
.widgets { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
.widget { text-align: center; padding: 1.5rem 1rem; background: var(--color-background-hover); border-radius: var(--border-radius); border: 1px solid var(--color-border); }
.widget__value { font-size: 2rem; font-weight: 700; color: var(--color-main-text); }
.widget__label { font-size: 0.85rem; color: var(--color-text-maxcontrast); margin-top: 0.25rem; }
.sections { display: flex; flex-direction: column; gap: 1.5rem; }
.section { padding: 1rem; background: var(--color-background-hover); border-radius: var(--border-radius); border: 1px solid var(--color-border); }
.section h3 { margin: 0 0 0.75rem 0; font-size: 1rem; }
.color-bars { display: flex; flex-direction: column; gap: 0.5rem; }
.color-bar { display: flex; align-items: center; gap: 0.75rem; }
.color-bar__fill { height: 20px; border-radius: 3px; min-width: 4px; }
.color-bar__label { font-size: 0.85rem; white-space: nowrap; }
.drink-soon, .recent { list-style: none; padding: 0; margin: 0; }
.drink-soon li, .recent li { padding: 0.4rem 0; border-bottom: 1px solid var(--color-border); }
.drink-soon li:last-child, .recent li:last-child { border-bottom: none; }
.rating { font-weight: 600; margin-left: 0.5rem; }
.muted { color: var(--color-text-maxcontrast); }
.export { margin-top: 2rem; }
.error { padding: 0.75rem; background: #c62828; color: white; border-radius: var(--border-radius); margin-bottom: 1rem; }
.export-link { display: inline-block; padding: 0.5rem 1rem; background: var(--color-primary-element); color: var(--color-primary-element-text); border-radius: var(--border-radius); text-decoration: none; }
</style>

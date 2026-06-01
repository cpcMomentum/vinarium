<template>
	<div class="dashboard">
		<!-- Toolbar -->
		<div class="dash-toolbar">
			<h2>{{ t('vinarium', 'Dashboard') }}</h2>
			<div class="sp"></div>
			<div class="searchbox" role="search">
				<span class="searchbox__icon" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z"/></svg>
				</span>
				<input
					type="search"
					:placeholder="t('vinarium', 'Weine, Weingüter, Jahrgänge suchen…')"
					disabled
					:title="t('vinarium', 'Suche folgt in einem späteren Update')"
				/>
			</div>
			<NcButton variant="primary" @click="onAddPurchase">
				{{ t('vinarium', '+ Kauf erfassen') }}
			</NcButton>
		</div>

		<p v-if="errorMsg" class="error">{{ errorMsg }}</p>

		<template v-if="stats">
			<!-- Bestand-Hero -->
			<section class="stock" :aria-label="t('vinarium', 'Bestand')">
				<header class="stock__head">
					<div class="stock__label">{{ t('vinarium', 'Im Bestand') }}</div>
					<div class="stock__big">
						{{ stats.inStorage }}
						<small>{{ t('vinarium', 'Flaschen') }}</small>
					</div>
				</header>
				<div class="stock__bar" role="presentation">
					<i
						v-for="seg in categorySegments"
						:key="seg.color"
						:style="{ width: seg.pct + '%', background: cssColorFor(seg.color) }"
						:title="`${WINE_COLOR_LABELS[seg.color]}: ${seg.count}`"
					/>
				</div>
				<div class="stock__cats">
					<div class="stock__cats-list">
						<span v-for="seg in categorySegments" :key="seg.color">
							<span class="dot" :style="{ background: cssColorFor(seg.color) }"></span>
							{{ t('vinarium', WINE_COLOR_LABELS[seg.color]) }}
							<b>{{ seg.count }}</b>
						</span>
					</div>
					<div v-if="stats.shelfCount > 0" class="stock__where">
						{{ n('vinarium', 'verteilt auf {n} Regal', 'verteilt auf {n} Regale', stats.shelfCount, { n: stats.shelfCount }) }}
					</div>
				</div>
			</section>

			<!-- Hero "Bald trinken" — immer sichtbar, Empty-State wenn keine Daten -->
			<section class="hero">
				<header class="hero__head">
					<h3>{{ t('vinarium', 'Bald trinken') }}</h3>
					<span class="hero__sub">— {{ t('vinarium', 'Trinkfenster läuft bald ab') }}</span>
					<div class="sp"></div>
					<a class="hero__all" @click.prevent="goToInventoryDrinkSoon">{{ t('vinarium', 'alle ›') }}</a>
				</header>
				<p v-if="topDrinkSoon.length === 0" class="hero__empty">
					{{ t('vinarium', 'Keine Weine mit demnächst ablaufendem Trinkfenster. Trage „Trinken bis" am Jahrgang ein, damit hier Vorschläge erscheinen.') }}
				</p>
				<div v-else class="hero__cards">
					<article
						v-for="d in topDrinkSoon"
						:key="d.wine_id + '-' + d.year"
						class="hcard"
						@click="goToInventoryDrinkSoon"
					>
						<div class="hcard__label" :class="'hcard__label--' + d.wine_color">
							<span class="hcard__qty">{{ d.bottle_count }}×</span>
							{{ d.producer_name }}
						</div>
						<div class="hcard__body">
							<div class="hcard__name">{{ d.wine_name }} {{ d.year }}</div>
							<div class="hcard__meta">
								<strong :class="{ 'hcard__meta--urgent': d.drink_until_year <= currentYear + 1 }">
									{{ t('vinarium', 'bis {y}', { y: d.drink_until_year }) }}
								</strong>
								<span v-if="d.slot_label"> · {{ d.slot_label }}</span>
							</div>
						</div>
					</article>
				</div>
			</section>

			<!-- 2 Spalten: Letzte Verkostungen | Aktivität -->
			<div class="row-2">
				<section class="dash-card">
					<h3>
						{{ t('vinarium', 'Letzte Verkostungen') }}
						<a class="dash-card__all" @click.prevent="goToTastings">{{ t('vinarium', 'alle ›') }}</a>
					</h3>
					<p v-if="stats.recentTastings.length === 0" class="muted">
						{{ t('vinarium', 'Noch keine Verkostungen erfasst.') }}
					</p>
					<ul v-else class="lst">
						<li v-for="(t_, i) in stats.recentTastings" :key="i">
							<span class="dot" :style="{ background: cssColorFor(t_.wine_color ?? 'red') }"></span>
							<strong>{{ t_.wine_name }} {{ t_.year }}</strong>
							<div class="lst__meta" v-if="t_.rating !== null">
								<span class="rat">
									<span class="rat__val">{{ Number(t_.rating).toFixed(1) }}</span>
									<span class="rat__bar"><i :style="{ width: ratingPct(t_.rating) + '%' }"></i></span>
								</span>
							</div>
						</li>
					</ul>
				</section>

				<section class="dash-card">
					<h3>
						{{ t('vinarium', 'Aktivität') }}
						<a class="dash-card__all" @click.prevent="goToActivity">{{ t('vinarium', 'alle ›') }}</a>
					</h3>
					<p v-if="stats.recentActivity.length === 0" class="muted">
						{{ t('vinarium', 'Noch keine Aktivität.') }}
					</p>
					<ul v-else class="lst-activity">
						<li v-for="(e, i) in stats.recentActivity" :key="i">
							<span class="chip" :class="'chip--' + e.type">{{ chipLabel(e.type) }}</span>
							<span class="lst-activity__text">{{ e.label }}</span>
							<span class="lst-activity__date">{{ formatShortDate(e.date) }}</span>
						</li>
					</ul>
				</section>
			</div>
		</template>

		<!-- PurchaseWizard Modal -->
		<PurchaseWizardModal v-if="wizardOpen" :open="wizardOpen" @close="onWizardClose" />
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { fetchStats, type DashboardStats, type ActivityType } from '@/api/dashboard'
import { WINE_COLOR_LABELS, WINE_COLORS, type WineColor } from '@/types/api'
import { cssColorFor } from '@/utils/wineColors'
import PurchaseWizardModal from '@/components/PurchaseWizardModal.vue'

const stats = ref<DashboardStats | null>(null)
const errorMsg = ref('')
const wizardOpen = ref(false)
const router = useRouter()
const currentYear = new Date().getFullYear()

async function loadStats() {
	try {
		stats.value = await fetchStats()
	} catch (e: any) {
		errorMsg.value = e?.message ?? JSON.stringify(e)
		console.error('Dashboard stats error:', e)
	}
}

onMounted(loadStats)

const categorySegments = computed(() => {
	if (!stats.value) return []
	const dist = stats.value.colorDistribution
	const total = Object.values(dist).reduce((s, c) => s + c, 0) || 1
	return WINE_COLORS
		.map((c) => ({ color: c, count: dist[c] ?? 0 }))
		.filter((s) => s.count > 0)
		.sort((a, b) => b.count - a.count)
		.map((s) => ({ ...s, pct: (s.count / total) * 100 }))
})

const topDrinkSoon = computed(() => stats.value?.drinkSoon.slice(0, 4) ?? [])

function ratingPct(rating: number | null): number {
	if (rating === null) return 0
	return Math.round((rating / 10) * 100)
}

function chipLabel(type: ActivityType): string {
	switch (type) {
		case 'purchase': return t('vinarium', '+ Kauf')
		case 'tasting': return t('vinarium', 'Getrunken')
		case 'gifted': return t('vinarium', 'Verschenkt')
		case 'lost': return t('vinarium', 'Verloren')
	}
}

function formatShortDate(iso: string): string {
	if (!iso) return ''
	const d = new Date(iso)
	if (isNaN(d.getTime())) return iso
	return d.toLocaleDateString(undefined, { day: '2-digit', month: '2-digit' })
}

function onAddPurchase() {
	wizardOpen.value = true
}

async function onWizardClose() {
	wizardOpen.value = false
	await loadStats()
}

function goToInventoryDrinkSoon() {
	router.push({ path: '/inventory' })
}

function goToTastings() {
	router.push({ path: '/tastings' })
}

function goToActivity() {
	// Aktivitätslog-View: noch nicht implementiert (#92) — fallback auf Tastings
	router.push({ path: '/tastings' })
}
</script>

<style scoped>
.dashboard {
	padding: 20px 24px;
	max-width: 1200px;
}

.error {
	padding: 0.75rem;
	background: #c62828;
	color: #fff;
	border-radius: var(--border-radius);
	margin-bottom: 1rem;
}

/* Toolbar */
.dash-toolbar {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-bottom: 22px;
	flex-wrap: wrap;
}
.dash-toolbar h2 {
	font-size: 24px;
	font-weight: 600;
	letter-spacing: -0.01em;
}
.dash-toolbar .sp { flex: 1; }

.searchbox {
	display: flex;
	align-items: center;
	gap: 8px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: var(--border-radius-element, 8px);
	padding: 0 12px;
	height: 40px;
	min-width: 260px;
}
.searchbox__icon { color: var(--color-text-maxcontrast); display: inline-flex; }
.searchbox input {
	flex: 1;
	border: none;
	background: transparent;
	font-size: 14px;
	outline: none;
	color: var(--color-text-maxcontrast);
}
.searchbox input:disabled { cursor: not-allowed; }

/* Bestand-Hero */
.stock {
	background: #fff;
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: 12px;
	padding: 20px 24px;
	margin-bottom: 14px;
}
.stock__head {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-bottom: 14px;
}
.stock__label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.stock .sp { flex: 1; }
.stock__head .sp,
.stock__head + .sp { flex: 1; }
.stock__big {
	font-size: 24px;
	font-weight: 700;
	line-height: 1;
	font-variant-numeric: tabular-nums;
	margin-left: auto;
}
.stock__big small {
	font-size: 14px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	margin-left: 5px;
}
.stock__bar {
	display: flex;
	height: 16px;
	border-radius: 8px;
	background: var(--color-background-dark, #e9eaec);
	overflow: hidden;
	margin-bottom: 14px;
}
.stock__bar > i {
	display: block;
	height: 100%;
}
.stock__bar > i:first-child { border-radius: 8px 0 0 8px; }
.stock__bar > i:last-child { border-radius: 0 8px 8px 0; }
.stock__cats {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 20px;
	font-size: 14.5px;
	color: var(--color-text-maxcontrast);
	flex-wrap: wrap;
}
.stock__cats-list {
	display: flex;
	flex-wrap: wrap;
	gap: 20px;
}
.stock__cats-list span {
	display: inline-flex;
	align-items: center;
	gap: 7px;
}
.stock__cats-list b {
	color: var(--color-main-text);
	font-weight: 600;
	font-variant-numeric: tabular-nums;
}
.stock__where { white-space: nowrap; font-size: 13.5px; }

/* Hero "Bald trinken" — vereinheitlichtes Padding/Spacing mit Bestand-Hero */
.hero {
	background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: 12px;
	padding: 20px 24px;
	margin-bottom: 14px;
}
.hero__head {
	display: flex;
	align-items: baseline;
	gap: 10px;
	margin-bottom: 14px;
	flex-wrap: wrap;
}
.hero__head h3 { font-size: 18px; font-weight: 600; }
.hero__sub { color: var(--color-text-maxcontrast); font-size: 13.5px; }
.hero__head .sp { flex: 1; }
.hero__all {
	color: var(--color-primary-element, #0082c9);
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	text-decoration: none;
}
.hero__empty {
	color: var(--color-text-maxcontrast);
	font-size: 13.5px;
	padding: 6px 0 4px;
	margin: 0;
}
.hero__cards {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 14px;
}
@media (max-width: 900px) {
	.hero__cards { grid-template-columns: repeat(2, 1fr); }
}
.hcard {
	background: #fff;
	border: 1px solid var(--color-border-light, #e2e3e5);
	border-radius: 10px;
	overflow: hidden;
	cursor: pointer;
	transition: transform 0.15s, box-shadow 0.15s;
}
.hcard:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}
.hcard__label {
	height: 78px;
	display: flex;
	align-items: flex-end;
	justify-content: center;
	padding: 10px;
	color: #fff;
	font-weight: 600;
	font-size: 12.5px;
	text-align: center;
	text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
	position: relative;
}
.hcard__label--red { background: linear-gradient(160deg, #9a3b39, #6e2624); }
.hcard__label--white { background: linear-gradient(160deg, #d6c468, #a4943a); }
.hcard__label--rose { background: linear-gradient(160deg, #e0a3a4, #b56e6f); }
.hcard__label--sparkling { background: linear-gradient(160deg, #d4be58, #9a8b3a); }
.hcard__label--dessert { background: linear-gradient(160deg, #c89352, #8e6128); }
.hcard__label--fortified { background: linear-gradient(160deg, #86462f, #532b1f); }
.hcard__qty {
	position: absolute;
	top: 8px;
	right: 10px;
	background: rgba(255, 255, 255, 0.92);
	color: var(--color-main-text);
	font-size: 11px;
	font-weight: 700;
	border-radius: 10px;
	padding: 2px 8px;
	text-shadow: none;
}
.hcard__body { padding: 11px 13px; }
.hcard__name {
	font-weight: 600;
	font-size: 14px;
	line-height: 1.3;
	margin-bottom: 3px;
}
.hcard__meta { font-size: 12px; color: var(--color-text-maxcontrast); }
.hcard__meta--urgent { color: #b03b33; font-weight: 600; }

/* 2-Spalten unten */
.row-2 {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 18px;
}
@media (max-width: 700px) {
	.row-2 { grid-template-columns: 1fr; }
}
.dash-card {
	background: #fff;
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: var(--border-radius, 8px);
	padding: 16px 18px;
}
.dash-card h3 {
	font-size: 15px;
	font-weight: 600;
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 8px;
}
.dash-card__all {
	color: var(--color-primary-element, #0082c9);
	font-size: 12.5px;
	font-weight: 600;
	cursor: pointer;
	text-decoration: none;
}
.muted { color: var(--color-text-maxcontrast); }

/* Listen */
.lst { list-style: none; padding: 0; margin: 0; }
.lst li {
	padding: 10px 0;
	border-top: 1px solid var(--color-border-light, #e2e3e5);
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 14px;
}
.lst li:first-child { border-top: none; }
.lst__meta { margin-left: auto; }

.dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	flex-shrink: 0;
	display: inline-block;
}

.rat {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	font-variant-numeric: tabular-nums;
}
.rat__val {
	font-weight: 700;
	color: var(--color-primary-element, #0082c9);
	font-size: 14px;
	min-width: 30px;
	text-align: right;
}
.rat__bar {
	width: 70px;
	height: 7px;
	background: var(--color-background-dark, #e9eaec);
	border-radius: var(--border-radius-element, 8px);
	overflow: hidden;
}
.rat__bar > i {
	display: block;
	height: 100%;
	background: var(--color-primary-element, #0082c9);
	border-radius: var(--border-radius-element, 8px);
}

/* Aktivität: 3-Spalten-Grid mit fixer Chip-Spalte */
.lst-activity { list-style: none; padding: 0; margin: 0; }
.lst-activity li {
	padding: 10px 0;
	border-top: 1px solid var(--color-border-light, #e2e3e5);
	display: grid;
	grid-template-columns: 90px 1fr auto;
	align-items: center;
	gap: 12px;
	font-size: 14px;
}
.lst-activity li:first-child { border-top: none; }
.lst-activity__text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lst-activity__date {
	color: var(--color-text-maxcontrast);
	font-size: 12.5px;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

.chip {
	font-size: 11.5px;
	font-weight: 600;
	border-radius: var(--border-radius-element, 8px);
	padding: 3px 10px;
	display: inline-flex;
	align-items: center;
	gap: 5px;
	white-space: nowrap;
	justify-self: start;
}
.chip--purchase { background: #eaf5ee; color: #2f7d49; }
.chip--tasting { background: #eeeeee; color: #5a5a5a; }
.chip--gifted { background: #fbf3e6; color: #9a6c25; }
.chip--lost { background: #fbecea; color: #b03b33; }
</style>

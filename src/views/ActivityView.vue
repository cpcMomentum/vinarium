<!--
  - SPDX-FileCopyrightText: 2026 cpcMomentum
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->
<template>
	<div class="activity-view">
		<header class="activity-view__header">
			<h2>{{ t('vinarium', 'Aktivität') }}</h2>
			<span class="activity-view__subtitle">{{ subtitle }}</span>
		</header>

		<section class="filters">
			<label>
				{{ t('vinarium', 'Art') }}
				<select v-model="filterType" class="input" @change="reload">
					<option value="all">{{ t('vinarium', 'alle') }}</option>
					<option v-for="typ in ACTIVITY_TYPES" :key="typ" :value="typ">{{ chipLabel(typ) }}</option>
				</select>
			</label>
			<label>
				{{ t('vinarium', 'Zeitraum') }}
				<select v-model.number="filterDays" class="input" @change="reload">
					<option v-for="opt in rangeOptions" :key="opt.days" :value="opt.days">{{ opt.label }}</option>
				</select>
			</label>
			<button class="reset" @click="resetFilter">{{ t('vinarium', 'Filter zurücksetzen') }}</button>
		</section>

		<p v-if="errorMsg" class="error">{{ errorMsg }}</p>

		<div v-if="events.length > 0" class="activity-card">
			<ul class="lst-activity">
				<li v-for="(e, i) in events" :key="`${e.type}-${e.date}-${i}`">
					<span class="chip" :class="'chip--' + e.type">{{ chipLabel(e.type) }}</span>
					<span class="lst-activity__text">
						<span v-if="e.refs.wine_color" class="dot" :style="{ background: cssColorFor(e.refs.wine_color) }" />
						{{ e.label }}
						<span v-if="e.refs.producer_name" class="muted"> · {{ e.refs.producer_name }}</span>
					</span>
					<span class="lst-activity__date">{{ formatDate(e.date) }}</span>
				</li>
			</ul>
		</div>
		<p v-else-if="!loading" class="empty">{{ t('vinarium', 'Keine Aktivität im gewählten Zeitraum.') }}</p>

		<div v-if="hasMore" class="activity-view__more">
			<NcButton :disabled="loading" @click="loadMore">{{ t('vinarium', 'Mehr laden') }}</NcButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { ACTIVITY_TYPES, fetchActivity, type ActivityEvent, type ActivityType } from '@/api/activity'
import { cssColorFor } from '@/utils/wineColors'
import { formatDate } from '@/utils/date'

const PAGE_SIZE = 50

const events = ref<ActivityEvent[]>([])
const hasMore = ref(false)
const loading = ref(false)
const errorMsg = ref('')

const filterType = ref<ActivityType | 'all'>('all')
const filterDays = ref(90)

// 0 = ohne Begrenzung; der Default deckt das laufende Quartal ab.
const rangeOptions = computed(() => [
	{ days: 30, label: t('vinarium', 'letzte 30 Tage') },
	{ days: 90, label: t('vinarium', 'letzte 90 Tage') },
	{ days: 365, label: t('vinarium', 'letztes Jahr') },
	{ days: 0, label: t('vinarium', 'gesamter Zeitraum') },
])

const subtitle = computed(() =>
	events.value.length === 1
		? t('vinarium', '1 Ereignis')
		: t('vinarium', '{n} Ereignisse', { n: events.value.length }),
)

function chipLabel(type: ActivityType): string {
	switch (type) {
		case 'purchase': return t('vinarium', '+ Kauf')
		case 'tasting': return t('vinarium', 'Getrunken')
		case 'gifted': return t('vinarium', 'Verschenkt')
		case 'lost': return t('vinarium', 'Verloren')
	}
	return type
}

/** Untergrenze des Zeitraums als YYYY-MM-DD; null bei „gesamter Zeitraum". */
function fromDate(): string | undefined {
	if (filterDays.value === 0) return undefined
	const d = new Date()
	d.setDate(d.getDate() - filterDays.value)
	return d.toISOString().slice(0, 10)
}

async function load(offset: number) {
	loading.value = true
	errorMsg.value = ''
	try {
		const stream = await fetchActivity({
			type: filterType.value,
			from: fromDate(),
			limit: PAGE_SIZE,
			offset,
		})
		events.value = offset === 0 ? stream.events : [...events.value, ...stream.events]
		hasMore.value = stream.hasMore
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Aktivität konnte nicht geladen werden')
	} finally {
		loading.value = false
	}
}

function reload() {
	return load(0)
}

function loadMore() {
	return load(events.value.length)
}

function resetFilter() {
	filterType.value = 'all'
	filterDays.value = 90
	return reload()
}

onMounted(reload)
</script>

<style scoped>
.activity-view {
	padding: 20px 24px;
	max-width: 1000px;
}
.activity-view__header {
	display: flex;
	align-items: baseline;
	gap: 12px;
	margin-bottom: 16px;
}
.activity-view__subtitle {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}
.filters {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 12px;
	margin-bottom: 16px;
}
.filters label {
	display: flex;
	flex-direction: column;
	gap: 4px;
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
}
.input {
	padding: 0.4rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-family: inherit;
}
.reset {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	padding: 0.4rem 0;
}
.activity-card {
	background: var(--color-main-background, #fff);
	border: 1px solid var(--color-border, #d2d4d7);
	border-radius: var(--border-radius, 8px);
	overflow: hidden;
}
.lst-activity { list-style: none; padding: 0; margin: 0; }
.lst-activity li {
	display: grid;
	grid-template-columns: 90px 1fr auto;
	align-items: center;
	gap: 12px;
	padding: 10px 16px;
	border-bottom: 1px solid var(--color-border);
}
.lst-activity li:last-child { border-bottom: none; }
.lst-activity__text { min-width: 0; }
.lst-activity__date {
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
	white-space: nowrap;
}
.dot {
	display: inline-block;
	width: 8px;
	height: 8px;
	border-radius: 50%;
	margin-right: 6px;
	vertical-align: middle;
}
.muted { color: var(--color-text-maxcontrast); }
.chip {
	font-size: 11.5px;
	font-weight: 600;
	border-radius: var(--border-radius-element, 8px);
	padding: 3px 10px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	white-space: nowrap;
}
.chip--purchase { background: #eaf5ee; color: #2f7d49; }
.chip--tasting { background: #eeeeee; color: #5a5a5a; }
.chip--gifted { background: #fbf3e6; color: #9a6c25; }
.chip--lost { background: #fbecea; color: #b03b33; }
.empty {
	color: var(--color-text-maxcontrast);
	padding: 24px 0;
}
.error {
	padding: 0.75rem;
	background: var(--color-error, #c62828);
	color: white;
	border-radius: var(--border-radius);
	margin-bottom: 1rem;
}
.activity-view__more {
	display: flex;
	justify-content: center;
	margin-top: 16px;
}
</style>

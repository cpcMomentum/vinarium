<template>
	<aside class="bottle-detail">
		<div v-if="loading" class="bottle-detail__loading">
			<p class="muted">{{ t('vinarium', 'Laden...') }}</p>
		</div>

		<template v-else-if="detail">
			<div class="bottle-detail__header">
				<span class="dot" :style="{ background: cssColorFor(detail.wine_color) }"></span>
				<div>
					<strong class="bottle-detail__wine">{{ detail.wine_name }}</strong>
					<span class="bottle-detail__year muted"> {{ detail.year }}</span>
					<div class="bottle-detail__producer muted">{{ detail.producer_name }}</div>
				</div>
				<button class="bottle-detail__close" @click="$emit('close')">✕</button>
			</div>

			<dl class="bottle-detail__dl">
				<template v-if="detail.appellation">
					<dt>{{ t('vinarium', 'Appellation') }}</dt>
					<dd>{{ detail.appellation }}</dd>
				</template>

				<template v-if="detail.grape_varieties">
					<dt>{{ t('vinarium', 'Rebsorten') }}</dt>
					<dd>{{ detail.grape_varieties }}</dd>
				</template>

				<template v-if="detail.alcohol_percent">
					<dt>{{ t('vinarium', 'Alkohol') }}</dt>
					<dd>{{ detail.alcohol_percent }} %</dd>
				</template>

				<template v-if="detail.drink_from_year || detail.drink_until_year">
					<dt>{{ t('vinarium', 'Trinkfenster') }}</dt>
					<dd>
						<span v-if="detail.drink_from_year">{{ detail.drink_from_year }}</span>
						<span v-if="detail.drink_from_year && detail.drink_until_year"> – </span>
						<span v-if="detail.drink_until_year">{{ detail.drink_until_year }}</span>
					</dd>
				</template>

				<template v-if="detail.external_rating">
					<dt>{{ t('vinarium', 'Externe Bewertung') }}</dt>
					<dd>{{ detail.external_rating }}<span v-if="detail.external_rating_source" class="muted"> ({{ detail.external_rating_source }})</span></dd>
				</template>
			</dl>

			<section class="bottle-detail__section">
				<h4>{{ t('vinarium', 'Kaufdetails') }}</h4>
				<dl class="bottle-detail__dl">
					<dt>{{ t('vinarium', 'Datum') }}</dt>
					<dd>{{ formatDate(detail.purchased_at) }}</dd>

					<template v-if="detail.vendor">
						<dt>{{ t('vinarium', 'Händler') }}</dt>
						<dd>{{ detail.vendor }}</dd>
					</template>

					<template v-if="detail.unit_price !== null">
						<dt>{{ t('vinarium', 'Preis') }}</dt>
						<dd>{{ detail.unit_price!.toFixed(2) }} {{ detail.currency ?? '€' }}</dd>
					</template>

					<dt>{{ t('vinarium', 'Größe') }}</dt>
					<dd>{{ t('vinarium', BOTTLE_SIZE_LABELS[detail.bottle_size_ml as BottleSizeMl] ?? detail.bottle_size_ml + ' ml') }}</dd>
				</dl>
			</section>

			<section v-if="detail.slot_id !== null" class="bottle-detail__section">
				<h4>{{ t('vinarium', 'Aktueller Platz') }}</h4>
				<p class="bottle-detail__slot">
					{{ detail.compartment_label }}
					<span class="muted">
						· {{ t('vinarium', 'Ebene') }} {{ (detail.slot_level ?? 0) + 1 }}
						· {{ detail.slot_row === 'back' ? t('vinarium', 'Hinten') : t('vinarium', 'Vorne') }}
						· {{ t('vinarium', 'Platz') }} {{ (detail.slot_column ?? 0) + 1 }}
					</span>
				</p>
			</section>

			<div class="bottle-detail__actions">
				<NcButton type="primary" @click="$emit('uncork', detail.id)">
					{{ t('vinarium', 'Entkorken') }}
				</NcButton>
			</div>
		</template>

		<div v-else-if="error" class="bottle-detail__hint">
			<p class="muted">{{ error }}</p>
		</div>

		<div v-else class="bottle-detail__hint">
			<p class="muted">{{ t('vinarium', 'Flasche auswählen um Details zu sehen.') }}</p>
		</div>
	</aside>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import NcButton from '@nextcloud/vue/components/NcButton'
import { getBottleDetails, type BottleDetail } from '@/api/bottles'
import { BOTTLE_SIZE_LABELS, type BottleSizeMl, type WineColor } from '@/types/api'

const props = defineProps<{ bottleId: number | null }>()
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'uncork', bottleId: number): void
}>()

const detail = ref<BottleDetail | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

watch(() => props.bottleId, async (id) => {
	if (id === null) { detail.value = null; error.value = null; return }
	loading.value = true
	detail.value = null
	error.value = null
	try {
		detail.value = await getBottleDetails(id)
	} catch (e: any) {
		error.value = e?.message ?? t('vinarium', 'Fehler beim Laden')
	} finally {
		loading.value = false
	}
}, { immediate: true })

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
.bottle-detail {
	width: 300px;
	flex-shrink: 0;
	position: sticky;
	top: 2rem;
	align-self: flex-start;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 1.25rem;
	max-height: calc(100vh - 120px);
	overflow-y: auto;
}
.bottle-detail__loading,
.bottle-detail__hint {
	min-height: 80px;
	display: flex;
	align-items: center;
	justify-content: center;
}
.bottle-detail__header {
	display: flex;
	align-items: flex-start;
	gap: 0.6rem;
	margin-bottom: 1rem;
}
.bottle-detail__wine {
	font-size: 1rem;
	line-height: 1.3;
}
.bottle-detail__year {
	font-size: 0.9rem;
}
.bottle-detail__producer {
	font-size: 0.85rem;
	margin-top: 0.1rem;
}
.bottle-detail__close {
	margin-left: auto;
	background: none;
	border: none;
	cursor: pointer;
	font-size: 1rem;
	color: var(--color-text-maxcontrast);
	padding: 0 2px;
	flex-shrink: 0;
}
.bottle-detail__close:hover { color: var(--color-main-text); }
.bottle-detail__dl {
	display: grid;
	grid-template-columns: auto 1fr;
	gap: 0.2rem 0.75rem;
	font-size: 0.85rem;
	margin: 0 0 0.75rem;
}
.bottle-detail__dl dt {
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}
.bottle-detail__dl dd {
	margin: 0;
	word-break: break-word;
}
.bottle-detail__section {
	border-top: 1px solid var(--color-border);
	padding-top: 0.75rem;
	margin-top: 0.75rem;
}
.bottle-detail__section h4 {
	font-size: 0.75rem;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--color-text-maxcontrast);
	margin: 0 0 0.5rem;
}
.bottle-detail__slot {
	font-size: 0.85rem;
	margin: 0;
}
.bottle-detail__actions {
	margin-top: 1rem;
	border-top: 1px solid var(--color-border);
	padding-top: 1rem;
	display: flex;
	justify-content: flex-end;
}
.dot {
	display: inline-block;
	width: 14px;
	height: 14px;
	border-radius: 50%;
	flex-shrink: 0;
	margin-top: 3px;
}
.muted { color: var(--color-text-maxcontrast); }
</style>

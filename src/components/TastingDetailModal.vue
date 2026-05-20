<template>
	<NcModal v-if="open" :name="detail ? detail.wine_name + ' ' + detail.year : t('vinarium', 'Laden...')" @close="$emit('close')">
		<div v-if="loading" class="detail-modal detail-modal--loading">
			<p class="muted">{{ t('vinarium', 'Laden...') }}</p>
		</div>
		<div v-else-if="error" class="detail-modal detail-modal--loading">
			<p class="error-text">{{ t('vinarium', 'Fehler beim Laden der Verkostung.') }}</p>
		</div>
		<div v-else-if="detail" class="detail-modal">
			<!-- Header -->
			<div class="detail-modal__header">
				<div class="detail-modal__wine">
					<span class="dot" :style="{ background: cssColorFor(detail.wine_color) }"></span>
					<h2>{{ detail.wine_name }} <span class="year">{{ detail.year }}</span></h2>
				</div>
				<div class="detail-modal__meta">
					<span class="producer">{{ detail.producer_name }}</span>
					<span class="sep">·</span>
					<span>{{ t('vinarium', WINE_COLOR_LABELS[detail.wine_color as WineColor] ?? detail.wine_color) }}</span>
					<span class="sep">·</span>
					<span>{{ formatDate(detail.tasted_at) }}</span>
					<template v-if="detail.rating !== null">
						<span class="sep">·</span>
						<span class="rating">⭐ {{ Number(detail.rating).toFixed(1) }}</span>
					</template>
					<template v-if="detail.occasion">
						<span class="sep">·</span>
						<span class="occasion">{{ detail.occasion }}</span>
					</template>
				</div>
			</div>

			<!-- Fotos -->
			<section v-if="detail.photo_file_ids && detail.photo_file_ids.length > 0" class="detail-section">
				<h3 class="detail-section__title">{{ t('vinarium', 'Fotos') }}</h3>
				<div class="photo-strip">
					<img
						v-for="fid in detail.photo_file_ids"
						:key="fid"
						:src="thumbUrl(fid)"
						class="photo-strip__thumb"
						:alt="t('vinarium', 'Foto')"
						@click="openLightbox(fid)"
					/>
				</div>
			</section>

			<!-- Lightbox overlay — teleported to body so NcModal doesn't intercept clicks -->
			<Teleport to="body">
				<div v-if="lightboxFileId !== null" class="lightbox" @click.self="lightboxFileId = null">
					<button class="lightbox__close" @click="lightboxFileId = null">✕</button>
					<img :src="fullUrl(lightboxFileId)" class="lightbox__img" :alt="t('vinarium', 'Foto')" />
				</div>
			</Teleport>

			<!-- Notizen + Begleitung -->
			<section v-if="detail.notes || detail.companions" class="detail-section">
				<div v-if="detail.notes" class="detail-section__notes">{{ detail.notes }}</div>
				<div v-if="detail.companions" class="detail-section__companions">
					<span class="label">{{ t('vinarium', 'Begleitung') }}:</span> {{ detail.companions }}
				</div>
			</section>

			<!-- Kaufdetails -->
			<section class="detail-section">
				<h3 class="detail-section__title">{{ t('vinarium', 'Kaufdetails') }}</h3>
				<div class="detail-section__purchase">
					<span v-if="detail.purchase.purchased_at">{{ formatDate(detail.purchase.purchased_at) }}</span>
					<span v-if="detail.purchase.vendor" class="sep">·</span>
					<span v-if="detail.purchase.vendor">{{ detail.purchase.vendor }}</span>
					<span v-if="detail.purchase.unit_price !== null" class="sep">·</span>
					<span v-if="detail.purchase.unit_price !== null">
						{{ detail.purchase.unit_price.toFixed(2) }} {{ detail.purchase.currency ?? '€' }}
					</span>
					<span class="sep">·</span>
					<span>{{ t('vinarium', BOTTLE_SIZE_LABELS[detail.purchase.bottle_size_ml as BottleSizeMl] ?? detail.purchase.bottle_size_ml + ' ml') }}</span>
				</div>
			</section>

			<!-- Weitere Verkostungen dieses Weins -->
			<section v-if="detail.related_same_wine.length > 0" class="detail-section">
				<h3 class="detail-section__title">{{ t('vinarium', 'Weitere Verkostungen dieses Weins') }}</h3>
				<ul class="related-list">
					<li
						v-for="r in detail.related_same_wine"
						:key="r.id"
						class="related-item"
						@click="navigateTo(r.id)"
					>
						<span class="related-item__date">{{ formatDate(r.tasted_at) }}</span>
						<span v-if="r.year" class="related-item__year muted">· {{ r.year }}</span>
						<span v-if="r.rating !== null" class="related-item__rating">⭐ {{ Number(r.rating).toFixed(1) }}</span>
						<span v-if="r.notes" class="related-item__notes muted">{{ truncate(r.notes) }}</span>
					</li>
				</ul>
			</section>

			<!-- Vom gleichen Weingut -->
			<section v-if="detail.related_same_producer.length > 0" class="detail-section">
				<h3 class="detail-section__title">{{ t('vinarium', 'Vom gleichen Weingut') }}</h3>
				<ul class="related-list">
					<li
						v-for="r in detail.related_same_producer"
						:key="r.id"
						class="related-item"
						@click="navigateTo(r.id)"
					>
						<span class="related-item__wine">{{ r.wine_name }}</span>
						<span v-if="r.year" class="related-item__year muted">{{ r.year }}</span>
						<span class="related-item__date muted">· {{ formatDate(r.tasted_at) }}</span>
						<span v-if="r.rating !== null" class="related-item__rating">⭐ {{ Number(r.rating).toFixed(1) }}</span>
					</li>
				</ul>
			</section>

			<!-- Aktionen -->
			<div class="detail-modal__actions">
				<NcButton @click="$emit('close')">{{ t('vinarium', 'Schließen') }}</NcButton>
				<NcButton variant="secondary" @click="$emit('edit', detail)">{{ t('vinarium', 'Bearbeiten') }}</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { formatDate } from '@/utils/date'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import { getTastingDetails, tastingPhotoThumbnailUrl, tastingPhotoFullUrl, type TastingDetail } from '@/api/tastings'
import { BOTTLE_SIZE_LABELS, WINE_COLOR_LABELS, type BottleSizeMl, type WineColor } from '@/types/api'
import { cssColorFor } from '@/utils/wineColors'

const props = defineProps<{
	open: boolean
	tastingId: number | null
}>()
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'edit', detail: TastingDetail): void
}>()

const detail = ref<TastingDetail | null>(null)
const loading = ref(false)
const error = ref(false)
const lightboxFileId = ref<number | null>(null)

watch([() => props.open, () => props.tastingId], async () => {
	if (!props.open || props.tastingId === null) return
	loading.value = true
	detail.value = null
	error.value = false
	try {
		detail.value = await getTastingDetails(props.tastingId)
	} catch {
		error.value = true
	} finally {
		loading.value = false
	}
}, { immediate: true })

async function navigateTo(id: number) {
	loading.value = true
	detail.value = null
	error.value = false
	try {
		detail.value = await getTastingDetails(id)
	} catch {
		error.value = true
	} finally {
		loading.value = false
	}
}

function thumbUrl(fileId: number): string {
	return tastingPhotoThumbnailUrl(fileId)
}

function fullUrl(fileId: number): string {
	return tastingPhotoFullUrl(fileId)
}

function openLightbox(fileId: number) {
	lightboxFileId.value = fileId
}


function truncate(text: string, max = 60): string {
	return text.length > max ? text.slice(0, max) + '…' : text
}

</script>

<style scoped>
.detail-modal {
	padding: 2rem;
	min-width: 480px;
	max-width: 680px;
}
.detail-modal--loading {
	min-height: 120px;
	display: flex;
	align-items: center;
	justify-content: center;
}
.detail-modal__header {
	margin-bottom: 1.5rem;
}
.detail-modal__wine {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	margin-bottom: 0.4rem;
}
.detail-modal__wine h2 {
	margin: 0;
	font-size: 1.3rem;
}
.year {
	color: var(--color-text-maxcontrast);
	font-weight: 400;
}
.detail-modal__meta {
	display: flex;
	flex-wrap: wrap;
	gap: 0.25rem;
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast);
}
.producer {
	font-weight: 600;
	color: var(--color-main-text);
}
.rating {
	font-weight: 600;
	color: var(--color-main-text);
}
.occasion {
	font-style: italic;
}
.sep {
	color: var(--color-text-maxcontrast);
}
.detail-section {
	margin-bottom: 1.25rem;
	padding-bottom: 1.25rem;
	border-bottom: 1px solid var(--color-border);
}
.detail-section:last-of-type {
	border-bottom: none;
}
.detail-section__title {
	font-size: 0.8rem;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--color-text-maxcontrast);
	margin: 0 0 0.6rem;
}
.detail-section__notes {
	white-space: pre-wrap;
	word-break: break-word;
	margin-bottom: 0.5rem;
}
.detail-section__companions {
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast);
}
.label {
	font-weight: 500;
	color: var(--color-main-text);
}
.detail-section__purchase {
	display: flex;
	flex-wrap: wrap;
	gap: 0.25rem;
	font-size: 0.9rem;
}
.related-list {
	list-style: none;
	padding: 0;
	margin: 0;
}
.related-item {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.4rem;
	padding: 0.5rem 0.6rem;
	border-radius: var(--border-radius);
	cursor: pointer;
	font-size: 0.9rem;
}
.related-item:hover {
	background: var(--color-background-hover);
}
.related-item__wine {
	font-weight: 500;
}
.related-item__rating {
	font-weight: 600;
	color: var(--color-main-text);
}
.related-item__notes {
	font-style: italic;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	max-width: 280px;
}
.dot {
	display: inline-block;
	width: 12px;
	height: 12px;
	border-radius: 50%;
	flex-shrink: 0;
}
.muted {
	color: var(--color-text-maxcontrast);
}
.error-text {
	color: var(--color-error);
}
.detail-modal__actions {
	display: flex;
	justify-content: flex-end;
	gap: 0.5rem;
	margin-top: 1.5rem;
}
.photo-strip {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
}
.photo-strip__thumb {
	width: 80px;
	height: 80px;
	object-fit: cover;
	border-radius: var(--border-radius);
	cursor: pointer;
	border: 2px solid transparent;
}
.photo-strip__thumb:hover {
	border-color: var(--color-primary-element);
}
.lightbox {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.85);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 9999;
}
.lightbox__close {
	position: absolute;
	top: 1rem;
	right: 1rem;
	background: rgba(255,255,255,0.15);
	border: none;
	border-radius: 50%;
	width: 36px;
	height: 36px;
	color: #fff;
	font-size: 1rem;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
}
.lightbox__close:hover { background: rgba(255,255,255,0.3); }
.lightbox__img {
	max-width: 90vw;
	max-height: 90vh;
	object-fit: contain;
	border-radius: var(--border-radius);
}
</style>

<template>
	<NcModal v-if="open" :name="title" @keydown.esc="e => escCloses(e, () => $emit('close'))" @close="$emit('close')">
		<div class="event-dialog">
			<h2>{{ title }}</h2>
			<p class="muted">
				{{ mode === 'gift'
					? t('vinarium', 'Die Flasche wird als „verschenkt“ markiert und der Slot freigegeben.')
					: t('vinarium', 'Die Flasche wird als „verloren“ markiert und der Slot freigegeben.') }}
			</p>

			<fieldset class="fieldset">
				<label v-if="mode === 'gift'" class="field">
					<span>{{ t('vinarium', 'Empfänger *') }}</span>
					<input
						v-model="form.recipient"
						class="input"
						list="gift-recipient-list"
						:placeholder="t('vinarium', 'z. B. Anna')"
					/>
					<datalist id="gift-recipient-list">
						<option v-for="r in recipients" :key="r" :value="r" />
					</datalist>
				</label>
				<label class="field">
					<span>{{ t('vinarium', 'Datum') }}</span>
					<input v-model="form.date" type="date" class="input" />
				</label>
				<label class="field">
					<span>{{ mode === 'gift' ? t('vinarium', 'Anlass') : t('vinarium', 'Grund') }}</span>
					<input
						v-model="form.note"
						class="input"
						:placeholder="mode === 'gift' ? t('vinarium', 'z. B. Geburtstag') : t('vinarium', 'z. B. zerbrochen')"
					/>
				</label>
			</fieldset>

			<p v-if="submitError" class="submit-error">{{ submitError }}</p>

			<div class="actions">
				<NcButton @click="$emit('close')">{{ t('vinarium', 'Abbrechen') }}</NcButton>
				<NcButton variant="primary" :disabled="saving || (mode === 'gift' && !form.recipient.trim())" @click="submit">
					{{ mode === 'gift' ? t('vinarium', 'Verschenken') : t('vinarium', 'Als verloren markieren') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { escCloses } from '@/utils/modalEsc'
import { computed, ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import { giftBottle, loseBottle, fetchGiftRecipients } from '@/api/bottles'

const props = defineProps<{
	open: boolean
	bottleId?: number | null
	mode: 'gift' | 'lost'
}>()

const emit = defineEmits<{
	(e: 'close'): void
	(e: 'done'): void
}>()

const saving = ref(false)
const submitError = ref<string | null>(null)
const recipients = ref<string[]>([])

const form = ref({
	recipient: '',
	date: new Date().toISOString().substring(0, 10),
	note: '',
})

const title = computed(() => props.mode === 'gift'
	? t('vinarium', 'Flasche verschenken')
	: t('vinarium', 'Flasche als verloren markieren'))

watch(() => props.open, async (isOpen) => {
	if (!isOpen) return
	submitError.value = null
	form.value = {
		recipient: '',
		date: new Date().toISOString().substring(0, 10),
		note: '',
	}
	if (props.mode === 'gift') {
		try {
			recipients.value = await fetchGiftRecipients()
		} catch {
			recipients.value = []
		}
	}
})

async function submit() {
	if (!props.bottleId) return
	saving.value = true
	submitError.value = null
	try {
		if (props.mode === 'gift') {
			await giftBottle(props.bottleId, {
				recipient: form.value.recipient.trim(),
				date: form.value.date,
				occasion: form.value.note || undefined,
			})
		} else {
			await loseBottle(props.bottleId, {
				date: form.value.date,
				reason: form.value.note || undefined,
			})
		}
		emit('done')
		emit('close')
	} catch (e: any) {
		submitError.value = e?.message ?? t('vinarium', 'Speichern fehlgeschlagen')
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.event-dialog {
	padding: 2rem;
	min-width: 420px;
}
.event-dialog h2 { margin-bottom: 0.5rem; }
.muted { color: var(--color-text-maxcontrast); font-size: 0.9rem; margin-bottom: 1rem; }
.fieldset { border: 1px solid var(--color-border); border-radius: var(--border-radius); padding: 1rem; }
.field { display: block; margin-bottom: 0.75rem; }
.field:last-child { margin-bottom: 0; }
.field span { display: block; font-size: 0.85rem; color: var(--color-text-maxcontrast); margin-bottom: 0.25rem; }
.input { width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); font-family: inherit; }
.actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; }
.submit-error {
	margin: 1rem 0 0;
	padding: 0.5rem 0.75rem;
	background: rgba(198, 40, 40, 0.1);
	border-left: 3px solid #c62828;
	border-radius: var(--border-radius);
	color: #c62828;
	font-size: 0.9rem;
}
</style>

<template>
	<NcDialog :open="open" :name="t('vinarium', 'Neues Regal anlegen')" @update:open="$emit('close')">
		<div class="wizard">
			<!-- Step 1: Name + Fächer -->
			<div v-if="step === 1" class="wizard__step">
				<h3 class="wizard__title">{{ t('vinarium', 'Regal-Grunddaten') }}</h3>
				<label class="wizard__label">
					{{ t('vinarium', 'Name des Regals') }}
					<input v-model="name" type="text" class="wizard__input" placeholder="z. B. Holzregal Keller" />
				</label>
				<label class="wizard__label">
					{{ t('vinarium', 'Anzahl Fächer (1–20)') }}
					<input v-model.number="compartmentCount" type="number" min="1" max="20" class="wizard__input wizard__input--short" />
				</label>
			</div>

			<!-- Step 2: Ebenen konfigurieren -->
			<div v-else-if="step === 2" class="wizard__step">
				<h3 class="wizard__title">{{ t('vinarium', 'Ebenen konfigurieren') }}</h3>
				<p class="wizard__hint">{{ t('vinarium', 'Diese Konfiguration gilt für alle {n} Fächer.', { n: compartmentCount }) }}</p>
				<div class="levels-config">
					<div class="levels-config__header">
						<span>{{ t('vinarium', 'Ebene') }}</span>
						<span>{{ t('vinarium', 'Vorne') }}</span>
						<span>{{ t('vinarium', 'Hinten (leer = keine)') }}</span>
					</div>
					<div v-for="(level, idx) in levelsConfig" :key="idx" class="levels-config__row">
						<span class="levels-config__label">{{ t('vinarium', 'Ebene {n}', { n: idx + 1 }) }}</span>
						<input v-model.number="level.columnsFront" type="number" min="1" max="30" class="wizard__input wizard__input--short" />
						<input
							:value="level.columnsBack ?? ''"
							type="number"
							min="0"
							max="30"
							class="wizard__input wizard__input--short"
							placeholder="—"
							@input="onBackInput(idx, $event)"
						/>
						<button v-if="levelsConfig.length > 1" class="levels-config__remove" @click="removeLevel(idx)">✕</button>
					</div>
					<button class="wizard__add-level" @click="addLevel">{{ t('vinarium', '+ Ebene hinzufügen') }}</button>
				</div>
			</div>

			<!-- Step 3: Zusammenfassung -->
			<div v-else-if="step === 3" class="wizard__step">
				<h3 class="wizard__title">{{ t('vinarium', 'Zusammenfassung') }}</h3>
				<table class="wizard__summary">
					<tr><td>{{ t('vinarium', 'Name') }}</td><td><strong>{{ name }}</strong></td></tr>
					<tr><td>{{ t('vinarium', 'Fächer') }}</td><td><strong>{{ compartmentCount }}</strong></td></tr>
					<tr><td>{{ t('vinarium', 'Ebenen') }}</td><td><strong>{{ levelsConfig.length }}</strong></td></tr>
					<tr>
						<td>{{ t('vinarium', 'Slots gesamt') }}</td>
						<td><strong>{{ totalSlots }}</strong></td>
					</tr>
				</table>
			</div>

			<p v-if="errorMsg" class="wizard__error">{{ errorMsg }}</p>
		</div>

		<template #actions>
			<NcButton v-if="step > 1" type="secondary" :disabled="saving" @click="step--">{{ t('vinarium', 'Zurück') }}</NcButton>
			<NcButton v-if="step < 3" type="primary" :disabled="!canProceed" @click="step++">{{ t('vinarium', 'Weiter') }}</NcButton>
			<NcButton v-else type="primary" :disabled="saving" @click="submit">
				{{ saving ? t('vinarium', 'Wird angelegt…') : t('vinarium', 'Regal anlegen') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import { createShelf, type LevelConfig } from '@/api/cellar'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; created: [] }>()

const step = ref(1)
const name = ref('')
const compartmentCount = ref(4)
const levelsConfig = ref<LevelConfig[]>([
	{ columnsFront: 6, columnsBack: 7 },
	{ columnsFront: 6, columnsBack: 7 },
	{ columnsFront: 6, columnsBack: 7 },
])
const saving = ref(false)
const errorMsg = ref('')

watch(() => props.open, (v) => {
	if (v) reset()
})

function reset() {
	step.value = 1
	name.value = ''
	compartmentCount.value = 4
	levelsConfig.value = [
		{ columnsFront: 6, columnsBack: 7 },
		{ columnsFront: 6, columnsBack: 7 },
		{ columnsFront: 6, columnsBack: 7 },
	]
	errorMsg.value = ''
	saving.value = false
}

const canProceed = computed(() => {
	if (step.value === 1) return name.value.trim() !== '' && compartmentCount.value >= 1
	if (step.value === 2) return levelsConfig.value.every(l => l.columnsFront >= 1)
	return true
})

const totalSlots = computed(() =>
	compartmentCount.value * levelsConfig.value.reduce((sum, l) => sum + l.columnsFront + (l.columnsBack ?? 0), 0)
)

function addLevel() {
	const last = levelsConfig.value[levelsConfig.value.length - 1]
	levelsConfig.value.push({ columnsFront: last?.columnsFront ?? 6, columnsBack: last?.columnsBack ?? 7 })
}

function removeLevel(idx: number) {
	levelsConfig.value.splice(idx, 1)
}

function onBackInput(idx: number, event: Event) {
	const val = (event.target as HTMLInputElement).value
	levelsConfig.value[idx].columnsBack = val === '' ? null : Math.max(0, parseInt(val, 10))
}

async function submit() {
	saving.value = true
	errorMsg.value = ''
	try {
		await createShelf(name.value.trim(), compartmentCount.value, levelsConfig.value)
		emit('created')
		emit('close')
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Fehler beim Anlegen')
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
:deep(.dialog__content) {
	padding-top: 1.25rem;
	padding-bottom: 1.25rem;
}
:deep(.dialog__actions) {
	padding-bottom: 0.75rem;
}
.wizard {
	padding: 0;
}
.wizard__title {
	font-size: 1rem;
	margin: 0 0 1.25rem 0;
}
.wizard__hint {
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
	margin: 0 0 1.25rem 0;
}
.wizard__label {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
	margin-bottom: 1rem;
	font-size: 0.9rem;
}
.wizard__input {
	padding: 0.4rem 0.6rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 0.9rem;
}
.wizard__input--short { width: 80px; }
.levels-config__header {
	display: grid;
	grid-template-columns: 55px 1fr 1fr 36px;
	gap: 0.5rem;
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
	margin-bottom: 0.5rem;
}
.levels-config__row {
	display: grid;
	grid-template-columns: 55px 1fr 1fr 36px;
	gap: 0.5rem;
	align-items: center;
	margin-bottom: 0.4rem;
}
.levels-config__label {
	font-size: 0.85rem;
}
.levels-config__remove {
	background: none;
	border: none;
	cursor: pointer;
	color: #c0392b;
	font-size: 1rem;
	font-weight: bold;
	padding: 0 4px;
	line-height: 1;
}
.wizard__add-level {
	margin-top: 0.5rem;
	background: none;
	border: 1px dashed var(--color-border);
	border-radius: var(--border-radius);
	padding: 0.3rem 0.75rem;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}
.wizard__summary {
	width: 100%;
	border-collapse: collapse;
}
.wizard__summary td {
	padding: 0.4rem 0.5rem;
	border-bottom: 1px solid var(--color-border);
	font-size: 0.9rem;
}
.wizard__error {
	color: var(--color-error, #c62828);
	margin-top: 0.75rem;
	font-size: 0.85rem;
}
</style>

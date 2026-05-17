<template>
	<NcDialog :open="open" :name="t('vinarium', '{label} konfigurieren', { label: compartment.compartment.label })" @update:open="$emit('close')">
		<div class="config-dialog">
			<p class="config-dialog__hint">{{ t('vinarium', 'Ebenen und Plätze anpassen. Flaschen die keinen Platz mehr haben kommen in die Parkzone.') }}</p>

			<div class="levels-config">
				<div class="levels-config__header">
					<span>{{ t('vinarium', 'Ebene') }}</span>
					<span>{{ t('vinarium', 'Vorne') }}</span>
					<span>{{ t('vinarium', 'Hinten (leer = keine)') }}</span>
					<span></span>
				</div>
				<div v-for="(level, idx) in editLevels" :key="idx" class="levels-config__row">
					<span class="levels-config__label">{{ t('vinarium', 'Ebene {n}', { n: idx + 1 }) }}</span>
					<input v-model.number="level.columnsFront" type="number" min="1" max="30" class="config-input" />
					<input
						:value="level.columnsBack ?? ''"
						type="number"
						min="0"
						max="30"
						class="config-input"
						placeholder="—"
						@input="onBackInput(idx, $event)"
					/>
					<button v-if="editLevels.length > 1" class="levels-config__remove" @click="removeLevel(idx)">✕</button>
				</div>
				<button class="config-add-level" @click="addLevel">{{ t('vinarium', '+ Ebene hinzufügen') }}</button>
			</div>

			<p v-if="bottlesAtRisk" class="config-dialog__warning">
				{{ t('vinarium', '⚠ Flaschen die keinen Platz mehr finden landen in der Parkzone.') }}
			</p>
			<p v-if="errorMsg" class="config-dialog__error">{{ errorMsg }}</p>
		</div>

		<template #actions>
			<NcButton type="secondary" @click="$emit('close')">{{ t('vinarium', 'Abbrechen') }}</NcButton>
			<NcButton type="primary" :disabled="saving || !isValid" @click="submit">
				{{ saving ? t('vinarium', 'Wird gespeichert…') : t('vinarium', 'Speichern') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import { reconfigureCompartment, type LevelConfig } from '@/api/cellar'
import type { CompartmentWithLevels } from '@/types/api'

const props = defineProps<{ open: boolean; compartment: CompartmentWithLevels }>()
const emit = defineEmits<{ close: []; reconfigured: [movedCount: number] }>()

const editLevels = ref<LevelConfig[]>([])
const saving = ref(false)
const errorMsg = ref('')

watch(() => props.open, (v) => { if (v) initFromProps() }, { immediate: true })

function initFromProps() {
	editLevels.value = props.compartment.levels.map(l => ({
		columnsFront: l.columnsFront,
		columnsBack: l.columnsBack,
	}))
	errorMsg.value = ''
	saving.value = false
}

const isValid = computed(() => editLevels.value.length > 0 && editLevels.value.every(l => l.columnsFront >= 1))

const bottlesAtRisk = computed(() => {
	const original = props.compartment.levels
	if (editLevels.value.length < original.length) return true
	for (let i = 0; i < Math.min(editLevels.value.length, original.length); i++) {
		if (editLevels.value[i].columnsFront < original[i].columnsFront) return true
		if ((editLevels.value[i].columnsBack ?? 0) < (original[i].columnsBack ?? 0)) return true
	}
	return false
})

function addLevel() {
	const last = editLevels.value[editLevels.value.length - 1]
	editLevels.value.push({ columnsFront: last?.columnsFront ?? 6, columnsBack: last?.columnsBack ?? 7 })
}

function removeLevel(idx: number) {
	editLevels.value.splice(idx, 1)
}

function onBackInput(idx: number, event: Event) {
	const val = (event.target as HTMLInputElement).value
	editLevels.value[idx].columnsBack = val === '' ? null : Math.max(0, parseInt(val, 10))
}

async function submit() {
	saving.value = true
	errorMsg.value = ''
	try {
		const { movedToParkzone } = await reconfigureCompartment(props.compartment.compartment.id, editLevels.value)
		emit('reconfigured', movedToParkzone)
		emit('close')
	} catch (e: any) {
		errorMsg.value = e?.message ?? t('vinarium', 'Fehler beim Speichern')
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
.config-dialog {
	padding: 0;
}
.config-dialog__hint {
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
	margin: 0 0 1.25rem 0;
}
.config-dialog__warning {
	margin-top: 1rem;
	padding: 0.5rem 0.75rem;
	background: var(--color-warning-text, #7a4a00);
	color: white;
	border-radius: var(--border-radius);
	font-size: 0.85rem;
}
.config-dialog__error {
	color: var(--color-error, #c62828);
	margin-top: 0.75rem;
	font-size: 0.85rem;
}
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
.levels-config__label { font-size: 0.85rem; }
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
.config-input {
	padding: 0.35rem 0.5rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 0.85rem;
	width: 100%;
}
.config-add-level {
	margin-top: 0.5rem;
	background: none;
	border: 1px dashed var(--color-border);
	border-radius: var(--border-radius);
	padding: 0.3rem 0.75rem;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}
</style>

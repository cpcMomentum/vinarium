<template>
	<NcDialog :open="open" :name="name" @update:open="onUpdateOpen">
		<div class="confirm-dialog">
			<p class="confirm-dialog__message">{{ message }}</p>
		</div>

		<template #actions>
			<NcButton variant="secondary" @click="$emit('close')">{{ cancelLabel || t('vinarium', 'Abbrechen') }}</NcButton>
			<NcButton
				:variant="destructive ? 'error' : 'primary'"
				:class="{ 'confirm-dialog__btn--destructive': destructive }"
				@click="$emit('confirm')"
			>
				{{ confirmLabel || t('vinarium', 'Bestätigen') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'

defineProps<{
	open: boolean
	name: string
	message: string
	confirmLabel?: string
	cancelLabel?: string
	destructive?: boolean
}>()

const emit = defineEmits<{
	close: []
	confirm: []
}>()

function onUpdateOpen(value: boolean) {
	if (!value) emit('close')
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
.confirm-dialog {
	padding: 0;
}
.confirm-dialog__message {
	margin: 0;
	font-size: 0.95rem;
	line-height: 1.5;
	white-space: pre-wrap;
	word-break: break-word;
}


</style>

<!-- Globaler Style: NcDialog teleportiert in body, scoped CSS greift dort nicht. -->
<style>
.button-vue.confirm-dialog__btn--destructive {
	background-color: #c62828 !important;
	border-color: #c62828 !important;
	color: #fff !important;
}
.button-vue.confirm-dialog__btn--destructive:hover:not([disabled]),
.button-vue.confirm-dialog__btn--destructive:focus:not([disabled]),
.button-vue.confirm-dialog__btn--destructive:active {
	background-color: #b71c1c !important;
	border-color: #b71c1c !important;
	color: #fff !important;
}
.button-vue.confirm-dialog__btn--destructive .button-vue__text {
	color: #fff !important;
}
</style>

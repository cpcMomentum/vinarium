<template>
	<NcContent app-name="vinarium">
		<NcAppContent>
			<div class="vinarium-view">
				<h2>{{ t('vinarium', 'Vinarium') }}</h2>
				<p>{{ t('vinarium', 'Willkommen in deinem Weinkeller.') }}</p>
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppContent,
	},
}
</script>

<style scoped>
.vinarium-view {
	padding: 20px;
	padding-left: 50px;
}
</style>

<template>
	<NcContent app-name="vinarium">
		<NcAppNavigation aria-label="Vinarium">
			<NcAppNavigationItem
				name="Dashboard"
				:to="{ name: 'dashboard' }"
				exact
			>
				<template #icon>
					<ViewDashboard :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				name="Weine"
				:to="{ name: 'wines' }"
			>
				<template #icon>
					<GlassWine :size="20" />
				</template>
			</NcAppNavigationItem>
		</NcAppNavigation>

		<NcAppContent>
			<router-view />
		</NcAppContent>
	</NcContent>
</template>

<script setup lang="ts">
import { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent } from '@nextcloud/vue'
import GlassWine from 'vue-material-design-icons/GlassWine.vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
</script>

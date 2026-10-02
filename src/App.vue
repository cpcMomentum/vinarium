<template>
	<NcContent appName="vinarium">
		<NcAppNavigation aria-label="Vinarium">
			<NcAppNavigationItem
				:name="t('vinarium', 'Dashboard')"
				:to="{ name: 'dashboard' }"
			>
				<template #icon>
					<ViewDashboard :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('vinarium', 'Bestand')"
				:to="{ name: 'inventory' }"
			>
				<template #icon>
					<FormatListBulleted :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('vinarium', 'Regal')"
				:to="{ name: 'shelf' }"
			>
				<template #icon>
					<Grid :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('vinarium', 'Verkostungen')"
				:to="{ name: 'tastings' }"
			>
				<template #icon>
					<StarOutline :size="20" />
				</template>
			</NcAppNavigationItem>

			<template #footer>
				<!-- Dauerhafter Zugang zu allen bisherigen Neuerungen (#298); kein Router-Ziel. -->
				<NcAppNavigationItem
					:name="t('vinarium', 'Neuerungen')"
					@click="openWhatsNew"
				>
					<template #icon>
						<BullhornOutline :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<router-view />
		</NcAppContent>

		<!-- „Was ist neu?"-Fenster (#284) -->
		<WhatsNewDialog ref="whatsNew" />
	</NcContent>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent } from '@nextcloud/vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import FormatListBulleted from 'vue-material-design-icons/FormatListBulleted.vue'
import Grid from 'vue-material-design-icons/Grid.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import BullhornOutline from 'vue-material-design-icons/BullhornOutline.vue'
import WhatsNewDialog from '@/components/WhatsNewDialog.vue'

const whatsNew = ref<InstanceType<typeof WhatsNewDialog> | null>(null)

function openWhatsNew(): void {
	whatsNew.value?.openArchive()
}
</script>

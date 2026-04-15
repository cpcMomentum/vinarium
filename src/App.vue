<template>
  <NcContent app-name="vinarium">
    <NcAppNavigation aria-label="Vinarium">
      <NcAppNavigationItem
        name="Dashboard"
        :active="true"
      >
        <template #icon>
          <GlassWine :size="20" />
        </template>
      </NcAppNavigationItem>
    </NcAppNavigation>

    <NcAppContent>
      <div class="welcome">
        <h2>Willkommen in Vinarium</h2>
        <p>Dein privater Weinkeller läuft.</p>
        <p class="hint">
          Phase 0 abgeschlossen &mdash; Stammdaten, Bestand und Lagerplätze folgen in den nächsten Phasen.
        </p>
      </div>
    </NcAppContent>
  </NcContent>
</template>

<script setup>
import { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent } from '@nextcloud/vue'
import GlassWine from 'vue-material-design-icons/GlassWine.vue'
</script>

<style scoped>
.welcome {
  padding: 2rem;
  max-width: 640px;
}

.welcome h2 {
  margin-bottom: 1rem;
}

.welcome .hint {
  color: var(--color-text-maxcontrast);
  margin-top: 1.5rem;
  font-size: 0.9rem;
}
</style>

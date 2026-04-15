<template>
	<div class="welcome">
		<h2>Willkommen in Vinarium</h2>
		<p>Dein privater Weinkeller läuft.</p>
		<p class="hint">
			Phase 0-2 abgeschlossen — Stammdaten für Weingüter, Weine und Jahrgänge sind bereit.
		</p>
	</div>
</template>

<script setup lang="ts">
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

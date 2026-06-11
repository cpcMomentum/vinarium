/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'
import DashboardView from '@/views/DashboardView.vue'
import InventoryView from '@/views/InventoryView.vue'
import SimpleShelfView from '@/views/SimpleShelfView.vue'
import TastingsView from '@/views/TastingsView.vue'

const routes: RouteRecordRaw[] = [
	{ path: '/', name: 'dashboard', component: DashboardView },
	{ path: '/inventory', name: 'inventory', component: InventoryView },
	// Old /wines route folded into Inventory's Master Data tab (#95)
	{ path: '/wines', redirect: { path: '/inventory', query: { tab: 'stammdaten' } } },
	{ path: '/shelf', name: 'shelf', component: SimpleShelfView },
	{ path: '/tastings', name: 'tastings', component: TastingsView },
]

export const router = createRouter({
	history: createWebHashHistory(),
	routes,
})

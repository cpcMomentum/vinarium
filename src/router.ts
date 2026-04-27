/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'
import DashboardView from '@/views/DashboardView.vue'
import WinesView from '@/views/WinesView.vue'
import InventoryView from '@/views/InventoryView.vue'
import SimpleShelfView from '@/views/SimpleShelfView.vue'

const routes: RouteRecordRaw[] = [
	{ path: '/', name: 'dashboard', component: DashboardView },
	{ path: '/wines', name: 'wines', component: WinesView },
	{ path: '/inventory', name: 'inventory', component: InventoryView },
	{ path: '/shelf', name: 'shelf', component: SimpleShelfView },
]

export const router = createRouter({
	history: createWebHashHistory(),
	routes,
})

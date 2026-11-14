// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-only OR AGPL-3.0-or-later

/// <reference types="@nextcloud/typings" />

import VueShowdown from 'vue-showdown';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';

import helpers from './js/helper';
import setupLogging from './js/logging';
import { setApp as setAppInApiInterface } from 'cookbook/js/api-interface';
import { useLegacyStore } from './store';

import PublicApp from './components/PublicApp.vue';

const mountPoint = document.getElementById('cookbook-public');
const token = mountPoint?.dataset.token ?? '';

const app = createApp(PublicApp, { token });

const router = createRouter({
	history: createMemoryHistory(),
	routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }],
});
app.use(router);
helpers.useRouter(router);

window.escapeHTML = helpers.escapeHTML;

app.config.globalProperties.$window = window;
app.config.globalProperties.OC = window.OC;
app.config.globalProperties.t = window.t;
app.config.globalProperties.n = window.n;

app.use(VueShowdown, { flavor: 'vanilla' });

setupLogging(app);
setAppInApiInterface(app);

app.use(createPinia());

useLegacyStore().setConfig({
	config: {
		visibleInfoBlocks: {
			'preparation-time': true,
			'cooking-time': true,
			'total-time': true,
			'nutrition-information': true,
			tools: true,
		},
	},
});

if (mountPoint) {
	app.mount(mountPoint);
}

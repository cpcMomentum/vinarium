import Vue from 'vue'
import App from './App.vue'
import { translate, translatePlural } from '@nextcloud/l10n'

// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('vinarium', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

new Vue({
	render: h => h(App),
}).$mount('.app-vinarium')

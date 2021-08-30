import Vue from 'vue'
import 'prismjs/themes/prism.css'
import {t} from '../base/filters/craft'
import store from './store'
import AddressField from './components/address/AddressField'

Vue.config.productionTip = false
if (process.env.NODE_ENV === 'development') {
    Vue.config.devtools = true
}
Vue.filter('t', t)

window.AddressField = new Vue({
    render: h => h(AddressField),
    store
}).$mount('#address-field')

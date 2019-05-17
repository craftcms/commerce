import Vue from 'vue'
import App from './OrderDetailsApp'
import {currency} from './filters/currency'

Vue.config.productionTip = false

Vue.filter('currency', currency)

new Vue({
  render: h => h(App),
}).$mount('#order-details-app')

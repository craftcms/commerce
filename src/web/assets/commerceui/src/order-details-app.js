import Vue from 'vue'
import App from './OrderDetailsApp'

Vue.config.productionTip = false

new Vue({
  render: h => h(App),
}).$mount('#order-details-app')

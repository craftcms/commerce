import Vue from 'vue'
import App from './OrderMeta'

Vue.config.productionTip = false

new Vue({
  render: h => h(App),
}).$mount('#order-meta-app')

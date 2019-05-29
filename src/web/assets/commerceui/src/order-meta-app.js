import Vue from 'vue'
import App from './OrderMetaApp'

Vue.config.productionTip = false

new Vue({
  render: h => h(App),

  data() {
    return {
      purchasables: []
    }
  }
}).$mount('#order-meta-app')

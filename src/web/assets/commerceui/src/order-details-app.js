import Vue from 'vue'
import App from './OrderDetailsApp'
// import 'prismjs'
import 'prismjs/themes/prism.css'

Vue.config.productionTip = false

new Vue({
  render: h => h(App),
}).$mount('#order-details-app')

import Vue from 'vue'
import App from './OrderDetailsApp'
// import 'prismjs'
import 'prismjs/themes/prism.css'

Vue.config.productionTip = false

new Vue({
  render: h => h(App),

  data() {
    return {
      purchasables: []
    }
  },

  computed: {
    edition() {
      return window.orderEdit.edition
    },
    maxLineItems() {
      if (this.edition === 'lite') {
        return 1
      }

      return null
    },
  }
}).$mount('#order-details-app')

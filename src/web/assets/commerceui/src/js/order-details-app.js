import Vue from 'vue'
import App from './OrderDetailsApp'
// import 'prismjs'
import 'prismjs/themes/prism.css'

Vue.config.productionTip = false

new Vue({
  render: h => h(App),

  data() {
    return {
      draft: null,
      originalDraft: null,
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

    orderId() {
      return window.orderEdit.orderId
    },

    canAddLineItem() {
      if (!this.maxLineItems) {
        return true
      }

      if (this.draft.order.lineItems.length < this.maxLineItems) {
        return true
      }

      return false
    }
  },

  methods: {
    displayError(msg) {
      Craft.cp.displayError(msg)
    },
    displayNotice(msg) {
      Craft.cp.displayNotice(msg)
    },
  }
}).$mount('#order-details-app')

/* global Craft */

import Vue from 'vue'
import App from './OrderDetails'
// import 'prismjs'
import 'prismjs/themes/prism.css'
import OrderMeta from './OrderMeta'
import store from './store'

Vue.config.productionTip = false


// Order details
// =========================================================================

window.OrderDetailsApp = new Vue({
    render: h => h(App),
    store,

    mounted() {
        this.$store.dispatch('getOrder')
        this.$store.dispatch('getPurchasables')
    }
}).$mount('#order-details-app')


// Order meta
// =========================================================================

window.OrderMetaApp = new Vue({
    render: h => h(OrderMeta),
    store,
}).$mount('#order-meta-app')

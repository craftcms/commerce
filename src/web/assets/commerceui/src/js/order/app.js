import Vue from 'vue'
import App from './apps/OrderDetails'
import 'prismjs/themes/prism.css'
import OrderMeta from './apps/OrderMeta'
import OrderNotices from './apps/OrderNotices'
import OrderActions from './apps/OrderActions'
import OrderCustomer from './apps/OrderCustomer'
import OrderErrors from './apps/OrderErrors'
import OrderSecondaryActions from './apps/OrderSecondaryActions'
import store from './store'
import {t} from '../base/filters/craft'
import BtnLink from '../base/components/BtnLink'
import OrderBlock from './components/OrderBlock'
import OrderTitle from './components/OrderTitle'


Vue.config.productionTip = false
if (process.env.NODE_ENV === 'development') {
    Vue.config.devtools = true
}
Vue.filter('t', t)
Vue.component('btn-link', BtnLink)
Vue.component('order-block', OrderBlock)
Vue.component('order-title', OrderTitle)


// Order actions
// =========================================================================

window.OrderActionsApp = new Vue({
    render: h => h(OrderActions),
    store,
}).$mount('#order-actions-app')


// Order errors
// =========================================================================
window.OrderErrorsApp = new Vue({
    render: h => h(OrderErrors),
    store,
}).$mount('#order-errors-app')

// Order customer
// =========================================================================
window.OrderCustomerApp = new Vue({
    render: h => h(OrderCustomer),
    store,
}).$mount('#order-customer-app')


// Order details
// =========================================================================

window.OrderDetailsApp = new Vue({
    render: h => h(App),
    store,

    methods: {
        externalRefresh() {
            const draft = this.$store.state.draft
            this.$store.dispatch('recalculateOrder', draft)
                .then(() => {
                    this.$store.dispatch('displayNotice', "Order recalculated.")
                })
                .catch((error) => {
                    this.$store.dispatch('displayError', error);
                })
        }
    },

    mounted() {
        this.$store.dispatch('getOrder')
    }
}).$mount('#order-details-app')

// Order meta
// =========================================================================

window.OrderMetaApp = new Vue({
    render: h => h(OrderMeta),
    store,
}).$mount('#order-meta-app')

// Order meta
// =========================================================================

window.OrderNotices = new Vue({
    render: h => h(OrderNotices),
    store,
}).$mount('#order-notices-app')

// Order secondary actions
// =========================================================================

window.OrderSecondaryActionsApp = new Vue({
    render: h => h(OrderSecondaryActions),
    store,
}).$mount('#order-secondary-actions-app')

import Vue from 'vue'
import App from './OrderDetails'
import 'prismjs/themes/prism.css'
import OrderMeta from './OrderMeta'
import OrderActions from './OrderActions'
import OrderSecondaryActions from './OrderSecondaryActions'
import store from './store'
import {t} from './filters/craft'
import {capitalize} from './filters/capitalize';
import BtnLink from './components/BtnLink'
import OrderBlock from './components/OrderBlock'
import OrderTitle from './components/OrderTitle'


Vue.config.productionTip = false
Vue.filter('t', t)
Vue.filter('capitalize', capitalize)
Vue.component('btn-link', BtnLink)
Vue.component('order-block', OrderBlock)
Vue.component('order-title', OrderTitle)


// Order actions
// =========================================================================

window.OrderActionsApp = new Vue({
    render: h => h(OrderActions),
    store,
}).$mount('#order-actions-app')


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
        this.$store.dispatch('getPurchasables')
    }
}).$mount('#order-details-app')


// Order meta
// =========================================================================

window.OrderMetaApp = new Vue({
    render: h => h(OrderMeta),
    store,
}).$mount('#order-meta-app')


// Order secondary actions
// =========================================================================

window.OrderSecondaryActionsApp = new Vue({
    render: h => h(OrderSecondaryActions),
    store,
}).$mount('#order-secondary-actions-app')

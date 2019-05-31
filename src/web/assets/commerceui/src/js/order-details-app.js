import Vue from 'vue'
import App from './OrderDetailsApp'
// import 'prismjs'
import 'prismjs/themes/prism.css'
import orderApi from './api/order'

Vue.config.productionTip = false

new Vue({
    render: h => h(App),

    data() {
        return {
            loading: false,
            editing: false,
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

        getOrder(orderId) {
            this.loading = true
            return orderApi.get(orderId)
                .then((response) => {
                    this.loading = false
                    this.draft = JSON.parse(JSON.stringify(response.data))

                    if (!this.originalDraft) {
                        this.originalDraft = JSON.parse(JSON.stringify(this.draft))
                    }
                })
                .catch((error) => {
                    this.loading = false

                    let errorMsg = 'Couldn’t get order.'

                    if (error.response.data.error) {
                        errorMsg = error.response.data.error
                    }

                    this.displayError(errorMsg);

                    throw errorMsg + ': ' + error.response
                })
        },
    },

    mounted() {
        this.getOrder(this.orderId)

        purchasablesApi.search(this.orderId)
            .then((response) => {
                this.purchasables = response.data
            })
    }
}).$mount('#order-details-app')

import Vue from 'vue'
import App from './OrderDetails'
// import 'prismjs'
import 'prismjs/themes/prism.css'
import orderApi from './api/order'
import purchasablesApi from './api/purchasables'

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

        parseInputValue(type, value) {
            let parsedValue = null

            switch (type) {
                case 'int':
                    parsedValue = parseInt(value)
                    break;
                case 'float':
                    parsedValue = parseFloat(value)
                    break;
                case 'bool':
                    parsedValue = !!value
                    break;
            }

            if (isNaN(parsedValue)) {
                return value
            }

            return parsedValue
        },

        recalculateOrder(draft) {
            this.loading = true


            // make sure values have the right type

            draft.order.id = this.parseInputValue('int', draft.order.id)

            draft.order.lineItems.forEach((lineItem, lineItemKey) => {
                draft.order.lineItems[lineItemKey].id = this.parseInputValue('int', lineItem.id)
                draft.order.lineItems[lineItemKey].purchasableId = this.parseInputValue('int', lineItem.purchasableId)
                draft.order.lineItems[lineItemKey].shippingCategoryId = this.parseInputValue('int', lineItem.shippingCategoryId)
                draft.order.lineItems[lineItemKey].salePrice = this.parseInputValue('float', lineItem.salePrice)
                draft.order.lineItems[lineItemKey].qty = this.parseInputValue('int', lineItem.qty)

                lineItem.adjustments.forEach((adjustment, adjustmentKey) => {
                    draft.order.lineItems[lineItemKey].adjustments[adjustmentKey].id = this.parseInputValue('int', adjustment.id)
                    draft.order.lineItems[lineItemKey].adjustments[adjustmentKey].amount = this.parseInputValue('float', adjustment.amount)
                    draft.order.lineItems[lineItemKey].adjustments[adjustmentKey].included = this.parseInputValue('bool', adjustment.included)
                    draft.order.lineItems[lineItemKey].adjustments[adjustmentKey].orderId = this.parseInputValue('int', adjustment.orderId)
                    draft.order.lineItems[lineItemKey].adjustments[adjustmentKey].lineItemId = this.parseInputValue('int', adjustment.lineItemId)
                })
            })

            draft.order.orderAdjustments.forEach((adjustment, adjustmentKey) => {
                draft.order.orderAdjustments[adjustmentKey].id = this.parseInputValue('int', adjustment.id)
                draft.order.orderAdjustments[adjustmentKey].amount = this.parseInputValue('float', adjustment.amount)
                draft.order.orderAdjustments[adjustmentKey].included = this.parseInputValue('bool', adjustment.included)
                draft.order.orderAdjustments[adjustmentKey].orderId = this.parseInputValue('int', adjustment.orderId)
            })


            // recalculate

            orderApi.recalculate(draft)
                .then((response) => {
                    this.loading = false
                    this.draft = JSON.parse(JSON.stringify(response.data))

                    if (response.data.error) {
                        this.displayError(response.data.error);
                        return
                    }

                    this.displayNotice('Order recalculated.');
                })
                .catch((error) => {
                    this.loading = false

                    let errorMsg = 'Couldn’t recalculate order.'

                    if (error.response.data.error) {
                        errorMsg = error.response.data.error
                    }

                    this.displayError(errorMsg);

                    throw errorMsg + ': '+ error.response
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

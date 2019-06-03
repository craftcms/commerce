/* global Craft */

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
            recalculateLoading: false,
            saveLoading: false,
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

        canAddLineItem() {
            if (!this.maxLineItems) {
                return true
            }

            if (this.draft.order.lineItems.length < this.maxLineItems) {
                return true
            }

            return false
        },

        lineItemStatuses() {
            return window.orderEdit.lineItemStatuses
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

        taxCategories() {
            return window.orderEdit.taxCategories
        },

        shippingCategories() {
            return window.orderEdit.shippingCategories
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
            this.recalculateLoading = true
            return orderApi.get(orderId)
                .then((response) => {
                    this.recalculateLoading = false
                    this.draft = JSON.parse(JSON.stringify(response.data))

                    if (!this.originalDraft) {
                        this.originalDraft = JSON.parse(JSON.stringify(this.draft))
                    }
                })
                .catch((error) => {
                    this.recalculateLoading = false

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
            this.recalculateLoading = true


            // build draft data to be sent and make sure values have the right type

            const draftData = {
                order: {
                    recalculationMode: draft.order.recalculationMode,
                    lineItems: [],
                    orderAdjustments: [],
                }
            }

            draftData.order.id = this.parseInputValue('int', draft.order.id)

            draft.order.lineItems.forEach((lineItem, lineItemKey) => {
                draftData.order.lineItems[lineItemKey] = {}
                draftData.order.lineItems[lineItemKey].lineItemStatusId = this.parseInputValue('int', lineItem.lineItemStatusId)
                draftData.order.lineItems[lineItemKey].id = this.parseInputValue('int', lineItem.id)
                draftData.order.lineItems[lineItemKey].purchasableId = this.parseInputValue('int', lineItem.purchasableId)
                draftData.order.lineItems[lineItemKey].shippingCategoryId = this.parseInputValue('int', lineItem.shippingCategoryId)
                draftData.order.lineItems[lineItemKey].salePrice = this.parseInputValue('float', lineItem.salePrice)
                draftData.order.lineItems[lineItemKey].qty = this.parseInputValue('int', lineItem.qty)
                draftData.order.lineItems[lineItemKey].note = lineItem.note
                draftData.order.lineItems[lineItemKey].adminNote = lineItem.adminNote
                draftData.order.lineItems[lineItemKey].orderId = lineItem.orderId
                draftData.order.lineItems[lineItemKey].options = lineItem.options
                draftData.order.lineItems[lineItemKey].adjustments = []

                lineItem.adjustments.forEach((adjustment, adjustmentKey) => {
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey] = {}
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey].id = this.parseInputValue('int', adjustment.id)
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey].amount = this.parseInputValue('float', adjustment.amount)
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey].included = this.parseInputValue('bool', adjustment.included)
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey].orderId = this.parseInputValue('int', adjustment.orderId)
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey].lineItemId = this.parseInputValue('int', adjustment.lineItemId)
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey].name = adjustment.name
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey].description = adjustment.description
                    draftData.order.lineItems[lineItemKey].adjustments[adjustmentKey].type = adjustment.type
                })
            })

            draft.order.orderAdjustments.forEach((adjustment, adjustmentKey) => {
                draftData.order.orderAdjustments[adjustmentKey] = {}
                draftData.order.orderAdjustments[adjustmentKey].id = this.parseInputValue('int', adjustment.id)
                draftData.order.orderAdjustments[adjustmentKey].amount = this.parseInputValue('float', adjustment.amount)
                draftData.order.orderAdjustments[adjustmentKey].included = this.parseInputValue('bool', adjustment.included)
                draftData.order.orderAdjustments[adjustmentKey].orderId = this.parseInputValue('int', adjustment.orderId)
                draftData.order.orderAdjustments[adjustmentKey].name = adjustment.name
                draftData.order.orderAdjustments[adjustmentKey].description = adjustment.description
                draftData.order.orderAdjustments[adjustmentKey].type = adjustment.type
            })


            // Recalculate

            orderApi.recalculate(draftData)
                .then((response) => {
                    this.recalculateLoading = false
                    this.draft = JSON.parse(JSON.stringify(response.data))

                    if (response.data.error) {
                        this.displayError(response.data.error);
                        return
                    }

                    this.displayNotice('Order recalculated.');
                })
                .catch((error) => {
                    this.recalculateLoading = false

                    let errorMsg = 'Couldn’t recalculate order.'

                    if (error.response.data.error) {
                        errorMsg = error.response.data.error
                    }

                    this.displayError(errorMsg);

                    throw errorMsg + ': '+ error.response
                })
        },

        getErrors(errorKey) {
            if (this.draft && this.draft.order.errors && this.draft.order.errors[errorKey]) {
                return [this.draft.order.errors[errorKey]]
            }

            return []
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

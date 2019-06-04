/* global Craft */

import Vue from 'vue'
import App from './OrderDetails'
// import 'prismjs'
import 'prismjs/themes/prism.css'
import orderApi from './api/order'
import purchasablesApi from './api/purchasables'
import { EventBus } from './event-bus.js';
import OrderMeta from './OrderMeta'

Vue.config.productionTip = false

// Order details
// =========================================================================

window.OrderDetailsApp = new Vue({
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

    watch: {
        editing() {
            this.$emit('onEditingChange', this.editing)
        },
        saveLoading() {
            this.$emit('onSaveLoadingChange', this.saveLoading)
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
            const statuses = window.orderEdit.lineItemStatuses

            for (let key in statuses) {
                statuses[key].id = parseInt(statuses[key].id)
            }

            return statuses
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

        edit() {
            this.editing = true
        },

        cancel() {
            this.editing = false
            this.draft = JSON.parse(JSON.stringify(this.originalDraft))
        },

        save() {
            if (this.saveLoading) {
                return false
            }

            this.saveLoading = true

            const data = this.buildDraftData(this.draft)

            orderApi.save(data)
                .then((response) => {
                    this.originalDraft = JSON.parse(JSON.stringify(response.data))
                    this.saveLoading = false
                    this.displayNotice('Order saved.');
                })
                .catch((error) => {
                    this.saveLoading = false
                    this.displayError('Couldn’t save order.');
                })
        },

        getOrder(orderId) {
            this.recalculateLoading = true
            return orderApi.get(orderId)
                .then((response) => {
                    this.recalculateLoading = false
                    this.draft = JSON.parse(JSON.stringify(response.data))

                    // Todo: Temporary fix, controllers should return IDs as strings instead
                    this.draft.order.lineItems.forEach((lineItem, lineItemKey) => {
                        this.draft.order.lineItems[lineItemKey].lineItemStatusId = this.parseInputValue('int', lineItem.lineItemStatusId)
                    })

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

        /**
         * Builds draft data and makes sure values have the right type.
         **/
        buildDraftData(draft) {
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

            return draftData;
        },

        recalculateOrder(draft) {
            this.recalculateLoading = true

            const data = this.buildDraftData(draft)

            // Recalculate

            orderApi.recalculate(data)
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
        this.$emit('app-mounted')

        this.getOrder(this.orderId)

        purchasablesApi.search(this.orderId)
            .then((response) => {
                this.purchasables = response.data
            })

        EventBus.$on('someAction', () => {
            console.log('Some action triggered!')
        });

    }
}).$mount('#order-details-app')


// Order meta
// =========================================================================

window.OrderMetaApp = new Vue({
    render: h => h(OrderMeta),

    computed: {
        draft() {
            return window.OrderDetailsApp.draft
        },

        orderStatuses() {
            const statuses = window.orderEdit.orderStatuses

            for (let key in statuses) {
                statuses[key].id = parseInt(statuses[key].id)
            }

            return statuses
        },
    },

    methods: {
        save() {
            return window.OrderDetailsApp.save()
        }
    }
}).$mount('#order-meta-app')

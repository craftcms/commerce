/* global Craft */
export default {
    /**
     * Builds draft data and makes sure values have the right type.
     **/
    buildDraftData(draft) {
        const draftData = {
            order: {
                email: draft.order.email,
                customerId: draft.order.customerId,
                orderStatusId: draft.order.orderStatusId,
                isCompleted: draft.order.isCompleted,
                reference: draft.order.reference,
                couponCode: draft.order.couponCode,
                recalculationMode: draft.order.recalculationMode,
                shippingMethodHandle: draft.order.shippingMethodHandle,
                shippingAddressId: draft.order.shippingAddressId,
                shippingAddress: null,
                billingAddressId: draft.order.billingAddressId,
                billingAddress: null,
                message: draft.order.message,
                dateOrdered: draft.order.dateOrdered,
                lineItems: [],
                orderAdjustments: [],
            }
        }

        if (draft.order.billingAddress) {
            draftData.order.billingAddress = draft.order.billingAddress
        }

        if (draft.order.shippingAddress) {
            draftData.order.shippingAddress = draft.order.shippingAddress
        }

        if (draftData.order.dateOrdered && !draftData.order.dateOrdered.hasOwnProperty('timezone')) {
            draftData.order.dateOrdered['timezone'] = Craft.timezone
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
            draftData.order.lineItems[lineItemKey].privateNote = lineItem.privateNote
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

    /**
     * Parse input value.
     **/
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
}

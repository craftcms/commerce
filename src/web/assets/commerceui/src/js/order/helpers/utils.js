/* jshint esversion: 6 */
/* globals Craft */
export default {
  /**
   * Builds draft data and makes sure values have the right type.
   **/
  buildDraftData(draft) {
    const draftData = {
      order: {
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
        orderSiteId: draft.order.orderSiteId,
        notices: draft.order.notices,
      },
    };

    if (draft.order.billingAddress) {
      draftData.order.billingAddress = draft.order.billingAddress;
    }

    if (draft.order.shippingAddress) {
      draftData.order.shippingAddress = draft.order.shippingAddress;
    }

    if (draft.order.sourceBillingAddressId != undefined) {
      draftData.order.sourceBillingAddressId =
        draft.order.sourceBillingAddressId;
    }

    if (draft.order.sourceShippingAddressId != undefined) {
      draftData.order.sourceShippingAddressId =
        draft.order.sourceShippingAddressId;
    }

    if (draft.order.suppressEmails != undefined) {
      draftData.order.suppressEmails = draft.order.suppressEmails;
    }

    if (
      draftData.order.dateOrdered &&
      !draftData.order.dateOrdered.hasOwnProperty('timezone')
    ) {
      draftData.order.dateOrdered.timezone = Craft.timezone;
    }

    draftData.order.id = this.parseInputValue('int', draft.order.id);

    draft.order.lineItems.forEach((lineItem, lineItemKey) => {
      let _lineItem = {};
      _lineItem.lineItemStatusId = this.parseInputValue(
        'int',
        lineItem.lineItemStatusId
      );
      _lineItem.id = this.parseInputValue('int', lineItem.id);
      _lineItem.type = lineItem.type;

      if (lineItem.type.value === 'custom' && lineItem.description) {
        _lineItem.description = lineItem.description;
      }
      if (lineItem.type.value === 'custom' && lineItem.sku) {
        _lineItem.sku = lineItem.sku;
      }

      _lineItem.purchasableId = this.parseInputValue(
        'int',
        lineItem.purchasableId
      );
      _lineItem.shippingCategoryId = this.parseInputValue(
        'int',
        lineItem.shippingCategoryId
      );
      _lineItem.taxCategoryId = this.parseInputValue(
        'int',
        lineItem.taxCategoryId
      );
      _lineItem.promotionalPrice =
        lineItem.promotionalPrice === '' ? null : lineItem.promotionalPrice;
      _lineItem.price = lineItem.price;
      _lineItem.qty = this.parseInputValue('int', lineItem.qty);
      _lineItem.note = lineItem.note;
      _lineItem.privateNote = lineItem.privateNote;
      _lineItem.orderId = lineItem.orderId;
      _lineItem.options = lineItem.options;
      _lineItem.adjustments = [];
      _lineItem.uid = lineItem.uid;

      lineItem.adjustments.forEach((adjustment, adjustmentKey) => {
        let _adjustment = {};
        _adjustment.id = this.parseInputValue('int', adjustment.id);
        _adjustment.amount = this.parseInputValue('float', adjustment.amount);
        _adjustment.included = this.parseInputValue(
          'bool',
          adjustment.included
        );
        _adjustment.orderId = this.parseInputValue('int', adjustment.orderId);
        _adjustment.lineItemId = this.parseInputValue(
          'int',
          adjustment.lineItemId
        );
        _adjustment.name = adjustment.name;
        _adjustment.description = adjustment.description;
        _adjustment.type = adjustment.type;
        _adjustment.sourceSnapshot = adjustment.sourceSnapshot;

        _lineItem.adjustments[adjustmentKey] = _adjustment;
      });

      draftData.order.lineItems[lineItemKey] = _lineItem;
    });

    draft.order.orderAdjustments.forEach((adjustment, adjustmentKey) => {
      let _orderAdjustment = {};
      _orderAdjustment.id = this.parseInputValue('int', adjustment.id);
      _orderAdjustment.amount = this.parseInputValue(
        'float',
        adjustment.amount
      );
      _orderAdjustment.included = this.parseInputValue(
        'bool',
        adjustment.included
      );
      _orderAdjustment.orderId = this.parseInputValue(
        'int',
        adjustment.orderId
      );
      _orderAdjustment.name = adjustment.name;
      _orderAdjustment.description = adjustment.description;
      _orderAdjustment.type = adjustment.type;
      _orderAdjustment.sourceSnapshot = adjustment.sourceSnapshot;

      draftData.order.orderAdjustments[adjustmentKey] = _orderAdjustment;
    });

    return draftData;
  },

  /**
   * Parse input value.
   **/
  parseInputValue(type, value) {
    let parsedValue = null;

    switch (type) {
      case 'int':
        parsedValue = parseInt(value);
        break;
      case 'float':
        parsedValue = parseFloat(value);
        break;
      case 'bool':
        parsedValue = !!value;
        break;
    }

    if (isNaN(parsedValue)) {
      return value;
    }

    return parsedValue;
  },
};

/* jshint esversion: 6, strict: false */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.InventoryMovementModal = Craft.CpModal.extend({
  $quantityInput: null,
  $toInventoryMovementTypeInput: null,

  init: function (settings) {
    this.base('commerce/inventory/edit-movement-modal', settings);

    this.debouncedRefresh = this.debounce(this.refresh, 500);

    // after load event is triggered on this
    this.on('load', this.afterLoad.bind(this));
  },
  afterLoad: function () {
    const quantityId = Craft.namespaceId(
      'inventoryMovement-quantity',
      this.namespace
    );
    this.$quantityInput = this.$container.find('#' + quantityId);
    this.addListener(this.$quantityInput, 'keyup', this.debouncedRefresh);

    const toTransactionId = Craft.namespaceId(
      'inventoryMovement-toInventoryTransactionType',
      this.namespace
    );
    this.$toInventoryMovementTypeInput = this.$container.find(
      '#' + toTransactionId
    );
    this.addListener(
      this.$toInventoryMovementTypeInput,
      'change',
      this.refresh
    );
  },
  refresh: function () {
    let postData = Garnish.getPostData(this.$container);
    let expandedData = Craft.expandPostArray(postData);

    let data = {
      data: expandedData,
      headers: {
        'X-Craft-Namespace': this.namespace,
      },
    };

    Craft.sendActionRequest('POST', this.action, data).then((response) => {
      this.showLoadSpinner();
      this.update(response.data)
        .then(() => {
          // focus on the quantity input
          this.$quantityInput.trigger('focus');
          this.updateSizeAndPosition();
        })
        .finally(() => {
          this.hideLoadSpinner();
        });
    });
  },
  debounce: function (func, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        func.apply(this, args);
      }, delay);
    };
  },
});

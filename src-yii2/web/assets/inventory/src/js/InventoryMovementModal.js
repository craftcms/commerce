/* jshint esversion: 6, strict: false */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.InventoryMovementModal = Craft.CpModal.extend({
  $quantityInput: null,
  $toInventoryMovementTypeInput: null,
  $preview: null,

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

    this.$preview = this.$container.find('.js-inventory-movement-preview');
  },
  refresh: function () {
    let postData = Garnish.getPostData(this.$container);
    let expandedData = Craft.expandPostArray(postData);

    let data = {
      data: expandedData,
      // `preview` is a query param so it survives the X-Craft-Namespace body stripping
      params: {preview: 1},
      headers: {
        'X-Craft-Namespace': this.namespace,
      },
    };

    // Only swap the preview region, never the form inputs — replacing the
    // quantity input mid-typing would reset the caret and clobber keystrokes.
    Craft.sendActionRequest('POST', this.action, data).then((response) => {
      this.$preview.html(response.data.previewHtml);
      this.updateSizeAndPosition();
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

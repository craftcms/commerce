/* jshint esversion: 6, strict: false */
/* globals Craft, Garnish, $ */
import $ from 'jquery';

if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.UpdateInventoryLevelModal = Craft.CpModal.extend({
  $quantityInput: null,
  $typeInput: null,

  init: function (settings) {
    this.base('commerce/inventory/edit-update-levels-modal', settings);

    this.debouncedRefresh = this.debounce(this.refresh, 300);

    // after load event is triggered on this
    this.on('load', this.afterLoad.bind(this));
  },
  afterLoad: function () {
    const quantityId = Craft.namespaceId('quantity', this.namespace);
    this.$quantityInput = this.$container.find('#' + quantityId);
    this.addListener(this.$quantityInput, 'keyup', this.debouncedRefresh);

    const typeId = Craft.namespaceId('type', this.namespace);
    this.$typeInput = this.$container.find('#' + typeId);
    this.addListener(this.$typeInput, 'change', this.refresh);
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
      this.update(response.data);
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

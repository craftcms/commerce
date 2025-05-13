/* jshint esversion: 6, strict: false */
/* globals Craft, Garnish, $ */
import $ from 'jquery';

if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.ReceiveTransferScreen = Craft.CpScreenSlideout.extend({
  $quantityInput: null,
  $typeInput: null,

  init: function (settings) {
    this.base('commerce/transfers/receive-transfer-screen', settings);

    this.debouncedRefresh = this.debounce(this.refresh, 500);

    // after load event is triggered on this
    this.on('load', this.afterLoad.bind(this));
  },
  afterLoad: function () {},
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

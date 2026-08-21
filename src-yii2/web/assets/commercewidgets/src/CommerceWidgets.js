/* jshint esversion: 6 */
/* globals Craft, Garnish, $, htmx */

if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

// Wraps Craft.ui.createDateRangePicker, suppressing onChange during initialization
// so that restoring a saved custom date range does not overwrite the stored values.
Craft.Commerce.createDateRangePicker = function (config) {
  var initialized = false;
  var userOnChange = config.onChange || $.noop;

  config = $.extend({}, config, {
    onChange: function (start, end, handle) {
      if (!initialized) {
        return;
      }
      userOnChange(start, end, handle);
    },
  });

  var $btn = Craft.ui.createDateRangePicker(config);
  initialized = true;
  return $btn;
};

// Instantiate the CommerceWidgets as a global object
Craft.Commerce.CommerceWidgets = {
  updateOrderStatuses: function (storeId, selectizeId) {
    const $selectizeElement = $(
      `#${selectizeId} [data-attribute="orderStatuses"] .selectize select`
    );

    if (!$selectizeElement) {
      Craft.cp.displayError('Could not find order status field.');
      return;
    }

    const selectize = $selectizeElement[0].selectize;

    // Retrieve the order statuses for the store
    Craft.sendActionRequest(
      'get',
      'commerce/order-statuses/get-order-statuses',
      {params: {storeId}}
    )
      .then((response) => {
        const orderStatuses = response.data.orderStatuses;
        selectize.clear(true);
        selectize.clearOptions(true);

        response.data.orderStatuses.forEach((orderStatus) => {
          selectize.addOption({
            text: orderStatus.name,
            value: orderStatus.uid,
            status: orderStatus.color,
          });
        });

        selectize.refreshOptions(false);
      })
      .catch((error) => {
        Craft.cp.displayError(error);
      });
  },
};

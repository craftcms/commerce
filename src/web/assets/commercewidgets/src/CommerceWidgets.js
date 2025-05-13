/* jshint esversion: 6 */
/* globals Craft, Garnish, $, htmx */

if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

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

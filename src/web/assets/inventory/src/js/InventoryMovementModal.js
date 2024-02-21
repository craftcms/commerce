/* jshint esversion: 6, strict: false */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.InventoryMovementModal = Craft.CpModal.extend({
  init: function (settings) {
    this.base('commerce/inventory/edit-movement', settings);
  },
});

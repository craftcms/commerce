/** global: Craft */
/** global: Garnish */
/**
 * Transfer Edit class
 */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.TransferEdit = Garnish.Base.extend({
  $container: null,

  init: function (container, settings) {
    this.$container = $(container);
  },
});

/* jshint esversion: 6 */
/* globals Craft, Garnish, $, Map */
import './product.css';
/**
 * Product Edit Class
 */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.ProductEdit = Garnish.Base.extend({
  $discountsList: null,
  discountListSelector: '.product-discounts.commerce-discounts',
  discountIdsByVariantId: {},
  $container: null,
  $window: null,

  init: function (settings) {
    var _this = this;
    this.setSettings(settings, this.defaults);
    this.$container = $(this.settings.container);
    this.$window = $(window);

    if (this.$container && this.$container.length) {
      this.$discountsList = this.$container.find(this.discountListSelector);
      if (this.$discountsList && this.$discountsList.length) {
        this.populateDiscountList();
      }
    }
  },

  populateDiscountList: function () {
    if (this.$discountsList && this.$discountsList.length) {
      var _this = this;
      this.$discountsList.each(function (el) {
        var element = $(this);
        var id = element.data('id');
        var data = {
          id: id,
        };

        element.empty();

        Craft.sendActionRequest(
          'POST',
          'commerce/discounts/get-discounts-by-purchasable-id',
          {data}
        ).then((response) => {
          const {data} = response;
          if (data && data.discounts.length) {
            for (var i = 0; i < data.discounts.length; i++) {
              var discount = data.discounts[i];
              if (_this.discountIdsByVariantId[id] === undefined) {
                _this.discountIdsByVariantId[id] = [];
              }
              _this.discountIdsByVariantId[id].push(discount.id);

              var $discountLink = $('<a/>', {
                href: discount.cpEditUrl,
              });
              $(
                '<span>' + Craft.escapeHtml(discount.name) + '</span>'
              ).appendTo($discountLink);

              var $discountItem = $('<li>');
              $discountItem.append($discountLink);
              $discountItem.appendTo(element);
            }
          }
        });
      });
    }
  },

  defaults: {
    container: '#main-content',
    onChange: $.noop,
    id: null,
    purchasables: [],
  },
});

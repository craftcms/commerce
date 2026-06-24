/* jshint esversion: 6 */
/* globals Craft, Garnish, $ */

if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.PurchasablePriceField = Garnish.Base.extend({
  $container: null,
  $loadingElements: null,
  $refreshBtn: null,
  $tableContainer: null,
  $priceFields: null,
  $cprSlideouts: null,

  id: null,

  defaults: {
    siteId: null,
    conditionBuilderConfig: null,
    fieldNames: {
      price: null,
      promotionalPrice: null,
    },
  },

  init: function (id, settings) {
    this.setSettings(settings, this.defaults);
    this.id = id;
    this.$container = $('#' + this.id);
    this.$tableContainer = this.$container.find(
      '.js-purchasable-toggle-container'
    );
    this.$loadingElements = this.$tableContainer.find(
      '.js-purchasable-toggle-loading'
    );
    this.$refreshBtn = this.$container.find('.commerce-refresh-prices');

    if (this.$tableContainer.data('init-prices')) {
      this.initPurchasablePriceList();

      this.$refreshBtn.on('click', (e) => {
        e.preventDefault();
      });
    }
  },

  updatePriceList: function () {
    this.$loadingElements.removeClass('hidden');

    Craft.sendActionRequest('POST', 'commerce/catalog-pricing/prices', {
      data: {
        siteId: this.settings.siteId,
        condition: {condition: this.settings.conditionBuilderConfig},
        basePrice: $(
          'input[name="' + this.settings.fieldNames.price + '"][value]'
        ).val(),
        basePromotionalPrice: $(
          'input[name="' +
            this.settings.fieldNames.promotionalPrice +
            '"][value]'
        ).val(),
        forPurchasable: true,
        includeBasePrices: false,
      },
    })
      .then((response) => {
        this.$loadingElements.addClass('hidden');

        if (response.data) {
          this.$tableContainer
            .find('.tableview')
            .replaceWith(response.data.tableHtml);
        }

        this.$priceFields.off('change');
        this.$cprSlideouts.off('click');

        this.initPurchasablePriceList();
      })
      .catch(({response}) => {
        if (!response) {
          return;
        }

        this.$loadingElements.addClass('hidden');

        if (response.data && response.data.message) {
          Craft.cp.displayError(response.data.message);
        }

        this.$priceFields.off('change');
        this.$cprSlideouts.off('click');

        this.initPurchasablePriceList();
      });
  },

  initPurchasablePriceList: function () {
    const instance = this;
    // prettier-ignore
    this.$priceFields = this.$container.find(
      'input[name="' +
        this.settings.fieldNames.price +
        '[value]"], input[name="' +
        this.settings.fieldNames.promotionalPrice +
        '[value]"]'
    );
    this.$cprSlideouts = this.$container.find('.js-cpr-slideout');

    this.$priceFields.on('change', function (e) {
      instance.updatePriceList();
    });

    // New catalog price
    this.$cprSlideouts.on('click', function (e) {
      e.preventDefault();
      let _this = $(this);
      let params = {
        storeId: _this.data('store-id'),
        storeHandle: _this.data('store-handle'),
      };

      if (_this.data('catalog-pricing-rule-id')) {
        params.id = _this.data('catalog-pricing-rule-id');
      } else {
        params.purchasableId = _this.data('purchasable-id');
      }

      const slideout = new Craft.CpScreenSlideout(
        'commerce/catalog-pricing-rules/edit',
        {params}
      );

      slideout.on('submit', function ({response, data}) {
        Craft.cp.runQueue();
        instance.updatePriceList();
      });
    });
  },
});

/** global: Craft */
/** global: Garnish */
/** global: $ */
/**
 * Product Edit Class
 */
if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.ProductEdit = Garnish.Base.extend({
    $addToSale: null,
    addToSaleSelector: '.product-add-to-sale',
    $salesList: null,
    $discountsList: null,
    salesListSelector: '.product-sales.commerce-sales',
    spinnerSelector: '.product-sales.commerce-sales-spinner',
    discountListSelector: '.product-discounts.commerce-discounts',
    saleIdsByVariantId: {},
    discountIdsByVariantId: {},
    $container: null,
    $window: null,

    init: function(settings) {
        var _this = this;
        this.setSettings(settings, this.defaults);
        this.$container = $(this.settings.container);
        this.$window = $(window);

        if (this.$container && this.$container.length) {
            // List sales
            this.$salesList = this.$container.find(this.salesListSelector);
            if (this.$salesList && this.$salesList.length) {
                this.$window.scroll(function(ev) {
                    _this.checkSalesInView();
                });

                _this.checkSalesInView();
            }

            this.$discountsList = this.$container.find(this.discountListSelector);
            if (this.$discountsList && this.$discountsList.length) {
                this.populateDiscountList();
            }

            // Add to sale button
            this.$addToSale = this.$container.find(this.addToSaleSelector);
            if (this.$addToSale && this.$addToSale.length) {
                this.$addToSale.on('click', $.proxy(this, 'handleAddToSale'));
            }
        }
    },

    checkSalesInView: function() {
        if (this.$salesList && this.$salesList.length) {
            var _this = this;
            this.$salesList.each(function(el) {
                var $spinner = _this.$container.find(_this.spinnerSelector + '[data-id="' + $(this).data('id') + '"]');
                if (!$spinner.hasClass('hidden') && !$spinner.data('loading')) {
                    var top_of_element = $(this).offset().top;
                    var bottom_of_element = $(this).offset().top + $(this).outerHeight();
                    var bottom_of_screen = _this.$window.scrollTop() + _this.$window.innerHeight();
                    var top_of_screen = _this.$window.scrollTop();

                    if ((bottom_of_screen > top_of_element) && (top_of_screen < bottom_of_element)){
                        _this.populateSaleList($(this));
                    }
                }
            });
        }
    },

    handleAddToSale: function(ev) {
        ev.preventDefault();
        var el = $(ev.target);

        $.get({
            url: Craft.getActionUrl('commerce/sales/get-all-sales'),
            dataType: 'json',
            success: $.proxy(function(data) {
                this.createSalesModal(el.data('id'), data);
            }, this)
        });
    },

    resetSales: function() {
      var $spinners = this.$container.find(this.spinnerSelector);

      if (this.$salesList && this.$salesList.length) {
          this.$salesList.each(function() {
              $(this).empty();
          });
      }

      if ($spinners && $spinners.length) {
          $spinners.each(function() {
              $(this).removeClass('hidden');
          });
      }
      this.checkSalesInView();
    },

    createSalesModal: function(id, sales) {
        var data = {
            existingSaleIds: [],
            onHide: $.proxy(this, 'resetSales')
        };

        if (id === 'all') {
            data['purchasables'] = this.settings.purchasables;
        } else {
            data['id'] = id;
            data['existingSaleIds'] = this.saleIdsByVariantId[id];
        }

        var salesModal = new Craft.Commerce.ProductSalesModal(sales, data);
    },

    populateSaleList: function(element) {
        var _this = this;
        var id = element.data('id');
        var $spinner = _this.$container.find(_this.spinnerSelector + '[data-id="' + id + '"]');
        var data = {
            id: id
        };

        $spinner.removeClass('hidden');
        $spinner.data('loading', true);

        element.empty();

        Craft.postActionRequest('commerce/sales/get-sales-by-purchasable-id', data, function(response) {
            $spinner.addClass('hidden');
            $spinner.data('loading', false);
            if (response && response.success && response.sales.length) {
                for (var i = 0; i < response.sales.length; i++) {
                    var sale = response.sales[i];
                    if (_this.saleIdsByVariantId[id] === undefined) {
                        _this.saleIdsByVariantId[id] = [];
                    }
                    _this.saleIdsByVariantId[id].push(sale.id);

                    $('<li>\n' +
                        '<a href="'+sale.cpEditUrl+'"><span>'+sale.name+'</span></a>\n' +
                        '</li>').appendTo(element);
                }
            }
        });
    },

    populateDiscountList: function() {
        if (this.$discountsList && this.$discountsList.length) {
            var _this = this;
            this.$discountsList.each(function(el) {
                var element = $(this);
                var id = element.data('id');
                var data = {
                    id: id
                };

                element.empty();

                Craft.postActionRequest('commerce/discounts/get-discounts-by-purchasable-id', data, function(response) {
                    if (response && response.success && response.discounts.length) {
                        for (var i = 0; i < response.discounts.length; i++) {
                            var discount = response.discounts[i];
                            if (_this.discountIdsByVariantId[id] === undefined) {
                                _this.discountIdsByVariantId[id] = [];
                            }
                            _this.discountIdsByVariantId[id].push(discount.id);

                            $('<li>\n' +
                                '<a href="'+discount.cpEditUrl+'"><span>'+discount.name+'</span></a>\n' +
                                '</li>').appendTo(element);
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
        hasVariants: false,
        purchasables: []
    }
});

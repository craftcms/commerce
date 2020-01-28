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
    salesListSelector: '.product-sales.commerce-sales',
    saleIdsByVariantId: {},
    $container: null,

    init: function(settings) {
        this.setSettings(settings, this.defaults);
        this.$container = $(this.settings.container);

        if (this.$container && this.$container.length) {
            // List sales
            this.$salesList = this.$container.find(this.salesListSelector);
            if (this.$salesList && this.$salesList.length) {
                this.populateSalesList();
            }

            // Add to sale button
            this.$addToSale = this.$container.find(this.addToSaleSelector);
            if (this.$addToSale && this.$addToSale.length) {
                this.$addToSale.on('click', $.proxy(this, 'handleAddToSale'));
            }
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

    createSalesModal: function(id, sales) {
        var data = {
            existingSaleIds: [],
            onHide: $.proxy(this, 'populateSalesList')
        };

        if (id === 'all') {
            data['purchasables'] = this.settings.purchasables;
        } else {
            data['id'] = id;
            data['existingSaleIds'] = this.saleIdsByVariantId[id];
        }

        var salesModal = new Craft.Commerce.ProductSalesModal(sales, data);
    },

    populateSalesList: function() {
        if (this.$salesList && this.$salesList.length) {
            var _this = this;
            this.$salesList.each(function(el) {
                var element = $(this);
                var id = element.data('id');
                var data = {
                    id: id
                };

                element.empty();

                Craft.postActionRequest('commerce/sales/get-sales-by-purchasable-id', data, function(response) {
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

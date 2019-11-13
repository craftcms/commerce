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
        $.get({
            url: Craft.getActionUrl('commerce/sales/get-all-sales'),
            dataType: 'json',
            success: $.proxy(function(data) {
                this.createSalesModal(data);
            }, this)
        });
    },

    createSalesModal: function(sales) {
        var salesModal = new Craft.Commerce.ProductSalesModal(sales, {
            productId: this.settings.id,
            onHide: $.proxy(this, 'populateSalesList')
        });
    },

    populateSalesList: function() {
        if (this.$salesList && this.$salesList.length && this.settings.id) {
            this.$salesList.empty();
            var data = {id: this.settings.id};
            Craft.postActionRequest('commerce/sales/get-sales-by-product-id', data, $.proxy(function(response) {
                if (response && response.success && response.sales.length) {
                    for (var i = 0; i < response.sales.length; i++) {
                        var sale = response.sales[i];
                        $('<li>\n' +
                        '<a href="'+sale.cpEditUrl+'"><span>'+sale.name+'</span></a>\n' +
                        '</li>').appendTo(this.$salesList);
                    }
                }
            }, this));
        }
    },

    defaults: {
        container: '#main-content',
        onChange: $.noop,
        id: null
    }
});

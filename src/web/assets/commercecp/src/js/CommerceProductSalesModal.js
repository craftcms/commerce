if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.ProductSalesModal = Garnish.Modal.extend(
    {
        id: null,
        $cancelBtn: null,
        $select: null,
        $saveBtn: null,
        $spinner: null,

        init: function(sales, settings) {
            this.id = Math.floor(Math.random() * 1000000000);

            this.setSettings(settings, this.defaults);
            this.$form = $('<form class="modal fitted" method="post" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);
            var $body = $('<div class="body"></div>').appendTo(this.$form);
            var $inputs = $('<div class="content">' +
                '<h2 class="first">' + Craft.t('commerce', "Add Product to Sale") + '</h2>' +
                '<p>' + Craft.t('commerce', "Add this product to an existing sale. This will change the conditions of the sale, please review the sale.") + '</p>' +
                '</div>').appendTo($body);

            if (sales && sales.length) {
                this.$select = $('<select name="sale" />');
                $('<option value="">----</option>').appendTo(this.$select);

                for (var i = 0; i < sales.length; i++) {
                    var sale = sales[i];
                    this.$select.append($('<option value="'+sale.id+'">'+sale.name+'</option>'));
                }
                var $field = $('<div class="input ltr"></div>');
                var $container = $('<div class="select" />');
                this.$select.appendTo($container);
                $container.appendTo($field);

                var $fieldContainer = $('<div class="field"/>');
                $('<div class="heading">' +
                '<label>' + Craft.t('commerce', 'Sale') + '</label>' +
                '</div>' +
                '</div>').appendTo($fieldContainer);
                $container.appendTo($fieldContainer);

                $fieldContainer.appendTo($inputs);

                this.$select.on('change', $.proxy(this, 'handleSaleChange'));
            }

            // Error notice area
            this.$error = $('<div class="error"/>').appendTo($inputs);

            // Footer and buttons
            var $footer = $('<div class="footer"/>').appendTo(this.$form);
            var $mainBtnGroup = $('<div class="btngroup right"/>').appendTo($footer);
            this.$cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('commerce', 'Cancel') + '"/>').appendTo($mainBtnGroup);
            this.$saveBtn = $('<input type="button" class="btn submit" value="' + Craft.t('commerce', 'Save') + '"/>').appendTo($mainBtnGroup);
            this.$spinner = $('<div class="spinner hidden" />').appendTo($mainBtnGroup);

            this.$saveBtn.addClass('disabled');

            this.addListener(this.$cancelBtn, 'click', 'hide');
            this.addListener(this.$saveBtn, 'click', $.proxy(function(ev) {
                ev.preventDefault();
                if (!$(ev.target).hasClass('disabled')) {
                    this.$spinner.removeClass('hidden');
                    this.saveSale();
                }
            }, this));

            this.base(this.$form, this.settings);
        },

        saveSale: function() {
            var saleId = this.$form.find('select[name="sale"]').val();
            var data = {
                productId: this.settings.productId,
                saleId: saleId
            };

            Craft.postActionRequest('commerce/sales/add-product-to-sale', data, $.proxy(function(response) {
                if (response && response.error) {
                    Craft.cp.displayError(response.error);
                } else if (response && response.success ) {
                    Craft.cp.displayNotice(Craft.t('commerce', 'Product added to Sale.'));
                    this.hide();
                }
                this.$spinner.addClass('hidden');
            }, this));
        },

        handleSaleChange: function(ev) {
            if (this.$select.val() != '') {
                this.$saveBtn.removeClass('disabled');
            } else {
                this.$saveBtn.addClass('disabled');
            }
        },

        defaults: {
            onSubmit: $.noop,
            productId: null
        }
    });

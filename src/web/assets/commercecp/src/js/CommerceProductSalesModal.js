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

            if (this.settings.purchasables.length) {
                var $checkboxField = $('<div class="field" />');
                $('<div class="heading"><label>'+Craft.t('commerce', 'Select Variants')+'</label></div>').appendTo($checkboxField);
                var $inputContainer = $('<div class="input ltr" />');
                $.each(this.settings.purchasables, function(key, purchasable) {
                    $('<div>' +
                    '<input class="checkbox" type="checkbox" name="ids[]" id="add-to-sale-purchasable-'+purchasable.id+'" value="'+purchasable.id+'" checked /> ' +
                    '<label for="add-to-sale-purchasable-'+purchasable.id+'">' + purchasable.title +
                    ' <span class="extralight">'+purchasable.sku+'</span>' +
                    '</label>' +
                    '</div>').appendTo($inputContainer);
                });

                $inputContainer.appendTo($checkboxField);
                $checkboxField.appendTo($inputs);
            }

            if (sales && sales.length) {
                this.$select = $('<select name="sale" />');
                $('<option value="">----</option>').appendTo(this.$select);

                for (var i = 0; i < sales.length; i++) {
                    var sale = sales[i];
                    var disabled = false;

                    if (this.settings.existingSaleIds && this.settings.existingSaleIds.length && this.settings.existingSaleIds.indexOf(sale.id) >= 0) {
                        disabled = true;
                    }

                    this.$select.append($('<option value="'+sale.id+'" '+(disabled ? 'disabled' : '')+'>'+sale.name+'</option>'));
                }
                var $field = $('<div class="input ltr"></div>');
                var $container = $('<div class="select" />');
                this.$select.appendTo($container);
                $container.appendTo($field);

                var $fieldContainer = $('<div class="field"/>');
                $('<div class="heading">' +
                '<label>' + Craft.t('commerce', 'Sale') + '</label>' +
                '</div>').appendTo($fieldContainer);
                $container.appendTo($fieldContainer);

                $fieldContainer.appendTo($inputs);

                this.$select.on('change', $.proxy(this, 'handleSaleChange'));
            }

            // Error notice area
            this.$error = $('<div class="error"/>').appendTo($inputs);

            // Footer and buttons
            var $footer = $('<div class="footer"/>').appendTo(this.$form);
            var $newSaleBtnGroup = $('<div class="btngroup left"/>').appendTo($footer);
            var $newSale = $('<a class="btn icon add" target="_blank" href="'+Craft.getUrl('commerce/promotions/sales/new?purchasableIds=' + this.settings.id)+'">'+Craft.t('commerce', 'Create Sale')+'</a>').appendTo($newSaleBtnGroup);

            var $rightWrapper = $('<div class="right"/>').appendTo($footer);
            var $mainBtnGroup = $('<div class="btngroup"/>').appendTo($rightWrapper);
            this.$cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('commerce', 'Cancel') + '"/>').appendTo($mainBtnGroup);
            this.$saveBtn = $('<input type="button" class="btn submit" value="' + Craft.t('commerce', 'Save') + '"/>').appendTo($mainBtnGroup);
            this.$spinner = $('<div class="spinner hidden" />').appendTo($rightWrapper);

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
            var ids = [];

            if (this.settings.purchasables.length) {
                this.$form.find('input.checkbox:checked').each(function(el) {
                    ids.push($(this).val());
                });
            } else if (this.settings.id) {
                ids = [this.settings.id];
            }

            var data = {
                ids: ids,
                saleId: saleId
            };

            Craft.postActionRequest('commerce/sales/add-purchasable-to-sale', data, $.proxy(function(response) {
                if (response && response.error) {
                    Craft.cp.displayError(response.error);
                } else if (response && response.success ) {
                    Craft.cp.displayNotice(Craft.t('commerce', 'Added to Sale.'));
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
            id: null,
            productId: null,
            purchasables: [],
            existingSaleIds: []
        }
    });

(function($) {

    if (typeof Craft.Commerce === typeof undefined) {
        Craft.Commerce = {};
    }

    Craft.Commerce.initUnlimitedStockCheckbox = function($container) {
        $container.find('input.unlimited-stock:first').change(Craft.Commerce.handleUnlimitedStockCheckboxChange);
    };

    Craft.Commerce.handleUnlimitedStockCheckboxChange = function(ev) {
        var $checkbox = $(ev.currentTarget),
            $text = $checkbox.parent().prevAll('.textwrapper:first').children('.text:first');

        if ($checkbox.prop('checked')) {
            $text.prop('disabled', true).addClass('disabled').val('');
        }
        else {
            $text.prop('disabled', false).removeClass('disabled').focus();
        }
    };

})(jQuery);

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.AddressBox = Garnish.Modal.extend({
    $addressBox: null,
    $address: null,
    $content: null,
    address: null,
    editorModal: null,
    saveEndpoint: null,
    init: function($element, settings) {
        this.$addressBox = $element;

        this.$address = this.$addressBox.find('.address');
        this.address = this.$addressBox.data('address');
        this.saveEndpoint = this.$addressBox.data('saveendpoint');
        if (!this.saveEndpoint) {
            this.saveEndpoint = 'commerce/addresses/save';
        }
        this.setSettings(settings, this.defaults);

        this._renderAddress();

        this.$addressBox.toggleClass('hidden');
    },
    _renderAddress: function() {
        var $header = this.$addressBox.find(".address-box-header");

        // Set the edit button label
        var editLabel = this.address.id ? Craft.t('commerce', "Edit") : Craft.t('commerce', "New");

        $header.html("");
        $("<div class='address-header'><strong>" + this.$addressBox.data('title') + "</strong></div>").appendTo($header);

        var $buttons = $("<div class='address-buttons'/>").appendTo($header);

        // Delete button
        if (this.address.id && !this.settings.order) {
            var $deleteButton = $('<a class="small btn right delete" href="#"></a>');
            $deleteButton.text(Craft.t('commerce', 'Delete'));
            $deleteButton.data('id', this.address.id);
            $deleteButton.appendTo($buttons);
        }

        // Only show the map button if we have an address
        if (this.address.id) {
            var address = [this.address.address1, this.address.address2, this.address.city, this.address.zipCode, this.address.stateText, this.address.countryText];
            var addressStr = address.join(' ');
            $("<a class='small btn right' style='margin:2px' target='_blank' href='http://maps.google.com/maps?q=" + addressStr + "'>" + Craft.t('commerce', 'Map') + "</a>").appendTo($buttons);
        }

        // Edit button
        $("<a class='small btn right edit' style='margin:2px' href='" + Craft.getCpUrl('commerce/addresses/' + this.address.id, {'redirect': window.location.pathname}) + "'>" + editLabel + "</a>").appendTo($buttons);

        this.$address.html("");

        if (this.address.attention) {
            $("<span class='attention'>" + this.address.attention + "<br></span>").appendTo(this.$address);
        }

        if (this.address.title) {
            $("<span class='title'>" + this.address.title + "<br></span>").appendTo(this.$address);
        }

        if (this.address.firstName) {
            $("<span class='firstName'>" + this.address.firstName + "<br></span>").appendTo(this.$address);
        }

        if (this.address.lastName) {
            $("<span class='lastName'>" + this.address.lastName + "<br></span>").appendTo(this.$address);
        }

        if (this.address.fullName) {
            $("<span class='fullName'>" + this.address.fullName + "<br></span>").appendTo(this.$address);
        }

        if (this.address.label) {
            $("<span class='label'>" + this.address.label + "<br></span>").appendTo(this.$address);
        }

        if (this.address.notes) {
            $("<span class='notes'>" + this.address.notes + "<br></span>").appendTo(this.$address);
        }

        if (this.address.businessName) {
            $("<span class='businessName'>" + this.address.businessName + "<br></span>").appendTo(this.$address);
        }

        if (this.address.businessTaxId) {
            $("<span class='businessTaxId'>" + this.address.businessTaxId + "<br></span>").appendTo(this.$address);
        }

        if (this.address.businessId) {
            $("<span class='businessId'>" + this.address.businessId + "<br></span>").appendTo(this.$address);
        }

        if (this.address.phone) {
            $("<span class='phone'>" + this.address.phone + "<br></span>").appendTo(this.$address);
        }

        if (this.address.alternativePhone) {
            $("<span class='alternativePhone'>" + this.address.alternativePhone + "<br></span>").appendTo(this.$address);
        }

        if (this.address.address1) {
            $("<span class='address1'>" + this.address.address1 + "<br></span>").appendTo(this.$address);
        }

        if (this.address.address2) {
            $("<span class='address2'>" + this.address.address2 + "<br></span>").appendTo(this.$address);
        }

        if (this.address.address3) {
            $("<span class='address3'>" + this.address.address3 + "<br></span>").appendTo(this.$address);
        }

        if (this.address.city) {
            $("<span class='city'>" + this.address.city + "<br></span>").appendTo(this.$address);
        }

        if (this.address.zipCode) {
            $("<span class='zipCode'>" + this.address.zipCode + "<br></span>").appendTo(this.$address);
        }

        if (this.address.stateText) {
            $("<span class='stateText'>" + this.address.stateText + "<br></span>").appendTo(this.$address);
        }

        if (this.address.countryText) {
            $("<span class='countryText'>" + this.address.countryText + "<br></span>").appendTo(this.$address);
        }

        if (this.address.custom1) {
            $("<span class='custom1'>" + this.address.custom1 + "<br></span>").appendTo(this.$address);
        }

        if (this.address.custom2) {
            $("<span class='custom2'>" + this.address.custom2 + "<br></span>").appendTo(this.$address);
        }

        if (this.address.custom3) {
            $("<span class='custom3'>" + this.address.custom3 + "<br></span>").appendTo(this.$address);
        }

        if (this.address.custom4) {
            $("<span class='custom4'>" + this.address.custom4 + "<br></span>").appendTo(this.$address);
        }

        if (!this.address.id) {
            $("<span class='newAddress'>" + Craft.t('commerce', "No address") + "<br></span>").appendTo(this.$address);
        }

        this._attachListeners();
    },
    _attachListeners: function() {
        this.$addressBox.find('.edit').click($.proxy(function(ev) {
            ev.preventDefault();
            this.editorModal = new Craft.Commerce.EditAddressModal(this.address, {
                onSubmit: $.proxy(this, '_updateAddress')
            });
        }, this));

        this.$addressBox.find('.delete').click($.proxy(function(ev) {
            ev.preventDefault();
            var confirmationMessage = Craft.t('commerce', 'Are you sure you want to delete this address?');
            if (confirm(confirmationMessage)) {
                Craft.postActionRequest('commerce/addresses/delete', {id: this.address.id}, $.proxy(function(response) {
                    if (response.success) {
                        this.$addressBox.remove();
                    }
                }, this));
            }

        }, this));

    },
    _updateAddress: function(data, onError) {
        Craft.postActionRequest(this.saveEndpoint, data.address, $.proxy(function(response) {
            if (response && response.success) {
                this.address = response.address;
                this.settings.onChange(response.address);
                this._renderAddress();
                Craft.cp.displayNotice(Craft.t('commerce', 'Address Updated.'));
                this.editorModal.hide();
                this.editorModal.destroy();
            } else {
                Garnish.shake(this.editorModal.$form);
                onError(response.errors);
            }
        }, this));
    },
    defaults: {
        onChange: $.noop,
        order: false
    }
});

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.EditAddressModal = Garnish.Modal.extend(
    {
        id: null,
        $form: null,
        $body: null,
        $error: null,
        $updateBtn: null,
        $cancelBtn: null,
        $footerSpinner: null,
        addressFields: null,
        fields: {},
        countries: null,
        states: null,
        address: null,
        errors: {},
        modalTitle: null,
        submitLabel: null,
        init: function(address, settings) {

            this.id = Math.floor(Math.random() * 1000000000);
            this.countries = window.countries;
            this.states = window.states;
            this.address = address;

            this.setSettings(settings, Garnish.Modal.defaults);

            this.$form = $('<form class="modal fitted commerce-address" method="post" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);
            this.$body = $('<div class="body"></div>').appendTo(this.$form);

            if (!this.address.id) {
                this.modalTitle = Craft.t('commerce', 'Add Address');
                this.submitLabel = Craft.t('commerce', 'Add');
            } else {
                this.modalTitle = Craft.t('commerce', 'Update Address');
                this.submitLabel = Craft.t('commerce', 'Update');
            }

            this._renderFields();

            // Footer and buttons
            var $footer = $('<div class="footer"/>').appendTo(this.$form);
            var $mainBtnGroup = $('<div class="buttons right"/>').appendTo($footer);
            this.$cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('commerce', 'Cancel') + '"/>').appendTo($mainBtnGroup);
            this.$updateBtn = $('<input type="button" class="btn submit"  value="' + this.submitLabel + '"/>').appendTo($mainBtnGroup);
            this.$footerSpinner = $('<div class="spinner right hidden"/>').appendTo($footer);

            this.addListener(this.$cancelBtn, 'click', 'hide');
            this.addListener(this.$updateBtn, 'click', function(ev) {
                ev.preventDefault();
                this.updateAddress();
            });

            this.base(this.$form, settings);
        },
        _renderFields: function() {
            this.$body.empty();

            var $inputs = $('<div class="meta">' +
                '<h2 class="first">' + this.modalTitle + '</h2>' +
                '</div>').appendTo(this.$body);

            $('<input name="id" type="hidden" value="' + this.address.id + '">').appendTo($inputs);

            this.addressFields = [
                {field: 'attention', label: Craft.t('commerce', 'Attention'), type: 'Text'},
                {field: 'title', label: Craft.t('commerce', 'Title'), type: 'Text'},
                {
                    field: 'firstName',
                    label: Craft.t('commerce', 'First Name'),
                    required: true,
                    autofocus: true,
                    type: 'Text'
                },
                {
                    field: 'lastName',
                    label: Craft.t('commerce', 'Last Name'),
                    required: true,
                    type: 'Text'
                },
                {field: 'fullName', label: Craft.t('commerce', 'Full Name'), type: 'Text'},
                {field: 'address1', label: Craft.t('commerce', 'Address 1'), type: 'Text'},
                {field: 'address2', label: Craft.t('commerce', 'Address 2'), type: 'Text'},
                {field: 'address3', label: Craft.t('commerce', 'Address 3'), type: 'Text'},
                {field: 'city', label: Craft.t('commerce', 'City'), type: 'Text'},
                {field: 'zipCode', label: Craft.t('commerce', 'Zip Code'), type: 'Text'},
                {field: 'phone', label: Craft.t('commerce', 'Phone'), type: 'Text'},
                {field: 'alternativePhone', label: Craft.t('commerce', 'Phone (Alt)'), type: 'Text'},
                {field: 'label', label: Craft.t('commerce', 'Label'), type: 'Text'},
                {field: 'notes', label: Craft.t('commerce', 'Notes'), type: 'Textarea'},
                {field: 'businessName', label: Craft.t('commerce', 'Business Name'), type: 'Text'},
                {field: 'businessTaxId', label: Craft.t('commerce', 'Business Tax ID'), type: 'Text'},
                {field: 'businessId', label: Craft.t('commerce', 'Business ID'), type: 'Text'},
                {field: 'custom1', label: Craft.t('commerce', 'Custom 1'), type: 'Text'},
                {field: 'custom2', label: Craft.t('commerce', 'Custom 2'), type: 'Text'},
                {field: 'custom3', label: Craft.t('commerce', 'Custom 3'), type: 'Text'},
                {field: 'custom4', label: Craft.t('commerce', 'Custom 4'), type: 'Text'}
            ];

            this.fields = [];

            for (var i = 0; i < this.addressFields.length; ++i) {

                var item = this.addressFields[i];

                this.fields[item.field] = $(Craft.ui['create' + item.type + 'Field']({
                    id: this.id + item.field,
                    label: item.label,
                    name: this.id + item.field,
                    value: this.address[item.field],
                    required: item.required,
                    autofocus: item.autofocus,
                    errors: this.errors[item.field]
                })).appendTo($inputs);
            }

            var stateValueInput = $("<select id='" + this.id + "stateValue' name='" + this.id + "stateValue'/>");
            this.fields['stateValue'] = Craft.ui.createField(stateValueInput, {
                id: this.id + 'stateValue',
                label: Craft.t('commerce', 'State'),
                name: this.id + 'stateValue',
                errors: this.errors['stateValue']
            });

            var countryIdInput = $("<select id='" + this.id + "countryId' name='" + this.id + "countryId'/>");
            this.fields['countryId'] = Craft.ui.createField(countryIdInput, {
                id: this.id + 'countryId',
                label: Craft.t('commerce', 'Country'),
                name: this.id + 'countryId',
                required: true,
                errors: this.errors['countryId']
            }).appendTo($inputs);

            this.fields['countryId'].find('select').selectize({
                valueField: 'id',
                items: [this.address.countryId],
                options: this.countries,
                labelField: 'name',
                searchField: ['name'],
                dropdownParent: 'body',
                inputClass: 'selectize-input text',
                allowEmptyOption: false,
                onDropdownOpen: function($dropdown) {
                    $dropdown.css('z-index', 3000);
                }
            });

            // add any custom state value that could not be in the standard list of states.
            this.states.push({'name': this.address.stateValue, 'id': this.address.stateValue});

            this.fields['stateValue'].appendTo($inputs);
            this.fields['stateValue'].find('select').selectize({
                valueField: 'id',
                create: true,
                items: [this.address.stateValue],
                options: this.states,
                labelField: 'name',
                searchField: ['name'],
                dropdownParent: 'body',
                inputClass: 'selectize-input text',
                allowEmptyOption: false,
                onDropdownOpen: function($dropdown) {
                    $dropdown.css('z-index', 3000);
                }
            });

        },
        updateAddress: function() {
            if (this.$updateBtn.hasClass('disabled')) {
                return;
            }

            //clear errors
            this.errors = {};
            this.disableUpdateBtn();
            this.showFooterSpinner();

            this.address = {
                'id': this.$form.find('input[name=id]').val(),
                'attention': this.$form.find('input[name=' + this.id + 'attention]').val(),
                'title': this.$form.find('input[name=' + this.id + 'title]').val(),
                'firstName': this.$form.find('input[name=' + this.id + 'firstName]').val(),
                'lastName': this.$form.find('input[name=' + this.id + 'lastName]').val(),
                'fullName': this.$form.find('input[name=' + this.id + 'fullName]').val(),
                'address1': this.$form.find('input[name=' + this.id + 'address1]').val(),
                'address2': this.$form.find('input[name=' + this.id + 'address2]').val(),
                'address3': this.$form.find('input[name=' + this.id + 'address3]').val(),
                'city': this.$form.find('input[name=' + this.id + 'city]').val(),
                'zipCode': this.$form.find('input[name=' + this.id + 'zipCode]').val(),
                'phone': this.$form.find('input[name=' + this.id + 'phone]').val(),
                'alternativePhone': this.$form.find('input[name=' + this.id + 'alternativePhone]').val(),
                'label': this.$form.find('input[name=' + this.id + 'label]').val(),
                'notes': this.$form.find('textarea[name=' + this.id + 'notes]').val(),
                'businessName': this.$form.find('input[name=' + this.id + 'businessName]').val(),
                'businessTaxId': this.$form.find('input[name=' + this.id + 'businessTaxId]').val(),
                'businessId': this.$form.find('input[name=' + this.id + 'businessId]').val(),
                'stateValue': this.$form.find('select[name=' + this.id + 'stateValue]').val(),
                'countryId': this.$form.find('select[name=' + this.id + 'countryId]').val(),
                'custom1': this.$form.find('input[name=' + this.id + 'custom1]').val(),
                'custom2': this.$form.find('input[name=' + this.id + 'custom2]').val(),
                'custom3': this.$form.find('input[name=' + this.id + 'custom3]').val(),
                'custom4': this.$form.find('input[name=' + this.id + 'custom4]').val()
            };

            console.log(this.address);
            var self = this;
            this.settings.onSubmit({'address': this.address}, $.proxy(function(errors) {
                self.errors = errors;
                self.hideFooterSpinner();
                self.enableUpdateBtn();
                // re-render with errors
                self._renderFields();
            }));


        },
        enableUpdateBtn: function() {
            this.$updateBtn.removeClass('disabled');
        },
        disableUpdateBtn: function() {
            this.$updateBtn.addClass('disabled');
        },
        showFooterSpinner: function() {
            this.$footerSpinner.removeClass('hidden');
        },

        hideFooterSpinner: function() {
            this.$footerSpinner.addClass('hidden');
        },
        defaults: {
            onSubmit: $.noop
        }
    });

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.OrderEdit = Garnish.Base.extend(
    {
        orderId: null,
        paymentForm: null,

        $status: null,
        $completion: null,
        statusUpdateModal: null,
        billingAddressBox: null,
        shippingAddressBox: null,

        init: function(settings) {
            this.setSettings(settings);
            this.orderId = this.settings.orderId;
            this.paymentForm = this.settings.paymentForm;

            this.$makePayment = $('#make-payment');

            this.billingAddress = new Craft.Commerce.AddressBox($('#billingAddressBox'), {
                onChange: $.proxy(this, '_updateOrderAddress', 'billingAddress'),
                order: true
            });

            this.shippingAddress = new Craft.Commerce.AddressBox($('#shippingAddressBox'), {
                onChange: $.proxy(this, '_updateOrderAddress', 'shippingAddress'),
                order: true
            });

            this.addListener(this.$makePayment, 'click', 'makePayment');

            if (Object.keys(this.paymentForm.errors).length > 0) {
                this.openPaymentModal();
            }
        },
        openPaymentModal: function() {
            if (!this.paymentModal) {
                this.paymentModal = new Craft.Commerce.PaymentModal({
                    orderId: this.orderId,
                    paymentForm: this.paymentForm
                })
            } else {
                this.paymentModal.show();
            }
        },
        makePayment: function(ev) {
            ev.preventDefault();

            this.openPaymentModal();
        },
        _updateOrderAddress: function(name, address) {
            Craft.postActionRequest('commerce/orders/update-order-address', {
                addressId: address.id,
                addressType: name,
                orderId: this.orderId
            }, function(response) {
                if (!response.success) {
                    alert(response.error);
                }

                window.OrderDetailsApp.externalRefresh();

            });
        },
        _getCountries: function() {
            return window.countries;
        }
    },
    {
        defaults: {
            orderId: null,
            paymentForm: null
        }
    });

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderIndex
 */
Craft.Commerce.OrderIndex = Craft.BaseElementIndex.extend({

    startDate: null,
    endDate: null,

    init: function(elementType, $container, settings) {
        this.on('selectSource', $.proxy(this, 'updateSelectedSource'));
        this.base(elementType, $container, settings);

        Craft.ui.createDateRangePicker({
            onChange: function(startDate, endDate) {
                this.startDate = startDate;
                this.endDate = endDate;
                this.updateElements();
            }.bind(this),
        }).appendTo(this.$toolbar);

        if (window.orderEdit.currentUserPermissions['commerce-editOrders'] && window.orderEdit.edition != 'lite'){
            // Add the New Order button
            var $btn = $('<a class="btn submit icon add" href="'+Craft.getUrl('commerce/orders/create-new')+'">'+Craft.t('commerce', 'New Order')+'</a>');
            this.addButton($btn);
        }
    },

    updateSelectedSource() {
        if (!this.$source) {
            return;
        }

        var handle = this.$source.data('handle');
        if (!handle) {
            return;
        }

        if (this.settings.context === 'index' && typeof history !== 'undefined') {
            var uri = 'commerce/orders';

            if (handle) {
                uri += '/' + handle;
            }

            history.replaceState({}, '', Craft.getUrl(uri));
        }
    },

    getDefaultSourceKey() {
        var defaultStatusHandle = window.defaultStatusHandle;

        if (defaultStatusHandle) {
            for (var i = 0; i < this.$sources.length; i++) {
                var $source = $(this.$sources[i]);

                if ($source.data('handle') === defaultStatusHandle) {
                    return $source.data('key');
                }
            }
        }

        return this.base();
    },

    getViewParams: function() {
        var params = this.base();

        if (this.startDate || this.endDate) {
            var dateAttr = this.$source.data('date-attr') || 'dateUpdated';
            params.criteria[dateAttr] = ['and'];

            if (this.startDate) {
                params.criteria[dateAttr].push('>=' + (this.startDate.getTime() / 1000));
            }

            if (this.endDate) {
                params.criteria[dateAttr].push('<' + (this.endDate.getTime() / 1000 + 86400));
            }
        }

        return params;
    },
});

// Register the Commerce order index class
Craft.registerElementIndexClass('craft\\commerce\\elements\\Order', Craft.Commerce.OrderIndex);

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.PaymentModal
 */
Craft.Commerce.PaymentModal = Garnish.Modal.extend(
    {
        $container: null,
        $body: null,

        init: function(settings) {
            this.$container = $('<div id="paymentmodal" class="modal fitted loading"/>').appendTo(Garnish.$bod);

            this.base(this.$container, $.extend({
                resizable: false
            }, settings));

            var data = {
                orderId: settings.orderId,
                paymentForm: settings.paymentForm
            };

            Craft.postActionRequest('commerce/orders/get-payment-modal', data, $.proxy(function(response, textStatus) {
                this.$container.removeClass('loading');

                if (textStatus === 'success') {
                    if (response.success) {
                        this.$container.append(response.modalHtml);
                        Craft.appendHeadHtml(response.headHtml);
                        Craft.appendFootHtml(response.footHtml);

                        var $buttons = $('.buttons', this.$container),
                            $cancelBtn = $('<div class="btn">' + Craft.t('app', 'Cancel') + '</div>').prependTo($buttons);

                        this.addListener($cancelBtn, 'click', 'cancelPayment');

                        $('select#payment-form-select').change($.proxy(function(ev) {
                            var id = $(ev.currentTarget).val();
                            $('.gateway-form').addClass('hidden');
                            $('#gateway-' + id + '-form').removeClass('hidden');
                            Craft.initUiElements(this.$container);
                            this.updateSizeAndPosition();
                        }, this)).trigger('change');

                        this.updateSizeAndPosition();

                        Craft.initUiElements(this.$container);
                    }
                    else {
                        var error = Craft.t('commerce', 'An unknown error occurred.');

                        if (response.error) {
                            error = response.error;
                        }

                        this.$container.append('<div class="body">' + error + '</div>');
                    }
                }
            }, this));

        },

        cancelPayment: function() {
            this.hide();
        }
    },
    {});

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

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.RevenueWidget
 */
Craft.Commerce.CommerceShippingItemRatesValuesInput = Craft.BaseInputGenerator.extend({
    startListening: function() {
        if (this.listening) {
            return;
        }

        this.listening = true;

        this.addListener(this.$source, 'textchange', 'onTextChange');
        this.addListener(this.$form, 'submit', 'onFormSubmit');
    },
    updateTarget: function() {
        var sourceVal = this.$source.val();
        var targetVal = this.generateTargetValue(sourceVal);
        console.log(sourceVal);
        this.$target.prop('placeholder', targetVal);
    },
    onFormSubmit: function() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
    }
});

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.SubscriptionIndex
 */
Craft.Commerce.SubscriptionsIndex = Craft.BaseElementIndex.extend({

});

// Register the Commerce order index class
Craft.registerElementIndexClass('craft\\commerce\\elements\\Subscription', Craft.Commerce.SubscriptionsIndex);

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.UpdateOrderStatusModal = Garnish.Modal.extend(
    {
        id: null,
        orderStatusId: null,
        originalStatus: null,
        currentStatus: null,
        originalStatusId: null,
        $statusSelect: null,
        $selectedStatus: null,
        $orderStatusIdInput: null,
        $message: null,
        $error: null,
        $updateBtn: null,
        $statusMenuBtn: null,
        $cancelBtn: null,
        init: function(currentStatus, orderStatuses, settings) {
            this.id = Math.floor(Math.random() * 1000000000);

            this.setSettings(settings, {
                resizable: false
            });

            this.originalStatusId = currentStatus.id;
            this.currentStatus = currentStatus;

            var $form = $('<form class="modal fitted" method="post" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);
            var $body = $('<div class="body"></div>').appendTo($form);
            var $inputs = $('<div class="content">' +
                '<h2 class="first">' + Craft.t('commerce', "Update Order Status") + '</h2>' +
                '</div>').appendTo($body);

            // Build menu button
            this.$statusSelect = $('<a class="btn menubtn" href="#"><span class="status ' + currentStatus.color + '"></span>' + currentStatus.name + '</a>').appendTo($inputs);
            var $menu = $('<div class="menu"/>').appendTo($inputs);
            var $list = $('<ul class="padded"/>').appendTo($menu);
            var classes = "";
            for (var i = 0; i < orderStatuses.length; i++) {
                if (this.currentStatus.id === orderStatuses[i].id) {
                    classes = "sel";
                } else {
                    classes = "";
                }
                $('<li><a data-id="' + orderStatuses[i].id + '" data-color="' + orderStatuses[i].color + '" data-name="' + orderStatuses[i].name + '" class="' + classes + '"><span class="status ' + orderStatuses[i].color + '"></span>' + orderStatuses[i].name + '</a></li>').appendTo($list);
            }

            this.$selectedStatus = $('.sel', $list);

            // Build message input
            this.$message = $('<div class="field">' +
                '<div class="heading">' +
                '<label>' + Craft.t('commerce', 'Message') + '</label>' +
                '<div class="instructions"><p>' + Craft.t('commerce', 'Status change message') + '.</p>' +
                '</div>' +
                '</div>' +
                '<div class="input ltr">' +
                '<textarea class="text fullwidth" rows="2" cols="50" name="message" maxlength="10000"></textarea>' +
                '</div>' +
                '</div>').appendTo($inputs);

            // Error notice area
            this.$error = $('<div class="error"/>').appendTo($inputs);

            // Footer and buttons
            var $footer = $('<div class="footer"/>').appendTo($form);
            var $mainBtnGroup = $('<div class="btngroup right"/>').appendTo($footer);
            this.$cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('commerce', 'Cancel') + '"/>').appendTo($mainBtnGroup);
            this.$updateBtn = $('<input type="button" class="btn submit" value="' + Craft.t('commerce', 'Update') + '"/>').appendTo($mainBtnGroup);

            this.$updateBtn.addClass('disabled');

            // Listeners and
            this.$statusMenuBtn = new Garnish.MenuBtn(this.$statusSelect, {
                onOptionSelect: $.proxy(this, 'onSelectStatus')
            });

            this.addListener(this.$cancelBtn, 'click', 'hide');
            this.addListener(this.$updateBtn, 'click', function(ev) {
                ev.preventDefault();
                if (!$(ev.target).hasClass('disabled')) {
                    this.updateStatus();
                }
            });
            this.base($form, settings);
        },
        onSelectStatus: function(status) {
            this.deselectStatus();

            this.$selectedStatus = $(status);

            this.$selectedStatus.addClass('sel');

            this.currentStatus = {
                id: $(status).data('id'),
                name: $(status).data('name'),
                color: $(status).data('color')
            };

            var newHtml = "<span><span class='status " + this.currentStatus.color + "'></span>" + Craft.uppercaseFirst(this.currentStatus.name) + "</span>";
            this.$statusSelect.html(newHtml);

            if (this.originalStatusId === this.currentStatus.id) {
                this.$updateBtn.addClass('disabled');
            }
            else {
                this.$updateBtn.removeClass('disabled');
            }
        },

        deselectStatus: function() {
            if (this.$selectedStatus) {
                this.$selectedStatus.removeClass('sel');
            }
        },

        updateStatus: function() {
            var data = {
                'orderStatusId': this.currentStatus.id,
                'message': this.$message.find('textarea[name="message"]').val(),
                'color': this.currentStatus.color,
                'name': this.currentStatus.name
            };

            this.settings.onSubmit(data);
        },
        defaults: {
            onSubmit: $.noop
        }
    });

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.VariantValuesInput
 */
Craft.Commerce.VariantValuesInput = Craft.BaseInputGenerator.extend({
    startListening: function() {
        if (this.listening) {
            return;
        }

        this.listening = true;

        this.addListener(this.$source, 'textchange', 'onTextChange');
        this.addListener(this.$form, 'submit', 'onFormSubmit');
    },
    updateTarget: function() {
        var sourceVal = this.$source.val();
        var targetVal = this.generateTargetValue(sourceVal);
        console.log(sourceVal);
        this.$target.prop('checked', true);
    },
    onFormSubmit: function() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
    }
});

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.TableRowAdditionalInfoIcon = Garnish.Base.extend(
    {
        $icon: null,
        hud: null,

        init: function(icon) {
            this.$icon = $(icon);

            this.addListener(this.$icon, 'click', 'showHud');
        },

        showHud: function() {
            if (!this.hud) {
                var item = this.$icon.closest('.infoRow');
                var $hudBody = $("<div />");
                var $title = $('<h2>Details</h2>').appendTo($hudBody);
                var $table = $("<table class='data fullwidth detailHud'><tbody></tbody></table>").appendTo($hudBody);
                var $tbody = $table.find('tbody');

                var info = item.data('info');

                for (var i = 0; i < info.length; i++) {
                    var $tr = $('<tr />').appendTo($tbody);
                    var $label = $('<td><strong>' + Craft.t('commerce', info[i].label) + '</strong></td><td>').appendTo($tr);

                    var value = info[i].value;
                    var $value;

                    switch (info[i].type) {
                        case 'code':
                            $value = $('<td><code>'+value+'</code></td>');
                            break;
                        case 'response':
                            // Make sure we have proper spaces in it
                            try {
                                value = '<code class="language-json">'+JSON.stringify(JSON.parse(value), undefined, 4)+'</code>';
                            } catch (e) {
                                value = '<code class="language-xml">'+$('<div/>').text(value).html()+'</code>';
                            }

                            $value = $('<td class="highlight"><pre>'+value+'</pre></td>');
                            Prism.highlightElement($value.find('code').get(0));

                            break;
                        default:
                            $value = $('<td>'+value+'</td>');
                    }

                    $value.appendTo($tr);
                }

                this.hud = new Garnish.HUD(this.$icon, $hudBody, {
                    hudClass: 'hud'
                });
            }
            else {
                this.hud.show();
            }
        }
    });

// Borrowed from https://stackoverflow.com/a/7220510/2040791
function syntaxHighlight(json) {
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        var cls = 'number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'key';
            } else {
                cls = 'string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'boolean';
        } else if (/null/.test(match)) {
            cls = 'null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}
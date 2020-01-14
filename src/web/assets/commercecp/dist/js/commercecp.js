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
            $("<a class='small btn right' target='_blank' href='http://maps.google.com/maps?q=" + addressStr + "'>" + Craft.t('commerce', 'Map') + "</a>").appendTo($buttons);
        }

        // Edit button
        $("<a class='small btn right edit' href='" + Craft.getCpUrl('commerce/addresses/' + this.address.id, {'redirect': window.location.pathname}) + "'>" + editLabel + "</a>").appendTo($buttons);

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

            this.$status = $('#order-status');
            this.$completion = $('#order-completion');
            this.$makePayment = $('#make-payment');

            this.billingAddress = new Craft.Commerce.AddressBox($('#billingAddressBox'), {
                onChange: $.proxy(this, '_updateOrderAddress', 'billingAddress'),
                order: true
            });

            this.shippingAddress = new Craft.Commerce.AddressBox($('#shippingAddressBox'), {
                onChange: $.proxy(this, '_updateOrderAddress', 'shippingAddress'),
                order: true
            });

            this.$completion.toggleClass('hidden');
            this.addListener(this.$completion.find('.updatecompletion'), 'click', function(ev) {
                ev.preventDefault();
                this._markOrderCompleted();
            });

            this.$status.toggleClass('hidden');
            this.addListener(this.$status.find('.updatestatus'), 'click', function(ev) {
                ev.preventDefault();
                this._openCreateUpdateStatusModal();
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
            }
            else {
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
            });
        },
        _markOrderCompleted: function() {
            Craft.postActionRequest('commerce/orders/complete-order', {orderId: this.orderId}, function(response) {
                if (response.success) {
                    //Reload for now, until we build a full order screen SPA
                    window.location.reload();
                } else {
                    alert(response.error);
                }
            });
        },
        _openCreateUpdateStatusModal: function() {
            var self = this;
            var currentStatus = this.$status.find('.updatestatus').data('currentstatus');
            var statuses = this.$status.find('.updatestatus').data('orderstatuses');

            var id = this.orderId;

            this.statusUpdateModal = new Craft.Commerce.UpdateOrderStatusModal(currentStatus, statuses, {
                onSubmit: function(data) {
                    data.orderId = id;
                    Craft.postActionRequest('commerce/orders/update-status', data, function(response) {
                        if (response.success) {
                            self.$status.find('.updatestatus').data('currentstatus', self.statusUpdateModal.currentStatus);

                            // Update the current status in header
                            var html = "<span class='commerceStatusLabel'><span class='status " + self.statusUpdateModal.currentStatus.color + "'></span> " + self.statusUpdateModal.currentStatus.name + "</span>";
                            self.$status.find('.commerceStatusLabel').html(html);
                            Craft.cp.displayNotice(Craft.t('commerce', 'Status Updated.'));
                            self.statusUpdateModal.hide();

                        } else {
                            alert(response.error);
                        }
                    });
                }
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

    init: function(elementType, $container, settings) {
        this.on('selectSource', $.proxy(this, 'updateSelectedSource'));
        this.base(elementType, $container, settings);
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

    getViewClass: function(mode) {
        switch (mode) {
            case 'table':
                return Craft.Commerce.OrderTableView;
            default:
                return this.base(mode);
        }
    }
});

// Register the Commerce order index class
Craft.registerElementIndexClass('craft\\commerce\\elements\\Order', Craft.Commerce.OrderIndex);

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderTableView
 */
Craft.Commerce.OrderTableView = Craft.TableElementIndexView.extend({

        startDate: null,
        endDate: null,

        startDatepicker: null,
        endDatepicker: null,

        $chartExplorer: null,
        $totalValue: null,
        $chartContainer: null,
        $spinner: null,
        $error: null,
        $chart: null,
        $startDate: null,
        $endDate: null,
        $exportButton: null,

        afterInit: function() {
            this.$explorerContainer = $('<div class="chart-explorer-container"></div>').prependTo(this.$container);

            this.createChartExplorer();

            this.base();
        },

        getStorage: function(key) {
            return Craft.Commerce.OrderTableView.getStorage(this.elementIndex._namespace, key);
        },

        setStorage: function(key, value) {
            Craft.Commerce.OrderTableView.setStorage(this.elementIndex._namespace, key, value);
        },

        createChartExplorer: function() {
            // chart explorer
            var $chartExplorer = $('<div class="chart-explorer"></div>').appendTo(this.$explorerContainer),
                $chartHeader = $('<div class="chart-header"></div>').appendTo($chartExplorer),
                $exportButton = $('<div class="btn menubtn export-menubtn">' + Craft.t('commerce', 'Export') + '</div>').appendTo($chartHeader),
                $exportMenu = $('<div class="menu"><ul><li><a data-format="csv">CSV</a> <a data-format="xls">XLS</a></li><li><a data-format="xlsx">XLSX</a></li><li><a data-format="ods">ODS</a></li></ul></div>').appendTo($chartHeader),
                $dateRange = $('<div class="date-range" />').appendTo($chartHeader),
                $startDateContainer = $('<div class="datewrapper"></div>').appendTo($dateRange),
                $to = $('<span class="to light">-</span>').appendTo($dateRange),
                $endDateContainer = $('<div class="datewrapper"></div>').appendTo($dateRange),
                $total = $('<div class="total"></div>').appendTo($chartHeader),
                $totalLabel = $('<div class="total-label light">' + Craft.t('commerce', 'Total Revenue') + '</div>').appendTo($total),
                $totalValueWrapper = $('<div class="total-value-wrapper"></div>').appendTo($total),
                $totalValue = $('<span class="total-value">&nbsp;</span>').appendTo($totalValueWrapper);

            this.$exportButton = $exportButton;
            this.$chartExplorer = $chartExplorer;
            this.$totalValue = $totalValue;
            this.$chartContainer = $('<div class="chart-container"></div>').appendTo($chartExplorer);
            this.$spinner = $('<div class="spinner hidden" />').prependTo($chartHeader);
            this.$error = $('<div class="error"></div>').appendTo(this.$chartContainer);
            this.$chart = $('<div class="chart"></div>').appendTo(this.$chartContainer);

            this.$startDate = $('<input type="text" class="text" size="20" autocomplete="off" />').appendTo($startDateContainer);
            this.$endDate = $('<input type="text" class="text" size="20" autocomplete="off" />').appendTo($endDateContainer);

            this.$startDate.datepicker($.extend({
                onSelect: $.proxy(this, 'handleStartDateChange')
            }, Craft.datepickerOptions));

            this.$endDate.datepicker($.extend({
                onSelect: $.proxy(this, 'handleEndDateChange')
            }, Craft.datepickerOptions));

            this.startDatepicker = this.$startDate.data('datepicker');
            this.endDatepicker = this.$endDate.data('datepicker');

            this.addListener(this.$startDate, 'keyup', 'handleStartDateChange');
            this.addListener(this.$endDate, 'keyup', 'handleEndDateChange');

            new Garnish.MenuBtn(this.$exportButton, {
                onOptionSelect: $.proxy(this, 'handleClickExport')
            });

            // Set the start/end dates
            var startTime = this.getStorage('startTime') || ((new Date()).getTime() - (60 * 60 * 24 * 7 * 1000)),
                endTime = this.getStorage('endTime') || ((new Date()).getTime());

            this.setStartDate(new Date(startTime));
            this.setEndDate(new Date(endTime));

            // Load the report
            this.loadReport();
        },
        handleClickExport: function(option) {
            var data = {};
            data.source = this.settings.params.source;
            data.format = option.dataset.format;
            data.startDate = Craft.Commerce.OrderTableView.getDateValue(this.startDate);
            data.endDate = Craft.Commerce.OrderTableView.getDateValue(this.endDate);
            location.href = Craft.getActionUrl('commerce/downloads/export-order', data);

        },
        handleStartDateChange: function() {
            if (this.setStartDate(Craft.Commerce.OrderTableView.getDateFromDatepickerInstance(this.startDatepicker))) {
                this.loadReport();
            }
        },

        handleEndDateChange: function() {
            if (this.setEndDate(Craft.Commerce.OrderTableView.getDateFromDatepickerInstance(this.endDatepicker))) {
                this.loadReport();
            }
        },

        setStartDate: function(date) {
            // Make sure it has actually changed
            if (this.startDate && date.getTime() === this.startDate.getTime()) {
                return false;
            }

            this.startDate = date;
            this.setStorage('startTime', this.startDate.getTime());
            this.$startDate.val(Craft.formatDate(this.startDate));

            // If this is after the current end date, set the end date to match it
            if (this.endDate && this.startDate.getTime() > this.endDate.getTime()) {
                this.setEndDate(new Date(this.startDate.getTime()));
            }

            return true;
        },

        setEndDate: function(date) {
            // Make sure it has actually changed
            if (this.endDate && date.getTime() === this.endDate.getTime()) {
                return false;
            }

            this.endDate = date;
            this.setStorage('endTime', this.endDate.getTime());
            this.$endDate.val(Craft.formatDate(this.endDate));

            // If this is before the current start date, set the start date to match it
            if (this.startDate && this.endDate.getTime() < this.startDate.getTime()) {
                this.setStartDate(new Date(this.endDate.getTime()));
            }

            return true;
        },

        loadReport: function() {
            var requestData = this.settings.params;

            requestData.startDate = Craft.Commerce.OrderTableView.getDateValue(this.startDate);
            requestData.endDate = Craft.Commerce.OrderTableView.getDateValue(this.endDate);

            if (requestData.source.includes('carts:')) {
                this.$exportButton.addClass('hidden');
            } else {
                this.$exportButton.removeClass('hidden');
            }

            this.$spinner.removeClass('hidden');
            this.$error.addClass('hidden');
            this.$chart.removeClass('error');


            Craft.postActionRequest('commerce/charts/get-revenue-data', requestData, $.proxy(function(response, textStatus) {
                this.$spinner.addClass('hidden');

                if (textStatus === 'success' && typeof (response.error) === 'undefined') {
                    if (!this.chart) {
                        this.chart = new Craft.charts.Area(this.$chart);
                    }

                    var chartDataTable = new Craft.charts.DataTable(response.dataTable);

                    var chartSettings = {
                        formatLocaleDefinition: response.formatLocaleDefinition,
                        orientation: response.orientation,
                        formats: response.formats,
                        dataScale: response.scale
                    };

                    this.chart.draw(chartDataTable, chartSettings);

                    this.$totalValue.html(response.totalHtml);
                } else {
                    var msg = Craft.t('commerce', 'An unknown error occurred.');

                    if (typeof (response) !== 'undefined' && response && typeof (response.error) !== 'undefined') {
                        msg = response.error;
                    }

                    this.$error.html(msg);
                    this.$error.removeClass('hidden');
                    this.$chart.addClass('error');
                }
            }, this));
        }
    },
    {
        storage: {},

        getStorage: function(namespace, key) {
            if (Craft.Commerce.OrderTableView.storage[namespace] && Craft.Commerce.OrderTableView.storage[namespace][key]) {
                return Craft.Commerce.OrderTableView.storage[namespace][key];
            }

            return null;
        },

        setStorage: function(namespace, key, value) {
            if (typeof Craft.Commerce.OrderTableView.storage[namespace] === typeof undefined) {
                Craft.Commerce.OrderTableView.storage[namespace] = {};
            }

            Craft.Commerce.OrderTableView.storage[namespace][key] = value;
        },

        getDateFromDatepickerInstance: function(inst) {
            return new Date(inst.currentYear, inst.currentMonth, inst.currentDay);
        },

        getDateValue: function(date) {
            return date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
        }
    });

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

(function($) {

    if (typeof Craft.Commerce === typeof undefined) {
        Craft.Commerce = {};
    }

    /**
     * Registration Form class
     */
    Craft.Commerce.RegistrationForm = Craft.BaseElementIndex.extend({
        licenseKey: null,
        licenseKeyStatus: null,

        $headers: null,
        $views: null,

        $validLicenseHeader: null,
        $invalidLicenseHeader: null,
        $mismatchedLicenseHeader: null,
        $unknownLicenseHeader: null,

        $validLicenseView: null,
        $updateLicenseView: null,

        $updateLicenseForm: null,

        $unregisterLicenseSpinner: null,
        $updateLicenseSpinner: null,
        $transferLicenseSpinner: null,

        $licenseKeyLabel: null,
        $licenseKeyInput: null,
        $updateBtn: null,
        $clearBtn: null,
        $licenseKeyError: null,

        init: function(hasLicenseKey) {
            this.$headers = $('.reg-header');
            this.$views = $('.reg-view');

            this.$validLicenseHeader = $('#valid-license-header');
            this.$invalidLicenseHeader = $('#invalid-license-header');
            this.$mismatchedLicenseHeader = $('#mismatched-license-header');
            this.$unknownLicenseHeader = $('#unknown-license-header');

            this.$validLicenseView = $('#valid-license-view');
            this.$updateLicenseView = $('#update-license-view');

            this.$updateLicenseForm = $('#update-license-form');

            this.$unregisterLicenseSpinner = $('#unregister-license-spinner');
            this.$updateLicenseSpinner = $('#update-license-spinner');
            this.$transferLicenseSpinner = $('#transfer-license-spinner');

            this.$licenseKeyLabel = $('#license-key-label');
            this.$licenseKeyInput = $('#license-key-input');
            this.$updateBtn = $('#update-license-btn');
            this.$clearBtn = $('#clear-license-btn');
            this.$licenseKeyError = $('#license-key-error');

            this.addListener(this.$updateLicenseForm, 'submit', 'handleUpdateLicenseFormSubmit');

            this.addListener(this.$licenseKeyInput, 'focus', 'handleLicenseKeyFocus');
            this.addListener(this.$licenseKeyInput, 'textchange', 'handleLicenseKeyTextChange');
            this.addListener(this.$clearBtn, 'click', 'handleClearButtonClick');

            if (hasLicenseKey) {
                this.loadLicenseInfo();
            } else {
                this.unloadLoadingUi();
                this.setLicenseKey(null);
                this.setLicenseKeyStatus('unknown');
            }
        },

        unloadLoadingUi: function() {
            $('#loading-license-info').remove();
            $('#license-view-hr').removeClass('hidden');
        },

        loadLicenseInfo: function() {
            Craft.postActionRequest('commerce/registration/get-license-info', $.proxy(function(response, textStatus) {
                if (textStatus === 'success') {
                    this.unloadLoadingUi();
                    this.setLicenseKey(response.licenseKey);
                    this.setLicenseKeyStatus(response.licenseKeyStatus);
                } else {
                    $('#loading-graphic').addClass('error');
                    $('#loading-status').removeClass('light').text(Craft.t('commerce', 'Unable to load registration status at this time. Please try again later.'));
                }
            }, this));
        },

        setLicenseKey: function(licenseKey) {
            this.licenseKey = this.normalizeLicenseKey(licenseKey);
            var formattedLicenseKey = this.formatLicenseKey(this.licenseKey);
            this.$licenseKeyLabel.text(formattedLicenseKey);
            this.$licenseKeyInput.val(formattedLicenseKey);
            this.handleLicenseKeyTextChange();
        },

        setLicenseKeyStatus: function(licenseKeyStatus) {
            this.$headers.addClass('hidden');
            this.$views.addClass('hidden');

            this.licenseKeyStatus = licenseKeyStatus;

            // Show the proper header
            this['$' + licenseKeyStatus + 'LicenseHeader'].removeClass('hidden');

            // Show the proper form view
            if (this.licenseKeyStatus === 'valid') {
                this.$validLicenseView.removeClass('hidden');
            } else {
                this.$updateLicenseView.removeClass('hidden');
                this.$licenseKeyError.addClass('hidden');

                if (this.licenseKeyStatus === 'invalid') {
                    this.$licenseKeyInput.addClass('error');
                } else {
                    this.$licenseKeyInput.removeClass('error');
                }
            }
        },

        normalizeLicenseKey: function(licenseKey) {
            if (licenseKey) {
                return licenseKey.toUpperCase().replace(/[^A-Z0-9]/g, '');
            }

            return '';
        },

        formatLicenseKey: function(licenseKey) {
            if (licenseKey) {
                return licenseKey.match(/.{1,4}/g).join('-');
            }

            return '';
        },

        validateLicenseKey: function(licenseKey) {
            return (licenseKey.length === 24);
        },

        handleUpdateLicenseFormSubmit: function(ev) {
            ev.preventDefault();
            var licenseKey = this.normalizeLicenseKey(this.$licenseKeyInput.val());

            if (licenseKey && !this.validateLicenseKey(licenseKey)) {
                return;
            }

            this.$updateLicenseSpinner.removeClass('hidden');

            var data = {
                licenseKey: licenseKey
            };

            Craft.postActionRequest('commerce/registration/update-license-key', data, $.proxy(function(response, textStatus) {
                this.$updateLicenseSpinner.addClass('hidden');
                if (textStatus === 'success') {
                    if (response.success) {
                        this.setLicenseKey(response.licenseKey);
                        this.setLicenseKeyStatus(response.licenseKeyStatus);
                    } else {
                        this.$licenseKeyError.removeClass('hidden').text(response.error || Craft.t('commerce', 'An unknown error occurred.'));
                    }
                }
            }, this));
        },

        handleLicenseKeyFocus: function() {
            this.$licenseKeyInput.get(0).setSelectionRange(0, this.$licenseKeyInput.val().length);
        },

        handleLicenseKeyTextChange: function() {
            this.$licenseKeyInput.removeClass('error');

            var licenseKey = this.normalizeLicenseKey(this.$licenseKeyInput.val());

            if (licenseKey) {
                this.$clearBtn.removeClass('hidden');
            } else {
                this.$clearBtn.addClass('hidden');
            }

            if (licenseKey !== this.licenseKey && (!licenseKey || this.validateLicenseKey(licenseKey))) {
                this.$updateBtn.removeClass('disabled');
            } else {
                this.$updateBtn.addClass('disabled');
            }
        },

        handleClearButtonClick: function() {
            this.$licenseKeyInput.val('').focus();
            this.handleLicenseKeyTextChange();
        }
    });

})(jQuery);

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
 * Class Craft.Commerce.RevenueWidget
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

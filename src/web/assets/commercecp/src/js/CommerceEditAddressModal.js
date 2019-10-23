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

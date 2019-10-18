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

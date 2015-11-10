if (typeof Craft.Commerce === typeof undefined)
{
    Craft.Commerce = {};
}

Craft.Commerce.EditAddressModal = Garnish.Modal.extend({
    data: null,
    $body: null,
    desiredHeight: $(window).height(),
    type: null,
    orderId: null,
    $error: null,
    init: function (args, settings)
    {
        var self = this;
        this.data = args.address;
        var countries = window.countries;
        var states = window.states;
        this.type = args.addressType;
        this.orderId = args.orderId;

        this.setSettings(settings, {
            resizable: false
        });

        var $form = $('<form class="modal" style="height:100%" method="post" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);
        this.$body = $('<div class="body fields-body"></div>').appendTo($form);

        var fields = [];
        fields.push({attribute: 'firstName', label: Craft.t('First Name'), required: true});
        fields.push({attribute: 'lastName', label: Craft.t('Last Name'), required: true});
        fields.push({attribute: 'address1', label: Craft.t('Address Line 1')});
        fields.push({attribute: 'address2', label: Craft.t('Address Line 2')});
        fields.push({attribute: 'city', label: Craft.t('City')});
        fields.push({attribute: 'zipCode', label: Craft.t('Zip Code')});
        fields.push({attribute: 'phone', label: Craft.t('Phone')});
        fields.push({attribute: 'alternativePhone', label: Craft.t('Alternative Phone')});
        fields.push({attribute: 'businessName', label: Craft.t('Business Name')});
        fields.push({attribute: 'businessTaxId', label: Craft.t('Business Tax ID')});

        for (i = 0; i < fields.length; i++)
        {
            var $field = Craft.ui.createTextField({
                label: fields[i].label,
                required: fields[i].required,
                class: 'fullwidth' + ' ' + fields[i].attribute,
                id: fields[i].attribute + '-field',
                name: 'address[' + fields[i].attribute + ']',
                value: this.data[fields[i].attribute] ? this.data[fields[i].attribute] : ''
            }).appendTo(this.$body);
        }


        var $countryList = $('<div class="field">' +
            '<div class="heading">' +
            '<label for="field-country">' + Craft.t('Country') + '</label>' +
            '</div>' +
            '<div class="input ' + Craft.orientation + '">' +
            '<div class="select">' +
            '<select class="countryId" name="address[countryId]" />' +
            '</div>' +
            '</div>' +
            '</div>').appendTo(this.$body);

        $.each(countries, function (i, item)
        {
            var option = $('<option value="' + i + '" ' + (self.data.countryId == i ? "selected" : "") + '>' + item + '</option>').appendTo($countryList.find('select').first());
        });

        var $stateList = $('<div class="field">' +
            '<div class="heading">' +
            '<label for="field-state">' + Craft.t('State') + '</label>' +
            '</div>' +
            '<div class="input ' + Craft.orientation + '">' +
            '<div class="select">' +
            '<select class="stateId" name="address[stateId]" />' +
            '</div>' +
            '</div>' +
            '</div>').appendTo(this.$body);

        var $stateName = $('<div class="field">' +
            '<div class="heading">' +
            '<label>' + Craft.t('State') + '</label>' +
            '</div>' +
            '<div class="input ' + Craft.orientation + '">' +
            '<input class="text fullwidth stateName" type="text" name="address[stateName]" value="' + self.data["stateName"] + '" autocomplete="off" style="background-image: none; background-position: 0% 0%; background-repeat: repeat;">' +
            '</div>' +
            '</div>').appendTo(self.$body);

        this.$error = $('<div class="error"/>').appendTo(self.$body);

        if (states.hasOwnProperty(self.data.countryId))
        {
            $stateName.hide();
            $.each(states[self.data.countryId], function (i, item)
            {
                var option = $('<option value="' + i + '">' + item + '</option>').appendTo($stateList.find('select').first());
            });
        } else
        {
            $stateList.hide();
        }

        $countryList.find('select').first().change(function ()
        {
            var cid = $(this).val();
            $states = $stateList.find('select').first();
            $states.find('option').remove();

            if (states.hasOwnProperty(cid))
            {
                $stateName.hide();
                $stateList.show();
                for (var id in states[cid])
                {
                    var state = states[cid][id],
                        $option = $('<option/>');
                    $option.attr('value', id).text(state);
                    $states.append($option);
                }
            } else
            {
                $stateList.hide();
                $stateName.show();
            }
        });

        var $footer = $('<div class="footer"/>').appendTo($form);
        var $btnGroup = $('<div class="btngroup"/>').appendTo($footer);
        var $mainBtnGroup = $('<div class="btngroup right"/>').appendTo($footer);
        var $updateBtn = $('<input type="button" class="btn submit" value="' + Craft.t('Update Address') + '"/>').appendTo($mainBtnGroup);
        var $cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('Cancel') + '"/>').appendTo($btnGroup);

        this.addListener($cancelBtn, 'click', 'hide');
        this.addListener($updateBtn, 'click', function ()
        {
            this.updateAddress();
        });


        this.base($form, settings);
    },
    updateAddress: function ()
    {

        this.data = {
            orderId: this.orderId,
            addressType: this.type,
            address: {
                firstName: this.$body.find("input.firstName").val(),
                lastName: this.$body.find("input.lastName").val(),
                address1: this.$body.find("input.address1").val(),
                address2: this.$body.find("input.address2").val(),
                city: this.$body.find("input.city").val(),
                zipCode: this.$body.find("input.zipCode").val(),
                phone: this.$body.find("input.phone").val(),
                alternativePhone: this.$body.find("input.alternativePhone").val(),
                businessName: this.$body.find("input.businessName").val(),
                businessTaxId: this.$body.find("input.businessTaxId").val(),
                countryId: this.$body.find("select.countryId option:selected").val(),
                stateId: this.$body.find("select.stateId option:selected").val(),
                stateName: this.$body.find("input.stateName").val()
            }
        }

        Craft.postActionRequest('commerce/orders/updateAddress', this.data, function (response)
        {
            if (response.success)
            {
                location.reload(true);
            } else
            {
                console.log(response.error);
                //this.$error.html(response.error);
            }
        });
    },
    defaults: {
        onSubmit: $.noop
    }

});

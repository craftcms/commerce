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

    init: function (settings)
    {
        this.setSettings(settings);

        this.orderId = this.settings.orderId;
        this.paymentForm = this.settings.paymentForm;

        this.$status = $('#order-status');
        this.$completion = $('#order-completion');
        this.$makePayment = $('#make-payment');

        this.billingAddress = new Craft.Commerce.AddressBox($('#billingAddressBox'), {
            onChange: $.proxy(this, '_updateOrderAddress', 'billingAddress')
        });

        this.shippingAddress = new Craft.Commerce.AddressBox($('#shippingAddressBox'), {
            onChange: $.proxy(this, '_updateOrderAddress', 'shippingAddress')
        });

        this.$completion.toggleClass('hidden');
        this.addListener(this.$completion.find('.updatecompletion'), 'click', function (ev) {
            ev.preventDefault();
            this._markOrderCompleted();
        });

        this.$status.toggleClass('hidden');
        this.addListener(this.$status.find('.updatestatus'), 'click', function (ev) {
            ev.preventDefault();
            this._openCreateUpdateStatusModal();
        });

        this.addListener(this.$makePayment, 'click', 'makePayment');

        if(Object.keys(this.paymentForm.errors).length > 0)
        {
            this.openPaymentModal();
        }
    },

    openPaymentModal: function()
    {
        if(!this.paymentModal)
        {
            this.paymentModal = new Craft.Commerce.PaymentModal({
                orderId: this.orderId,
                paymentForm: this.paymentForm,
            })
        }
        else
        {
            this.paymentModal.show();
        }
    },

    makePayment: function(ev)
    {
        ev.preventDefault();

        this.openPaymentModal();
    },

    _updateOrderAddress: function (name, address) {
        Craft.postActionRequest('commerce/orders/updateOrderAddress', {
            addressId: address.id,
            addressType: name,
            orderId: this.orderId
        }, function (response) {
            if (!response.success) {
                alert(response.error);
            }
        });
    },
    _markOrderCompleted: function () {
        Craft.postActionRequest('commerce/orders/completeOrder', {orderId: this.orderId}, function (response) {
            if (response.success) {
                //Reload for now, until we build a full order screen SPA
                window.location.reload();
            } else {
                alert(response.error);
            }
        });
    },
    _openCreateUpdateStatusModal: function () {
        var self = this;
        var currentStatus = this.$status.find('.updatestatus').data('currentstatus');
        var statuses = this.$status.find('.updatestatus').data('orderstatuses');

        var id = this.orderId;

        this.statusUpdateModal = new Craft.Commerce.UpdateOrderStatusModal(currentStatus, statuses, {
            onSubmit: function (data) {
                data.orderId = id;
                Craft.postActionRequest('commerce/orders/updateStatus', data, function (response) {
                    if (response.success) {
                        self.$status.find('.updatestatus').data('currentstatus', self.statusUpdateModal.currentStatus);

                        // Update the current status in header
                        var html = "<span class='commerceStatusLabel'><span class='status " + self.statusUpdateModal.currentStatus.color + "'></span> " + self.statusUpdateModal.currentStatus.name + "</span>";
                        self.$status.find('.commerceStatusLabel').html(html);
                        Craft.cp.displayNotice(Craft.t('Status Updated.'));
                        self.statusUpdateModal.hide();

                    } else {
                        alert(response.error);
                    }
                });
            }
        });
    },
    _getCountries: function () {
        return window.countries;
    }
},
{
	defaults: {
        orderId: null,
        paymentForm: null,
	},
});

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

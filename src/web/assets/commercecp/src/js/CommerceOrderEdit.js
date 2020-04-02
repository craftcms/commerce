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

Craft.Commerce.OrderEdit = Garnish.Base.extend({
    $billingAddress: null,
    $shippingAddress: null,
    $statusUpdate: null,
    init: function(){
        this.billingAddress = $('.order-address-box');

        var $editBillingAddressBtn = $('.editBillingAddress').click(function (e) {
            e.preventDefault();
            if (!this.modal) {
                this.modal = new Craft.Commerce.EditAddressModal(this, {});
            } else {
                this.modal.show();
            }
        });

        var $editShippingAddressBtn = $('.editShippingAddress').click(function (e) {
            e.preventDefault();
            if (!this.modal) {
                this.modal = new Craft.Commerce.EditAddressModal(this, {});
            } else {
                this.modal.show();
            }
        });

        var $updateStatusBtn = $('.updatestatus').removeClass('hidden').click(function (e) {
            e.preventDefault();
            if (!this.modal) {
                this.modal = new Craft.Commerce.UpdateOrderStatusModal(this, {});
            } else {
                this.modal.show();
            }
        });

    }
});
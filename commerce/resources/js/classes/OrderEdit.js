Craft.Commerce = Craft.Commerce || {};

Craft.Commerce.OrderEdit = Garnish.Base.extend({
    $container: null,
    orderId: null,
    $billingAddress: null,
    $shippingAddress: null,
    $statusUpdate: null,
    init: function(args, settings){
        self = this;
        this.orderId = args.orderId;
        this.$container = args.container;
        this.$billingAddress = this.$container.find('.billingAddress');
        this.$shippingAddress = this.$container.find('.shippingAddress');

        var $editBillingAddressBtn = this.$billingAddress.find('.edit.btn').click(function (e) {
            e.preventDefault();
            if (!this.modal) {
                this.modal = new Craft.Commerce.EditAddressModal(this, {});
            } else {
                this.modal.show();
            }
        });

        var $editShippingAddressBtn = this.$billingAddress.find('.edit.btn').click(function (e) {
            e.preventDefault();
            if (!this.modal) {
                this.modal = new Craft.Commerce.EditAddressModal(this, {});
            } else {
                this.modal.show();
            }
        });

        $('.updatestatus').click($.proxy(function (e) {
            e.preventDefault();
            if (!this.modal) {
                var id = this.orderId;
                var handle = $(e.target).data('orderstatushandle');
                var statuses = $(e.target).data('orderstatuses');
                new Craft.Commerce.UpdateOrderStatusModal(handle,statuses, {
                    onSubmit: function(data){
                        data.orderId = id;
                        Craft.postActionRequest('commerce/orders/updateStatus', data, function (response) {
                            if (response.success) {
                                location.reload(true);
                            } else {
                                this.$error.html(response.error);
                            }
                        });
                    }
                });
            } else {
                this.modal.show();
            }
        },this));

    }
});
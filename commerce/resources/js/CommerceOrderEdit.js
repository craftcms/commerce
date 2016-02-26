if (typeof Craft.Commerce === typeof undefined)
{
    Craft.Commerce = {};
}

Craft.Commerce.OrderEdit = Garnish.Base.extend({
    orderId: null,
    $status: null,
    statusUpdateModal: null,
    billingAddressBox: null,
    shippingAddressBox: null,
    init: function (orderId, settings)
    {
        this.orderId = orderId;
        this.$status = $('#order-status');

        this.billingAddress = new Craft.Commerce.AddressBox($('#billingAddressBox'),{});
        this.shippingAddress = new Craft.Commerce.AddressBox($('#shippingAddressBox'),{});



        this.addListener(this.$status.find('.updatestatus'), 'click', function (ev)
        {
            ev.preventDefault();
            this._openCreateUpdateStatusModal();
        });

    },
    _openCreateUpdateStatusModal: function ()
    {
        var self = this;

        var currentStatus = this.$status.find('.updatestatus').data('currentstatus');
        var statuses = this.$status.find('.updatestatus').data('orderstatuses');

        var id = this.orderId;

        this.statusUpdateModal = new Craft.Commerce.UpdateOrderStatusModal(currentStatus, statuses, {
            onSubmit: function (data)
            {
                data.orderId = id;
                Craft.postActionRequest('commerce/orders/updateStatus', data, function (response)
                {
                    if (response.success)
                    {
                        self.$status.find('.updatestatus').data('currentstatus',self.statusUpdateModal.currentStatus);

                        // Update the current status in header
                        var html = "<span class='commerceStatusLabel'><span class='status "+self.statusUpdateModal.currentStatus.color+"'></span> "+self.statusUpdateModal.currentStatus.name+"</span>";
                        self.$status.find('.commerceStatusLabel').html(html);

                        self.statusUpdateModal.hide();

                    } else
                    {
                        alert(response.error);
                    }
                });
            }
        });
    },
    _getCountries: function()
    {
        return window.countries;
    }
});

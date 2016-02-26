if (typeof Craft.Commerce === typeof undefined)
{
    Craft.Commerce = {};
}

Craft.Commerce.OrderEdit = Garnish.Base.extend({
    orderId: null,
    $status: null,
    $completion: null,
    statusUpdateModal: null,
    billingAddressBox: null,
    shippingAddressBox: null,
    init: function (order, settings)
    {
        this.orderId = order.orderId;
        this.$status = $('#order-status');
        this.$completion = $('#order-completion');

        this.billingAddress = new Craft.Commerce.AddressBox($('#billingAddressBox'),{});
        this.shippingAddress = new Craft.Commerce.AddressBox($('#shippingAddressBox'),{});

        this.$completion.toggleClass('hidden');
        this.addListener(this.$completion.find('.updatecompletion'), 'click', function (ev)
        {
            ev.preventDefault();
            this._markOrderCompleted();
        });

        this.$status.toggleClass('hidden');
        this.addListener(this.$status.find('.updatestatus'), 'click', function (ev)
        {
            ev.preventDefault();
            this._openCreateUpdateStatusModal();
        });

    },
    _markOrderCompleted: function ()
    {
        var self = this;
        Craft.postActionRequest('commerce/orders/completeOrder', {'orderId':this.orderId}, function (response)
        {
            if (response.success)
            {
                //Reload for now, until we build a full order screen SPA
                window.location.reload();
            } else
            {
                alert(response.error);
            }
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

if (typeof Craft.Commerce === typeof undefined)
{
    Craft.Commerce = {};
}

Craft.Commerce.OrderEdit = Garnish.Base.extend({
    orderId: null,
    $status: null,
    statusUpdateModal: null,
    init: function (orderId, settings)
    {
        this.orderId = orderId;
        this.$status = $('#order-status');

        this.addListener(this.$status.find('.updatestatus'), 'click', function (ev)
        {
            ev.preventDefault();
            this._openCreateUpdateStatusModal();
        });
    },
    _openCreateUpdateStatusModal: function ()
    {
        if (!this.statusUpdateModal)
        {
            var id = this.orderId;
            var currentStatus = this.$status.find('.updatestatus').data('currentstatus');
            var statuses = this.$status.find('.updatestatus').data('orderstatuses');
            this.statusUpdateModal = new Craft.Commerce.UpdateOrderStatusModal(currentStatus, statuses, {
                onSubmit: function (data)
                {
                    data.orderId = id;
                    Craft.postActionRequest('commerce/orders/updateStatus', data, function (response)
                    {
                        if (response.success)
                        {
                            location.reload();
                        } else
                        {
                            this.$error.html(response.error);
                        }
                    });
                }
            });
        } else
        {
            this.statusUpdateModal.show();
        }
    }
});

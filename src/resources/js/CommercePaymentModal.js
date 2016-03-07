if (typeof Craft.Commerce === typeof undefined) {
	Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.PaymentModal
 */
Craft.Commerce.PaymentModal = Garnish.Modal.extend(
{
	$container: null,
	$body: null,

	init: function(settings)
	{
		this.$container = $('<div id="paymentmodal" class="modal fitted loading"/>').appendTo(Garnish.$bod),

		this.base(this.$container, $.extend({
			resizable: false
		}, settings));

        var data = {
            orderId: settings.orderId,
            paymentForm: settings.paymentForm
        };

		Craft.postActionRequest('commerce/orders/getPaymentModal', data, $.proxy(function(response, textStatus)
		{
			this.$container.removeClass('loading');

			if (textStatus == 'success')
			{
				if (response.success)
				{
					this.$container.append(response.modalHtml);
                    Craft.appendHeadHtml(response.headHtml);
                    Craft.appendFootHtml(response.footHtml);

					var $buttons = $('.buttons', this.$container),
					 	$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').prependTo($buttons);

					this.addListener($cancelBtn, 'click', 'cancelPayment');

                    $('select#payment-form-select').change($.proxy(function(ev){
                		var id = $( ev.currentTarget ).val();
                		$('.payment-method-form').addClass('hidden');
                		$('#payment-method-'+id+'-form').removeClass('hidden');
                        this.updateSizeAndPosition();
                        Craft.initUiElements(this.$container);
                	}, this));

                    this.updateSizeAndPosition();

                    Craft.initUiElements(this.$container);
				}
				else
				{
					if (response.error)
					{
						var error = response.error;
					}
					else
					{
						var error = Craft.t('An unknown error occurred.');
					}

					this.$container.append('<div class="body">'+error+'</div>');
				}
			}
		}, this));

	},

	cancelPayment: function()
	{
		this.hide();
	}
},
{

});

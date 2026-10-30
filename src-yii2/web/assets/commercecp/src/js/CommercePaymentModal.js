/* jshint esversion: 6 */
/* globals Craft, Garnish, $ */
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

    init: function (settings) {
      this.$container = $('<div/>', {
        id: 'paymentmodal',
        class: 'modal fitted loading',
      }).appendTo(Garnish.$bod);

      this.base(
        this.$container,
        $.extend(
          {
            resizable: false,
          },
          settings
        )
      );

      var data = {
        orderId: settings.orderId,
        paymentForm: settings.paymentForm,
        paymentAmount: settings.paymentAmount,
        paymentCurrency: settings.paymentCurrency,
      };

      Craft.sendActionRequest('POST', 'commerce/orders/get-payment-modal', {
        data,
      })
        .then((response) => {
          this.$container.removeClass('loading');
          var $this = this;
          this.$container.append(response.data.modalHtml);
          Craft.appendHeadHtml(response.data.headHtml);
          Craft.appendBodyHtml(response.data.footHtml);

          var $buttons = $('.buttons', this.$container),
            $cancelBtn = $(
              '<div class="btn">' + Craft.t('commerce', 'Cancel') + '</div>'
            ).prependTo($buttons);

          this.addListener($cancelBtn, 'click', 'cancelPayment');

          $('select#payment-form-select')
            .change(
              $.proxy(function (ev) {
                var id = $(ev.currentTarget).val();
                $('.gateway-form').addClass('hidden');
                $('#gateway-' + id + '-form').removeClass('hidden');

                setTimeout(function () {
                  Craft.initUiElements(this.$container);
                  $this.updateSizeAndPosition();
                }, 200);
              }, this)
            )
            .trigger('change');

          setTimeout(function () {
            Craft.initUiElements(this.$container);
            $this.updateSizeAndPosition();
          }, 200);
        })
        .catch(({response}) => {
          this.$container.removeClass('loading');
          var error = Craft.t('commerce', 'An unknown error occurred.');

          if (response.data.message) {
            error = response.data.message;
          }

          this.$container.append('<div class="body">' + error + '</div>');
        });
    },

    cancelPayment: function () {
      this.hide();
    },
  },
  {}
);

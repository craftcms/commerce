if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrdersWidgetSettings
 */
Craft.Commerce.OrdersWidgetSettings = Garnish.Base.extend(
    {
        init: function(id, settings) {
            this.$container = $('#' + id);
            this.$menuBtn = $('.menubtn', this.$container);
            this.$statusInput = $('.status-input', this.$container);

            this.menuBtn = new Garnish.MenuBtn(this.$menuBtn, {
                onOptionSelect: $.proxy(this, 'onSelectStatus')
            });

            var statusId = this.$statusInput.val();

            var $currentStatus = $('[data-id="' + statusId + '"]', this.menuBtn.menu.$container);

            $currentStatus.trigger('click');
        },

        onSelectStatus: function(status) {
            this.deselectStatus();

            var $status = $(status);
            $status.addClass('sel');

            this.selectedStatus = $status;

            this.$statusInput.val($status.data('id'));

            // clone selected status item to menu menu
            var $label = $('.commerceStatusLabel', $status);
            this.$menuBtn.empty();
            $label.clone().appendTo(this.$menuBtn);
        },

        deselectStatus: function() {
            if (this.selectedStatus) {
                this.selectedStatus.removeClass('sel');
            }
        }
    });


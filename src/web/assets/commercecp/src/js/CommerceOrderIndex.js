if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderIndex
 */
Craft.Commerce.OrderIndex = Craft.BaseElementIndex.extend({

    startDate: null,
    endDate: null,

    init: function(elementType, $container, settings) {
        this.on('selectSource', $.proxy(this, 'updateSelectedSource'));
        this.base(elementType, $container, settings);

        Craft.ui.createDateRangePicker({
            onChange: function(startDate, endDate) {
                this.startDate = startDate;
                this.endDate = endDate;
                this.updateElements();
            }.bind(this),
        }).appendTo(this.$toolbar);

        if (window.orderEdit.currentUserPermissions['commerce-editOrders'] && window.orderEdit.edition != 'lite'){
            // Add the New Order button
            var $btn = $('<a class="btn submit icon add" href="'+Craft.getUrl('commerce/orders/create-new')+'">'+Craft.t('commerce', 'New Order')+'</a>');
            this.addButton($btn);
        }
    },

    updateSelectedSource() {
        if (!this.$source) {
            return;
        }

        var handle = this.$source.data('handle');
        if (!handle) {
            return;
        }

        if (this.settings.context === 'index' && typeof history !== 'undefined') {
            var uri = 'commerce/orders';

            if (handle) {
                uri += '/' + handle;
            }

            history.replaceState({}, '', Craft.getUrl(uri));
        }
    },

    getDefaultSourceKey() {
        var defaultStatusHandle = window.defaultStatusHandle;

        if (defaultStatusHandle) {
            for (var i = 0; i < this.$sources.length; i++) {
                var $source = $(this.$sources[i]);

                if ($source.data('handle') === defaultStatusHandle) {
                    return $source.data('key');
                }
            }
        }

        return this.base();
    },

    getViewParams: function() {
        var params = this.base();

        if (this.startDate || this.endDate) {
            var dateAttr = this.$source.data('date-attr') || 'dateUpdated';
            params.criteria[dateAttr] = ['and'];

            if (this.startDate) {
                params.criteria[dateAttr].push('>=' + (this.startDate.getTime() / 1000));
            }

            if (this.endDate) {
                params.criteria[dateAttr].push('<' + (this.endDate.getTime() / 1000 + 86400));
            }
        }

        return params;
    },
});

// Register the Commerce order index class
Craft.registerElementIndexClass('craft\\commerce\\elements\\Order', Craft.Commerce.OrderIndex);

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderIndex
 */
Craft.Commerce.OrderIndex = Craft.BaseElementIndex.extend({

    init: function(elementType, $container, settings) {
        this.on('selectSource', $.proxy(this, 'updateSelectedSource'));
        this.base(elementType, $container, settings);
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

    getViewClass: function(mode) {
        switch (mode) {
            case 'table':
                return Craft.Commerce.OrderTableView;
            default:
                return this.base(mode);
        }
    }
});

// Register the Commerce order index class
Craft.registerElementIndexClass('craft\\commerce\\elements\\Order', Craft.Commerce.OrderIndex);

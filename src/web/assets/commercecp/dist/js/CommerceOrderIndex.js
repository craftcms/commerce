if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderIndex
 */
Craft.Commerce.OrderIndex = Craft.BaseElementIndex.extend({

    $newEntryBtn: null,

    init: function(elementType, $container, settings) {
        this.base(elementType, $container, settings);
        this.$newEntryBtn = $('<a class="btn submit add icon" href="'+Craft.getCpUrl('commerce/order/new')+'">New order</a>');
        this.addButton(this.$newEntryBtn);
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



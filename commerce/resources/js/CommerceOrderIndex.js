if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderIndex
 */
Craft.Commerce.OrderIndex = Craft.BaseElementIndex.extend({
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
Craft.registerElementIndexClass('Commerce_Order', Craft.Commerce.OrderIndex);

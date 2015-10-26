/**
 * Class Craft.CommerceProductIndex
 */
Craft.CommerceProductIndex = Craft.BaseElementIndex.extend({
    getViewClass: function(mode) {
        switch (mode) {
            case 'table':
                return Craft.CommerceProductTableView;
            default:
                return this.base(mode);
        }
    }
});

// Register the Commerce order index class
Craft.registerElementIndexClass('Commerce_Product', Craft.CommerceProductIndex);

/**
 * Class Craft.CommerceOrderIndex
 */
Craft.CommerceOrderIndex = Craft.BaseElementIndex.extend({
    getViewClass: function(mode) {
        switch (mode) {
            case 'table':
                return Craft.CommerceOrderTableView;
            default:
                return this.base(mode);
        }
    }
});

// Register the Commerce order index class
Craft.registerElementIndexClass('Commerce_Order', Craft.CommerceOrderIndex);

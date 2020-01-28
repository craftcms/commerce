if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderTableView
 */
Craft.Commerce.OrderTableView = Craft.TableElementIndexView.extend({

    afterInit: function() {
        this.base();
    }
});

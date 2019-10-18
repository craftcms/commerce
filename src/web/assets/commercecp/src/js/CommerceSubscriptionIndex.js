if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.SubscriptionIndex
 */
Craft.Commerce.SubscriptionsIndex = Craft.BaseElementIndex.extend({

});

// Register the Commerce order index class
Craft.registerElementIndexClass('craft\\commerce\\elements\\Subscription', Craft.Commerce.SubscriptionsIndex);
